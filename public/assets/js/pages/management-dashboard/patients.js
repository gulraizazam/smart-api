/**
 * Patient-widget renderers. Both Retention Cohorts and Revenue
 * Concentration live on Overview now; this file remains the single
 * owner of their load + render code, exposed through
 * `window.MD_PATIENT_WIDGETS`. The Patients tab itself has been
 * deleted — there is no longer any section-ready listener here.
 */
(function () {
    'use strict';

    let showExtended = false;

    /**
     * Fetches /patients (which returns retention + concentration in one
     * payload) and renders ONLY the cohort table. Concentration data is
     * ignored here — the Patients tab uses `loadConcentration()` for that.
     * MD_API caches by URL+params so back-to-back calls don't double-hit.
     */
    async function loadCohort() {
        try {
            const params = {
                ...window.MD_FILTERS.toParams(),
                cohort_months: showExtended ? 24 : 12,
                extended_windows: showExtended ? 1 : 0,
            };
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.patients, params);
            if (resp && resp.data) renderCohort(resp.data.retention || { months: [], windows: [], cohorts: {} });
        } catch (e) { console.error('cohort fetch failed', e); }
    }

    async function loadConcentration() {
        try {
            const params = {
                ...window.MD_FILTERS.toParams(),
                cohort_months: showExtended ? 24 : 12,
                extended_windows: showExtended ? 1 : 0,
            };
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.patients, params);
            if (resp && resp.data) renderConcentration(resp.data.concentration || {});
        } catch (e) { console.error('concentration fetch failed', e); }
    }

    function renderCohort(r) {
        const thead = document.getElementById('mdCohortThead');
        const tbody = document.getElementById('mdCohortTbody');
        if (!thead || !tbody) return;

        const windows = r.windows || [];
        thead.innerHTML = '<tr><th>Cohort</th><th>Size</th>' + windows.map((w) => `<th>${w}d</th>`).join('') + '</tr>';

        const months = r.months || [];
        tbody.innerHTML = months.map((m) => {
            const cell = r.cohorts[m] || { size: 0, retention: {} };
            const cells = windows.map((w) => {
                const v = cell.retention[w];
                if (v === null || v === undefined) return '<td class="md-cohort-cell md-cohort-na">—</td>';
                const cls = cohortClass(v);
                return `<td class="md-cohort-cell ${cls}">${v.toFixed(1)}%</td>`;
            }).join('');
            return `<tr><td class="md-cohort-month">${m}</td><td class="md-cohort-size">${cell.size}</td>${cells}</tr>`;
        }).join('');
    }

    function cohortClass(v) {
        if (v >= 40) return 'md-cohort-hi';
        if (v >= 20) return 'md-cohort-mid';
        if (v > 0) return 'md-cohort-lo';
        return 'md-cohort-zero';
    }

    function renderConcentration(c) {
        setText('mdConcentrationHeadline', `Top 20% = ${c.top_20_pct_share || 0}% of revenue`);
        setText('mdConcentrationSub', `Top 10% = ${c.top_10_pct_share || 0}% · ${c.total_patients || 0} patients · ${window.MD_CHARTS.currency(c.total_revenue || 0)}`);

        const lorenz = (c.lorenz || []).map((p) => [p.x, p.y]);
        if (lorenz.length > 0) {
            window.MD_CHARTS.render('mdLorenzChart', {
                chart: { type: 'line', height: 160, sparkline: { enabled: true } },
                series: [{ name: 'Revenue share', data: lorenz }],
                xaxis: { type: 'numeric', min: 0, max: 100 },
                yaxis: { min: 0, max: 100 },
                stroke: { width: 2, curve: 'smooth' },
                tooltip: { enabled: false },
            });
        }
    }

    function wireCohortToggle() {
        const el = document.getElementById('mdCohort24');
        if (!el || el.dataset.wired === '1') return;
        el.dataset.wired = '1';
        el.addEventListener('change', (ev) => {
            showExtended = ev.target.checked;
            loadCohort();
        });
    }

    function setText(id, value) { const el = document.getElementById(id); if (el) el.textContent = value; }

    // Cross-section exposure — Overview owns the cohort panel, Patients
    // tab owns concentration + new-patients trend.
    window.MD_PATIENT_WIDGETS = {
        loadCohort,
        loadConcentration,
        wireCohortToggle,
    };

})();
