/**
 * Filter bus. Leans on the project-wide Select2 auto-init in custom.js
 * (`$('.select2').select2()`), mirroring how admin/packages filters work.
 *
 * Public contract (consumed by api.js and every section script):
 *   window.MD_FILTERS.toParams()  -> { range, branches? }
 *   window.MD_FILTERS.state       -> same shape, direct read
 *   window.MD_FILTERS.emit()      -> re-dispatches 'md:filter-changed'
 *   event 'md:filter-changed'     -> detail = state snapshot
 */
(function ($) {
    'use strict';

    const DEFAULT_PRESET = 'this_month';
    const DEBOUNCE_MS = 250;
    const state = { range: DEFAULT_PRESET, branches: [], from: null, to: null };
    let emitTimer = null;

    function emit() {
        clearTimeout(emitTimer);
        emitTimer = setTimeout(() => {
            document.dispatchEvent(new CustomEvent('md:filter-changed', { detail: { ...state } }));
        }, DEBOUNCE_MS);
    }

    function toParams() {
        const p = { range: state.range };
        if (state.branches.length) p.branches = state.branches;
        // Custom range needs explicit from/to; every other preset resolves
        // server-side from the preset name alone.
        if (state.range === 'custom') {
            if (state.from) p.from = state.from;
            if (state.to) p.to = state.to;
        }
        return p;
    }

    function toggleCustomInputs(show) {
        const host = document.getElementById('mdCustomRange');
        if (host) host.hidden = !show;
    }

    function wire() {
        // No explicit .select2() call — custom.js's global `$('.select2').select2()`
        // already initialized both widgets, reading our data-* attributes for
        // `dropdown-css-class` and `minimum-results-for-search`. Re-initializing
        // here would destroy/rebuild the widget and cause a visible flicker.
        $('#mdDateRange').on('change', function () {
            state.range = String($(this).val() || DEFAULT_PRESET);
            toggleCustomInputs(state.range === 'custom');
            // Don't emit until the user picks both ends of a custom range —
            // a partial payload would error out server-side.
            if (state.range === 'custom' && (!state.from || !state.to)) return;
            emit();
        });

        const handleCustomChange = () => {
            const from = document.getElementById('mdCustomFrom').value || null;
            const to = document.getElementById('mdCustomTo').value || null;
            state.from = from;
            state.to = to;
            if (state.range === 'custom' && from && to && from <= to) emit();
        };
        $('#mdCustomFrom, #mdCustomTo').on('change', handleCustomChange);

        $('#mdBranchSelect').on('change', function () {
            const val = Number($(this).val());
            state.branches = val ? [val] : [];
            emit();
        });
    }

    window.MD_FILTERS = { state, toParams, emit };

    $(function () { wire(); });
})(window.jQuery);
