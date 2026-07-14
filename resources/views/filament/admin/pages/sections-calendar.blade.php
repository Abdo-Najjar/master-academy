<x-filament-panels::page>
    <style>
        .ma-cal-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;}
        .ma-cal-nav{display:flex;align-items:center;gap:.5rem;}
        .ma-cal-title{font-size:1.25rem;font-weight:700;min-width:9rem;text-align:center;}
        .ma-cal-btn{display:inline-flex;align-items:center;justify-content:center;padding:.4rem .9rem;border-radius:.5rem;font-size:.8125rem;font-weight:600;border:1px solid rgba(148,163,184,.35);background:rgba(148,163,184,.08);cursor:pointer;color:inherit;}
        .ma-cal-btn:hover{background:rgba(148,163,184,.18);}
        .ma-cal-btn--icon{padding:.4rem .55rem;}

        .ma-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);border:1px solid rgba(148,163,184,.25);border-radius:.75rem;overflow:hidden;}
        .ma-cal-head{background:rgba(148,163,184,.10);padding:.6rem 0;text-align:center;font-size:.75rem;font-weight:600;color:rgb(100,116,139);border-bottom:1px solid rgba(148,163,184,.25);}
        .ma-cal-cell{min-height:7.5rem;padding:.4rem;border-inline-end:1px solid rgba(148,163,184,.15);border-top:1px solid rgba(148,163,184,.15);display:flex;flex-direction:column;gap:.3rem;}
        .ma-cal-cell:nth-child(7n){border-inline-end:none;}
        .ma-cal-cell--out{opacity:.4;}
        .ma-cal-cell--today{background:rgba(250,204,21,.10);}
        .ma-cal-daynum{font-size:.8125rem;font-weight:600;}

        .ma-cal-event{display:block;padding:.2rem .4rem;border-radius:.35rem;font-size:.6875rem;line-height:1.3;background:rgba(148,163,184,.12);border-inline-start:3px solid rgb(100,116,139);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .ma-cal-event__name{font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .ma-cal-event__meta{color:rgb(100,116,139);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .ma-cal-more{font-size:.6875rem;color:rgb(100,116,139);padding:0 .2rem;}

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
                <button type="button" wire:click="previousMonth" class="ma-cal-btn ma-cal-btn--icon" aria-label="{{ __('Previous') }}">‹</button>
                <button type="button" wire:click="nextMonth" class="ma-cal-btn ma-cal-btn--icon" aria-label="{{ __('Next') }}">›</button>
            </div>
            <div class="ma-cal-title">{{ $this->monthLabel }}</div>
            <div></div>
        </div>

        <div class="ma-cal-grid">
            @foreach ($weekdays as $w)
                <div class="ma-cal-head">{{ $dayLabels[$w] }}</div>
            @endforeach

            @foreach ($days as $d)
                @php
                    $date = $d['date'];
                    $events = $this->eventsFor($date);
                    $visible = $events->take($maxVisible);
                    $extra = $events->count() - $visible->count();
                @endphp
                <div class="ma-cal-cell {{ $d['inMonth'] ? '' : 'ma-cal-cell--out' }} {{ $date->toDateString() === $today ? 'ma-cal-cell--today' : '' }}">
                    <div class="ma-cal-daynum">{{ $date->day }}</div>

                    @foreach ($visible as $event)
                        @php
                            $section = $event->section;
                            $subjectColor = $section?->subject?->color;
                            $sectionName = $section ? $section->getTranslation('name', app()->getLocale(), false) : '—';
                            $roomName = $event->room?->number;
                            $time = \Illuminate\Support\Carbon::parse($event->start_time)->format('H:i').'–'.\Illuminate\Support\Carbon::parse($event->end_time)->format('H:i');
                        @endphp
                        <span class="ma-cal-event" style="{{ $subjectColor ? 'border-inline-start-color:'.$subjectColor : '' }}" title="{{ $sectionName }} · {{ $time }}{{ $roomName ? ' · '.$roomName : '' }}">
                            <span class="ma-cal-event__name">{{ $sectionName }}</span>
                            <span class="ma-cal-event__meta">{{ $time }}{{ $roomName ? ' · '.$roomName : '' }}</span>
                        </span>
                    @endforeach

                    @if ($extra > 0)
                        <span class="ma-cal-more">{{ __('+:count more', ['count' => $extra]) }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
