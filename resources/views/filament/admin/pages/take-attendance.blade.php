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
    </style>

    {{-- Section + Date selectors --}}
    <x-filament::section>
        {{ $this->form }}

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
