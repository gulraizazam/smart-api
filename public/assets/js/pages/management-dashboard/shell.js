/**
 * Shell controller. Dispatches a section-ready event so each section's
 * script knows when to render.
 */
(function () {
    'use strict';

    function init() {
        // Mark body so CSS rules that hide the Metronic header/subheader
        // match via a plain class selector instead of :has(). :has() on
        // common ancestors (body/.wrapper) forces a whole-tree re-evaluation
        // every time Select2 mutates the DOM, which felt like lag.
        document.body.classList.add('has-md-shell');
        dispatchSectionReady();
    }

    function dispatchSectionReady() {
        const shell = document.getElementById('mdShell');
        if (!shell) return;
        const section = shell.dataset.activeSection;
        document.dispatchEvent(new CustomEvent('md:section-ready', { detail: { section } }));
    }

    /**
     * Wire a pill-toggle group that switches between .md-view children inside
     * the same .md-panel. Expects:
     *   <div class="md-toggle" data-md-toggle>
     *     <button data-view="a" data-title="View A" class="is-active">A</button>
     *     <button data-view="b" data-title="View B">B</button>
     *   </div>
     *   <h3 data-md-toggle-title>View A</h3>
     *   <div class="md-view-stack">
     *     <div class="md-view is-active" data-view="a">…</div>
     *     <div class="md-view" data-view="b">…</div>
     *   </div>
     * If a button has data-title, the panel's [data-md-toggle-title] element
     * updates to that string on activation (falls back to button text).
     * Idempotent — safe to call on every section-ready.
     */
    function wireViewToggle(selector) {
        document.querySelectorAll(selector).forEach((group) => {
            if (group.dataset.mdWired === '1') return;
            group.dataset.mdWired = '1';

            const panel = group.closest('.md-panel');
            if (!panel) return;

            const buttons = group.querySelectorAll('.md-toggle-btn');
            const views = panel.querySelectorAll('.md-view');
            const titleEl = panel.querySelector('[data-md-toggle-title]');

            const titleFor = (btn) => btn.dataset.title || btn.textContent.trim();

            buttons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const target = btn.dataset.view;
                    buttons.forEach((b) => {
                        const active = b.dataset.view === target;
                        b.classList.toggle('is-active', active);
                        b.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    views.forEach((v) => { v.classList.toggle('is-active', v.dataset.view === target); });
                    if (titleEl) titleEl.textContent = titleFor(btn);
                });
            });
        });
    }

    window.MD_UI = { wireViewToggle };

    document.addEventListener('DOMContentLoaded', init);
})();
