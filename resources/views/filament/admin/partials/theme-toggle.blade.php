{{-- Floating light/dark theme toggle for Filament auth (simple) pages.
     Uses self-contained inline CSS because Filament's compiled panel
     stylesheet does not ship the arbitrary Tailwind utilities we'd
     otherwise need here. Mirrors the portal toggle in
     resources/views/components/layouts/app.blade.php. --}}
@if (filament()->hasDarkMode() && ! filament()->hasDarkModeForced())
    <style>
        .ma-theme-toggle {
            position: fixed; bottom: 1rem; left: 1rem; z-index: 50;
            display: flex; align-items: center; justify-content: center;
            width: 2.75rem; height: 2.75rem; padding: 0; border: 0; cursor: pointer;
            border-radius: 9999px; background: #ffffff; color: #374151;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1);
            outline: 1px solid #e5e7eb; outline-offset: 0;
            transition: transform .15s ease, background .15s ease;
        }
        .ma-theme-toggle:hover { transform: scale(1.05); }
        .ma-theme-toggle svg { width: 1.25rem; height: 1.25rem; }
        html.dark .ma-theme-toggle { background: #1f2937; color: #e5e7eb; outline-color: #374151; }
        .ma-theme-toggle .ma-icon-sun { display: none; }
        .ma-theme-toggle .ma-icon-moon { display: block; }
        html.dark .ma-theme-toggle .ma-icon-sun { display: block; }
        html.dark .ma-theme-toggle .ma-icon-moon { display: none; }
    </style>
    <button
        type="button"
        onclick="window.dispatchEvent(new CustomEvent('theme-changed', { detail: document.documentElement.classList.contains('dark') ? 'light' : 'dark' }))"
        aria-label="{{ __('Toggle dark mode') }}"
        class="ma-theme-toggle"
    >
        <svg class="ma-icon-sun" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
        </svg>
        <svg class="ma-icon-moon" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
        </svg>
    </button>
@endif
