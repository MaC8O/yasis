import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Data tables that don't use the stacked-card layout (.rt) scroll sideways on narrow
// screens instead of overflowing the page. Done before Alpine starts so no bound
// element is moved after initialisation.
document.querySelectorAll('main table:not(.rt)').forEach((table) => {
    if (table.closest('.overflow-x-auto, .table-scroll')) return;
    const wrap = document.createElement('div');
    wrap.className = 'table-scroll overflow-x-auto';
    table.replaceWith(wrap);
    wrap.appendChild(table);
});

Alpine.start();

/*
 * Double-submit guard: once a form is submitted, further submits are ignored and the
 * clicked button shows a spinner. The button is NOT disabled, because a disabled
 * submitter drops its name/value (e.g. action=approve vs action=reject).
 * Opt out per form with data-no-loading. Forms that trigger a file download never
 * navigate away, so the guard releases itself after a few seconds.
 */
const release = (form) => {
    delete form.dataset.submitting;
    form.querySelectorAll('.is-submitting').forEach((b) => {
        b.classList.remove('is-submitting');
        b.removeAttribute('aria-busy');
    });
};

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) return;
    if (form.dataset.submitting) {
        e.preventDefault();
        return;
    }
    // An inline onsubmit="return confirm(...)" that was cancelled already prevented it.
    if (e.defaultPrevented) return;

    form.dataset.submitting = '1';
    const button = e.submitter ?? form.querySelector('[type=submit]');
    if (button && button.tagName === 'BUTTON') {
        button.style.setProperty('--spin', getComputedStyle(button).color);
        button.classList.add('is-submitting');
        button.setAttribute('aria-busy', 'true');
    }
    setTimeout(() => release(form), 6000);
});

// Restore buttons when the page comes back from the back/forward cache.
window.addEventListener('pageshow', (e) => {
    if (e.persisted) document.querySelectorAll('form[data-submitting]').forEach(release);
});
