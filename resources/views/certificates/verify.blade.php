<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('Certificate Verification') }}</title>
<style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{
        font-family:'Tajawal','Segoe UI',Tahoma,sans-serif;
        min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;
        background:linear-gradient(135deg,#ecfdf5 0%,#d1fae5 100%);
    }
    .cv-card{max-width:440px;width:100%;background:#fff;border-radius:1.25rem;box-shadow:0 20px 50px -20px rgba(0,0,0,.25);padding:2.5rem 2rem;text-align:center;}
    .cv-badge{width:80px;height:80px;background:#dcfce7;border-radius:9999px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;}
    .cv-badge svg{width:40px;height:40px;color:#16a34a;}
    .cv-title{font-size:1.5rem;font-weight:800;color:#15803d;margin-bottom:.4rem;}
    .cv-sub{font-size:.875rem;color:#6b7280;margin-bottom:1.5rem;}
    .cv-rows{text-align:start;background:#f9fafb;border:1px solid #f0f1f3;border-radius:.85rem;padding:1.1rem 1.25rem;}
    .cv-row{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.5rem 0;font-size:.9rem;border-bottom:1px solid #f1f3f5;}
    .cv-row:last-child{border-bottom:0;}
    .cv-label{color:#9ca3af;white-space:nowrap;}
    .cv-val{font-weight:700;color:#111827;text-align:end;}
    .cv-mono{font-family:ui-monospace,'Courier New',monospace;}
</style>
</head>
<body>
    <div class="cv-card">
        <div class="cv-badge">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
        </div>

        <h1 class="cv-title">{{ __('Certificate Verified') }} ✓</h1>
        <p class="cv-sub">{{ __('This is an authentic certificate issued by') }} <strong>منبع التميز</strong></p>

        <div class="cv-rows">
            <div class="cv-row">
                <span class="cv-label">{{ __('Student Name') }}</span>
                <span class="cv-val">
                    {{ is_array($certificate->student?->name)
                        ? ($certificate->student->name['ar'] ?? reset($certificate->student->name))
                        : $certificate->student?->name }}
                </span>
            </div>
            @if($certificate->section)
            <div class="cv-row">
                <span class="cv-label">{{ __('Section') }}</span>
                <span class="cv-val">{{ $certificate->section->getTranslation('name', 'ar', false) }}</span>
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
</body>
</html>
