@php
    $brand = \App\Support\AppBranding::settings();
    $primary = $brand['primary_color'] ?? '#dc2626';
    $appName = \App\Support\AppBranding::appName();
    $initial = mb_substr(trim((string) $name), 0, 1);
    $location = trim(implode(' - ', array_filter([
        optional($student->governorate)->name,
        optional($student->city)->name,
    ])));
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $name }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 0; color: #1f2937; }
        table.layout { width: 100%; height: 100%; border-collapse: collapse; }
        td.accent {
            width: 33%;
            background: {{ $primary }};
            text-align: center;
            vertical-align: middle;
            padding: 6pt 4pt;
        }
        .photo {
            width: 52pt; height: 52pt;
            margin: 0 auto 5pt;
            border: 1.5pt solid #ffffff;
            border-radius: 5pt;
            background: #ffffff;
            overflow: hidden;
        }
        .photo img { width: 52pt; height: 52pt; }
        .photo .initial {
            display: block; width: 52pt; height: 52pt;
            color: {{ $primary }}; font-size: 26pt; font-weight: bold;
            text-align: center; line-height: 52pt;
        }
        .qr { background: #ffffff; padding: 2pt; border-radius: 4pt; display: inline-block; }
        .qr img { width: 42pt; height: 42pt; display: block; }
        td.main { vertical-align: top; padding: 7pt 9pt; }
        .brand { font-size: 7pt; font-weight: bold; color: {{ $primary }}; text-transform: uppercase; letter-spacing: .3pt; }
        .brand .sub { color: #9ca3af; font-weight: normal; }
        .name { font-size: 13pt; font-weight: bold; color: #111827; margin: 5pt 0 1pt; }
        .num {
            display: inline-block; font-size: 9pt; font-weight: bold; color: #ffffff;
            background: {{ $primary }}; padding: 1.5pt 6pt; border-radius: 8pt; margin-bottom: 6pt;
        }
        table.info { width: 100%; border-collapse: collapse; }
        table.info td { padding: 1.6pt 0; font-size: 8pt; vertical-align: top; }
        table.info td.label { color: #9ca3af; width: 38%; }
        table.info td.value { color: #1f2937; font-weight: bold; }
    </style>
</head>
<body>
    <table class="layout">
        <tr>
            <td class="accent">
                <div class="photo">
                    @if ($photo)
                        <img src="{{ $photo }}" alt="">
                    @else
                        <span class="initial">{{ $initial }}</span>
                    @endif
                </div>
                <div class="qr"><img src="data:image/png;base64,{{ $qrPng }}" alt="QR"></div>
            </td>
            <td class="main">
                <div class="brand">{{ $appName }} <span class="sub">— {{ __('Student Card') }}</span></div>
                <div class="name">{{ $name }}</div>
                <div class="num">{{ $student->student_number }}</div>
                <table class="info">
                    @if ($student->dob)
                        <tr><td class="label">{{ __('Date of Birth') }}</td><td class="value">{{ $student->dob->format('Y-m-d') }}</td></tr>
                    @endif
                    @if ($student->phone_number)
                        <tr><td class="label">{{ __('Phone') }}</td><td class="value">{{ $student->phone_number }}</td></tr>
                    @endif
                    @if ($student->parent_phone)
                        <tr><td class="label">{{ __('Parent Phone') }}</td><td class="value">{{ $student->parent_phone }}</td></tr>
                    @endif
                    @if ($location !== '')
                        <tr><td class="label">{{ __('Location') }}</td><td class="value">{{ $location }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
