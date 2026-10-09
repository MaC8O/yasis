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

/*
 * Master-detail list (User Management, Student Records): a single click on a row swaps the
 * detail panel in place and records ?selected= in the URL, so a panel action that redirects
 * back() returns to the same record; a double click opens the record's full profile.
 *   <div x-data="masterDetail(5)"> … <tr @click="select(id, panelUrl)" @dblclick="open(fullUrl)"> … <div x-ref="panel">
 */
Alpine.data('masterDetail', (initial = null) => ({
    selected: initial,
    loading: false,
    // Set once this page starts navigating away. A panel load still in flight is then cancelled
    // and must be ignored — otherwise the double-click that opens the full profile would also
    // trigger the error fallback below and send the user back to the list.
    leaving: false,
    request: null,
    init() {
        window.addEventListener('pagehide', () => this.leave());
    },
    async select(id, url) {
        if (this.leaving || id === this.selected || this.loading) return;
        const pageUrl = new URL(window.location.href);
        pageUrl.searchParams.set('selected', id);

        this.selected = id;
        this.loading = true;
        this.request = new AbortController();
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: this.request.signal });
            if (this.leaving) return;
            // Expired session or other failure: fall back to a full page load.
            if (!res.ok || res.redirected) return this.go(pageUrl);
            this.$refs.panel.innerHTML = await res.text();
            history.replaceState(null, '', pageUrl);
            if (window.matchMedia('(max-width: 1279px)').matches) {
                this.$refs.panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch {
            if (!this.leaving) this.go(pageUrl);
        } finally {
            this.loading = false;
            this.request = null;
        }
    },
    open(url) {
        this.go(url);
    },
    go(url) {
        this.leave();
        window.location.assign(url);
    },
    leave() {
        this.leaving = true;
        this.request?.abort();
    },
}));

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
