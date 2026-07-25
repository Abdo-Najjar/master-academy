@php
    $brand = \App\Support\AppBranding::settings();
    $primary = $brand['primary_color'] ?? '#1e3a8a';
    $appName = \App\Support\AppBranding::appName();
    $navy = '#16224a';
    $gold = '#c9a227';
    $initial = mb_substr(trim((string) $name), 0, 1);

    // Academic year: Sep–Dec → Y/Y+1, Jan–Aug → Y-1/Y
    $y = now()->month >= 9 ? now()->year : now()->year - 1;
    $academicYear = $y . '/' . ($y + 1);
    $validUntil = \Carbon\Carbon::create($y + 1, 8, 31)->format('Y-m-d');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $name }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 0;
            color: #1f2937; background: #ffffff;
            border: 1.4pt solid {{ $navy }};
        }

        /* ── Header band ─────────────────────────────── */
        .hdr { background-color: {{ $navy }}; padding: 5pt 9pt 4pt; }
        .hdr table { width: 100%; border-collapse: collapse; }
        .hdr td { vertical-align: middle; }
        .hdr .logo-cell { width: 26pt; }
        .hdr .logo-cell img { width: 23pt; height: 23pt; }
        .hdr .t1 { font-size: 11.5pt; font-weight: bold; color: #ffffff; letter-spacing: .2pt; }
        .hdr .t2 { font-size: 5.6pt; color: {{ $gold }}; letter-spacing: .6pt; }
        .hdr .badge-cell { text-align: left; vertical-align: middle; }
        .hdr .badge-wrap { border-collapse: collapse; margin-inline-start: auto; }
        .hdr .badge-wrap td {
            font-size: 6pt; font-weight: bold; color: {{ $navy }};
            background-color: {{ $gold }}; padding: 3pt 8pt; border-radius: 8pt; letter-spacing: .3pt;
            white-space: nowrap;
        }
        .goldline { height: 2.2pt; background-color: {{ $gold }}; font-size: 0; line-height: 0; }

        /* ── Body ────────────────────────────────────── */
        table.body { width: 100%; border-collapse: collapse; }
        td.photo-cell { width: 66pt; text-align: center; vertical-align: top; padding: 7pt 4pt 0 8pt; }
        table.photoframe { border-collapse: collapse; margin: 0 auto; width: 52pt; }
        table.photoframe td {
            height: 60pt; text-align: center; vertical-align: middle;
            border: 1.6pt solid {{ $gold }}; background-color: {{ $navy }};
            color: #ffffff; font-size: 24pt; font-weight: bold; border-radius: 3pt;
        }
        table.photoframe img { width: 48pt; height: 58pt; }
        .photo-caption { font-size: 5pt; color: #9ca3af; margin-top: 2pt; letter-spacing: 1.2pt; }

        td.divider-cell { width: 1pt; padding: 6pt 0; }
        .divider { width: 1pt; height: 100%; background-color: #e5e7eb; font-size: 0; }

        td.info-cell { vertical-align: top; padding: 7pt 4pt 0 8pt; }
        .name { font-size: 11.8pt; font-weight: bold; color: {{ $navy }}; margin: 0 0 4pt; line-height: 1.35; }
        .num-wrap { border-collapse: collapse; margin-bottom: 5pt; }
        .num-wrap td {
            font-size: 7.6pt; font-weight: bold; color: #ffffff;
            background-color: {{ $primary }}; padding: 1.6pt 8pt; border-radius: 7pt; letter-spacing: .3pt;
        }
        table.info { border-collapse: collapse; }
        table.info td { padding: 1.5pt 0; font-size: 6.9pt; vertical-align: top; }
        table.info td.label { color: #9ca3af; padding-left: 6pt; white-space: nowrap; }
        table.info td.value { color: #111827; font-weight: bold; }

        td.qr-cell { width: 54pt; text-align: center; vertical-align: top; padding: 8pt 8pt 0 4pt; }
        .qr {
            background-color: #ffffff; border: 1.4pt solid {{ $navy }};
            border-radius: 4pt; padding: 3pt; display: inline-block;
        }
        .qr img { width: 42pt; height: 42pt; display: block; }
        .qr-caption { font-size: 5pt; color: #9ca3af; margin-top: 2.5pt; letter-spacing: .3pt; }

        /* ── Footer band ─────────────────────────────── */
        .ftr {
            position: absolute; bottom: 0; right: 0; left: 0;
            background-color: {{ $navy }};
        }
        .ftr table { width: 100%; border-collapse: collapse; }
        .ftr td { padding: 3.5pt 9pt; font-size: 5.6pt; color: #c7cee8; vertical-align: middle; }
        .ftr .year { font-weight: bold; color: {{ $gold }}; font-size: 6.2pt; }
        .ftr .valid-cell { text-align: left; }
    </style>
</head>
<body>
    <div class="hdr">
        <table>
            <tr>
                @if ($logo)
                    <td class="logo-cell"><img src="{{ $logo }}" alt="" width="31" height="31"></td>
                @endif
                <td>
                    <div class="t1">{{ $appName }}</div>
                    <div class="t2">EXCELLENCE TRAINING CENTER</div>
                </td>
                <td class="badge-cell">
                    <table class="badge-wrap"><tr><td>بطاقة طالب &nbsp;•&nbsp; STUDENT ID</td></tr></table>
                </td>
            </tr>
        </table>
    </div>
    <div class="goldline"></div>

    <table class="body">
        <tr>
            <td class="photo-cell">
                <table class="photoframe">
                    <tr>
                        <td>
                            @if ($photo)
                                <img src="{{ $photo }}" alt="" width="69" height="80">
                            @else
                                {{ $initial }}
                            @endif
                        </td>
                    </tr>
                </table>
                <div class="photo-caption">PHOTO</div>
            </td>
            <td class="divider-cell"><div class="divider"></div></td>
            <td class="info-cell">
                <div class="name">{{ $name }}</div>
                <table class="num-wrap"><tr><td>{{ $student->student_number ?? ('#' . $student->id) }}</td></tr></table>
                <table class="info">
                    @if ($student->dob)
                        <tr><td class="label">{{ __('Date of Birth') }}</td><td class="value">{{ $student->dob->format('Y-m-d') }}</td></tr>
                    @endif
                    @if ($student->phone_number)
                        <tr><td class="label">{{ __('Phone') }}</td><td class="value">{{ $student->phone_number }}</td></tr>
                    @endif
                </table>
            </td>
            <td class="qr-cell">
                <div class="qr">{!! $qrSvg !!}</div>
                <div class="qr-caption">{{ __('Scan to verify') }}</div>
            </td>
        </tr>
    </table>

    <div class="ftr">
        <table>
            <tr>
                <td>
                    <span class="year">{{ __('Academic Year') }} {{ $academicYear }}</span>
                    &nbsp;&nbsp;•&nbsp;&nbsp;
                    {{ __('This card is personal and non-transferable') }}
                </td>
                <td class="valid-cell">{{ __('Valid until') }} {{ $validUntil }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
