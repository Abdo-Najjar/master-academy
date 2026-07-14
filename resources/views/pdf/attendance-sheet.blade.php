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
  .head { border-bottom: 2px solid #000; padding-bottom: 8pt; margin-bottom: 12pt; }

  table.meta { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
  table.meta td { width: 25%; vertical-align: top; padding: 0 8pt 0 0; font-size: 10pt; }
  table.meta .lbl { color: #666; font-size: 8pt; margin-bottom: 2pt; }

  table.roster { width: 100%; border-collapse: collapse; margin-top: 6pt; }
  table.roster th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 5pt 6pt; font-size: 9.5pt; text-align: start; }
  table.roster td { border: 1px solid #e2e8f0; padding: 5pt 6pt; font-size: 9.5pt; }
  table.roster tr { page-break-inside: avoid; }
  table.roster tr:nth-child(even) td { background: #fafafa; }

  .badge { display: inline-block; padding: 1pt 6pt; border-radius: 8pt; font-size: 8.5pt; }
  .b-present { background: #dcfce7; color: #15803d; }
  .b-absent { background: #fee2e2; color: #b91c1c; }
  .b-late { background: #fef3c7; color: #92400e; }
  .b-excused { background: #dbeafe; color: #1d4ed8; }
  .b-none { background: #f1f5f9; color: #64748b; }

  table.summary { width: 100%; border-collapse: collapse; margin-top: 14pt; page-break-inside: avoid; }
  table.summary td { width: 25%; text-align: center; border: 1px solid #e2e8f0; padding: 7pt; font-size: 9pt; color: #555; }
  table.summary td strong { display: block; font-size: 14pt; color: #111; margin-top: 2pt; }

  table.sig { width: 100%; margin-top: 34pt; page-break-inside: avoid; }
  table.sig td { width: 50%; padding-top: 4pt; border-top: 1px solid #999; font-size: 8.5pt; color: #666; }
</style>
</head>
<body>
  <div class="head">
    <h1>{{ \App\Support\AppBranding::appName() }}</h1>
    <div class="muted">{{ __('Attendance Sheet') }}</div>
  </div>

  <table class="meta">
    <tr>
      <td><div class="lbl">{{ __('Section') }}</div>{{ $sectionName }}</td>
      <td><div class="lbl">{{ __('Subject') }}</div>{{ $subjectName ?? '—' }}</td>
      <td><div class="lbl">{{ __('Trainer') }}</div>{{ $trainerName ?? '—' }}</td>
      <td><div class="lbl">{{ __('Date') }}</div>{{ $date->translatedFormat('l، d F Y') }}</td>
    </tr>
  </table>

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

  <table class="summary">
    <tr>
      <td>{{ __('Total') }}<strong>{{ $students->count() }}</strong></td>
      <td>{{ __('Present') }}<strong>{{ $presentCount }}</strong></td>
      <td>{{ __('Absent') }}<strong>{{ $absentCount }}</strong></td>
      <td>{{ __('Attendance %') }}<strong>{{ $percent }}%</strong></td>
    </tr>
  </table>

  <table class="sig">
    <tr>
      <td>{{ __('Trainer Signature') }}</td>
      <td>{{ __('Center Stamp') }}</td>
    </tr>
  </table>
</body>
</html>
