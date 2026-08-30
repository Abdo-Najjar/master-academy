<x-filament-panels::page>
    <style>
        .ma-ta-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.75rem;}
        .ma-ta-btn{padding:.5rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:500;color:#fff;border:none;cursor:pointer;}
        .ma-ta-btn--green{background:rgb(22,163,74);}
        .ma-ta-btn--red{background:rgb(220,38,38);}

        .ma-ta-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;margin:1rem 0;}
        .ma-ta-stat{padding:1rem;border-radius:.75rem;border:1px solid;}
        .ma-ta-stat__label{font-size:.75rem;margin:0;}
        .ma-ta-stat__value{font-size:1.5rem;font-weight:700;margin:.25rem 0 0;}
        .ma-ta-stat--gray{background:rgba(148,163,184,.10);border-color:rgba(148,163,184,.25);}
        .ma-ta-stat--gray .ma-ta-stat__label{color:rgb(100,116,139);}
        .ma-ta-stat--green{background:rgba(34,197,94,.10);border-color:rgba(34,197,94,.30);}
        .ma-ta-stat--green .ma-ta-stat__label,.ma-ta-stat--green .ma-ta-stat__value{color:rgb(22,101,52);}
        .ma-ta-stat--red{background:rgba(239,68,68,.10);border-color:rgba(239,68,68,.30);}
        .ma-ta-stat--red .ma-ta-stat__label,.ma-ta-stat--red .ma-ta-stat__value{color:rgb(153,27,27);}
        .ma-ta-stat--amber{background:rgba(245,158,11,.10);border-color:rgba(245,158,11,.30);}
        .ma-ta-stat--amber .ma-ta-stat__label,.ma-ta-stat--amber .ma-ta-stat__value{color:rgb(146,64,14);}
        .ma-ta-stat--blue{background:rgba(59,130,246,.10);border-color:rgba(59,130,246,.30);}
        .ma-ta-stat--blue .ma-ta-stat__label,.ma-ta-stat--blue .ma-ta-stat__value{color:rgb(30,64,175);}

        .ma-ta-row{padding:.75rem 0;border-top:1px solid rgba(148,163,184,.15);display:flex;flex-wrap:wrap;align-items:center;gap:1rem;}
        .ma-ta-row__info{display:flex;align-items:center;gap:.75rem;flex:1;min-width:200px;}
        .ma-ta-avatar{width:40px;height:40px;border-radius:9999px;object-fit:cover;}
        .ma-ta-avatar--initials{display:flex;align-items:center;justify-content:center;background:rgb(220,38,38);color:#fff;font-weight:700;font-size:.875rem;}
        .ma-ta-row__name{font-weight:600;margin:0;}
        .ma-ta-row__id{font-size:.75rem;color:rgb(100,116,139);margin:0;}
        .ma-ta-row__toggles{display:flex;flex-wrap:wrap;gap:.375rem;}
        .ma-ta-toggle{padding:.375rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:500;border:none;cursor:pointer;}
        .ma-ta-toggle--green{background:rgba(34,197,94,.15);color:rgb(22,101,52);}
        .ma-ta-toggle--green.is-active{background:rgb(22,163,74);color:#fff;}
        .ma-ta-toggle--red{background:rgba(239,68,68,.15);color:rgb(153,27,27);}
        .ma-ta-toggle--red.is-active{background:rgb(220,38,38);color:#fff;}
        .ma-ta-toggle--amber{background:rgba(245,158,11,.15);color:rgb(146,64,14);}
        .ma-ta-toggle--amber.is-active{background:rgb(217,119,6);color:#fff;}
        .ma-ta-toggle--blue{background:rgba(59,130,246,.15);color:rgb(30,64,175);}
        .ma-ta-toggle--blue.is-active{background:rgb(37,99,235);color:#fff;}
        .ma-ta-note{width:200px;padding:.375rem .75rem;border-radius:.5rem;border:1px solid rgba(148,163,184,.40);background:transparent;font-size:.75rem;}

        .ma-ta-save-bar{margin-top:1rem;padding-top:1rem;border-top:1px solid rgba(148,163,184,.30);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;}
        .ma-ta-empty{text-align:center;color:rgb(100,116,139);padding:1rem 0;}

        /* Day picker: only timetable days are clickable, and days already on
           file carry a tick so a lesson is never recorded twice by accident. */
        /* Centred rather than pinned to one edge: the picker is the main thing
           on this card until a section is chosen, so it reads as the subject of
           the page instead of something parked in a corner. */
        .ma-ta-cal{margin:1rem auto 0;border:1px solid rgba(148,163,184,.30);border-radius:.75rem;padding:1rem;max-width:38rem;}
        .ma-ta-cal__bar{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.75rem;}
        .ma-ta-cal__month{font-weight:700;font-size:.9375rem;}
        .ma-ta-cal__nav{display:flex;gap:.375rem;}
        .ma-ta-cal__nav button{width:2rem;height:2rem;border-radius:.5rem;border:1px solid rgba(148,163,184,.40);background:transparent;color:inherit;cursor:pointer;font-size:1rem;line-height:1;}
        .ma-ta-cal__nav button:hover{background:rgba(148,163,184,.15);}
        .ma-ta-cal__grid{display:grid;grid-template-columns:repeat(7,1fr);gap:.375rem;}
        .ma-ta-cal__dow{text-align:center;font-size:.6875rem;font-weight:600;color:rgb(100,116,139);padding-bottom:.375rem;}
        .ma-ta-day{position:relative;aspect-ratio:1;border-radius:.5rem;border:1px solid transparent;background:transparent;color:inherit;font-size:.875rem;font-weight:600;display:flex;align-items:center;justify-content:center;cursor:pointer;}
        .ma-ta-day--out{opacity:.30;}
        /* Not a lesson day: shown, but inert — the ask was to make the wrong
           days unpickable rather than to hide the shape of the month. */
        .ma-ta-day--off{cursor:not-allowed;color:rgb(148,163,184);font-weight:400;opacity:.55;}
        .ma-ta-day--open{background:rgba(148,163,184,.12);}
        .ma-ta-day--open:hover{background:rgba(148,163,184,.28);}
        .ma-ta-day--recorded{background:rgba(34,197,94,.16);color:rgb(21,128,61);}
        .ma-ta-day--recorded:hover{background:rgba(34,197,94,.30);}
        .ma-ta-day--today{border-color:rgb(148,163,184);}
        .ma-ta-day--selected{background:var(--primary-600);color:#fff;border-color:var(--primary-600);}
        .ma-ta-day--selected:hover{background:var(--primary-600);}
        .ma-ta-day__tick{position:absolute;top:.1875rem;inset-inline-end:.25rem;font-size:.625rem;line-height:1;color:rgb(22,163,74);}
        .ma-ta-day--selected .ma-ta-day__tick{color:#fff;}
        /* Legend and hint sit under a rule and centre with the grid above. */
        .ma-ta-cal__legend{display:flex;flex-wrap:wrap;justify-content:center;gap:.75rem;margin-top:1rem;padding-top:.75rem;border-top:1px solid rgba(148,163,184,.20);font-size:.6875rem;color:rgb(100,116,139);}
        .ma-ta-cal__legend span{display:inline-flex;align-items:center;gap:.375rem;}
        .ma-ta-cal__swatch{width:.75rem;height:.75rem;border-radius:.25rem;display:inline-block;flex:none;}
        .ma-ta-cal__hint{font-size:.6875rem;color:rgb(100,116,139);margin:.5rem 0 0;text-align:center;}
        .ma-ta-cal__hint strong{color:inherit;font-weight:700;}
    </style>

    {{-- Section + Date selectors --}}
    <x-filament::section>
        {{ $this->form }}

        @php $calendar = $this->calendar; @endphp

        <div class="ma-ta-cal">
            <div class="ma-ta-cal__bar">
                <span class="ma-ta-cal__month">{{ $calendar['month'] }}</span>
                <span class="ma-ta-cal__nav">
                    <button type="button" wire:click="shiftMonth(-1)" aria-label="{{ __('Previous') }}">‹</button>
                    <button type="button" wire:click="shiftMonth(1)" aria-label="{{ __('Next') }}">›</button>
                </span>
            </div>

            <div class="ma-ta-cal__grid">
                {{-- Locale-aware short weekday names, Saturday-first. --}}
                @php $dowCursor = \Carbon\Carbon::parse('next saturday'); @endphp
                @for ($d = 0; $d < 7; $d++)
                    <div class="ma-ta-cal__dow">{{ $dowCursor->copy()->addDays($d)->translatedFormat('D') }}</div>
                @endfor

                @foreach ($calendar['days'] as $day)
                    @php
                        // A day already on file stays open even when it is off
                        // the timetable — otherwise an old record could never be
                        // corrected. Only brand-new attendance is restricted.
                        $canOpen = $day['isSessionDay'] || $day['isRecorded'];

                        $classes = ['ma-ta-day'];
                        $classes[] = $day['inMonth'] ? '' : 'ma-ta-day--out';
                        if ($day['isRecorded']) {
                            $classes[] = 'ma-ta-day--recorded';
                        } elseif (! $day['isSessionDay']) {
                            $classes[] = 'ma-ta-day--off';
                        } else {
                            $classes[] = 'ma-ta-day--open';
                        }
                        if ($day['isToday']) { $classes[] = 'ma-ta-day--today'; }
                        if ($day['isSelected']) { $classes[] = 'ma-ta-day--selected'; }

                        $title = \Carbon\Carbon::parse($day['date'])->translatedFormat('l, d M Y');
                        if ($day['isRecorded']) {
                            $title .= ' — '.__('Present').': '.$day['present'].' / '.__('Absent').': '.$day['absent'];
                            if (! $day['isSessionDay']) {
                                $title .= ' — '.__('Recorded outside the timetable');
                            }
                        } elseif (! $day['isSessionDay']) {
                            $title .= ' — '.__('This section does not meet on that day.');
                        }
                    @endphp
                    <button type="button"
                            class="{{ trim(implode(' ', array_filter($classes))) }}"
                            title="{{ $title }}"
                            @disabled(! $canOpen)
                            @if ($canOpen) wire:click="selectDate('{{ $day['date'] }}')" @endif>
                        {{ $day['day'] }}
                        @if ($day['isRecorded'])
                            <span class="ma-ta-day__tick">✔</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="ma-ta-cal__legend">
                <span><span class="ma-ta-cal__swatch" style="background:rgba(34,197,94,.35);"></span>{{ __('Attendance recorded') }}</span>
                <span><span class="ma-ta-cal__swatch" style="background:rgba(148,163,184,.25);"></span>{{ __('Lesson day') }}</span>
                <span><span class="ma-ta-cal__swatch" style="background:transparent;border:1px solid rgba(148,163,184,.45);"></span>{{ __('Not a lesson day') }}</span>
            </div>

            @if ($sectionId)
                <p class="ma-ta-cal__hint">
                    {{ __('Lesson days: :days', ['days' => $this->scheduleSummary()]) }}
                </p>
            @endif
        </div>

        @if ($sectionId)
            <div class="ma-ta-actions">
                <button type="button" wire:click="markAll('present')" class="ma-ta-btn ma-ta-btn--green">
                    {{ __('Mark All Present') }}
                </button>
                <button type="button" wire:click="markAll('absent')" class="ma-ta-btn ma-ta-btn--red">
                    {{ __('Mark All Absent') }}
                </button>
            </div>
        @endif
    </x-filament::section>

    @php $section = $this->currentSection(); @endphp

    @if ($section)
        {{-- Stats --}}
        <div class="ma-ta-stats">
            <div class="ma-ta-stat ma-ta-stat--gray">
                <p class="ma-ta-stat__label">{{ __('Total Students') }}</p>
                <p class="ma-ta-stat__value">{{ count($statuses) }}</p>
            </div>
            <div class="ma-ta-stat ma-ta-stat--green">
                <p class="ma-ta-stat__label">{{ __('Present') }}</p>
                <p class="ma-ta-stat__value">{{ $this->counts['present'] }}</p>
            </div>
            <div class="ma-ta-stat ma-ta-stat--red">
                <p class="ma-ta-stat__label">{{ __('Absent') }}</p>
                <p class="ma-ta-stat__value">{{ $this->counts['absent'] }}</p>
            </div>
            <div class="ma-ta-stat ma-ta-stat--amber">
                <p class="ma-ta-stat__label">{{ __('Late') }}</p>
                <p class="ma-ta-stat__value">{{ $this->counts['late'] }}</p>
            </div>
            <div class="ma-ta-stat ma-ta-stat--blue">
                <p class="ma-ta-stat__label">{{ __('Attendance Rate') }}</p>
                <p class="ma-ta-stat__value">{{ $this->attendanceRate }}%</p>
            </div>
        </div>

        {{-- Student list --}}
        @if ($section->registrations->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Students') }} ({{ $section->registrations->count() }})
                </x-slot>
                <x-slot name="description">
                    {{ \Carbon\Carbon::parse($date)->translatedFormat('l, d M Y') }}
                </x-slot>

                <div>
                    @foreach ($section->registrations as $registration)
                        @php
                            $student = $registration->student;
                            $sid = $student?->id;
                            $current = $statuses[$sid] ?? 'present';
                            $avatar = $student?->getFirstMediaUrl('main');
                        @endphp
                        @if ($student)
                            <div class="ma-ta-row">
                                <div class="ma-ta-row__info">
                                    @if ($avatar)
                                        <img src="{{ $avatar }}" class="ma-ta-avatar" alt="">
                                    @else
                                        <div class="ma-ta-avatar ma-ta-avatar--initials">
                                            {{ mb_substr($student->getTranslation('name', app()->getLocale(), false) ?? 'S', 0, 1) }}
                                        </div>
                                    @endif
                                    <div>
                                        <p class="ma-ta-row__name">{{ $student->getTranslation('name', app()->getLocale(), false) }}</p>
                                        <p class="ma-ta-row__id">{{ $student->student_number }}</p>
                                    </div>
                                </div>

                                <div class="ma-ta-row__toggles">
                                    @foreach ([
                                        'present' => [__('Present'), 'green'],
                                        'absent'  => [__('Absent'),  'red'],
                                        'late'    => [__('Late'),    'amber'],
                                        'excused' => [__('Excused'), 'blue'],
                                    ] as $key => [$label, $color])
                                        <button type="button"
                                                wire:click="setStatus({{ $sid }}, '{{ $key }}')"
                                                class="ma-ta-toggle ma-ta-toggle--{{ $color }} @if ($current === $key) is-active @endif">
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>

                                <input type="text" wire:model="notes.{{ $sid }}"
                                       placeholder="{{ __('Optional note') }}"
                                       class="ma-ta-note" />
                            </div>
                        @endif
                    @endforeach
                </div>

                @if ($isEditingExistingDay)
                    <div style="margin-top:1rem;">
                        <label for="ma-ta-edit-reason" style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.35rem;">
                            {{ __('Reason for change') }}
                        </label>
                        <input id="ma-ta-edit-reason" type="text" wire:model="editReason"
                               placeholder="{{ __('Recorded in the audit log with this change.') }}"
                               style="width:100%;padding:.5rem .75rem;border:1px solid rgb(203,213,225);border-radius:.5rem;font-size:.875rem;" />
                    </div>
                @endif

                <div class="ma-ta-save-bar">
                    <span style="font-size:.75rem;color:rgb(100,116,139);" wire:loading.remove wire:target="save">
                        {{ __('Changes are saved when you click "Save".') }}
                    </span>
                    <span style="font-size:.75rem;color:rgb(22,163,74);" wire:loading wire:target="save">{{ __('Saving...') }}</span>
                    <x-filament::button wire:click="save" wire:loading.attr="disabled" size="lg" icon="heroicon-o-check">
                        {{ __('Save Attendance') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <p class="ma-ta-empty">{{ __('No students registered in this section yet.') }}</p>
            </x-filament::section>
        @endif
    @else
        <x-filament::section>
            <p class="ma-ta-empty">{{ __('Select a section to begin.') }}</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
