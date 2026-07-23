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
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $name }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 0; color: #1f2937; background: #ffffff; }

        /* ── Header band ─────────────────────────────── */
        .hdr { background-color: {{ $navy }}; padding: 5pt 10pt 4pt; }
        .hdr table { width: 100%; border-collapse: collapse; }
        .hdr td { vertical-align: middle; }
        .hdr .logo-cell { width: 26pt; }
        .hdr .logo-cell img { width: 23pt; height: 23pt; }
        .hdr .t1 { font-size: 11.5pt; font-weight: bold; color: #ffffff; }
        .hdr .t2 { font-size: 5.6pt; color: #aab4d8; letter-spacing: .4pt; }
        .hdr .badge-cell { text-align: left; }
        .hdr .badge {
            display: inline-block; font-size: 6.2pt; font-weight: bold; color: {{ $navy }};
            background-color: {{ $gold }}; padding: 2pt 7pt; border-radius: 7pt; letter-spacing: .3pt;
        }
        .goldline { height: 2.2pt; background-color: {{ $gold }}; font-size: 0; line-height: 0; }

        /* ── Body ────────────────────────────────────── */
        table.body { width: 100%; border-collapse: collapse; }
        td.photo-cell { width: 72pt; text-align: center; vertical-align: top; padding: 6pt 10pt 0 4pt; }
        table.photoframe { border-collapse: collapse; margin: 0 auto; width: 56pt; }
        table.photoframe td {
            height: 62pt; text-align: center; vertical-align: middle;
            border: 2pt solid {{ $navy }}; background-color: #eef1f8;
            color: {{ $navy }}; font-size: 26pt; font-weight: bold;
        }
        table.photoframe img { width: 52pt; height: 60pt; }
        .photo-caption { font-size: 5.4pt; color: #9ca3af; margin-top: 2pt; letter-spacing: 1pt; }

        td.info-cell { vertical-align: top; padding: 6pt 2pt 0 4pt; }
        .name { font-size: 12.5pt; font-weight: bold; color: {{ $navy }}; margin: 0 0 6pt; line-height: 1.4; }
        .num {
            display: inline-block; font-size: 8pt; font-weight: bold; color: #ffffff;
            background-color: {{ $primary }}; padding: 1.5pt 8pt; border-radius: 8pt; margin: 0 0 6pt;
        }
        table.info { border-collapse: collapse; }
        table.info td { padding: 1.5pt 0; font-size: 7.4pt; vertical-align: top; }
        table.info td.label { color: #8a93a8; padding-left: 7pt; white-space: nowrap; }
        table.info td.value { color: #111827; font-weight: bold; }

        td.qr-cell { width: 58pt; text-align: center; vertical-align: top; padding: 8pt 4pt 0 10pt; }
        .qr {
            background-color: #ffffff; border: 1.4pt solid {{ $gold }};
            border-radius: 5pt; padding: 3pt; display: inline-block;
        }
        .qr img { width: 44pt; height: 44pt; display: block; }
        .qr-caption { font-size: 5.4pt; color: #9ca3af; margin-top: 2.5pt; }

        /* ── Footer band ─────────────────────────────── */
        .ftr {
            position: absolute; bottom: 0; right: 0; left: 0;
            background-color: {{ $navy }}; padding: 3.5pt 10pt;
            font-size: 5.8pt; color: #c7cee8; text-align: center;
        }
        .ftr .year { font-weight: bold; color: {{ $gold }}; font-size: 6.4pt; }
    </style>
</head>
<body>
    <div class="hdr">
        <table>
            <tr>
                @if ($logo)
                    <td class="logo-cell"><img src="{{ $logo }}" alt=""></td>
                @endif
                <td>
                    <div class="t1">{{ $appName }}</div>
                    <div class="t2">EXCELLENCE TRAINING CENTER</div>
                </td>
                <td class="badge-cell"><span class="badge">بطاقة طالب &nbsp;•&nbsp; STUDENT ID</span></td>
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
                                <img src="{{ $photo }}" alt="">
                            @else
                                {{ $initial }}
                            @endif
                        </td>
                    </tr>
                </table>
                <div class="photo-caption">PHOTO</div>
            </td>
            <td class="info-cell">
                <div class="name">{{ $name }}</div>
                <div style="margin: 2pt 0 5pt;"><span class="num">{{ $student->student_number ?? ('#' . $student->id) }}</span></div>
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
        <span class="year">{{ __('Academic Year') }} {{ $academicYear }}</span>
        &nbsp;&nbsp;•&nbsp;&nbsp;
        {{ __('This card is personal and non-transferable') }}
    </div>
</body>
</html>
