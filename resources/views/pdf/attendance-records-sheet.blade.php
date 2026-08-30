<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ __('Attendance Records') }} — {{ $sectionName }}</title>
<style>
  /* Deliberately the same shape as the on-screen sheet: the roster first, one
     column per session of the month, tallies on the tail. Sized for a full A4
     landscape page rather than squeezed into its top corner. */
  @page { margin: 12mm 10mm 16mm; }
  body { font-family: tajawal, sans-serif; font-size: 9.5pt; color: #1e293b; }

  /* Masthead: logo + centre name on one side, what this sheet is on the other.
     mPDF never applies a descendant CSS rule to an `img`, so the mark is sized
     with width/height attributes on the tag itself — left to itself it draws at
     the file's intrinsic 512px and eats the whole first page. */
  table.masthead { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
  table.masthead td { vertical-align: middle; padding: 0 0 7pt; border-bottom: 2pt solid #b91c1c; }
  td.mark { width: 34pt; }
  .brand { font-size: 15pt; font-weight: bold; color: #0f172a; }
  .doc { text-align: left; font-size: 9pt; color: #475569; }
  .doc .month { display: block; font-size: 11.5pt; font-weight: bold; color: #b91c1c; margin-bottom: 1pt; }

  /* Meta strip: five boxed facts, so the header does not read as a loose row
     of words floating across the page. */
  table.meta { width: 100%; border-collapse: separate; border-spacing: 5pt 0; margin-bottom: 9pt; }
  table.meta td { width: 20%; border: 1pt solid #e2e8f0; background: #f8fafc; border-radius: 4pt; padding: 4pt 7pt; }
  table.meta .lbl { color: #64748b; font-size: 7pt; margin-bottom: 2pt; }
  table.meta .val { font-size: 9.5pt; font-weight: bold; color: #0f172a; }

  /* Horizontal rules only. A full box around every cell turns a 12-column
     sheet into graph paper; letting the rows breathe and separating them with
     one hairline reads far calmer and still lines up. */
  table.grid { width: 100%; border-collapse: collapse; }
  table.grid th, table.grid td {
    border: 0;
    border-bottom: 0.6pt solid #eef2f6;
    padding: 5pt 3pt;
    text-align: center;
  }
  table.grid thead th {
    background: #f8fafc; color: #475569; font-size: 8pt; font-weight: bold;
    border-bottom: 1.5pt solid #cbd5e1; padding: 6pt 3pt;
  }
  table.grid tbody tr:nth-child(even) td { background: #fcfdfe; }
  table.grid tbody tr:last-child td { border-bottom: 0; }
  table.grid tr { page-break-inside: avoid; }

  /* Physical sides, not logical ones: mPDF understands `right`, and silently
     drops `inline-start` / `text-align: start`. The document is RTL, so the
     roster column's leading edge is the right one. */
  th.name, td.name { text-align: right; width: 19%; padding-right: 8pt; }
  td.name { font-size: 9pt; }
  .idx { color: #94a3b8; font-weight: normal; }
  .who { font-weight: bold; }
  .num { color: #64748b; font-size: 8pt; font-weight: normal; }
  .phone { direction: ltr; font-size: 8.5pt; }

  th.date .d { font-weight: bold; font-size: 9pt; display: block; }
  th.date .w { font-size: 7pt; font-weight: normal; color: #64748b; display: block; margin-top: 1pt; }

  /* Status marks: a filled chip, same colour language as the screen, in softer
     tints than the screen uses — print exaggerates saturation. */
  .cell { display: inline-block; min-width: 16pt; padding: 3pt 5pt; border-radius: 9pt; font-weight: bold; font-size: 9pt; }
  .c-present { background: #e8f8ee; color: #1a7f45; }
  .c-absent { background: #fdecec; color: #c0392b; }
  .c-late { background: #fdf4e3; color: #b06f1a; }
  .c-excused { background: #e9f1fd; color: #2563a8; }
  .c-none { color: #d7dee6; font-weight: normal; }

  td.tally { font-weight: bold; font-size: 10pt; color: #94a3b8; }
  td.tally.on-present { color: #16a34a; }
  td.tally.on-absent { color: #dc2626; }
  td.tally.on-late { color: #d97706; }
  td.tally.on-excused { color: #2563eb; }
  /* Divider between the day columns and the tallies. In RTL the tallies sit to
     the left of the dates, so the rule belongs on the right edge. */
  .sep { border-right: 2pt solid #94a3b8 !important; }

  .rate { display: inline-block; padding: 3pt 7pt; border-radius: 9pt; font-weight: bold; font-size: 9pt; }
  .r-good { background: #e8f8ee; color: #1a7f45; }
  .r-mid { background: #fdf4e3; color: #b06f1a; }
  .r-low { background: #fdecec; color: #c0392b; }

  .fin { display: inline-block; padding: 2pt 6pt; border-radius: 9pt; font-size: 7.5pt; font-weight: bold; }
  .f-ok { background: #e8f8ee; color: #1a7f45; }
  .f-warning, .f-due { background: #fdf4e3; color: #b06f1a; }
  .f-overdue { background: #fdecec; color: #c0392b; }
  /* The status word alone never said paid *how much*. */
  td.money { width: 13%; }
  .amt { margin-top: 2pt; font-size: 7.5pt; color: #64748b; }

  tfoot td { background: #f8fafc; font-weight: bold; border-top: 1.5pt solid #cbd5e1; border-bottom: 0; padding: 6pt 3pt; }
  tfoot td.name { color: #475569; }

  /* Legend: each mark and its word live in one bordered pill, so no amount of
     RTL shaping can run "ح حاضر" into "غ غائب" the way loose inline spans did. */
  table.legend { border-collapse: separate; border-spacing: 7pt 0; margin-top: 10pt; }
  table.legend td {
    font-size: 8.5pt; color: #475569; white-space: nowrap;
    border: 0.6pt solid #e6ebf0; border-radius: 10pt;
    padding: 4pt 9pt; background: #fcfdfe;
  }
  table.legend .cell { margin-left: 6pt; }

  /* Just the trainer's line: the stamp and date boxes were dead space. */
  table.sign { margin-top: 22pt; page-break-inside: avoid; }
  table.sign td { width: 170pt; }
  table.sign .line { border-top: 1pt solid #94a3b8; padding-top: 6pt; font-size: 8.5pt; color: #64748b; text-align: center; }

  .printed { margin-top: 12pt; font-size: 7.5pt; color: #a8b3c0; }
</style>
</head>
<body>
@php
    $rateClass = fn (float $rate): string => $rate >= 75 ? 'r-good' : ($rate >= 50 ? 'r-mid' : 'r-low');
    $initial = fn (string $status): string => \App\Filament\Admin\Pages\AttendanceRecords::statusInitial($status);
@endphp

<table class="masthead">
  <tr>
    @if ($logo)
      <td class="mark"><img src="{{ $logo }}" alt="" width="30" height="30"></td>
    @endif
    <td><span class="brand">{{ \App\Support\AppBranding::appName() }}</span></td>
    <td class="doc">
      <span class="month">{{ __('Month :number', ['number' => $sheet['month']]) }} / {{ $sheet['months'] }}</span>
      {{ __('Attendance Records') }}
    </td>
  </tr>
</table>

<table class="meta">
  <tr>
    <td><div class="lbl">{{ __('Section') }}</div><div class="val">{{ $sectionName }}</div></td>
    <td><div class="lbl">{{ __('Course') }}</div><div class="val">{{ $subjectName ?? '—' }}</div></td>
    <td><div class="lbl">{{ __('Trainer') }}</div><div class="val">{{ $trainerName ?? '—' }}</div></td>
    <td><div class="lbl">{{ __('Students') }}</div><div class="val">{{ count($sheet['rows']) }}</div></td>
    <td>
      <div class="lbl">{{ __('Sessions') }}</div>
      <div class="val">{{ count($sheet['dates']) }} <span class="num">/ {{ $sheet['allDates'] }}</span></div>
    </td>
  </tr>
</table>

<table class="grid">
  <thead>
    <tr>
      <th class="name">{{ __('Student') }}</th>
      <th>{{ __('Phone') }}</th>
      <th>{{ __('Financial Status') }}</th>
      @foreach ($sheet['dates'] as $date)
        @php $day = \Carbon\Carbon::parse($date); @endphp
        <th class="date">
          <span class="d">{{ $day->format('d/m') }}</span>
          <span class="w">{{ $day->translatedFormat('D') }}</span>
        </th>
      @endforeach
      <th class="sep">{{ __('Present') }}</th>
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
        <td class="name">
          <span class="idx">{{ $index + 1 }}.</span>
          <span class="who">{{ $student->getTranslation('name', app()->getLocale(), false) }}</span>
          @if ($student->student_number)
            <span class="num">({{ $student->student_number }})</span>
          @endif
        </td>
        <td class="phone">{{ $row['phone'] ?: '—' }}</td>
        <td class="money">
          @if ($row['financial_status'])
            <span class="fin f-{{ $row['financial_status'] }}">
              {{ $financialLabels[$row['financial_status']] ?? $row['financial_status'] }}
            </span>
            @if ($amounts = \App\Filament\Admin\Pages\AttendanceRecords::financialAmounts($row))
              <div class="amt">{{ $amounts }}</div>
            @endif
          @else
            —
          @endif
        </td>

        @foreach ($sheet['dates'] as $date)
          @php $status = $row['cells'][$date] ?? null; @endphp
          <td>
            @if ($status)
              <span class="cell c-{{ $status }}">{{ $initial($status) }}</span>
            @else
              <span class="cell c-none">—</span>
            @endif
          </td>
        @endforeach

        @foreach (['present', 'absent', 'late', 'excused'] as $bucket)
          <td class="tally @if ($row['counts'][$bucket] > 0) on-{{ $bucket }} @endif @if ($bucket === 'present') sep @endif">
            {{ $row['counts'][$bucket] }}
          </td>
        @endforeach
        <td><span class="rate {{ $rateClass((float) $row['rate']) }}">{{ $row['rate'] }}%</span></td>
      </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr>
      <td class="name">{{ __('Present') }}</td>
      <td colspan="2"></td>
      @foreach ($sheet['dates'] as $date)
        <td class="tally on-present">
          {{ $sheet['columnTotals'][$date]['present'] + $sheet['columnTotals'][$date]['late'] }}
        </td>
      @endforeach
      @foreach (['present', 'absent', 'late', 'excused'] as $bucket)
        <td class="tally @if ($grandTotals[$bucket] > 0) on-{{ $bucket }} @endif @if ($bucket === 'present') sep @endif">
          {{ $grandTotals[$bucket] }}
        </td>
      @endforeach
      <td><span class="rate {{ $rateClass((float) $overallRate) }}">{{ $overallRate }}%</span></td>
    </tr>
  </tfoot>
</table>

<table class="legend">
  <tr>
    @foreach ($labels as $key => $label)
      <td><span class="cell c-{{ $key }}">{{ $initial($key) }}</span>{{ $label }}</td>
    @endforeach
  </tr>
</table>

<table class="sign">
  <tr>
    <td><div class="line">{{ __('Trainer Signature') }}</div></td>
  </tr>
</table>

<div class="printed">
  {{ __('Every :count sessions count as one month.', ['count' => $sheet['perMonth']]) }}
  — {{ $printedAt->translatedFormat('d F Y H:i') }}
</div>
</body>
</html>
