{{-- Shared style for the portal login pages. Accent colors come from
     --pa-a1/--pa-a2 CSS vars set inline on .pa-root by each page. --}}
<style>
    @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

    .pa-root * { box-sizing: border-box; }
    .pa-root {
        --pa-bg1: #eef2ff; --pa-bg2: #f8fafc; --pa-bg3: #eef2f7;
        --pa-text: #0f172a; --pa-muted: #64748b; --pa-footer: #64748b;
        --pa-card-bg: rgba(255, 255, 255, .65); --pa-card-border: rgba(15, 23, 42, .08);
        --pa-input-bg: rgba(15, 23, 42, .04); --pa-input-border: rgba(15, 23, 42, .12); --pa-input-color: #0f172a;
        --pa-back: #475569; --pa-back-hover: #0f172a; --pa-error: #dc2626;
    }
    html.dark .pa-root {
        --pa-bg1: #0b1023; --pa-bg2: #131b3a; --pa-bg3: #0e2433;
        --pa-text: #eef1fb; --pa-muted: #9aa6cc; --pa-footer: #5e6c9e;
        --pa-card-bg: rgba(255, 255, 255, .06); --pa-card-border: rgba(255, 255, 255, .1);
        --pa-input-bg: rgba(255, 255, 255, .07); --pa-input-border: rgba(255, 255, 255, .14); --pa-input-color: #fff;
        --pa-back: #8e9ac4; --pa-back-hover: #fff; --pa-error: #fca5a5;
    }
    .pa-root {
        font-family: 'Tajawal', ui-sans-serif, system-ui, sans-serif;
        min-height: 100vh; position: relative; overflow: hidden;
        background: linear-gradient(160deg, var(--pa-bg1) 0%, var(--pa-bg2) 55%, var(--pa-bg3) 100%);
        color: var(--pa-text);
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 28px 16px;
        transition: background .3s ease, color .3s ease;
    }

    .pa-blob { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .33; pointer-events: none; animation: pa-float 14s ease-in-out infinite alternate; }
    .pa-blob--1 { width: 440px; height: 440px; background: var(--pa-a1); top: -150px; inset-inline-start: -110px; }
    .pa-blob--2 { width: 380px; height: 380px; background: var(--pa-a2); bottom: -130px; inset-inline-end: -90px; animation-delay: -6s; opacity: .22; }
    @keyframes pa-float { from { transform: translateY(0) scale(1); } to { transform: translateY(46px) scale(1.1); } }

    .pa-card {
        position: relative; z-index: 1; width: 100%; max-width: 430px;
        background: var(--pa-card-bg); border: 1px solid var(--pa-card-border);
        border-radius: 24px; padding: 38px 34px 30px;
        backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
        box-shadow: 0 30px 70px -30px rgba(0, 0, 0, .3);
        overflow: hidden;
        transition: background .3s ease, border-color .3s ease;
    }
    html.dark .pa-card { box-shadow: 0 30px 70px -30px rgba(0, 0, 0, .6); }
    .pa-card::before { content: ''; position: absolute; inset: 0 0 auto; height: 4px; background: linear-gradient(90deg, var(--pa-a1), var(--pa-a2)); }

    .pa-logo { height: 76px; width: auto; margin: 0 auto 16px; display: block; filter: drop-shadow(0 6px 20px color-mix(in srgb, var(--pa-a1) 50%, transparent)); }

    .pa-head { text-align: center; margin-bottom: 26px; }
    .pa-title { font-size: 1.65rem; font-weight: 900; margin: 0 0 4px; color: var(--pa-text); }
    .pa-sub { font-size: .92rem; color: var(--pa-muted); margin: 0; font-weight: 500; }

    .pa-form { display: flex; flex-direction: column; gap: 16px; }
    .pa-label { display: block; font-size: .88rem; font-weight: 700; color: var(--pa-muted); margin-bottom: 6px; }
    .pa-input {
        width: 100%; padding: 12px 14px; font-size: .95rem; font-family: inherit;
        color: var(--pa-input-color); background: var(--pa-input-bg);
        border: 1px solid var(--pa-input-border); border-radius: 12px;
        outline: none; transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }
    .pa-input:focus {
        border-color: var(--pa-a1);
        background: color-mix(in srgb, var(--pa-input-bg) 60%, white 10%);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--pa-a1) 28%, transparent);
    }
    .pa-error { margin: 6px 2px 0; font-size: .82rem; color: var(--pa-error); font-weight: 500; }

    .pa-remember { display: flex; align-items: center; gap: 9px; font-size: .88rem; color: var(--pa-muted); font-weight: 500; cursor: pointer; }
    .pa-remember input { width: 17px; height: 17px; accent-color: var(--pa-a1); cursor: pointer; }

    .pa-btn {
        width: 100%; padding: 13px; border: none; border-radius: 12px; cursor: pointer;
        font-family: inherit; font-size: 1.02rem; font-weight: 800; color: #fff;
        background: linear-gradient(135deg, var(--pa-a1), var(--pa-a2));
        box-shadow: 0 10px 24px -8px color-mix(in srgb, var(--pa-a1) 70%, transparent);
        transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }
    .pa-btn:hover { transform: translateY(-2px); filter: brightness(1.08); box-shadow: 0 14px 30px -8px color-mix(in srgb, var(--pa-a1) 80%, transparent); }
    .pa-btn:active { transform: translateY(0); }
    .pa-btn[disabled] { opacity: .7; cursor: wait; transform: none; }

    .pa-back { display: block; text-align: center; margin-top: 22px; font-size: .88rem; color: var(--pa-back); text-decoration: none; font-weight: 500; transition: color .2s ease; }
    .pa-back:hover { color: var(--pa-back-hover); }

    .pa-footer { position: relative; z-index: 1; margin-top: 22px; font-size: .8rem; color: var(--pa-footer); font-weight: 500; text-align: center; }

    @media (max-width: 480px) {
        .pa-card { padding: 28px 20px 24px; border-radius: 20px; }
        .pa-title { font-size: 1.4rem; }
    }
</style>
