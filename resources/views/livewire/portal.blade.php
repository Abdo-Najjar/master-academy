<div class="mp-root">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

        .mp-root { --mp-bg1: #0b1023; --mp-bg2: #131b3a; --mp-text: #eef1fb; --mp-muted: #9aa6cc; }
        .mp-root * { box-sizing: border-box; }
        .mp-root { font-family: 'Tajawal', ui-sans-serif, system-ui, sans-serif; min-height: 100vh; position: relative; overflow: hidden; background: linear-gradient(160deg, var(--mp-bg1) 0%, var(--mp-bg2) 55%, #0e2433 100%); color: var(--mp-text); display: flex; flex-direction: column; }

        /* Animated background blobs */
        .mp-blob { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .35; pointer-events: none; animation: mp-float 14s ease-in-out infinite alternate; }
        .mp-blob--1 { width: 480px; height: 480px; background: #2563eb; top: -160px; inset-inline-start: -120px; }
        .mp-blob--2 { width: 420px; height: 420px; background: #0d9488; bottom: -140px; inset-inline-end: -100px; animation-delay: -5s; }
        .mp-blob--3 { width: 300px; height: 300px; background: #7c3aed; top: 40%; inset-inline-start: 55%; opacity: .22; animation-delay: -9s; }
        @keyframes mp-float { from { transform: translateY(0) scale(1); } to { transform: translateY(50px) scale(1.12); } }

        .mp-wrap { position: relative; z-index: 1; width: 100%; max-width: 1180px; margin: 0 auto; padding: 48px 24px 32px; flex: 1; display: flex; flex-direction: column; justify-content: center; }

        /* Hero */
        .mp-hero { text-align: center; margin-bottom: 48px; }
        .mp-logo { height: 132px; width: auto; margin: 0 auto 18px; display: block; filter: drop-shadow(0 8px 28px rgba(37, 99, 235, .45)); }
        .mp-title { font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 900; margin: 0 0 10px; background: linear-gradient(92deg, #ffffff 20%, #93c5fd 60%, #5eead4 100%); -webkit-background-clip: text; background-clip: text; color: transparent; letter-spacing: -.5px; }
        .mp-sub { font-size: clamp(1rem, 2vw, 1.25rem); color: var(--mp-muted); margin: 0; font-weight: 500; }
        .mp-sub-line { width: 90px; height: 4px; border-radius: 999px; margin: 22px auto 0; background: linear-gradient(90deg, #2563eb, #14b8a6); }

        /* Cards grid */
        .mp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 22px; }

        .mp-card { --mp-a1: #3b82f6; --mp-a2: #1d4ed8; position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 38px 26px 30px; border-radius: 22px; text-decoration: none; color: var(--mp-text); background: rgba(255, 255, 255, .055); border: 1px solid rgba(255, 255, 255, .09); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease, background .3s ease; overflow: hidden; }
        .mp-card::before { content: ''; position: absolute; inset: 0 0 auto; height: 4px; background: linear-gradient(90deg, var(--mp-a1), var(--mp-a2)); opacity: .85; }
        .mp-card:hover { transform: translateY(-8px); background: rgba(255, 255, 255, .085); border-color: color-mix(in srgb, var(--mp-a1) 55%, transparent); box-shadow: 0 22px 50px -16px color-mix(in srgb, var(--mp-a1) 55%, transparent); }

        .mp-icon { width: 78px; height: 78px; border-radius: 22px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; background: linear-gradient(135deg, var(--mp-a1), var(--mp-a2)); box-shadow: 0 12px 28px -8px color-mix(in srgb, var(--mp-a1) 70%, transparent); transition: transform .3s ease; }
        .mp-card:hover .mp-icon { transform: scale(1.1) rotate(-4deg); }
        .mp-icon svg { width: 38px; height: 38px; color: #fff; }

        .mp-card h2 { font-size: 1.45rem; font-weight: 800; margin: 0 0 6px; }
        .mp-card p { font-size: .92rem; color: var(--mp-muted); margin: 0 0 22px; font-weight: 500; }

        .mp-btn { margin-top: auto; display: inline-flex; align-items: center; gap: 8px; padding: 11px 30px; border-radius: 12px; font-weight: 700; font-size: .95rem; color: #fff; background: linear-gradient(135deg, var(--mp-a1), var(--mp-a2)); box-shadow: 0 8px 20px -6px color-mix(in srgb, var(--mp-a1) 65%, transparent); transition: transform .2s ease, box-shadow .2s ease; }
        .mp-card:hover .mp-btn { transform: scale(1.05); box-shadow: 0 12px 26px -6px color-mix(in srgb, var(--mp-a1) 75%, transparent); }
        .mp-btn svg { width: 17px; height: 17px; transition: transform .2s ease; }
        .mp-card:hover .mp-btn svg { transform: translateX(-4px); }
        [dir="ltr"] .mp-card:hover .mp-btn svg { transform: translateX(4px) scaleX(-1); }
        [dir="ltr"] .mp-btn svg { transform: scaleX(-1); }

        .mp-card--staff    { --mp-a1: #3b82f6; --mp-a2: #1e40af; }
        .mp-card--trainers { --mp-a1: #10b981; --mp-a2: #047857; }
        .mp-card--students { --mp-a1: #8b5cf6; --mp-a2: #6d28d9; }

        .mp-footer { position: relative; z-index: 1; text-align: center; padding: 18px 0 26px; color: #7484b3; font-size: .85rem; font-weight: 500; }

        @media (max-width: 640px) {
            .mp-wrap { padding-top: 36px; }
            .mp-hero { margin-bottom: 34px; }
            .mp-logo { height: 96px; }
        }
    </style>

    <span class="mp-blob mp-blob--1"></span>
    <span class="mp-blob mp-blob--2"></span>
    <span class="mp-blob mp-blob--3"></span>

    <div class="mp-wrap">
        <div class="mp-hero">
            <img src="{{ \App\Support\AppBranding::logoUrl('dark') }}" alt="{{ __('Logo') }}" class="mp-logo" onerror="this.style.display='none'">
            <h1 class="mp-title">منبع التميز</h1>
            <div class="mp-sub-line"></div>
        </div>

        <div class="mp-grid">
            <a href="{{ url('/admin') }}" class="mp-card mp-card--staff">
                <span class="mp-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                    </svg>
                </span>
                <h2>{{ __('Employees') }}</h2>
                <p>{{ __('Employee login portal') }}</p>
                <span class="mp-btn">
                    {{ __('Login') }}
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                </span>
            </a>

            <a href="{{ route('trainer.login') }}" wire:navigate class="mp-card mp-card--trainers">
                <span class="mp-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
                    </svg>
                </span>
                <h2>{{ __('Trainers') }}</h2>
                <p>{{ __('Trainer login portal') }}</p>
                <span class="mp-btn">
                    {{ __('Login') }}
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                </span>
            </a>

            <a href="{{ route('student.login') }}" wire:navigate class="mp-card mp-card--students">
                <span class="mp-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                    </svg>
                </span>
                <h2>{{ __('Students') }}</h2>
                <p>{{ __('Student login portal') }}</p>
                <span class="mp-btn">
                    {{ __('Login') }}
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                </span>
            </a>

        </div>
    </div>

    <div class="mp-footer">© {{ now()->year }} {{ __('Manba Al-Tamayoz Center') }}</div>
</div>
