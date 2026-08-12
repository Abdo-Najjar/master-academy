<x-filament-panels::page>
    <style>
        /* One palette for the whole sheet, flipped as a block for dark mode, so
           the sticky name column and the striped rows never drift apart. */
        :root{
            --ma-as-surface:#fff;
            --ma-as-stripe:rgb(249,250,251);
            --ma-as-hover:rgb(243,244,246);
            --ma-as-head:rgb(248,250,252);
            --ma-as-foot:rgb(248,250,252);
            --ma-as-border:rgb(228,231,235);
            --ma-as-line:rgba(148,163,184,.18);
            --ma-as-text:rgb(30,41,59);
            --ma-as-muted:rgb(100,116,139);
        }
        .dark{
            --ma-as-surface:rgb(24,24,27);
            --ma-as-stripe:rgba(255,255,255,.022);
            --ma-as-hover:rgba(255,255,255,.055);
            --ma-as-head:rgb(39,39,42);
            --ma-as-foot:rgb(35,35,38);
            --ma-as-border:rgb(63,63,70);
            --ma-as-line:rgba(255,255,255,.07);
            --ma-as-text:rgb(228,228,231);
            --ma-as-muted:rgb(161,161,170);
        }

        .ma-as-meta{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem;font-size:.75rem;}
        .ma-as-meta span{display:inline-flex;align-items:center;gap:.375rem;padding:.25rem .625rem;border-radius:9999px;background:var(--ma-as-stripe);border:1px solid var(--ma-as-border);color:var(--ma-as-muted);}
        .ma-as-meta strong{color:var(--ma-as-text);font-weight:600;}

        .ma-as-scroll{overflow:auto;max-height:75vh;border:1px solid var(--ma-as-border);border-radius:.75rem;background:var(--ma-as-surface);}
        .ma-as-table{border-collapse:separate;border-spacing:0;font-size:.75rem;width:100%;color:var(--ma-as-text);}
        .ma-as-table th,.ma-as-table td{padding:.5rem;text-align:center;white-space:nowrap;border-bottom:1px solid var(--ma-as-line);}
        .ma-as-table tbody tr:last-child td{border-bottom:0;}

        /* Header: quiet surface + a single accent rule, instead of a solid red block. */
        .ma-as-table thead th{
            background:var(--ma-as-head);color:var(--ma-as-muted);
            font-weight:600;letter-spacing:.01em;
            position:sticky;top:0;z-index:2;
            border-bottom:1px solid var(--ma-as-border);
            box-shadow:inset 0 -2px 0 0 var(--primary-500);
        }
        .ma-as-table thead th.ma-as-name{z-index:3;background:var(--ma-as-head);}
        .ma-as-date{writing-mode:vertical-rl;transform:rotate(180deg);padding:.625rem .125rem;font-variant-numeric:tabular-nums;letter-spacing:.02em;}

        /* Sticky roster column: inherits the row's own background so striping and
           hover stay continuous while scrolling sideways. */
        .ma-as-table tbody tr{background:var(--ma-as-surface);}
        .ma-as-table tbody tr:nth-child(even){background:var(--ma-as-stripe);}
        .ma-as-table tbody tr:hover{background:var(--ma-as-hover);}
        .ma-as-name{
            position:sticky;inset-inline-start:0;z-index:1;
            background:inherit;text-align:start;
            min-width:200px;max-width:260px;white-space:normal;
            border-inline-end:1px solid var(--ma-as-border);
        }
        .ma-as-idx{color:var(--ma-as-muted);font-variant-numeric:tabular-nums;margin-inline-end:.25rem;}
        .ma-as-student{font-weight:600;}
        .ma-as-sub{font-size:.6875rem;color:var(--ma-as-muted);font-weight:400;}

        .ma-as-cell{width:1.875rem;height:1.75rem;line-height:1.75rem;border-radius:.375rem;display:inline-block;font-weight:700;font-size:.6875rem;}
        .ma-as-cell--present{background:rgba(34,197,94,.16);color:rgb(21,128,61);}
        .ma-as-cell--absent{background:rgba(239,68,68,.16);color:rgb(185,28,28);}
        .ma-as-cell--late{background:rgba(245,158,11,.18);color:rgb(180,83,9);}
        .ma-as-cell--excused{background:rgba(59,130,246,.16);color:rgb(29,78,216);}
        .ma-as-cell--none{color:var(--ma-as-muted);opacity:.45;font-weight:400;}
        .dark .ma-as-cell--present{background:rgba(34,197,94,.16);color:rgb(134,239,172);}
        .dark .ma-as-cell--absent{background:rgba(239,68,68,.18);color:rgb(252,165,165);}
        .dark .ma-as-cell--late{background:rgba(245,158,11,.18);color:rgb(253,224,71);}
        .dark .ma-as-cell--excused{background:rgba(59,130,246,.18);color:rgb(147,197,253);}

        .ma-as-total{font-variant-numeric:tabular-nums;font-weight:600;color:var(--ma-as-muted);}
        .ma-as-total.is-on--present{color:rgb(22,163,74);}
        .ma-as-total.is-on--absent{color:rgb(220,38,38);}
        .ma-as-total.is-on--late{color:rgb(217,119,6);}
        .ma-as-total.is-on--excused{color:rgb(37,99,235);}
        .dark .ma-as-total.is-on--present{color:rgb(134,239,172);}
        .dark .ma-as-total.is-on--absent{color:rgb(252,165,165);}
        .dark .ma-as-total.is-on--late{color:rgb(253,224,71);}
        .dark .ma-as-total.is-on--excused{color:rgb(147,197,253);}

        /* Attendance rate reads at a glance instead of being one more grey number. */
        .ma-as-rate{display:inline-block;min-width:3rem;padding:.125rem .5rem;border-radius:9999px;font-weight:700;font-variant-numeric:tabular-nums;font-size:.6875rem;}
        .ma-as-rate--good{background:rgba(34,197,94,.16);color:rgb(21,128,61);}
        .ma-as-rate--mid{background:rgba(245,158,11,.18);color:rgb(180,83,9);}
        .ma-as-rate--low{background:rgba(239,68,68,.16);color:rgb(185,28,28);}
        .dark .ma-as-rate--good{color:rgb(134,239,172);}
        .dark .ma-as-rate--mid{color:rgb(253,224,71);}
        .dark .ma-as-rate--low{color:rgb(252,165,165);}

        .ma-as-sep{border-inline-start:1px solid var(--ma-as-border);}
        .ma-as-table tfoot td{background:var(--ma-as-foot);font-weight:600;position:sticky;bottom:0;z-index:2;border-top:1px solid var(--ma-as-border);border-bottom:0;}
        .ma-as-table tfoot td.ma-as-name{z-index:3;background:var(--ma-as-foot);color:var(--ma-as-muted);}
        .ma-as-foot-label{font-weight:600;}

        .ma-as-legend{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.875rem;font-size:.75rem;color:var(--ma-as-muted);}
        .ma-as-legend span.ma-as-legend__item{display:inline-flex;align-items:center;gap:.4375rem;padding:.25rem .625rem;border-radius:9999px;border:1px solid var(--ma-as-border);}
        .ma-as-empty{text-align:center;color:var(--ma-as-muted);padding:1rem 0;}

        @media (max-width: 640px){
            .ma-as-name{min-width:150px;}
            .ma-as-scroll{max-height:65vh;}
        }
    </style>

    <x-filament::section>
        <div style="max-width:28rem;">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="sheetSectionId">
                    <option value="">{{ __('Select a section to begin.') }}</option>
                    @foreach ($this->sectionOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </x-filament::section>

    @php
        $section = $this->selectedSection();
        $sheet = $this->sheet;
        $statusColors = ['present' => 'present', 'absent' => 'absent', 'late' => 'late', 'excused' => 'excused'];

        $grandTotals = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        foreach ($sheet['rows'] as $sheetRow) {
            foreach ($grandTotals as $bucket => $_) {
                $grandTotals[$bucket] += $sheetRow['counts'][$bucket];
            }
        }
        $grandRecorded = array_sum($grandTotals);
        $overallRate = $grandRecorded > 0
            ? round((($grandTotals['present'] + $grandTotals['late']) / $grandRecorded) * 100, 1)
            : 0.0;
    @endphp

    @if (! $section)
        <x-filament::section>
            <p class="ma-as-empty">{{ __('Select a section to begin.') }}</p>
        </x-filament::section>
    @elseif ($sheet['dates'] === [])
        <x-filament::section>
            <p class="ma-as-empty">{{ __('No attendance has been recorded for this section yet.') }}</p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">
                {{ $section->name }}
            </x-slot>

            <x-slot name="description">
                <div class="ma-as-meta">
                    @if ($section->subject)
                        <span>{{ __('Course') }}: <strong>{{ $section->subject->getTranslation('name', app()->getLocale(), false) }}</strong></span>
                    @endif
                    @if ($section->trainer)
                        <span>{{ __('Trainer') }}: <strong>{{ $section->trainer->getTranslation('name', app()->getLocale(), false) }}</strong></span>
                    @endif
                    <span>{{ __('Students') }}: <strong>{{ count($sheet['rows']) }}</strong></span>
                    <span>{{ __('Sessions') }}: <strong>{{ count($sheet['dates']) }}</strong></span>
                </div>
            </x-slot>

            <x-slot name="afterHeader">
                <x-filament::button wire:click="exportSheet" color="success" size="sm"
                                    icon="heroicon-o-arrow-down-tray">
                    {{ __('Export to Excel') }}
                </x-filament::button>
            </x-slot>

            <div class="ma-as-scroll">
                <table class="ma-as-table">
                    <thead>
                        <tr>
                            <th class="ma-as-name">{{ __('Student') }}</th>
                            @foreach ($sheet['dates'] as $date)
                                <th>
                                    <div class="ma-as-date">{{ \Carbon\Carbon::parse($date)->format('d/m') }}</div>
                                </th>
                            @endforeach
                            <th class="ma-as-sep">{{ __('Present') }}</th>
                            <th>{{ __('Absent') }}</th>
                            <th>{{ __('Late') }}</th>
                            <th>{{ __('Excused') }}</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sheet['rows'] as $index => $row)
                            @php $student = $row['student']; @endphp
                            <tr>
                                <td class="ma-as-name">
                                    <span class="ma-as-idx">{{ $index + 1 }}.</span>
                                    <span class="ma-as-student">{{ $student->getTranslation('name', app()->getLocale(), false) }}</span>
                                    @if ($student->student_number)
                                        <span class="ma-as-sub">({{ $student->student_number }})</span>
                                    @endif
                                </td>

                                @foreach ($sheet['dates'] as $date)
                                    @php $status = $row['cells'][$date] ?? null; @endphp
                                    <td>
                                        @if ($status)
                                            <span class="ma-as-cell ma-as-cell--{{ $statusColors[$status] ?? 'none' }}"
                                                  title="{{ \Carbon\Carbon::parse($date)->translatedFormat('d M Y') }} — {{ \App\Filament\Admin\Pages\AttendanceRecords::statusLabels()[$status] ?? $status }}">
                                                {{ \App\Filament\Admin\Pages\AttendanceRecords::statusInitial($status) }}
                                            </span>
                                        @else
                                            <span class="ma-as-cell ma-as-cell--none">—</span>
                                        @endif
                                    </td>
                                @endforeach

                                @foreach (['present', 'absent', 'late', 'excused'] as $bucket)
                                    <td class="ma-as-total @if ($row['counts'][$bucket] > 0) is-on--{{ $bucket }} @endif @if ($bucket === 'present') ma-as-sep @endif">
                                        {{ $row['counts'][$bucket] }}
                                    </td>
                                @endforeach
                                <td>
                                    <span class="ma-as-rate ma-as-rate--{{ $row['rate'] >= 75 ? 'good' : ($row['rate'] >= 50 ? 'mid' : 'low') }}">
                                        {{ $row['rate'] }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="ma-as-name ma-as-foot-label">{{ __('Present') }}</td>
                            @foreach ($sheet['dates'] as $date)
                                <td class="ma-as-total is-on--present">
                                    {{ $sheet['columnTotals'][$date]['present'] + $sheet['columnTotals'][$date]['late'] }}
                                </td>
                            @endforeach
                            {{-- Column grand totals, instead of leaving the tail of the footer blank. --}}
                            @foreach (['present', 'absent', 'late', 'excused'] as $bucket)
                                <td class="ma-as-total @if ($grandTotals[$bucket] > 0) is-on--{{ $bucket }} @endif @if ($bucket === 'present') ma-as-sep @endif">
                                    {{ $grandTotals[$bucket] }}
                                </td>
                            @endforeach
                            <td>
                                <span class="ma-as-rate ma-as-rate--{{ $overallRate >= 75 ? 'good' : ($overallRate >= 50 ? 'mid' : 'low') }}">
                                    {{ $overallRate }}%
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="ma-as-legend">
                @foreach (\App\Filament\Admin\Pages\AttendanceRecords::statusLabels() as $key => $label)
                    <span class="ma-as-legend__item">
                        <span class="ma-as-cell ma-as-cell--{{ $key }}">{{ \App\Filament\Admin\Pages\AttendanceRecords::statusInitial($key) }}</span>
                        {{ $label }}
                    </span>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
