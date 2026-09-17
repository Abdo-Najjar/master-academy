<x-filament-panels::page>
    <style>
        .ma-rep-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;}
        .ma-rep-card{padding:1rem;border-radius:.75rem;border:1px solid;}
        .ma-rep-card__label{font-size:.75rem;color:rgb(100,116,139);margin:0;}
        .ma-rep-card__value{font-size:1.5rem;font-weight:700;margin:.25rem 0 0;}
        .ma-rep-card__hint{font-size:.7rem;color:rgb(148,163,184);margin:.125rem 0 0;}
        .ma-rep-card--blue{background:rgba(59,130,246,.08);border-color:rgba(59,130,246,.25);}
        .ma-rep-card--blue .ma-rep-card__value{color:rgb(37,99,235);}
        .ma-rep-card--indigo{background:rgba(99,102,241,.08);border-color:rgba(99,102,241,.25);}
        .ma-rep-card--indigo .ma-rep-card__value{color:rgb(79,70,229);}
        .ma-rep-card--green{background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.25);}
        .ma-rep-card--green .ma-rep-card__value{color:rgb(22,163,74);}
        .ma-rep-card--emerald{background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.25);}
        .ma-rep-card--emerald .ma-rep-card__value{color:rgb(5,150,105);}
        .ma-rep-card--red{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.25);}
        .ma-rep-card--red .ma-rep-card__value{color:rgb(220,38,38);}
        .ma-rep-card--amber{background:rgba(245,158,11,.08);border-color:rgba(245,158,11,.25);}
        .ma-rep-card--amber .ma-rep-card__value{color:rgb(180,83,9);}
        .ma-rep-card--purple{background:rgba(168,85,247,.08);border-color:rgba(168,85,247,.25);}
        .ma-rep-card--purple .ma-rep-card__value{color:rgb(147,51,234);}

        .ma-rep-2col{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;}
        .ma-rep-finrow{display:flex;align-items:center;justify-content:space-between;font-size:.875rem;}
        .ma-rep-finrow__label{color:rgb(100,116,139);}
        .ma-rep-finrow__val{font-weight:600;}
        .ma-rep-finrow__val--amber{color:rgb(217,119,6);}
        .ma-rep-finrow__val--blue{color:rgb(37,99,235);}
        .ma-rep-finrow--total{border-top:1px solid rgba(148,163,184,.30);padding-top:.75rem;}
        .ma-rep-finrow--total .ma-rep-finrow__val{font-size:1.125rem;color:rgb(22,163,74);font-weight:700;}

        .ma-rep-ab{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;}
        .ma-rep-ab__cell{padding:.75rem;border-radius:.5rem;}
        .ma-rep-ab__label{font-size:.75rem;margin:0;}
        .ma-rep-ab__value{font-size:1.25rem;font-weight:700;margin:.25rem 0 0;}
        .ma-rep-ab--green{background:rgba(34,197,94,.1);}
        .ma-rep-ab--green .ma-rep-ab__label,.ma-rep-ab--green .ma-rep-ab__value{color:rgb(22,163,74);}
        .ma-rep-ab--red{background:rgba(239,68,68,.1);}
        .ma-rep-ab--red .ma-rep-ab__label,.ma-rep-ab--red .ma-rep-ab__value{color:rgb(239,68,68);}
        .ma-rep-ab--amber{background:rgba(245,158,11,.1);}
        .ma-rep-ab--amber .ma-rep-ab__label,.ma-rep-ab--amber .ma-rep-ab__value{color:rgb(245,158,11);}
        .ma-rep-ab--blue{background:rgba(59,130,246,.1);}
        .ma-rep-ab--blue .ma-rep-ab__label,.ma-rep-ab--blue .ma-rep-ab__value{color:rgb(59,130,246);}

        .ma-rep-table{width:100%;font-size:.875rem;border-collapse:collapse;}
        .ma-rep-table thead{font-size:.75rem;color:rgb(100,116,139);}
        .ma-rep-table thead tr{border-bottom:1px solid rgba(148,163,184,.30);}
        .ma-rep-table th{padding:.5rem 0;text-align:start;font-weight:500;}
        .ma-rep-table th.end{text-align:end;}
        .ma-rep-table tbody tr{border-top:1px solid rgba(148,163,184,.15);}
        .ma-rep-table td{padding:.5rem 0;}
        .ma-rep-table td.name{font-weight:500;}
        .ma-rep-table td.money{text-align:end;font-weight:600;color:rgb(22,163,74);}

        .ma-rep-empty{font-size:.875rem;color:rgb(100,116,139);}
        .ma-rep-overflow{overflow-x:auto;}
    </style>

    @php
        $stats = $this->stats;
        $topTrainers = $this->topTrainers;
        $levels = $this->subjectBreakdown;
        $withdrawalStats = $this->withdrawalStats;
        $money = fn ($n) => number_format((float) $n, 2).' ₪';

        $centreWide = ! $this->hasTrainerFilter();
        $expenseStats = $this->expenseStats;
        $expenseBreakdown = $this->expenseBreakdown;
        $bookingStats = $this->bookingStats;
        $collections = $this->collections;
        $netResult = $this->netResult;
    @endphp

    {{-- Filters --}}
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    {{-- Top stats cards --}}
    <x-filament::section>
        <div class="ma-rep-stats">
            <div class="ma-rep-card ma-rep-card--blue">
                <p class="ma-rep-card__label">{{ __('Registrations') }}</p>
                <p class="ma-rep-card__value">{{ number_format($stats['registrations']) }}</p>
            </div>
            <div class="ma-rep-card ma-rep-card--indigo">
                <p class="ma-rep-card__label">{{ __('New Students') }}</p>
                <p class="ma-rep-card__value">{{ number_format($stats['new_students']) }}</p>
            </div>
            <div class="ma-rep-card ma-rep-card--green">
                <p class="ma-rep-card__label">{{ __('Revenue') }}</p>
                <p class="ma-rep-card__value">{{ $money($stats['revenue']) }}</p>
            </div>
            <div class="ma-rep-card ma-rep-card--red">
                <p class="ma-rep-card__label">{{ __('Outstanding from Students') }}</p>
                <p class="ma-rep-card__value">{{ $money($stats['outstanding']) }}</p>
            </div>
            <div class="ma-rep-card ma-rep-card--emerald">
                <p class="ma-rep-card__label">{{ __('Attendance Rate') }}</p>
                <p class="ma-rep-card__value">{{ $stats['attendance_rate'] }}%</p>
                <p class="ma-rep-card__hint">{{ __(':total marks', ['total' => number_format($stats['attendance_total'])]) }}</p>
            </div>
        </div>
    </x-filament::section>

    {{-- Financial + Attendance breakdown --}}
    <div class="ma-rep-2col">
        <x-filament::section icon="heroicon-o-banknotes">
            <x-slot name="heading">{{ __('Financial Breakdown') }}</x-slot>
            <div style="display:flex;flex-direction:column;gap:.75rem;">
                <div class="ma-rep-finrow">
                    <span class="ma-rep-finrow__label">{{ __('Gross Revenue') }}</span>
                    <span class="ma-rep-finrow__val">{{ $money($stats['revenue']) }}</span>
                </div>
                <div class="ma-rep-finrow">
                    <span class="ma-rep-finrow__label">{{ __('Exemptions / Discounts') }}</span>
                    <span class="ma-rep-finrow__val ma-rep-finrow__val--amber">{{ $money($stats['exemptions']) }}</span>
                </div>
                <div class="ma-rep-finrow">
                    <span class="ma-rep-finrow__label">{{ __('Trainer Share') }}</span>
                    <span class="ma-rep-finrow__val ma-rep-finrow__val--blue">{{ $money($stats['trainer_share']) }}</span>
                </div>
                <div class="ma-rep-finrow">
                    <span class="ma-rep-finrow__label">{{ __('Outstanding from Students') }}</span>
                    <span class="ma-rep-finrow__val" style="color:rgb(220,38,38);">{{ $money($stats['outstanding']) }}</span>
                </div>
                <div class="ma-rep-finrow ma-rep-finrow--total">
                    <span class="ma-rep-finrow__label" style="color:inherit;font-weight:600;">{{ __('Net Revenue') }}</span>
                    <span class="ma-rep-finrow__val">{{ $money($stats['net_revenue']) }}</span>
                </div>

                @if ($centreWide)
                    {{-- What the courses earned is only half the ledger: the halls
                         bring money in too, and rent and bills take it out. --}}
                    <div class="ma-rep-finrow">
                        <span class="ma-rep-finrow__label">{{ __('Room Booking Income') }}</span>
                        <span class="ma-rep-finrow__val" style="color:rgb(147,51,234);">{{ $money($bookingStats['collected']) }}</span>
                    </div>
                    <div class="ma-rep-finrow">
                        <span class="ma-rep-finrow__label">{{ __('Total Expenses') }}</span>
                        <span class="ma-rep-finrow__val" style="color:rgb(220,38,38);">− {{ $money($expenseStats['total']) }}</span>
                    </div>
                    <div class="ma-rep-finrow ma-rep-finrow--total">
                        <span class="ma-rep-finrow__label" style="color:inherit;font-weight:600;">{{ __('Net After Expenses') }}</span>
                        <span class="ma-rep-finrow__val" style="{{ $netResult < 0 ? 'color:rgb(220,38,38);' : '' }}">{{ $money($netResult) }}</span>
                    </div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-check-badge">
            <x-slot name="heading">{{ __('Attendance Breakdown') }}</x-slot>
            <div class="ma-rep-ab">
                @foreach ([
                    'present' => [__('Present'), 'green'],
                    'absent'  => [__('Absent'),  'red'],
                    'late'    => [__('Late'),    'amber'],
                    'excused' => [__('Excused'), 'blue'],
                ] as $key => [$label, $color])
                    <div class="ma-rep-ab__cell ma-rep-ab--{{ $color }}">
                        <p class="ma-rep-ab__label">{{ $label }}</p>
                        <p class="ma-rep-ab__value">{{ number_format($stats['attendance_breakdown'][$key]) }}</p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    @if ($centreWide)
        {{-- Money out, and the halls let out to people who are not students --}}
        <x-filament::section>
            <div class="ma-rep-stats">
                <div class="ma-rep-card ma-rep-card--red">
                    <p class="ma-rep-card__label">{{ __('Total Expenses') }}</p>
                    <p class="ma-rep-card__value">{{ $money($expenseStats['total']) }}</p>
                    <p class="ma-rep-card__hint">{{ __(':count records', ['count' => number_format($expenseStats['count'])]) }}</p>
                </div>
                <div class="ma-rep-card ma-rep-card--purple">
                    <p class="ma-rep-card__label">{{ __('Room Booking Income') }}</p>
                    <p class="ma-rep-card__value">{{ $money($bookingStats['collected']) }}</p>
                    <p class="ma-rep-card__hint">{{ __(':count records', ['count' => number_format($bookingStats['count'])]) }}</p>
                </div>
                <div class="ma-rep-card ma-rep-card--amber">
                    <p class="ma-rep-card__label">{{ __('Outstanding from Bookings') }}</p>
                    <p class="ma-rep-card__value">{{ $money($bookingStats['outstanding']) }}</p>
                    <p class="ma-rep-card__hint">{{ __('Booking Price') }}: {{ $money($bookingStats['contracted']) }}</p>
                </div>
                <div class="ma-rep-card ma-rep-card--green">
                    <p class="ma-rep-card__label">{{ __('Net After Expenses') }}</p>
                    <p class="ma-rep-card__value">{{ $money($netResult) }}</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Where the money came in, and where it went --}}
        <div class="ma-rep-2col">
            <x-filament::section icon="heroicon-o-banknotes">
                <x-slot name="heading">{{ __('Payments Received by Method') }}</x-slot>
                <p class="ma-rep-card__hint" style="margin:0 0 .75rem;">
                    {{ __('Cash actually banked in the period — student payments and booking instalments together.') }}
                </p>
                @if ($collections['by_type']->isEmpty())
                    <p class="ma-rep-empty">{{ __('No data for the selected period') }}</p>
                @else
                    <div class="ma-rep-overflow">
                        <table class="ma-rep-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Payment Type') }}</th>
                                    <th>{{ __('Records') }}</th>
                                    <th class="end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($collections['by_type'] as $row)
                                    <tr>
                                        <td class="name">{{ $row['name'] }}</td>
                                        <td>{{ number_format($row['count']) }}</td>
                                        <td class="money">{{ $money($row['total']) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="name">{{ __('Total') }}</td>
                                    <td>{{ number_format($collections['count']) }}</td>
                                    <td class="money">{{ $money($collections['total']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section icon="heroicon-o-arrow-trending-down">
                <x-slot name="heading">{{ __('Expenses by Type') }}</x-slot>
                @if ($expenseBreakdown->isEmpty())
                    <p class="ma-rep-empty">{{ __('No data for the selected period') }}</p>
                @else
                    <div class="ma-rep-overflow">
                        <table class="ma-rep-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Expense Type') }}</th>
                                    <th>{{ __('Records') }}</th>
                                    <th class="end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($expenseBreakdown as $row)
                                    <tr>
                                        <td class="name">{{ $row['name'] }}</td>
                                        <td>{{ number_format($row['count']) }}</td>
                                        <td class="money" style="color:rgb(220,38,38);">{{ $money($row['total']) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="name">{{ __('Total Expenses') }}</td>
                                    <td>{{ number_format($expenseStats['count']) }}</td>
                                    <td class="money" style="color:rgb(220,38,38);">{{ $money($expenseStats['total']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif

    {{-- Withdrawal + certificate stats --}}
    <x-filament::section>
        <div class="ma-rep-stats">
            <div class="ma-rep-card ma-rep-card--blue">
                <p class="ma-rep-card__label">{{ __('Withdrawn (period)') }}</p>
                <p class="ma-rep-card__value">{{ number_format($withdrawalStats['withdrawn']) }}</p>
            </div>
            <div class="ma-rep-card ma-rep-card--indigo">
                <p class="ma-rep-card__label">{{ __('Suspended') }}</p>
                <p class="ma-rep-card__value">{{ number_format($withdrawalStats['suspended']) }}</p>
            </div>
            <div class="ma-rep-card ma-rep-card--green">
                <p class="ma-rep-card__label">{{ __('Archived') }}</p>
                <p class="ma-rep-card__value">{{ number_format($withdrawalStats['archived']) }}</p>
            </div>
            <div class="ma-rep-card ma-rep-card--emerald">
                <p class="ma-rep-card__label">{{ __('Certificates Issued (period)') }}</p>
                <p class="ma-rep-card__value">{{ number_format($withdrawalStats['certificates_issued']) }}</p>
            </div>
        </div>
    </x-filament::section>

    {{-- Due/overdue students: a real table — searchable, sortable, paginated,
         and actionable without leaving the report. --}}
    {{ $this->table }}

    {{-- Top trainers + Subject breakdown --}}
    <div class="ma-rep-2col">
        <x-filament::section icon="heroicon-o-trophy">
            <x-slot name="heading">{{ __('Top 5 Trainers by Revenue') }}</x-slot>
            @if ($topTrainers->isEmpty())
                <p class="ma-rep-empty">{{ __('No data for the selected period') }}</p>
            @else
                <div class="ma-rep-overflow">
                    <table class="ma-rep-table">
                        <thead>
                            <tr>
                                <th>{{ __('Trainer') }}</th>
                                <th>{{ __('Registrations') }}</th>
                                <th class="end">{{ __('Revenue') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topTrainers as $trainer)
                                <tr>
                                    <td class="name">{{ is_array($trainer->name) ? ($trainer->name[app()->getLocale()] ?? reset($trainer->name)) : $trainer->name }}</td>
                                    <td>{{ number_format($trainer->registrations_count ?? 0) }}</td>
                                    <td class="money">{{ $money($trainer->revenue ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section icon="heroicon-o-book-open">
            <x-slot name="heading">{{ __('Registrations by Subject') }}</x-slot>
            @if ($levels->isEmpty())
                <p class="ma-rep-empty">{{ __('No data for the selected period') }}</p>
            @else
                <div class="ma-rep-overflow">
                    <table class="ma-rep-table">
                        <thead>
                            <tr>
                                <th>{{ __('Subject') }}</th>
                                <th>{{ __('Registrations') }}</th>
                                <th class="end">{{ __('Revenue') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($levels as $level)
                                @php
                                    $name = $level->subject_name;
                                    if (is_string($name) && str_starts_with($name, '{')) {
                                        $decoded = json_decode($name, true);
                                        $name = is_array($decoded) ? ($decoded[app()->getLocale()] ?? reset($decoded)) : $name;
                                    }
                                @endphp
                                <tr>
                                    <td class="name">{{ $name }}</td>
                                    <td>{{ number_format($level->total) }}</td>
                                    <td class="money">{{ $money($level->revenue) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
