<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ is_array($student->name) ? ($student->name['ar'] ?? reset($student->name)) : $student->name }}</title>
<style>
  @page { margin: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
  .card { width: 100%; height: 100%; padding: 8pt 10pt; box-sizing: border-box; }
  .header { background: #dc2626; color: #fff; padding: 6pt 8pt; border-radius: 4pt; margin-bottom: 8pt; font-size: 9pt; font-weight: bold; text-align: center; }
  .body { display: table; width: 100%; }
  .left { display: table-cell; vertical-align: top; width: 65%; padding-inline-end: 6pt; }
  .right { display: table-cell; vertical-align: middle; width: 35%; text-align: center; }
  .name { font-size: 12pt; font-weight: bold; margin: 0 0 4pt; }
  .num { font-size: 11pt; color: #dc2626; font-weight: bold; margin-bottom: 4pt; }
  .row { font-size: 8pt; color: #444; margin-bottom: 2pt; }
  .row span { color: #888; }
  img.qr { width: 70pt; height: 70pt; }
</style>
</head>
<body>
  <div class="card">
    <div class="header">{{ \App\Support\AppBranding::appName() }} — {{ __('Student Card') }}</div>
    <div class="body">
      <div class="left">
        <p class="name">{{ is_array($student->name) ? ($student->name['ar'] ?? reset($student->name)) : $student->name }}</p>
        <p class="num">{{ $student->student_number }}</p>
        @if ($student->dob)
          <div class="row"><span>{{ __('Date of Birth') }}:</span> {{ $student->dob->format('Y-m-d') }}</div>
        @endif
        @if ($student->phone_number)
          <div class="row"><span>{{ __('Phone') }}:</span> {{ $student->phone_number }}</div>
        @endif
        @if ($student->parent_phone)
          <div class="row"><span>{{ __('Parent Phone') }}:</span> {{ $student->parent_phone }}</div>
        @endif
      </div>
      <div class="right">
        <img class="qr" src="data:image/png;base64,{{ $qrPng }}" alt="QR" />
      </div>
    </div>
  </div>
</body>
</html>
