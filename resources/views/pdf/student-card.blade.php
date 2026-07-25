@php
    $appName = \App\Support\AppBranding::appName();
    $navy = '#16224a';
    $gold = '#c9a227';
    $tint = '#eef1f8';
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
            border: 1pt solid #e2e5eb;
        }

        /* ── Top corner brand mark ─────────────────────── */
        .brandrow { width: 100%; border-collapse: collapse; }
        .brandrow td { vertical-align: middle; padding: 7pt 9pt 0; }
        .brandrow .logo-cell { width: 20pt; text-align: center; }
        .brandrow .logo-cell img {
            width: 18pt; height: 18pt; border-radius: 50%; border: 1pt solid {{ $gold }};
        }
        .brandrow .name-cell { text-align: left; }
        .brandrow .app-name { font-size: 8pt; font-weight: bold; color: {{ $navy }}; }
        .brandrow .app-tag { font-size: 4.6pt; color: #9ca3af; letter-spacing: .4pt; }

        /* ── Body: colored panel + fields ─────────────── */
        table.body { width: 100%; border-collapse: collapse; margin-top: 5pt; }

        td.panel-cell { width: 88pt; vertical-align: top; padding: 0; }
        .panel {
            background-color: {{ $navy }}; margin: 0 8pt 0 0; padding: 9pt 0 7pt;
            border-radius: 8pt; text-align: center;
        }
        table.photoring { border-collapse: collapse; margin: 0 auto; }
        table.photoring td {
            width: 46pt; height: 46pt; text-align: center; vertical-align: middle;
            background-color: #ffffff; border: 2pt solid {{ $gold }}; border-radius: 50%;
            color: {{ $navy }}; font-size: 20pt; font-weight: bold;
        }
        table.photoring img { width: 46pt; height: 46pt; border-radius: 50%; }
        .panel-caption { font-size: 4.6pt; color: {{ $gold }}; letter-spacing: 1pt; margin: 4pt 0 6pt; }
        .qr {
            background-color: #ffffff; border-radius: 3pt; padding: 3pt; display: inline-block;
        }
        .qr img { width: 34pt; height: 34pt; display: block; }

        td.info-cell { vertical-align: middle; padding: 2pt 8pt 0 0; }
        .field { margin-bottom: 6pt; }
        .field .lbl { font-size: 5.4pt; color: #9ca3af; letter-spacing: .3pt; margin-bottom: 1.5pt; }
        .field .val {
            font-size: 8.4pt; font-weight: bold; color: {{ $navy }};
            padding-bottom: 3pt; border-bottom: 1pt solid #e2e5eb;
        }
        .field:last-child .val { border-bottom: 0; }
        .field .num { font-family: 'DejaVu Sans', sans-serif; letter-spacing: .3pt; }

        /* ── Footer ─────────────────────────────────── */
        .ftr {
            position: absolute; bottom: 0; right: 0; left: 0;
            border-top: 1pt solid {{ $gold }}; padding: 3pt 9pt;
        }
        .ftr table { width: 100%; border-collapse: collapse; }
        .ftr td { font-size: 4.8pt; color: #9ca3af; vertical-align: middle; }
        .ftr .valid-cell { text-align: left; }
    </style>
</head>
<body>
    <table class="brandrow">
        <tr>
            @if ($logo)
                <td class="logo-cell"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td class="name-cell">
                <div class="app-name">{{ $appName }}</div>
                <div class="app-tag">EXCELLENCE TRAINING CENTER &nbsp;•&nbsp; STUDENT ID</div>
            </td>
        </tr>
    </table>

    <table class="body">
        <tr>
            <td class="panel-cell">
                <div class="panel">
                    <table class="photoring">
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
                    <div class="panel-caption">{{ __('Scan to verify') }}</div>
                    <div class="qr">{!! $qrSvg !!}</div>
                </div>
            </td>
            <td class="info-cell">
                <div class="field">
                    <div class="lbl">{{ __('Student Name') }}</div>
                    <div class="val">{{ $name }}</div>
                </div>
                <div class="field">
                    <div class="lbl">{{ __('Student Number') }}</div>
                    <div class="val num">{{ $student->student_number ?? ('#' . $student->id) }}</div>
                </div>
                @if ($student->dob)
                    <div class="field">
                        <div class="lbl">{{ __('Date of Birth') }}</div>
                        <div class="val num">{{ $student->dob->format('Y-m-d') }}</div>
                    </div>
                @elseif ($student->phone_number)
                    <div class="field">
                        <div class="lbl">{{ __('Phone') }}</div>
                        <div class="val num">{{ $student->phone_number }}</div>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div class="ftr">
        <table>
            <tr>
                <td>{{ __('Academic Year') }} {{ $academicYear }} &nbsp;•&nbsp; {{ __('This card is personal and non-transferable') }}</td>
                <td class="valid-cell">{{ __('Valid until') }} {{ $validUntil }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
