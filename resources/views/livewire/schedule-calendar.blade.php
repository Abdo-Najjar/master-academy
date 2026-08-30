<div>
    <style>
        .ma-sc{border:1px solid rgba(148,163,184,.28);border-radius:.75rem;overflow:hidden;}
        .ma-sc__bar{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.625rem .75rem;border-bottom:1px solid rgba(148,163,184,.22);}
        .ma-sc__month{font-weight:700;font-size:.875rem;}
        .ma-sc__nav{display:flex;gap:.25rem;align-items:center;}
        .ma-sc__nav button{height:1.75rem;min-width:1.75rem;padding:0 .5rem;border-radius:.5rem;border:1px solid rgba(148,163,184,.40);background:transparent;color:inherit;cursor:pointer;font-size:.75rem;line-height:1;}
        .ma-sc__nav button:hover{background:rgba(148,163,184,.15);}

        .ma-sc__grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));}
        .ma-sc__dow{padding:.375rem .25rem;text-align:center;font-size:.625rem;font-weight:700;color:rgb(100,116,139);border-bottom:1px solid rgba(148,163,184,.22);}
        .ma-sc__cell{min-height:5.5rem;padding:.3125rem;border-inline-start:1px solid rgba(148,163,184,.14);border-bottom:1px solid rgba(148,163,184,.14);}
        .ma-sc__cell:nth-child(7n+8){border-inline-start:0;}
        .ma-sc__cell--out{opacity:.38;}
        .ma-sc__cell--today{background:rgba(59,130,246,.07);}
        .ma-sc__num{font-size:.6875rem;font-weight:700;color:rgb(100,116,139);margin-bottom:.1875rem;}
        .ma-sc__cell--today .ma-sc__num{color:rgb(37,99,235);}

        .ma-sc__event{display:block;padding:.1875rem .3125rem;margin-bottom:.1875rem;border-radius:.375rem;font-size:.625rem;line-height:1.3;border-inline-start:3px solid var(--primary-500);background:rgba(148,163,184,.12);}
        .ma-sc__event b{display:block;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .ma-sc__event span{display:block;color:rgb(100,116,139);font-variant-numeric:tabular-nums;direction:ltr;text-align:start;}
        /* Lessons entered for one date only — extra, make-up, private. Dashed
           and named, so they never pass for the weekly lesson. */
        .ma-sc__event--extra{border-inline-start-style:dashed;border-inline-start-color:rgb(244,63,94);}
        .ma-sc__event i{display:block;font-style:normal;font-size:.5625rem;font-weight:700;color:rgb(190,18,60);}
        .dark .ma-sc__event i{color:rgb(253,164,175);}

        .ma-sc__empty{padding:1.25rem;text-align:center;color:rgb(100,116,139);font-size:.8125rem;}
    </style>

    @php $grid = $this->grid; @endphp

    <div class="ma-sc">
        <div class="ma-sc__bar">
            <span class="ma-sc__month">{{ $grid['month'] }}</span>
            <span class="ma-sc__nav">
                <button type="button" wire:click="shiftMonth(-1)" aria-label="{{ __('Previous') }}">‹</button>
                <button type="button" wire:click="goToday">{{ __('Today') }}</button>
                <button type="button" wire:click="shiftMonth(1)" aria-label="{{ __('Next') }}">›</button>
            </span>
        </div>

        @if ($sectionIds === [])
            <p class="ma-sc__empty">{{ __('No sections') }}</p>
        @else
            <div class="ma-sc__grid">
                {{-- Locale-aware short weekday names, Saturday-first. --}}
                @php $dow = \Carbon\Carbon::parse('next saturday'); @endphp
                @for ($d = 0; $d < 7; $d++)
                    <div class="ma-sc__dow">{{ $dow->copy()->addDays($d)->translatedFormat('D') }}</div>
                @endfor

                @foreach ($grid['days'] as $day)
                    <div class="ma-sc__cell @if (! $day['inMonth']) ma-sc__cell--out @endif @if ($day['isToday']) ma-sc__cell--today @endif">
                        <div class="ma-sc__num">{{ $day['date']->day }}</div>

                        @foreach ($day['lessons'] as $lesson)
                            @php
                                $section = $lesson->section;
                                $subject = $section?->subject?->getTranslation('name', app()->getLocale(), false);
                                $extraLabel = $lesson->extra_session_type
                                    ? \App\Models\SectionSession::extraLabelFor($lesson->extra_session_type)
                                    : null;
                            @endphp
                            <span class="ma-sc__event @if ($extraLabel) ma-sc__event--extra @endif"
                                  title="{{ $section?->name }}{{ $subject ? ' — '.$subject : '' }}{{ $extraLabel ? ' — '.$extraLabel : '' }}{{ $lesson->room?->number ? ' — '.__('Room').' '.$lesson->room->number : '' }}">
                                <b>{{ $subject ?: $section?->name }}</b>
                                @if ($extraLabel)
                                    <i>{{ $extraLabel }}</i>
                                @endif
                                @if ($lesson->start_time)
                                    <span>{{ substr((string) $lesson->start_time, 0, 5) }}–{{ substr((string) ($lesson->end_time ?: $lesson->start_time), 0, 5) }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
