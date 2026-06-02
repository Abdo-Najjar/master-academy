<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ __('Receipt') }} #{{ $registration->id }}</title>
<style>
  @page { margin: 18mm 14mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; }
  h1 { font-size: 16pt; margin: 0 0 4pt; }
  .muted { color: #666; font-size: 9pt; }
  .head { border-bottom: 2px solid #000; padding-bottom: 8pt; margin-bottom: 12pt; }
  .row { display: table; width: 100%; margin-bottom: 4pt; }
  .row .lbl { display: table-cell; color: #555; width: 40%; }
  .row .val { display: table-cell; font-weight: bold; }
  .total { margin-top: 14pt; padding-top: 8pt; border-top: 2px solid #000; font-size: 13pt; }
  .total .val { color: #0d6e2f; }
  .footer { margin-top: 24pt; font-size: 9pt; color: #888; text-align: center; }
  .footer .sig { margin-top: 30pt; text-align: end; }
</style>
</head>
<body>
  <div class="head">
    <h1>{{ \App\Support\AppBranding::appName() }}</h1>
    <div class="muted">{{ __('Receipt') }} #{{ str_pad($registration->id, 6, '0', STR_PAD_LEFT) }}</div>
    <div class="muted">{{ $now->translatedFormat('l، d F Y - H:i') }}</div>
  </div>

  <div class="row"><div class="lbl">{{ __('Student') }}</div><div class="val">{{ is_array($registration->student?->name) ? ($registration->student->name['ar'] ?? reset($registration->student->name)) : $registration->student?->name }}</div></div>
  <div class="row"><div class="lbl">{{ __('Student Number') }}</div><div class="val">{{ $registration->student?->student_number ?? '—' }}</div></div>
  <div class="row"><div class="lbl">{{ __('Section') }}</div><div class="val">{{ is_array($registration->section?->name) ? ($registration->section->name['ar'] ?? reset($registration->section->name)) : $registration->section?->name }}</div></div>
  <div class="row"><div class="lbl">{{ __('Subject') }}</div><div class="val">{{ is_array($registration->section?->subject?->name) ? ($registration->section->subject->name['ar'] ?? reset($registration->section->subject->name)) : ($registration->section?->subject?->name ?? '—') }}</div></div>
  <div class="row"><div class="lbl">{{ __('Trainer') }}</div><div class="val">{{ is_array($registration->section?->trainer?->name) ? ($registration->section->trainer->name['ar'] ?? reset($registration->section->trainer->name)) : ($registration->section?->trainer?->name ?? '—') }}</div></div>
  <div class="row"><div class="lbl">{{ __('Payment Type') }}</div><div class="val">{{ $registration->paymentType?->name ?? '—' }}</div></div>

  <div class="row" style="margin-top:14pt;"><div class="lbl">{{ __('Amount Due') }}</div><div class="val">{{ number_format((float) $registration->amount_due, 2) }} ₪</div></div>
  <div class="row"><div class="lbl">{{ __('Exemption / Discount') }}</div><div class="val">{{ number_format((float) $registration->exemption_amount, 2) }} ₪</div></div>
  <div class="row total"><div class="lbl">{{ __('Amount Paid') }}</div><div class="val">{{ number_format((float) $registration->amount_paid, 2) }} ₪</div></div>

  @if ($registration->note)
    <div style="margin-top:14pt;"><strong>{{ __('Note') }}:</strong> {{ $registration->note }}</div>
  @endif

  <div class="footer">
    <div class="sig">{{ __('Issued by') }}: {{ $issuer?->name ?? '—' }}</div>
    <div style="margin-top:10pt;">{{ __('Thank you!') }}</div>
  </div>
</body>
</html>
