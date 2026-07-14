<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ __('Attendance Sheet') }} — {{ $sectionName }}</title>
<style>
  @page { margin: 16mm 14mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #111; }
  h1 { font-size: 15pt; margin: 0 0 4pt; }
  .muted { color: #666; font-size: 9pt; }
  .head { border-bottom: 2px solid #000; padding-bottom: 8pt; margin-bottom: 10pt; }
  .meta { display: table; width: 100%; margin-bottom: 10pt; }
  .meta .cell { display: table-cell; width: 25%; font-size: 9.5pt; }
  .meta .cell strong { display: block; color: #555; font-size: 8pt; font-weight: normal; margin-bottom: 2pt; }
  table.roster { width: 100%; border-collapse: collapse; margin-top: 6pt; }
  table.roster th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 5pt 6pt; font-size: 9.5pt; text-align: start; }
  table.roster td { border: 1px solid #e2e8f0; padding: 5pt 6pt; font-size: 9.5pt; }
  table.roster tr:nth-child(even) td { background: #fafafa; }
  .badge { display: inline-block; padding: 1pt 6pt; border-radius: 8pt; font-size: 8.5pt; }
  .b-present { background: #dcfce7; color: #15803d; }
  .b-absent { background: #fee2e2; color: #b91c1c; }
  .b-late { background: #fef3c7; color: #92400e; }
  .b-excused { background: #dbeafe; color: #1d4ed8; }
  .b-none { background: #f1f5f9; color: #64748b; }
  .summary { display: table; width: 100%; margin-top: 12pt; }
  .summary .cell { display: table-cell; width: 25%; text-align: center; border: 1px solid #e2e8f0; padding: 6pt; }
  .summary .cell strong { display: block; font-size: 13pt; }
  .footer { margin-top: 26pt; display: table; width: 100%; }
  .footer .cell { display: table-cell; width: 50%; font-size: 9.5pt; }
  .footer .line { margin-top: 26pt; border-top: 1px solid #999; width: 70%; padding-top: 3pt; color: #666; font-size: 8.5pt; }
</style>
</head>
<body>
  <div class="head">
    <h1>{{ \App\Support\AppBranding::appName() }}</h1>
    <div class="muted">{{ __('Attendance Sheet') }}</div>
  </div>

  <div class="meta">
    <div class="cell"><strong>{{ __('Section') }}</strong>{{ $sectionName }}</div>
    <div class="cell"><strong>{{ __('Subject') }}</strong>{{ $subjectName ?? '—' }}</div>
    <div class="cell"><strong>{{ __('Trainer') }}</strong>{{ $trainerName ?? '—' }}</div>
    <div class="cell"><strong>{{ __('Date') }}</strong>{{ $date->translatedFormat('l، d F Y') }}</div>
  </div>

  <table class="roster">
    <thead>
      <tr>
        <th style="width:6%;">#</th>
        <th>{{ __('Student') }}</th>
        <th style="width:22%;">{{ __('Status') }}</th>
        <th style="width:32%;">{{ __('Note') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($students as $i => $student)
        @php
          $a = $attendances->get($student->id);
          $status = $a?->status;
          $badgeClass = match ($status) {
              'present' => 'b-present',
              'absent' => 'b-absent',
              'late' => 'b-late',
              'excused' => 'b-excused',
              default => 'b-none',
          };
          $name = is_array($student->name) ? ($student->name['ar'] ?? reset($student->name)) : $student->name;
        @endphp
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $name }}</td>
          <td><span class="badge {{ $badgeClass }}">{{ $status ? ($labels[$status] ?? $status) : __('Not recorded') }}</span></td>
          <td>{{ $a?->note ?? '' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="summary">
    <div class="cell">{{ __('Total') }}<strong>{{ $students->count() }}</strong></div>
    <div class="cell">{{ __('Present') }}<strong>{{ $presentCount }}</strong></div>
    <div class="cell">{{ __('Absent') }}<strong>{{ $absentCount }}</strong></div>
    <div class="cell">{{ __('Attendance %') }}<strong>{{ $percent }}%</strong></div>
  </div>

  <div class="footer">
    <div class="cell"><div class="line">{{ __('Trainer Signature') }}</div></div>
    <div class="cell"><div class="line">{{ __('Center Stamp') }}</div></div>
  </div>
</body>
</html>
