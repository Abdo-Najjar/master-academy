<?php

namespace App\Filament\Admin\Pages;

use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Subject;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class SectionsCalendar extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'filament.admin.pages.sections-calendar';

    protected static ?int $navigationSort = 6;

    public ?array $filters = [];

    public string $cursor;

    public static function getNavigationGroup(): ?string
    {
        return __('Education');
    }

    public static function getNavigationLabel(): string
    {
        return __('Sections Calendar');
    }

    public function getTitle(): string
    {
        return __('Sections Calendar');
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('section.index') ?? false);
    }

    public function mount(): void
    {
        $this->cursor = now()->startOfMonth()->toDateString();

        $this->form->fill([
            'subject_id' => null,
            'section_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make('')
                    ->schema([
                        Select::make('subject_id')
                            ->label(__('Subject'))
                            ->options(fn () => Subject::query()->get()
                                ->mapWithKeys(fn (Subject $s) => [$s->id => $s->getTranslation('name', app()->getLocale(), false)]))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, $set) => $set('section_id', null)),
                        Select::make('section_id')
                            ->label(__('Section'))
                            ->options(function (Get $get): array {
                                return Section::query()
                                    ->when($get('subject_id'), fn ($q, $subjectId) => $q->where('subject_id', $subjectId))
                                    ->get()
                                    ->mapWithKeys(fn (Section $s) => [$s->id => $s->getTranslation('name', app()->getLocale(), false)])
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->live(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ])
            ->statePath('filters');
    }

    public function previousMonth(): void
    {
        $this->cursor = Carbon::parse($this->cursor)->subMonthNoOverflow()->toDateString();
    }

    public function nextMonth(): void
    {
        $this->cursor = Carbon::parse($this->cursor)->addMonthNoOverflow()->toDateString();
    }

    public function goToday(): void
    {
        $this->cursor = now()->startOfMonth()->toDateString();
    }

    public function getMonthLabelProperty(): string
    {
        return Carbon::parse($this->cursor)->translatedFormat('F Y');
    }

    /**
     * Section times for the active filters, grouped by weekday name
     * (e.g. 'monday' => Collection<SectionTime>). Fetched once per render;
     * each calendar cell reuses this map and only checks the section's
     * active date range in memory instead of re-querying per day.
     *
     * @return array<string, Collection<int, SectionTime>>
     */
    public function getTimesByWeekdayProperty(): array
    {
        $subjectId = $this->filters['subject_id'] ?? null;
        $sectionId = $this->filters['section_id'] ?? null;

        return SectionTime::query()
            ->with(['room', 'section.subject'])
            ->whereHas('section', fn ($q) => $q->when($subjectId, fn ($q2) => $q2->where('subject_id', $subjectId)))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->get()
            ->groupBy('day')
            ->all();
    }

    /**
     * The full grid of dates for the displayed month, Saturday-first,
     * padded with leading/trailing days from adjacent months so every
     * row is a complete week.
     *
     * @return list<array{date: Carbon, inMonth: bool}>
     */
    public function getCalendarDaysProperty(): array
    {
        $monthStart = Carbon::parse($this->cursor)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $gridStart = $monthStart->copy();
        while ($gridStart->dayOfWeek !== Carbon::SATURDAY) {
            $gridStart->subDay();
        }

        $gridEnd = $monthEnd->copy();
        while ($gridEnd->dayOfWeek !== Carbon::FRIDAY) {
            $gridEnd->addDay();
        }

        $days = [];
        $day = $gridStart->copy();
        while ($day->lte($gridEnd)) {
            $days[] = ['date' => $day->copy(), 'inMonth' => $day->month === $monthStart->month];
            $day->addDay();
        }

        return $days;
    }

    /**
     * Section times occurring on the given date: weekday match, plus the
     * section must be active (start_date/end_date range) on that date.
     *
     * @return Collection<int, SectionTime>
     */
    public function eventsFor(Carbon $date): Collection
    {
        $weekday = strtolower($date->format('l'));
        $times = $this->timesByWeekday[$weekday] ?? collect();

        return collect($times)
            ->filter(function (SectionTime $t) use ($date) {
                $section = $t->section;
                if (! $section) {
                    return false;
                }
                if ($section->start_date && $date->lt($section->start_date)) {
                    return false;
                }
                if ($section->end_date && $date->gt($section->end_date)) {
                    return false;
                }

                return true;
            })
            ->sortBy('start_time')
            ->values();
    }
}
