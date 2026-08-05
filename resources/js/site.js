/**
 * Behaviour for the public marketing site (landing + join form).
 *
 * Everything here is presentational — the header shrink, the mobile menu, the
 * scroll-reveal animation and the gallery lightbox. Content and filtering live
 * in the Volt components, so this file never fetches or renders data.
 *
 * All handlers are delegated from `document`, because `wire:navigate` swaps the
 * page body without re-running this module. Binding directly to the elements
 * would either go stale after a navigation or double up if we re-bound on every
 * `livewire:navigated` — a double-bound menu toggle cancels itself out.
 */

function updateHeader() {
    document.querySelector('.site-header')?.classList.toggle('scrolled', window.scrollY > 20);
}

function closeMenu() {
    const nav = document.querySelector('.primary-nav');

    nav?.classList.remove('open');
    document.querySelector('.menu-toggle')?.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('menu-open');
}

/**
 * Reveals elements as they scroll into view. Safe to call repeatedly: elements
 * already revealed are skipped, so it can run again after Livewire swaps part
 * of the DOM (e.g. filtering the program grid).
 */
function revealOnScroll(root = document) {
    const items = root.querySelectorAll('.reveal:not(.visible)');

    if (! ('IntersectionObserver' in window)) {
        items.forEach((item) => item.classList.add('visible'));

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08 });

    items.forEach((item) => observer.observe(item));
}

function openLightbox(trigger) {
    const dialog = document.querySelector('#media-dialog');
    const body = dialog?.querySelector('.dialog-body');

    if (! body) {
        return;
    }

    body.innerHTML = trigger.dataset.mediaType === 'video'
        ? '<video controls autoplay playsinline></video>'
        : '<img alt="">';

    const node = body.firstElementChild;
    node.src = trigger.dataset.mediaUrl;
    node.alt = trigger.dataset.mediaTitle || '';

    dialog.showModal();
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('.menu-toggle');

    if (toggle) {
        const nav = document.querySelector('.primary-nav');
        const open = nav.classList.toggle('open');

        toggle.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('menu-open', open);

        return;
    }

    if (event.target.closest('.primary-nav a')) {
        closeMenu();

        return;
    }

    const mediaTrigger = event.target.closest('[data-media-url]');

    if (mediaTrigger) {
        openLightbox(mediaTrigger);

        return;
    }

    if (event.target.closest('.dialog-close')) {
        document.querySelector('#media-dialog')?.close();

        return;
    }

    // Clicking the backdrop resolves to the dialog element itself.
    if (event.target.matches('.media-dialog')) {
        event.target.close();
    }
});

document.addEventListener('close', (event) => {
    if (event.target.matches?.('.media-dialog')) {
        event.target.querySelector('.dialog-body').innerHTML = '';
    }
}, true);

window.addEventListener('scroll', updateHeader, { passive: true });

document.addEventListener('livewire:navigated', () => {
    closeMenu();
    updateHeader();
    revealOnScroll();
});

document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', ({ el }) => revealOnScroll(el));
});

updateHeader();
revealOnScroll();
