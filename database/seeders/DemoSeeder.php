<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\City;
use App\Models\Exam;
use App\Models\ExamGrade;
use App\Models\Governorate;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Room;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fills the database with realistic demo data: rooms, trainers (linked to
 * subjects), sections with weekly schedules, students, registrations (which
 * move money through the wallet via the RegistrationObserver), attendance
 * records, and exams with grades.
 *
 * Idempotency: this seeder only adds rows. Run it once on a fresh install.
 * Re-running it will create another batch of demo data.
 *
 *   php artisan db:seed --class=DemoSeeder --force
 */
class DemoSeeder extends Seeder
{
    private const ROOMS = 6;
    private const TRAINERS = 8;
    private const SECTIONS = 14;
    private const STUDENTS = 80;
    private const MAX_SESSIONS_PER_SECTION = 6;

    /** @var list<string> */
    private array $arFirst = [
        'محمد', 'أحمد', 'علي', 'عمر', 'خالد', 'يوسف', 'إبراهيم', 'حسن', 'كريم', 'طارق',
        'فاطمة', 'مريم', 'عائشة', 'سارة', 'ليلى', 'نور', 'رنا', 'هدى', 'سلمى', 'دانا',
    ];

    /** @var list<string> */
    private array $arLast = [
        'الأحمد', 'الحسن', 'الخطيب', 'السيد', 'العلي', 'حمدان', 'الدباغ', 'النجار', 'الشامي', 'الحلبي',
        'العمر', 'الرفاعي', 'القاسم', 'الديب', 'الصالح', 'المصري', 'الكردي', 'الزعبي',
    ];

    /** @var list<string> */
    private array $enFirst = [
        'Mohammed', 'Ahmad', 'Ali', 'Omar', 'Yousef', 'Ibrahim', 'Hassan', 'Karim', 'Tarek', 'Khaled',
        'Fatima', 'Maryam', 'Aisha', 'Sara', 'Layla', 'Noor', 'Rana', 'Huda', 'Salma', 'Dana',
    ];

    /** @var list<string> */
    private array $enLast = [
        'Ahmad', 'Hassan', 'Khatib', 'Sayed', 'Ali', 'Hamdan', 'Dabbagh', 'Najjar', 'Shami', 'Halabi',
        'Omar', 'Rifai', 'Qasim', 'Deeb', 'Saleh', 'Masri', 'Kurdi', 'Zoubi',
    ];

