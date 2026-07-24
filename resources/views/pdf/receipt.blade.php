<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ __('Receipt') }} #{{ $registration->id }}</title>
<style>
@php
    $brand = \App\Support\AppBranding::settings();
    $primary = $brand['primary_color'] ?? '#dc2626';
    $navy = '#16224a';
    $gold = '#c9a227';
@endphp
    @page { margin: 16mm 14mm 14mm; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1f2937; margin: 0; }

    /* ── Header ─────────────────────────────────── */
    .hdr table { width: 100%; border-collapse: collapse; }
    .hdr td { vertical-align: middle; }
    .hdr .logo-cell { width: 34pt; }
    .hdr .logo-cell img { width: 30pt; height: 30pt; }
    .hdr .brand { font-size: 14pt; font-weight: bold; color: {{ $navy }}; }
    .hdr .tagline { font-size: 6.4pt; color: #9ca3af; letter-spacing: .4pt; margin-top: 1pt; }
    .hdr .badge-cell { text-align: left; }
    .hdr .badge {
        display: inline-block; font-size: 8pt; font-weight: bold; color: #ffffff;
        background-color: {{ $primary }}; padding: 4pt 12pt; border-radius: 10pt;
    }
    .goldline { height: 2pt; background-color: {{ $gold }}; font-size: 0; line-height: 0; margin: 8pt 0 12pt; }

    .meta table { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
    .meta td { font-size: 8.6pt; color: #6b7280; vertical-align: middle; }
    .meta .num { font-weight: bold; color: {{ $navy }}; font-size: 10pt; }
    .meta .date-cell { text-align: left; }

    /* ── Info table ─────────────────────────────── */
    table.info { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
    table.info tr:nth-child(odd) td { background-color: #f7f8fb; }
    table.info td { padding: 6pt 10pt; font-size: 9.4pt; border-bottom: 1pt solid #eef0f4; }
    table.info td.lbl { width: 38%; color: #6b7280; }
    table.info td.val { font-weight: bold; color: #111827; }

    /* ── Totals ─────────────────────────────────── */
    table.totals { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }
    table.totals td { padding: 5pt 10pt; font-size: 9.4pt; }
    table.totals td.lbl { color: #6b7280; }
    table.totals td.val { text-align: left; font-weight: bold; color: #111827; }
    table.totals tr.grand { background-color: {{ $navy }}; }
    table.totals tr.grand td { padding: 9pt 10pt; font-size: 12pt; color: #ffffff; font-weight: bold; border-radius: 4pt; }
    table.totals tr.grand td.lbl { color: {{ $gold }}; }
    table.totals tr.grand td.val { color: #ffffff; }

    .note { margin-top: 14pt; padding: 8pt 10pt; background-color: #f7f8fb; border-right: 3pt solid {{ $gold }}; font-size: 9pt; }
    .note strong { color: {{ $navy }}; }

    /* ── Footer ─────────────────────────────────── */
    .footer { margin-top: 26pt; }
    .footer table { width: 100%; border-collapse: collapse; }
    .footer .sig-cell { width: 50%; text-align: center; font-size: 8.4pt; color: #6b7280; }
    .footer .sig-line { border-top: 1pt solid #d1d5db; margin: 22pt 14pt 4pt; }
    .thanks { margin-top: 18pt; text-align: center; font-size: 8.4pt; color: #9ca3af; }
</style>
</head>
<body>
  <div class="hdr">
    <table>
      <tr>
        @if ($logo)
          <td class="logo-cell"><img src="{{ $logo }}" alt="" width="40" height="40"></td>
        @endif
        <td>
          <div class="brand">{{ \App\Support\AppBranding::appName() }}</div>
          <div class="tagline">EXCELLENCE TRAINING CENTER</div>
        </td>
        <td class="badge-cell"><span class="badge">{{ __('Receipt') }}</span></td>
      </tr>
    </table>
  </div>
  <div class="goldline"></div>

  <div class="meta">
    <table>
      <tr>
        <td class="num">#{{ str_pad($registration->id, 6, '0', STR_PAD_LEFT) }}</td>
        <td class="date-cell">{{ $now->translatedFormat('l، d F Y — H:i') }}</td>
      </tr>
    </table>
  </div>

  <table class="info">
    <tr>
      <td class="lbl">{{ __('Student') }}</td>
      <td class="val">{{ is_array($registration->student?->name) ? ($registration->student->name['ar'] ?? reset($registration->student->name)) : $registration->student?->name }}</td>
    </tr>
    <tr>
      <td class="lbl">{{ __('Student Number') }}</td>
      <td class="val">{{ $registration->student?->student_number ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">{{ __('Section') }}</td>
      <td class="val">{{ is_array($registration->section?->name) ? ($registration->section->name['ar'] ?? reset($registration->section->name)) : $registration->section?->name }}</td>
    </tr>
    <tr>
      <td class="lbl">{{ __('Subject') }}</td>
      <td class="val">{{ is_array($registration->section?->subject?->name) ? ($registration->section->subject->name['ar'] ?? reset($registration->section->subject->name)) : ($registration->section?->subject?->name ?? '—') }}</td>
    </tr>
    <tr>
      <td class="lbl">{{ __('Trainer') }}</td>
      <td class="val">{{ is_array($registration->section?->trainer?->name) ? ($registration->section->trainer->name['ar'] ?? reset($registration->section->trainer->name)) : ($registration->section?->trainer?->name ?? '—') }}</td>
    </tr>
    <tr>
      <td class="lbl">{{ __('Payment Type') }}</td>
      <td class="val">{{ $registration->paymentType?->name ?? '—' }}</td>
    </tr>
  </table>

  <table class="totals">
    <tr>
      <td class="lbl">{{ __('Amount Due') }}</td>
      <td class="val">{{ number_format((float) $registration->amount_due, 2) }} ₪</td>
    </tr>
    <tr>
      <td class="lbl">{{ __('Exemption / Discount') }}</td>
      <td class="val">{{ number_format((float) $registration->exemption_amount, 2) }} ₪</td>
    </tr>
    <tr class="grand">
      <td class="lbl">{{ __('Amount Paid') }}</td>
      <td class="val">{{ number_format((float) $registration->amount_paid, 2) }} ₪</td>
    </tr>
  </table>

  @if ($registration->note)
    <div class="note"><strong>{{ __('Note') }}:</strong> {{ $registration->note }}</div>
  @endif

  <div class="footer">
    <table>
      <tr>
        <td class="sig-cell">
          <div class="sig-line"></div>
          {{ __('Issued by') }}: {{ $issuer?->name ?? '—' }}
        </td>
        <td class="sig-cell">
          <div class="sig-line"></div>
          {{ __('Signature') }}
        </td>
      </tr>
    </table>
    <div class="thanks">{{ __('Thank you!') }}</div>
  </div>
</body>
</html>
