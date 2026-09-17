<x-filament-panels::page>
    <style>
        .ma-cal-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;}
        .ma-cal-nav{display:flex;align-items:center;gap:.5rem;}
        .ma-cal-title{font-size:1.25rem;font-weight:700;min-width:9rem;text-align:center;}
        .ma-cal-btn{display:inline-flex;align-items:center;justify-content:center;padding:.4rem .9rem;border-radius:.5rem;font-size:.8125rem;font-weight:600;border:1px solid rgba(148,163,184,.35);background:rgba(148,163,184,.08);cursor:pointer;color:inherit;}
        .ma-cal-btn:hover{background:rgba(148,163,184,.18);}
        .ma-cal-btn--icon{padding:.4rem .55rem;}
        .ma-cal-views{display:inline-flex;gap:.25rem;}
        .ma-cal-btn--active{background:rgba(59,130,246,.22);border-color:rgba(59,130,246,.45);}

        .ma-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);border:1px solid rgba(148,163,184,.25);border-radius:.75rem;overflow:hidden;}
        .ma-cal-head{background:rgba(148,163,184,.10);padding:.6rem 0;text-align:center;font-size:.75rem;font-weight:600;color:rgb(100,116,139);border-bottom:1px solid rgba(148,163,184,.25);}
        .ma-cal-cell{min-height:9rem;padding:.4rem;border-inline-end:1px solid rgba(148,163,184,.15);border-top:1px solid rgba(148,163,184,.15);display:flex;flex-direction:column;gap:.3rem;}
        .ma-cal-cell:nth-child(7n){border-inline-end:none;}
        .ma-cal-cell--out{opacity:.4;}
        .ma-cal-cell--today{background:rgba(250,204,21,.10);}
        .ma-cal-daynum{font-size:.8125rem;font-weight:600;}

        .ma-cal-event{display:block;padding:.25rem .4rem;border-radius:.35rem;font-size:.6875rem;line-height:1.35;background:rgba(148,163,184,.12);border-inline-start:3px solid rgb(100,116,139);overflow:hidden;}
        .ma-cal-event__name{font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .ma-cal-event__meta{color:rgb(100,116,139);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        /* Room and branch used to be bare numbers glued after the time, which
           read as noise. They are labelled chips now so the cell says *where*. */
        .ma-cal-tags{display:flex;flex-wrap:wrap;gap:.2rem;margin-top:.15rem;}
        .ma-cal-tag{display:inline-flex;align-items:center;gap:.15rem;padding:0 .3rem;border-radius:.25rem;font-size:.625rem;font-weight:600;line-height:1.5;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;}
        .ma-cal-tag--room{background:rgba(59,130,246,.16);color:rgb(29,78,216);}
        .ma-cal-tag--branch{background:rgba(168,85,247,.16);color:rgb(126,34,206);}
        .ma-cal-tag--none{background:rgba(148,163,184,.16);color:rgb(100,116,139);font-weight:500;}
        .ma-cal-tag--subject{background:rgba(16,185,129,.16);color:rgb(4,120,87);}
        .ma-cal-tag--trainer{background:rgba(245,158,11,.16);color:rgb(180,83,9);}
        /* A lesson entered by hand looks like any other on the grid, so it says
           so out loud — otherwise nobody can tell an extra lesson from the
           weekly one it sits next to. */
        .ma-cal-tag--extra{background:rgba(244,63,94,.16);color:rgb(190,18,60);}
        .ma-cal-event--extra{border-inline-start-style:dashed;}
        /* A room let out to someone who is not a section. It sits in the same
           cell as the lessons because the question the grid answers — "is that
           hall free then?" — is the same either way. */
        .ma-cal-tag--booking{background:rgba(168,85,247,.16);color:rgb(126,34,206);}
        .ma-cal-event--booking{background:rgba(168,85,247,.08);border-inline-start-color:rgb(168,85,247);}
        .dark .ma-cal-tag--room{color:rgb(147,197,253);}
        .dark .ma-cal-tag--branch{color:rgb(216,180,254);}
        .dark .ma-cal-tag--none{color:rgb(161,161,170);}
        .dark .ma-cal-tag--subject{color:rgb(110,231,183);}
        .dark .ma-cal-tag--trainer{color:rgb(252,211,77);}
        .dark .ma-cal-tag--extra{color:rgb(253,164,175);}
        .dark .ma-cal-tag--booking{color:rgb(216,180,254);}
        /* The overflow counter is a button: a day with four sections has to be
           able to show all four, not just admit that it has them. */
        .ma-cal-more{font-size:.6875rem;font-weight:600;color:rgb(100,116,139);padding:.1rem .3rem;border:0;background:transparent;cursor:pointer;text-align:start;border-radius:.25rem;}
        .ma-cal-more:hover{background:rgba(148,163,184,.18);color:inherit;}
        [x-cloak]{display:none!important;}

        .ma-cal-legend{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:.75rem;font-size:.75rem;color:rgb(100,116,139);align-items:center;}

        .ma-cal-empty{padding:3rem 1rem;text-align:center;color:rgb(100,116,139);font-size:.875rem;}
    </style>

    @php
        $weekdays = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $dayLabels = [
            'saturday' => __('Saturday'), 'sunday' => __('Sunday'), 'monday' => __('Monday'),
            'tuesday' => __('Tuesday'), 'wednesday' => __('Wednesday'), 'thursday' => __('Thursday'), 'friday' => __('Friday'),
        ];
        $days = $this->calendarDays;
        $today = now()->toDateString();
        $maxVisible = 3;
    @endphp

    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    <x-filament::section>
        <div class="ma-cal-toolbar">
            <div class="ma-cal-nav">
                <button type="button" wire:click="goToday" class="ma-cal-btn">{{ __('Today') }}</button>
                <button type="button" wire:click="previousPeriod" class="ma-cal-btn ma-cal-btn--icon" aria-label="{{ __('Previous') }}">‹</button>
                <button type="button" wire:click="nextPeriod" class="ma-cal-btn ma-cal-btn--icon" aria-label="{{ __('Next') }}">›</button>
            </div>

            <div class="ma-cal-title">{{ $this->periodLabel }}</div>

            <div class="ma-cal-nav">
                <div class="ma-cal-views">
                    @foreach (\App\Filament\Admin\Pages\SectionsCalendar::viewOptions() as $value => $label)
                        <button
                            type="button"
                            wire:click="setSpan('{{ $value }}')"
                            class="ma-cal-btn {{ $this->span === $value ? 'ma-cal-btn--active' : '' }}"
                        >{{ $label }}</button>
                    @endforeach
                </div>
                <button type="button" wire:click="exportPeriod" class="ma-cal-btn">{{ __('Export') }}</button>
                {{-- The spreadsheet is for working with; this is for the wall. --}}
                <button type="button" wire:click="exportWeeklyPdf" class="ma-cal-btn">{{ __('Print Weekly Schedule') }}</button>
            </div>
        </div>

        <div class="ma-cal-grid">
            @foreach ($weekdays as $w)
                <div class="ma-cal-head">{{ $dayLabels[$w] }}</div>
            @endforeach

            @foreach ($days as $d)
                @php
                    $date = $d['date'];
                    $events = $this->eventsFor($date)->values();
                    $extra = max(0, $events->count() - $maxVisible);
                @endphp
                <div
                    class="ma-cal-cell {{ $d['inMonth'] ? '' : 'ma-cal-cell--out' }} {{ $date->toDateString() === $today ? 'ma-cal-cell--today' : '' }}"
                    x-data="{ expanded: false }"
                >
                    <div class="ma-cal-daynum">{{ $date->day }}</div>

                    @foreach ($events as $event)
                        @php $isBooking = \App\Filament\Admin\Pages\SectionsCalendar::isBooking($event); @endphp

                        @if ($isBooking)
                            @php
                                $booking = $event->booking;
                                $roomName = $booking?->room?->number;
                                $branchName = $booking?->branch?->name;
                                $time = \Illuminate\Support\Carbon::parse($event->start_time)->format('H:i')
                                    .'–'.\Illuminate\Support\Carbon::parse($event->end_time)->format('H:i');

                                $tooltip = collect([
                                    $booking?->title,
                                    $time,
                                    __('Room Booking'),
                                    $booking?->client_name ? __('Booked By').': '.$booking->client_name : null,
                                    $booking?->client_phone,
                                    $branchName ? __('Branch').': '.$branchName : null,
                                    $roomName ? __('Room').': '.$roomName : __('No room set'),
                                ])->filter()->implode(' · ');
                            @endphp
                            <span
                                class="ma-cal-event ma-cal-event--booking"
                                title="{{ $tooltip }}"
                                @if ($loop->index >= $maxVisible) x-show="expanded" x-cloak @endif
                            >
                                <span class="ma-cal-event__name">{{ $booking?->title ?? '—' }}</span>
                                <span class="ma-cal-event__meta">{{ $time }}</span>
                                <span class="ma-cal-tags">
                                    <span class="ma-cal-tag ma-cal-tag--booking">{{ __('Room Booking') }}</span>
                                    @if ($booking?->client_name)
                                        <span class="ma-cal-tag ma-cal-tag--trainer">{{ $booking->client_name }}</span>
                                    @endif
                                </span>
                                <span class="ma-cal-tags">
                                    @if ($branchName)
                                        <span class="ma-cal-tag ma-cal-tag--branch">{{ $branchName }}</span>
                                    @endif
                                    @if ($roomName)
                                        <span class="ma-cal-tag ma-cal-tag--room">{{ __('Room') }} {{ $roomName }}</span>
                                    @else
                                        <span class="ma-cal-tag ma-cal-tag--none">{{ __('No room set') }}</span>
                                    @endif
                                </span>
                            </span>
                            @continue
                        @endif

                        @php
                            $section = $event->section;
                            $subjectColor = $section?->subject?->color;
                            $sectionName = $section ? $section->name : '—';
                            $roomName = $event->room?->number;
                            $branchName = $section?->branch?->name;
                            $subjectName = $section?->subject?->getTranslation('name', app()->getLocale(), false);
                            $trainerName = $section?->trainer?->getTranslation('name', app()->getLocale(), false);
                            $time = $event->start_time
                                ? \Illuminate\Support\Carbon::parse($event->start_time)->format('H:i').'–'.\Illuminate\Support\Carbon::parse($event->end_time ?: $event->start_time)->format('H:i')
                                : __('No time set');
                            $extraLabel = $event->extra_session_type
                                ? \App\Models\SectionSession::extraLabelFor($event->extra_session_type)
                                : null;

                            $tooltip = collect([
                                $sectionName,
                                $time,
                                $extraLabel,
                                $subjectName ? __('Course').': '.$subjectName : null,
                                $trainerName ? __('Trainer').': '.$trainerName : __('No trainer assigned'),
                                $branchName ? __('Branch').': '.$branchName : __('No branch set'),
                                $roomName ? __('Room').': '.$roomName : __('No room set'),
                            ])->filter()->implode(' · ');
                        @endphp
                        <span
                            class="ma-cal-event {{ $extraLabel ? 'ma-cal-event--extra' : '' }}"
                            style="{{ $subjectColor ? 'border-inline-start-color:'.$subjectColor : '' }}"
                            title="{{ $tooltip }}"
                            @if ($loop->index >= $maxVisible) x-show="expanded" x-cloak @endif
                        >
                            <span class="ma-cal-event__name">{{ $sectionName }}</span>
                            <span class="ma-cal-event__meta">{{ $time }}</span>
                            {{-- Which subject it is and who teaches it: the two
                                 things the desk is actually asked on the phone. --}}
                            <span class="ma-cal-tags">
                                @if ($extraLabel)
                                    <span class="ma-cal-tag ma-cal-tag--extra">{{ $extraLabel }}</span>
                                @endif
                                @if ($subjectName)
                                    <span class="ma-cal-tag ma-cal-tag--subject">{{ $subjectName }}</span>
                                @endif
                                @if ($trainerName)
                                    <span class="ma-cal-tag ma-cal-tag--trainer">{{ $trainerName }}</span>
                                @else
                                    <span class="ma-cal-tag ma-cal-tag--none">{{ __('No trainer assigned') }}</span>
                                @endif
                            </span>
                            <span class="ma-cal-tags">
                                @if ($branchName)
                                    <span class="ma-cal-tag ma-cal-tag--branch">{{ $branchName }}</span>
                                @endif
                                @if ($roomName)
                                    <span class="ma-cal-tag ma-cal-tag--room">{{ __('Room') }} {{ $roomName }}</span>
                                @else
                                    <span class="ma-cal-tag ma-cal-tag--none">{{ __('No room set') }}</span>
                                @endif
                            </span>
                        </span>
                    @endforeach

                    @if ($extra > 0)
                        <button
                            type="button"
                            class="ma-cal-more"
                            x-on:click="expanded = ! expanded"
                            x-text="expanded ? @js(__('Show less')) : @js(__('+:count more', ['count' => $extra]))"
                        >{{ __('+:count more', ['count' => $extra]) }}</button>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="ma-cal-legend">
            <span class="ma-cal-tag ma-cal-tag--subject">{{ __('Course') }}</span>
            <span class="ma-cal-tag ma-cal-tag--trainer">{{ __('Trainer') }}</span>
            <span class="ma-cal-tag ma-cal-tag--branch">{{ __('Branch') }}</span>
            <span class="ma-cal-tag ma-cal-tag--room">{{ __('Room') }}</span>
            <span class="ma-cal-tag ma-cal-tag--extra">{{ __('Extra Session') }}</span>
            <span class="ma-cal-tag ma-cal-tag--booking">{{ __('Room Booking') }}</span>
            <span>{{ __('Each entry shows the section, its time, its course, its trainer, its branch and its room.') }}</span>
        </div>
    </x-filament::section>
</x-filament-panels::page>