    /** @var list<string> */
    private array $days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];

    /** @var list<string> */
    private array $startTimes = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];

    /** @var list<string> */
    private array $statuses = ['present', 'present', 'present', 'present', 'late', 'absent', 'excused'];

    public function run(): void
    {
        $subjects = Subject::all();
        if ($subjects->isEmpty()) {
            $this->command?->warn('No subjects found — run the base seeders first. Aborting DemoSeeder.');

            return;
        }

        $governorates = Governorate::all();
        $cities = City::all();
        $paymentTypes = PaymentType::all();

        $rooms = $this->makeRooms();
        $trainers = $this->makeTrainers($subjects, $governorates, $cities);
        $sections = $this->makeSections($subjects, $trainers, $rooms);
        $students = $this->makeStudents($governorates, $cities);

        $this->makeRegistrations($sections, $students, $paymentTypes);
        $this->makeAttendances($sections);
        $this->makeExams($sections);

        $this->command?->info(sprintf(
            'Demo data: %d rooms, %d trainers, %d sections, %d students seeded.',
            $rooms->count(),
            $trainers->count(),
            $sections->count(),
            $students->count(),
        ));
    }

    private function arName(): string
    {
        return $this->arFirst[array_rand($this->arFirst)].' '.$this->arLast[array_rand($this->arLast)];
    }

    private function enName(): string
    {
        return $this->enFirst[array_rand($this->enFirst)].' '.$this->enLast[array_rand($this->enLast)];
    }

    private function phone(): string
    {
        return '09'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /** @return \Illuminate\Support\Collection<int,Room> */
    private function makeRooms()
    {
        $rooms = collect();
        for ($i = 1; $i <= self::ROOMS; $i++) {
            $rooms->push(Room::create([
                'number' => 'R-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'capacity' => random_int(20, 40),
                'description' => 'قاعة رقم '.$i,
            ]));
        }

        return $rooms;
    }

    /** @return \Illuminate\Support\Collection<int,Trainer> */
    private function makeTrainers($subjects, $governorates, $cities)
    {
        $trainers = collect();
        for ($i = 1; $i <= self::TRAINERS; $i++) {
            $gov = $governorates->random();
            $trainer = Trainer::create([
                'name' => ['ar' => $this->arName(), 'en' => $this->enName()],
                'is_active' => true,
                'dob' => CarbonImmutable::now()->subYears(random_int(28, 55))->subDays(random_int(0, 364))->toDateString(),
                'ssn' => '11'.str_pad((string) $i, 9, '0', STR_PAD_LEFT),
                'username' => 'trainer'.$i,
                'trainer_number' => 'TRN-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'email' => 'trainer'.$i.'@ma.test',
                'password' => Hash::make('password'),
                'phone_number' => $this->phone(),
                'whatsapp_number' => $this->phone(),
                'governorate_id' => $gov->id,
                'city_id' => optional($cities->where('governorate_id', $gov->id)->first())->id ?? $cities->random()->id,
                'default_rate' => random_int(20, 50),
                'bio' => 'مدرب ذو خبرة في مجاله.',
            ]);

            // Link to 1-2 subjects.
            $trainer->subjects()->syncWithoutDetaching(
                $subjects->random(min($subjects->count(), random_int(1, 2)))->pluck('id')->all()
            );

            $trainers->push($trainer);
        }

        return $trainers;
    }

    /** @return \Illuminate\Support\Collection<int,Section> */
    private function makeSections($subjects, $trainers, $rooms)
    {
        // Globally-unique (day, start_time) slots guarantee no trainer/room
        // double-booking, satisfying the SectionTimeObserver.
        $slots = [];
        foreach ($this->days as $day) {
            foreach ($this->startTimes as $start) {
                $slots[] = [$day, $start];
            }
        }
        shuffle($slots);

        $sections = collect();
        for ($i = 1; $i <= self::SECTIONS; $i++) {
            // Pick a subject that has at least one trainer.
            $candidates = $subjects->filter(fn (Subject $s) => $s->trainers()->exists());
            if ($candidates->isEmpty()) {
                break;
            }
            $subject = $candidates->random();
            $trainer = $subject->trainers()->inRandomOrder()->first();

            $start = CarbonImmutable::now()->subDays(random_int(20, 60));
            $rate = (float) ($trainer->default_rate ?: random_int(20, 50));

            $section = Section::create([
                'name' => [
                    'ar' => $subject->getTranslation('name', 'ar', false).' - شعبة '.$i,
                    'en' => 'Section '.$i,
                ],
                'subject_id' => $subject->id,
                'trainer_id' => $trainer->id,
                'start_date' => $start->toDateString(),
                'end_date' => $start->addMonths(3)->toDateString(),
                'price' => random_int(50, 300) * 1000,
                'trainer_rate' => $rate,
                'capacity' => random_int(15, 30),
                'google_meet_url' => null,
                'google_classroom_url' => null,
            ]);

            // 1-2 weekly times, each on a unique global slot.
            $timesCount = random_int(1, 2);
            for ($t = 0; $t < $timesCount && ! empty($slots); $t++) {
                [$day, $startTime] = array_pop($slots);
                $endTime = CarbonImmutable::createFromFormat('H:i', $startTime)->addHours(2)->format('H:i');
                SectionTime::create([
                    'section_id' => $section->id,
                    'room_id' => $rooms->random()->id,
                    'day' => $day,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]);
            }

            $sections->push($section->load('times'));
        }

        return $sections;
    }

    /** @return \Illuminate\Support\Collection<int,Student> */
    private function makeStudents($governorates, $cities)
    {
        $students = collect();
        for ($i = 1; $i <= self::STUDENTS; $i++) {
            $gov = $governorates->random();
            $students->push(Student::create([
                'name' => ['ar' => $this->arName(), 'en' => $this->enName()],
                'is_active' => random_int(1, 10) > 1, // ~90% active
                'dob' => CarbonImmutable::now()->subYears(random_int(15, 25))->subDays(random_int(0, 364))->toDateString(),
                'ssn' => '22'.str_pad((string) $i, 9, '0', STR_PAD_LEFT),
                'username' => 'student'.$i,
                'email' => 'student'.$i.'@ma.test',
                'password' => Hash::make('password'),
                'phone_number' => $this->phone(),
                'whatsapp_number' => $this->phone(),
                'parent_name' => $this->arName(),
                'parent_phone' => $this->phone(),
                'parent_whatsapp' => $this->phone(),
                'governorate_id' => $gov->id,
                'city_id' => optional($cities->where('governorate_id', $gov->id)->first())->id ?? $cities->random()->id,
            ]));
        }

        return $students;
    }

    private function makeRegistrations($sections, $students, $paymentTypes): void
    {
        foreach ($sections as $section) {
            $capacity = $section->capacity ?? 25;
            $count = random_int((int) ceil($capacity * 0.4), $capacity);
            $enrolled = $students->shuffle()->take($count);
            $rate = (float) ($section->trainer_rate ?? 0);

            foreach ($enrolled as $student) {
                $due = (float) $section->price;

                // Payment scenario.
                $scenario = ['full', 'partial', 'exempt'][random_int(0, 2)];
                $exemption = 0.0;
                if ($scenario === 'exempt') {
                    $exemption = round($due * (random_int(10, 40) / 100), 2);
                }
                $payable = $due - $exemption;
                $paid = match ($scenario) {
                    'full' => $payable,
                    'partial' => round($payable * (random_int(30, 80) / 100), 2),
                    'exempt' => $payable,
                };
                $trainerAmount = round($paid * $rate / 100, 2);

                Registration::create([
                    'student_id' => $student->id,
                    'section_id' => $section->id,
                    'payment_type_id' => optional($paymentTypes->random())->id,
                    'amount_due' => $due,
                    'amount_paid' => $paid,
                    'exemption_amount' => $exemption,
                    'trainer_amount' => $trainerAmount,
                    'note' => null,
                ]);
            }
        }
    }

    private function makeAttendances($sections): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        foreach ($sections as $section) {
            $studentIds = Registration::query()
                ->where('section_id', $section->id)
                ->pluck('student_id');

            if ($studentIds->isEmpty() || $section->times->isEmpty()) {
                continue;
            }

            $sessionDates = $this->sessionDates($section, $today);

            foreach ($sessionDates as $date) {
                foreach ($studentIds as $studentId) {
                    Attendance::updateOrCreate(
                        [
                            'section_id' => $section->id,
                            'student_id' => $studentId,
                            'date' => $date,
                        ],
                        ['status' => $this->statuses[array_rand($this->statuses)]]
                    );
                }
            }
        }
    }

    /**
     * Up to MAX_SESSIONS_PER_SECTION recent past dates that fall on the
     * section's scheduled weekdays, within [start_date, today].
     *
     * @return list<string>
     */
    private function sessionDates(Section $section, CarbonImmutable $today): array
    {
        $weekdays = $section->times->pluck('day')->map(fn ($d) => strtolower((string) $d))->unique()->all();
        if (empty($weekdays)) {
            return [];
        }

        $start = $section->start_date
            ? CarbonImmutable::parse($section->start_date)->startOfDay()
            : $today->subMonths(2);

        $dates = [];
        $cursor = $today;
        while ($cursor->greaterThanOrEqualTo($start) && count($dates) < self::MAX_SESSIONS_PER_SECTION) {
            if (in_array(strtolower($cursor->englishDayOfWeek), $weekdays, true)) {
                $dates[] = $cursor->toDateString();
            }
            $cursor = $cursor->subDay();
        }

        return $dates;
    }

    private function makeExams($sections): void
    {
        foreach ($sections as $section) {
            $studentIds = Registration::query()
                ->where('section_id', $section->id)
                ->pluck('student_id');

            if ($studentIds->isEmpty()) {
                continue;
            }

            $examCount = random_int(1, 2);
            for ($e = 1; $e <= $examCount; $e++) {
                $exam = Exam::create([
                    'section_id' => $section->id,
                    'name' => 'اختبار '.$e,
                    'date' => CarbonImmutable::now()->subDays(random_int(3, 30))->toDateString(),
                    'max_score' => 100,
                    'note' => null,
                ]);

                foreach ($studentIds as $studentId) {
                    ExamGrade::create([
                        'exam_id' => $exam->id,
                        'student_id' => $studentId,
                        'score' => random_int(40, 100),
                        'note' => null,
                    ]);
                }
            }
        }
    }
}
