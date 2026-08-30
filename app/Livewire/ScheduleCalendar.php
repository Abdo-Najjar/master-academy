<?php

namespace App\Livewire;

use App\Models\SectionTime;
use App\Services\SectionScheduleService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A month grid of the lessons a set of sections holds — the same weekday +
 * date-range rule the sections calendar uses, narrowed to whoever's page it is
 * embedded on (a student's enrolled sections, a trainer's own sections).
 */
class ScheduleCalendar extends Component
{
    /** @var list<int> */
    public array $sectionIds = [];

    /** First day of the month on screen, as `Y-m-d`. */
    public string $cursor = '';

    public function mount(array $sectionIds = []): void
    {
        $this->sectionIds = array_values(array_map('intval', $sectionIds));
        $this->cursor = now()->startOfMonth()->toDateString();
    }

    public function shiftMonth(int $months): void
    {
        $this->cursor = Carbon::parse($this->cursor)->startOfMonth()->addMonths($months)->toDateString();
    }

    public function goToday(): void
    {
        $this->cursor = now()->startOfMonth()->toDateString();
    }

    /**
     * Timetable rows for these sections, grouped by weekday name. Fetched once
     * per render so each cell is an in-memory lookup rather than a query.
     *
     * @return array<string, Collection<int, SectionTime>>
     */
    #[Computed]
    public function timesByWeekday(): array
    {
        if ($this->sectionIds === []) {
            return [];
        }

        return SectionTime::query()
            ->whereIn('section_id', $this->sectionIds)
            ->with(['room', 'section.subject', 'section.trainer'])
            ->get()
            ->groupBy(fn (SectionTime $time): string => strtolower((string) $time->day))
            ->all();
    }

    /**
     * The visible month as a Saturday-first grid, each day carrying its lessons.
     *
     * @return array{month: string, days: list<array{date: Carbon, inMonth: bool, isToday: bool, lessons: Collection<int, SectionTime>}>}
     */
    #[Computed]
    public function grid(): array
    {
        $month = Carbon::parse($this->cursor ?: now())->startOfMonth();

        $gridStart = $month->copy();
        while ($gridStart->dayOfWeek !== Carbon::SATURDAY) {
            $gridStart->subDay();
        }

        $gridEnd = $month->copy()->endOfMonth();
        while ($gridEnd->dayOfWeek !== Carbon::FRIDAY) {
            $gridEnd->addDay();
        }

        $today = now()->toDateString();
        $extras = SectionScheduleService::extraLessonsBetween($this->sectionIds, $gridStart, $gridEnd);
        $days = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $key = $cursor->toDateString();

            $days[] = [
                'date' => $cursor->copy(),
                'inMonth' => $cursor->month === $month->month,
                'isToday' => $key === $today,
                // Extra and make-up lessons sit beside the weekly ones, sorted
                // together: a student reading the month wants their day, not
                // two lists to merge in their head.
                'lessons' => $this->lessonsOn($cursor)
                    ->merge($extras[$key] ?? collect())
                    ->sortBy(fn (SectionTime $time): string => (string) $time->start_time)
                    ->values(),
            ];

            $cursor->addDay();
        }

        return [
            'month' => $month->translatedFormat('F Y'),
            'days' => $days,
        ];
    }

    /**
     * Lessons on a date: the weekday is on the timetable and the section is
     * still running then.
     *
     * @return Collection<int, SectionTime>
     */
    protected function lessonsOn(Carbon $date): Collection
    {
        $times = $this->timesByWeekday[strtolower($date->englishDayOfWeek)] ?? collect();

        return collect($times)
            ->filter(function (SectionTime $time) use ($date): bool {
                $section = $time->section;

                if (! $section) {
                    return false;
                }

                if ($section->start_date && $date->lt($section->start_date->copy()->startOfDay())) {
                    return false;
                }

                if ($section->end_date && $date->gt($section->end_date->copy()->startOfDay())) {
                    return false;
                }

                return true;
            })
            ->sortBy('start_time')
            ->values();
    }

    public function render(): View
    {
        return view('livewire.schedule-calendar');
    }
}
