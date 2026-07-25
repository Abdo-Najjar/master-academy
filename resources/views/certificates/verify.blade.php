<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('Certificate Verification') }}</title>
<style>
@php
    $brand = \App\Support\AppBranding::settings();
    $primary = $brand['primary_color'] ?? '#dc2626';
    $navy = '#0f172a';
    $gold = '#c9a227';
    $logo = \App\Support\AppBranding::logoUrl('light');
    $appName = \App\Support\AppBranding::appName();

    $studentName = is_array($certificate->student?->name)
        ? ($certificate->student->name['ar'] ?? reset($certificate->student->name))
        : $certificate->student?->name;

    $sectionName = is_array($certificate->section?->name ?? null)
        ? ($certificate->section->name['ar'] ?? reset($certificate->section->name))
        : $certificate->section?->name;
@endphp
    *{box-sizing:border-box;margin:0;padding:0;}
    body{
        font-family:'Tajawal','Segoe UI',Tahoma,sans-serif;
        min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;
        background:
            radial-gradient(1000px 500px at 100% -10%, color-mix(in srgb, {{ $primary }} 12%, transparent), transparent),
            radial-gradient(900px 500px at -10% 110%, color-mix(in srgb, {{ $navy }} 10%, transparent), transparent),
            #f3f4f6;
    }

    .cv-wrap{max-width:460px;width:100%;}

    /* ── Header: center brand ─────────────────────── */
    .cv-brand{display:flex;align-items:center;justify-content:center;gap:.6rem;margin-bottom:1.1rem;}
    .cv-brand img{width:34px;height:34px;border-radius:8px;}
    .cv-brand-text{text-align:start;}
    .cv-brand-name{font-size:1rem;font-weight:800;color:{{ $navy }};line-height:1.2;}
    .cv-brand-tag{font-size:.62rem;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase;}

    /* ── Card ──────────────────────────────────────── */
    .cv-card{
        background:#fff;border-radius:1.5rem;padding:2.25rem 2rem 2rem;text-align:center;
        box-shadow:0 24px 60px -24px rgba(15,23,42,.28), 0 1px 0 rgba(15,23,42,.04);
        border:1px solid #eef0f3;position:relative;overflow:hidden;
    }
    .cv-card::before{
        content:"";position:absolute;inset:0 0 auto 0;height:5px;
        background:linear-gradient(90deg, {{ $primary }}, {{ $gold }});
    }

    .cv-badge{
        width:76px;height:76px;border-radius:9999px;margin:.25rem auto 1rem;
        display:flex;align-items:center;justify-content:center;
        background:radial-gradient(circle at 30% 30%, #22c55e, #16a34a);
        box-shadow:0 10px 24px -8px rgba(22,163,74,.55);
    }
    .cv-badge svg{width:38px;height:38px;color:#fff;}

    .cv-title{font-size:1.35rem;font-weight:800;color:#111827;margin-bottom:.3rem;}
    .cv-sub{font-size:.85rem;color:#6b7280;margin-bottom:1.6rem;line-height:1.6;}
    .cv-sub strong{color:{{ $navy }};}

    .cv-rows{text-align:start;background:#f9fafb;border:1px solid #f0f1f3;border-radius:1rem;padding:.35rem 1.25rem;}
    .cv-row{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.65rem 0;font-size:.88rem;border-bottom:1px solid #eef0f3;}
    .cv-row:last-child{border-bottom:0;}
    .cv-label{color:#9ca3af;white-space:nowrap;}
    .cv-val{font-weight:700;color:#111827;text-align:end;}
    .cv-mono{font-family:ui-monospace,'Courier New',monospace;letter-spacing:.02em;}

    .cv-footer{margin-top:1.5rem;font-size:.7rem;color:#b7bdc6;}
</style>
</head>
<body>
    <div class="cv-wrap">
        <div class="cv-brand">
            <img src="{{ $logo }}" alt="">
            <div class="cv-brand-text">
                <div class="cv-brand-name">{{ $appName }}</div>
                <div class="cv-brand-tag">Excellence Training Center</div>
            </div>
        </div>

        <div class="cv-card">
            <div class="cv-badge">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            <h1 class="cv-title">{{ __('Certificate Verified') }}</h1>
            <p class="cv-sub">{{ __('This is an authentic certificate issued by') }} <strong>{{ $appName }}</strong></p>

            <div class="cv-rows">
                <div class="cv-row">
                    <span class="cv-label">{{ __('Student Name') }}</span>
                    <span class="cv-val">{{ $studentName }}</span>
                </div>
                @if($certificate->section)
                <div class="cv-row">
                    <span class="cv-label">{{ __('Section') }}</span>
                    <span class="cv-val">{{ $sectionName }}</span>
                </div>
                <div class="cv-row">
                    <span class="cv-label">{{ __('Subject') }}</span>
                    <span class="cv-val">{{ $certificate->section->subject?->getTranslation('name', 'ar', false) }}</span>
                </div>
                @endif
                <div class="cv-row">
                    <span class="cv-label">{{ __('Serial Number') }}</span>
                    <span class="cv-val cv-mono">{{ $certificate->serial_number }}</span>
                </div>
                <div class="cv-row">
                    <span class="cv-label">{{ __('Issued Date') }}</span>
                    <span class="cv-val">{{ $certificate->issued_at?->format('Y/m/d') }}</span>
                </div>
                <div class="cv-row">
                    <span class="cv-label">{{ __('Template') }}</span>
                    <span class="cv-val">{{ $certificate->template?->name }}</span>
                </div>
            </div>
        </div>

        <div class="cv-footer">{{ $appName }} &middot; {{ now()->format('Y') }}</div>
    </div>
</body>
</html>
