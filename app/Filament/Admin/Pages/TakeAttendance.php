<?php

namespace App\Filament\Admin\Pages;

use App\Models\Attendance;
use App\Models\Section;
use App\Services\AttendanceAlertService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use Livewire\Attributes\Computed;

class TakeAttendance extends Page implements HasForms
{
    use HasHexaLite, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected string $view = 'filament.admin.pages.take-attendance';

    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public ?int $sectionId = null;

    public string $date = '';

    /** @var array<int,string> student_id => status */
    public array $statuses = [];

    /** @var array<int,string> student_id => optional note */
    public array $notes = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('Take Attendance');
    }

    public function getTitle(): string
    {
        return __('Take Attendance');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('attendance.update');
    }

    public function roleName(): string
    {
        return __('Take Attendance');
    }

    public function defineGates(): array
    {
        return [
            'attendance.index' => __('View'),
            'attendance.update' => __('Update'),
            'attendance.delete' => __('Delete'),
        ];
    }

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->form->fill([
            'sectionId' => null,
            'date' => $this->date,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make('')
                    ->schema([
                        Select::make('sectionId')
                            ->label(__('Section'))
                            ->options(fn () => Section::query()
                                ->with('subject')
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn ($s) => [
                                    $s->id => $s->getTranslation('name', app()->getLocale(), false)
                                        .($s->subject ? ' — '.$s->subject->getTranslation('name', app()->getLocale(), false) : ''),
                                ]))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->sectionId = $state ? (int) $state : null;
                                $this->loadAttendance();
                            }),
                        DatePicker::make('date')
                            ->label(__('Date'))
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->date = $state ?: now()->toDateString();
                                $this->loadAttendance();
                            }),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function loadAttendance(): void
    {
        if (! $this->sectionId) {
            $this->statuses = [];
            $this->notes = [];
            return;
        }

        $section = $this->currentSection();
        if (! $section) {
            return;
        }

        $existing = Attendance::query()
            ->where('section_id', $this->sectionId)
            ->whereDate('date', $this->date)
            ->get()
            ->keyBy('student_id');

        $this->statuses = [];
        $this->notes = [];

        foreach ($section->registrations as $reg) {
            $row = $existing->get($reg->student_id);
            $this->statuses[$reg->student_id] = $row?->status ?? 'present';
            $this->notes[$reg->student_id] = $row?->note ?? '';
        }
    }

    public function setStatus(int $studentId, string $status): void
    {
        if (in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
            $this->statuses[$studentId] = $status;
        }
    }

    public function markAll(string $status): void
    {
        if (! in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
            return;
        }
        foreach (array_keys($this->statuses) as $studentId) {
            $this->statuses[$studentId] = $status;
        }
    }

    public function save(): void
    {
        if (! $this->sectionId) {
            Notification::make()->warning()->title(__('Please select a section first.'))->send();
            return;
        }

        $section = Section::find($this->sectionId);
        if (! $section) {
            return;
        }

        foreach ($this->statuses as $studentId => $status) {
            Attendance::query()->updateOrCreate(
                [
                    'section_id' => $this->sectionId,
                    'student_id' => $studentId,
                    'date' => $this->date,
                ],
                [
                    'status' => $status,
                    'note' => $this->notes[$studentId] ?? null,
                ]
            );
        }

        app(AttendanceAlertService::class)->checkForSection($section, $this->statuses);

        Notification::make()
            ->success()
            ->title(__('Attendance saved successfully'))
            ->send();
    }

    public function currentSection(): ?Section
    {
        if (! $this->sectionId) {
            return null;
        }

        return Section::query()
            ->with(['registrations.student', 'subject', 'trainer'])
            ->find($this->sectionId);
    }

    #[Computed]
    public function counts(): array
    {
        $tally = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        foreach ($this->statuses as $status) {
            if (isset($tally[$status])) {
                $tally[$status]++;
            }
        }
        return $tally;
    }

    #[Computed]
    public function attendanceRate(): float
    {
        $total = count($this->statuses);
        if ($total === 0) {
            return 0.0;
        }
        $present = $this->counts['present'] + $this->counts['late'];
        return round(($present / $total) * 100, 1);
    }
}
