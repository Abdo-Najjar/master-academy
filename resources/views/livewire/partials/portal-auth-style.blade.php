{{-- Shared style for the portal login pages. Accent colors come from
     --pa-a1/--pa-a2 CSS vars set inline on .pa-root by each page. --}}
<style>
    @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap');

    .pa-root * { box-sizing: border-box; }
    .pa-root {
        font-family: 'Tajawal', ui-sans-serif, system-ui, sans-serif;
        min-height: 100vh; position: relative; overflow: hidden;
        background: linear-gradient(160deg, #0b1023 0%, #131b3a 55%, #0e2433 100%);
        color: #eef1fb;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 28px 16px;
    }

    .pa-blob { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .33; pointer-events: none; animation: pa-float 14s ease-in-out infinite alternate; }
    .pa-blob--1 { width: 440px; height: 440px; background: var(--pa-a1); top: -150px; inset-inline-start: -110px; }
    .pa-blob--2 { width: 380px; height: 380px; background: #2563eb; bottom: -130px; inset-inline-end: -90px; animation-delay: -6s; opacity: .22; }
    @keyframes pa-float { from { transform: translateY(0) scale(1); } to { transform: translateY(46px) scale(1.1); } }

    .pa-card {
        position: relative; z-index: 1; width: 100%; max-width: 430px;
        background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 24px; padding: 38px 34px 30px;
        backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
        box-shadow: 0 30px 70px -30px rgba(0, 0, 0, .6);
        overflow: hidden;
    }
    .pa-card::before { content: ''; position: absolute; inset: 0 0 auto; height: 4px; background: linear-gradient(90deg, var(--pa-a1), var(--pa-a2)); }

    .pa-logo { height: 76px; width: auto; margin: 0 auto 12px; display: block; filter: drop-shadow(0 6px 20px color-mix(in srgb, var(--pa-a1) 50%, transparent)); }

    .pa-icon {
        width: 64px; height: 64px; border-radius: 18px; margin: 0 auto 16px;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, var(--pa-a1), var(--pa-a2));
        box-shadow: 0 12px 28px -8px color-mix(in srgb, var(--pa-a1) 70%, transparent);
    }
    .pa-icon svg { width: 32px; height: 32px; color: #fff; }

    .pa-head { text-align: center; margin-bottom: 26px; }
    .pa-title { font-size: 1.65rem; font-weight: 900; margin: 0 0 4px; color: #fff; }
    .pa-sub { font-size: .92rem; color: #9aa6cc; margin: 0; font-weight: 500; }

    .pa-form { display: flex; flex-direction: column; gap: 16px; }
    .pa-label { display: block; font-size: .88rem; font-weight: 700; color: #c3cbe8; margin-bottom: 6px; }
    .pa-input {
        width: 100%; padding: 12px 14px; font-size: .95rem; font-family: inherit;
        color: #fff; background: rgba(255, 255, 255, .07);
        border: 1px solid rgba(255, 255, 255, .14); border-radius: 12px;
        outline: none; transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }
    .pa-input:focus {
        border-color: var(--pa-a1);
        background: rgba(255, 255, 255, .1);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--pa-a1) 28%, transparent);
    }
    .pa-error { margin: 6px 2px 0; font-size: .82rem; color: #fca5a5; font-weight: 500; }

    .pa-remember { display: flex; align-items: center; gap: 9px; font-size: .88rem; color: #c3cbe8; font-weight: 500; cursor: pointer; }
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

    .pa-back { display: block; text-align: center; margin-top: 22px; font-size: .88rem; color: #8e9ac4; text-decoration: none; font-weight: 500; transition: color .2s ease; }
    .pa-back:hover { color: #fff; }

    .pa-footer { position: relative; z-index: 1; margin-top: 22px; font-size: .8rem; color: #5e6c9e; font-weight: 500; text-align: center; }

    @media (max-width: 480px) {
        .pa-card { padding: 28px 20px 24px; border-radius: 20px; }
        .pa-title { font-size: 1.4rem; }
    }
</style>
