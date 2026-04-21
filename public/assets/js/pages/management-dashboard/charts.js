/**
 * ApexCharts helpers. Minimal wrappers keyed by container ID so each section
 * can (re)render without owning the chart instance.
 */
(function () {
    'use strict';

    const instances = new Map();

    function render(id, options) {
        const el = document.getElementById(id);
        if (!el || typeof ApexCharts === 'undefined') return;
        // Always destroy + recreate so options like tooltip handlers apply
        // cleanly. updateOptions() doesn't re-bind every config path.
        const existing = instances.get(id);
        if (existing) {
            existing.destroy();
            instances.delete(id);
        }
        const chart = new ApexCharts(el, options);
        chart.render();
        instances.set(id, chart);
        return chart;
    }

    function destroy(id) {
        const chart = instances.get(id);
        if (chart) {
            chart.destroy();
            instances.delete(id);
        }
    }

    // Currency display follows the codebase convention: "PKR" prefix +
    // M/K abbreviation (mirrors DoctorDashboardHelper::formatCurrency).
    // Use for chart axes and tight UI; for KPI tiles use currencyFull().
    function currency(n) {
        if (n === null || n === undefined || isNaN(n)) return '—';
        if (n >= 1e6) return 'PKR ' + (n / 1e6).toFixed(1) + 'M';
        if (n >= 1e3) return 'PKR ' + (n / 1e3).toFixed(1) + 'K';
        return 'PKR ' + Math.round(n).toLocaleString();
    }

    // Full-precision currency: "PKR 5,234,567" — for KPI tiles where the
    // exact figure matters more than display compactness.
    function currencyFull(n) {
        if (n === null || n === undefined || isNaN(n)) return '—';
        return 'PKR ' + Math.round(n).toLocaleString();
    }

    function percent(n, digits = 1) {
        if (n === null || n === undefined || isNaN(n)) return '—';
        return n.toFixed(digits) + '%';
    }

    function deltaHtml(current, previous) {
        if (previous === null || previous === undefined || previous === 0) return '—';
        const d = ((current - previous) / Math.abs(previous)) * 100;
        const sign = d >= 0 ? '+' : '';
        const cls = d >= 0 ? 'md-delta-up' : 'md-delta-down';
        return '<span class="md-delta ' + cls + '">' + sign + d.toFixed(1) + '%</span>';
    }

    window.MD_CHARTS = { render, destroy, currency, currencyFull, percent, deltaHtml };
})();
