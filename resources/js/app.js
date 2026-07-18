import flatpickr from 'flatpickr';
import { Arabic } from 'flatpickr/dist/l10n/ar.js';

flatpickr.l10ns.ar = Arabic;

window.flatpickr = flatpickr;

function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.dataset.theme = theme;

    document.querySelectorAll('[data-theme-asset]').forEach((el) => {
        el.href = el.href.replace(/\/images\/(light|dark)\//, `/images/${theme}/`);
    });
}

window.toggleTheme = function toggleTheme() {
    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('theme', next);
    applyTheme(next);
};

applyTheme(document.documentElement.dataset.theme || 'light');

// A flatpickr instance's visible alt-input is a separate DOM node from the
// underlying value input, so it doesn't notice when Livewire sets that
// underlying value directly (e.g. after $this->reset()). Re-sync the
// display whenever Livewire morphs an element that owns a flatpickr
// instance, without re-triggering onChange (which would echo back to the
// server and could create a feedback loop).
document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', ({ el }) => {
        if (el._flatpickr) {
            el._flatpickr.setDate(el.value, false);
        }
    });
});
