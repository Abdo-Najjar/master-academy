<x-filament-panels::page>
    <style>
        .ma-as-modes{display:flex;gap:.5rem;flex-wrap:wrap;}
        .ma-as-mode{padding:.5rem 1rem;border-radius:.5rem;font-size:.8125rem;font-weight:600;border:1px solid rgba(148,163,184,.35);background:transparent;color:rgb(100,116,139);cursor:pointer;}
        .ma-as-mode.is-active{background:rgb(220,38,38);border-color:rgb(220,38,38);color:#fff;}

        .ma-as-meta{display:flex;flex-wrap:wrap;gap:1.25rem;margin-top:.25rem;font-size:.75rem;color:rgb(100,116,139);}
        .ma-as-meta strong{color:inherit;font-weight:600;}

        .ma-as-scroll{overflow-x:auto;overflow-y:visible;}
        .ma-as-table{border-collapse:separate;border-spacing:0;font-size:.75rem;width:100%;}
        .ma-as-table th,.ma-as-table td{border-bottom:1px solid rgba(148,163,184,.20);padding:.375rem .5rem;text-align:center;white-space:nowrap;}
        .ma-as-table thead th{background:rgb(220,38,38);color:#fff;font-weight:600;position:sticky;top:0;z-index:2;}
        .ma-as-table thead th.ma-as-name{z-index:3;}
        .ma-as-date{writing-mode:vertical-rl;transform:rotate(180deg);padding:.5rem .25rem;font-variant-numeric:tabular-nums;}
        .ma-as-name{position:sticky;inset-inline-start:0;background:var(--ma-as-sticky,#fff);text-align:start;min-width:190px;max-width:240px;white-space:normal;}
        .dark .ma-as-name{--ma-as-sticky:rgb(24,24,27);}
        .ma-as-table thead th.ma-as-name{background:rgb(220,38,38);}
        .ma-as-idx{color:rgb(100,116,139);font-variant-numeric:tabular-nums;}
        .ma-as-student{font-weight:600;}
        .ma-as-sub{font-size:.6875rem;color:rgb(100,116,139);font-weight:400;}

        .ma-as-cell{width:2rem;height:1.75rem;line-height:1.75rem;border-radius:.3125rem;display:inline-block;font-weight:700;font-size:.6875rem;}
        .ma-as-cell--present{background:rgba(34,197,94,.18);color:rgb(21,94,47);}
        .ma-as-cell--absent{background:rgba(239,68,68,.18);color:rgb(153,27,27);}
        .ma-as-cell--late{background:rgba(245,158,11,.20);color:rgb(146,64,14);}
        .ma-as-cell--excused{background:rgba(59,130,246,.18);color:rgb(30,64,175);}
        .ma-as-cell--none{color:rgb(203,213,225);font-weight:400;}
        .dark .ma-as-cell--present{color:rgb(134,239,172);}
        .dark .ma-as-cell--absent{color:rgb(252,165,165);}
        .dark .ma-as-cell--late{color:rgb(253,224,71);}
        .dark .ma-as-cell--excused{color:rgb(147,197,253);}

        .ma-as-total{font-variant-numeric:tabular-nums;font-weight:600;}
        .ma-as-total--present{color:rgb(22,163,74);}
        .ma-as-total--absent{color:rgb(220,38,38);}
        .ma-as-total--late{color:rgb(217,119,6);}
        .ma-as-total--excused{color:rgb(37,99,235);}
        .ma-as-sep{border-inline-start:2px solid rgba(148,163,184,.35);}
        .ma-as-table tfoot td{background:rgba(148,163,184,.10);font-weight:600;}

        .ma-as-legend{display:flex;flex-wrap:wrap;gap:1rem;margin-top:.75rem;font-size:.75rem;color:rgb(100,116,139);}
        .ma-as-legend span{display:flex;align-items:center;gap:.375rem;}
        .ma-as-empty{text-align:center;color:rgb(100,116,139);padding:1rem 0;}
    </style>

    {{-- View switcher: flat record list, or the per-section sheet --}}
    <x-filament::section>
        <div class="ma-as-modes">
            <button type="button" wire:click="$set('viewMode', 'records')"
                    class="ma-as-mode @if ($viewMode === 'records') is-active @endif">
                {{ __('Attendance Records') }}
            </button>
            <button type="button" wire:click="$set('viewMode', 'sheet')"
                    class="ma-as-mode @if ($viewMode === 'sheet') is-active @endif">
                {{ __('Section Sheet') }}
            </button>
        </div>

        @if ($viewMode === 'sheet')
            <div style="margin-top:1rem;max-width:28rem;">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sheetSectionId">
                        <option value="">{{ __('Select a section to begin.') }}</option>
                        @foreach ($this->sectionOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endif
    </x-filament::section>

    @if ($viewMode === 'records')
        {{ $this->table }}
    @else
        @php
            $section = $this->selectedSection();
            $sheet = $this->sheet;
            $statusColors = ['present' => 'present', 'absent' => 'absent', 'late' => 'late', 'excused' => 'excused'];
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

                                    <td class="ma-as-total ma-as-total--present ma-as-sep">{{ $row['counts']['present'] }}</td>
                                    <td class="ma-as-total ma-as-total--absent">{{ $row['counts']['absent'] }}</td>
                                    <td class="ma-as-total ma-as-total--late">{{ $row['counts']['late'] }}</td>
                                    <td class="ma-as-total ma-as-total--excused">{{ $row['counts']['excused'] }}</td>
                                    <td class="ma-as-total">{{ $row['rate'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="ma-as-name">{{ __('Present') }}</td>
                                @foreach ($sheet['dates'] as $date)
                                    <td class="ma-as-total ma-as-total--present">
                                        {{ $sheet['columnTotals'][$date]['present'] + $sheet['columnTotals'][$date]['late'] }}
                                    </td>
                                @endforeach
                                <td class="ma-as-sep" colspan="5"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="ma-as-legend">
                    @foreach (\App\Filament\Admin\Pages\AttendanceRecords::statusLabels() as $key => $label)
                        <span>
                            <span class="ma-as-cell ma-as-cell--{{ $key }}">{{ \App\Filament\Admin\Pages\AttendanceRecords::statusInitial($key) }}</span>
                            {{ $label }}
                        </span>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
