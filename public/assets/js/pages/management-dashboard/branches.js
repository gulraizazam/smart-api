/**
 * Branches section + cross-section renderers.
 *
 * The Branches tab now owns only the Service Category Trend (chart + matrix
 * summary + "Other includes …" footnote). The Branch Leaderboard and New-
 * vs-Returning panels are rendered on Overview — they live here under
 * `window.MD_BRANCH_WIDGETS` so overview.js can call them without
 * re-implementing the tooltips / health flags / custom sort.
 */
(function () {
    'use strict';

    // Tableau-20 inspired palette for Service Category Trend. Last entry is
    // a neutral grey reserved for the "Other" bucket so the long tail doesn't
    // visually compete with named top-N categories.
    const CATEGORY_PALETTE = [
        '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728',
        '#9467bd', '#8c564b', '#e377c2', '#17becf',
        '#bcbd22', '#7f7f7f', '#e7ba52', '#a55194',
        '#9ca3af', // reserved for "Other"
    ];
    const OTHER_COLOR = '#9ca3af';

    // --- Branch Leaderboard (rendered on Overview) ------------------------
    let lastBranchRows = [];
    let currentSort = 'net_revenue';

    async function loadLeaderboard() {
        try {
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.branches, window.MD_FILTERS.toParams());
            if (resp && resp.data) {
                lastBranchRows = resp.data.rows || [];
                renderLeaderboard();
            }
        } catch (e) { console.error('branches fetch failed', e); }
    }

    function renderLeaderboard() {
        const host = document.getElementById('mdBranchesCards');
        if (!host) return;
        const sorted = sortRows(lastBranchRows, currentSort);
        if (sorted.length === 0) {
            host.innerHTML = '<div class="md-empty">No branches in scope</div>';
            return;
        }
        const header = `
            <div class="md-blb-row md-blb-head">
                <span class="md-blb-rank">#</span>
                <span class="md-blb-name">Branch</span>
                <span class="md-blb-metric">Revenue</span>
                <span class="md-blb-metric">Conv.</span>
                <span class="md-blb-metric">Avg value</span>
            </div>`;
        host.innerHTML = header + sorted.map(branchRowHtml).join('');
    }

    function sortRows(rows, key) {
        const copy = rows.slice();
        copy.sort((a, b) => {
            const an = a[key] === null || a[key] === undefined ? -Infinity : a[key];
            const bn = b[key] === null || b[key] === undefined ? -Infinity : b[key];
            return bn - an;
        });
        return copy;
    }

    function branchRowHtml(r, idx) {
        return `
            <div class="md-blb-row" data-branch-id="${r.branch_id}">
                <span class="md-blb-rank">${idx + 1}</span>
                <span class="md-blb-name" title="${escapeHtml(r.branch_name)}">${escapeHtml(r.branch_name)}</span>
                <span class="md-blb-metric md-blb-revenue">${window.MD_CHARTS.currency(r.net_revenue)}</span>
                <span class="md-blb-metric md-blb-conv">${window.MD_CHARTS.percent(r.conversion_rate)}</span>
                <span class="md-blb-metric md-blb-avg">${window.MD_CHARTS.currency(r.avg_value)}</span>
            </div>`;
    }

    function wireLeaderboardSort() {
        const sort = document.getElementById('mdBranchesSort');
        if (!sort || sort.dataset.wired === '1') return;
        sort.dataset.wired = '1';
        sort.addEventListener('change', (e) => {
            currentSort = e.target.value;
            renderLeaderboard();
        });
    }

    // --- New vs Returning (rendered on Overview) --------------------------
    async function loadNewReturning() {
        try {
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.new_returning, window.MD_FILTERS.toParams());
            if (resp && resp.data) renderNewReturning(resp.data);
        } catch (e) { console.error('new-returning fetch failed', e); }
    }

    /**
     * Stacked horizontal bar per branch: new-patient count (light blue) +
     * returning-patient count (dark blue). Tooltip surfaces headcount and
     * revenue split + a health flag.
     */
    function renderNewReturning(data) {
        const rows = data.rows || [];
        if (rows.length === 0) {
            const splitHost = document.getElementById('mdNewReturningChart');
            if (splitHost) splitHost.innerHTML = '<div class="md-empty">No patient activity in range</div>';
            const rateHost = document.getElementById('mdReturnRateChart');
            if (rateHost) rateHost.innerHTML = '<div class="md-empty">No patient activity in range</div>';
            return;
        }

        renderReturnRate(rows);

        const categories = rows.map((r) => r.branch_name);

        window.MD_CHARTS.render('mdNewReturningChart', {
            chart: {
                type: 'bar',
                height: 320,
                stacked: true,
                stackType: 'normal',
                toolbar: { show: false },
                animations: { enabled: false },
            },
            series: [
                { name: 'New', data: rows.map((r) => r.new_patients) },
                { name: 'Returning', data: rows.map((r) => r.returning_patients) },
            ],
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '65%',
                    borderRadius: 2,
                    dataLabels: { position: 'center' },
                },
            },
            colors: ['#60a5fa', '#1e40af'],
            xaxis: {
                categories,
                labels: {
                    formatter: (v) => Math.abs(Math.round(Number(v) || 0)).toLocaleString(),
                    style: { fontSize: '11px', colors: '#6b7280' },
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: { labels: { style: { fontSize: '11px', colors: '#6b7280' } } },
            dataLabels: {
                enabled: true,
                formatter: (_val, opts) => {
                    const row = rows[opts.dataPointIndex];
                    if (!row) return '';
                    const isNew = opts.seriesIndex === 0;
                    const pct = isNew ? row.new_pct : row.returning_pct;
                    if (pct < 10) return '';
                    return pct.toFixed(0) + '%';
                },
                style: { fontSize: '10px', colors: ['#fff'], fontWeight: 600 },
                dropShadow: { enabled: false },
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '11px',
                markers: { width: 10, height: 10, radius: 2 },
            },
            tooltip: {
                enabled: true,
                shared: true,
                intersect: false,
                custom: ({ dataPointIndex }) => {
                    const r = rows[dataPointIndex];
                    if (!r) return '';
                    const retention = r.retention_rate === null
                        ? '<span class="md-tt-muted">no prior period</span>'
                        : `${r.retention_rate.toFixed(1)}% <span class="md-tt-muted">(${r.retained_from_prior.toLocaleString()} of ${r.prior_active.toLocaleString()})</span>`;
                    const followUp = r.follow_up_rate === null
                        ? '<span class="md-tt-muted">no treatments</span>'
                        : `${r.follow_up_rate.toFixed(1)}% <span class="md-tt-muted">(${r.follow_up_returned.toLocaleString()} of ${r.follow_up_treated.toLocaleString()})</span>`;
                    return `
                        <div class="md-tt">
                            <div class="md-tt-title">${escapeHtml(r.branch_name)}</div>
                            <div class="md-tt-row">
                                <span class="md-tt-dot" style="background:#60a5fa"></span>
                                <span class="md-tt-label">New patients</span>
                                <span class="md-tt-value">${r.new_patients.toLocaleString()} · ${r.new_pct.toFixed(1)}%</span>
                            </div>
                            <div class="md-tt-row">
                                <span class="md-tt-dot" style="background:#1e40af"></span>
                                <span class="md-tt-label">Returning</span>
                                <span class="md-tt-value">${r.returning_patients.toLocaleString()} · ${r.returning_pct.toFixed(1)}%</span>
                            </div>
                            <div class="md-tt-row md-tt-row-rev">
                                <span class="md-tt-dot" style="background:transparent"></span>
                                <span class="md-tt-label">Revenue from new</span>
                                <span class="md-tt-value">${window.MD_CHARTS.currency(r.new_revenue)} · ${r.new_revenue_pct.toFixed(1)}%</span>
                            </div>
                            <div class="md-tt-row md-tt-row-stat">
                                <span class="md-tt-dot" style="background:transparent"></span>
                                <span class="md-tt-label" title="Prior-period patients who returned this period">Retention (prior→now)</span>
                                <span class="md-tt-value">${retention}</span>
                            </div>
                            <div class="md-tt-row md-tt-row-stat">
                                <span class="md-tt-dot" style="background:transparent"></span>
                                <span class="md-tt-label" title="Treated patients who returned within 45 days">45-day follow-up</span>
                                <span class="md-tt-value">${followUp}</span>
                            </div>
                            <div class="md-tt-foot md-tt-foot-${r.health}">
                                <span>Health</span>
                                <span>${healthLabel(r.health)}</span>
                            </div>
                        </div>`;
                },
            },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
        });
    }

    function healthLabel(h) {
        return h === 'green' ? 'Healthy mix'
            : h === 'amber'  ? 'Check balance'
            : h === 'red'    ? 'Acquisition-dependent'
            :                  '—';
    }

    /**
     * 45-day return rate per branch. Reuses the follow_up_* fields already
     * returned by NewReturningMetric — no extra API call. Sorted high→low so
     * the strongest branch sits at the top.
     */
    function renderReturnRate(rows) {
        const host = document.getElementById('mdReturnRateChart');
        if (!host) return;

        const sorted = rows.slice().sort((a, b) => {
            const ar = a.follow_up_rate === null ? -1 : a.follow_up_rate;
            const br = b.follow_up_rate === null ? -1 : b.follow_up_rate;
            return br - ar;
        });

        const categories = sorted.map((r) => r.branch_name);
        const values = sorted.map((r) => r.follow_up_rate === null ? 0 : r.follow_up_rate);
        const barColors = sorted.map((r) => r.health === 'green' ? '#16a34a'
            : r.health === 'amber' ? '#f59e0b'
            : r.health === 'red'   ? '#dc2626'
            :                        '#9ca3af');

        // Auto-scale the x-axis max: when the selected range is short (e.g.
        // "This Month" early in the month), 45-day follow-ups haven't had
        // time to accrue and every branch reads 2–5%. Pinning max to 100%
        // squashes the bars invisible. 1.2× max-value with a 10% floor keeps
        // the scale meaningful while leaving headroom for data labels.
        const peak = values.reduce((m, v) => Math.max(m, v), 0);
        const axisMax = Math.max(10, Math.ceil((peak * 1.2) / 5) * 5);

        window.MD_CHARTS.render('mdReturnRateChart', {
            chart: {
                type: 'bar',
                height: 320,
                toolbar: { show: false },
                animations: { enabled: false },
            },
            series: [{ name: 'Return rate', data: values }],
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '65%',
                    borderRadius: 2,
                    distributed: true,
                    dataLabels: { position: 'top' },
                },
            },
            colors: barColors,
            xaxis: {
                categories,
                min: 0,
                max: axisMax,
                labels: {
                    formatter: (v) => (Number(v) || 0).toFixed(0) + '%',
                    style: { fontSize: '11px', colors: '#6b7280' },
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: { labels: { style: { fontSize: '11px', colors: '#6b7280' } } },
            dataLabels: {
                enabled: true,
                offsetX: 28,
                textAnchor: 'start',
                formatter: (val, opts) => {
                    const r = sorted[opts.dataPointIndex];
                    if (!r || r.follow_up_rate === null) return '';
                    return r.follow_up_rate.toFixed(1) + '%';
                },
                style: { fontSize: '10.5px', colors: ['#374151'], fontWeight: 700 },
                dropShadow: { enabled: false },
            },
            legend: { show: false },
            tooltip: {
                enabled: true,
                shared: false,
                intersect: true,
                custom: ({ dataPointIndex }) => {
                    const r = sorted[dataPointIndex];
                    if (!r) return '';
                    const rate = r.follow_up_rate === null
                        ? '<span class="md-tt-muted">no treatments</span>'
                        : `${r.follow_up_rate.toFixed(1)}%`;
                    const ratio = r.follow_up_rate === null
                        ? ''
                        : `<div class="md-tt-row md-tt-row-stat">
                            <span class="md-tt-dot" style="background:transparent"></span>
                            <span class="md-tt-label">Returned / treated</span>
                            <span class="md-tt-value">${r.follow_up_returned.toLocaleString()} of ${r.follow_up_treated.toLocaleString()}</span>
                        </div>`;
                    return `
                        <div class="md-tt">
                            <div class="md-tt-title">${escapeHtml(r.branch_name)}</div>
                            <div class="md-tt-row">
                                <span class="md-tt-dot" style="background:${barColors[dataPointIndex]}"></span>
                                <span class="md-tt-label" title="Treated patients who returned within 45 days">45-day return rate</span>
                                <span class="md-tt-value">${rate}</span>
                            </div>
                            ${ratio}
                            <div class="md-tt-foot md-tt-foot-${r.health}">
                                <span>Health</span>
                                <span>${healthLabel(r.health)}</span>
                            </div>
                        </div>`;
                },
            },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
        });
    }

    // --- Service Category Trend (rendered on Overview) -------------------
    async function loadCategoryTrend() {
        try {
            const params = { ...window.MD_FILTERS.toParams(), months: 6 };
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.service_category_trend, params);
            if (resp && resp.data) renderCategoryTrend(resp.data);
        } catch (e) { console.error('category-trend fetch failed', e); }
    }

    function renderCategoryTrend(data) {
        const months = data.months || [];
        const cats = data.categories || [];
        const otherMembers = data.other_members || [];
        if (months.length === 0 || cats.length === 0) {
            renderCategoryMatrix([], []);
            renderOtherFootnote([]);
            return;
        }

        const seriesData = cats.map((c, idx) => ({
            id: c.id,
            name: c.name,
            color: c.id === 'other' ? OTHER_COLOR : CATEGORY_PALETTE[idx % (CATEGORY_PALETTE.length - 1)],
            values: (data.series[c.id] || []).map((v) => Number(v) || 0),
        }));

        renderCategoryMatrix(seriesData, months);
        renderOtherFootnote(otherMembers);

        const series = seriesData.map((s) => ({ name: s.name, data: s.values }));

        window.MD_CHARTS.render('mdCategoryTrendChart', {
            chart: {
                type: 'area',
                height: 360,
                stacked: true,
                toolbar: { show: false },
                animations: { enabled: false },
            },
            series,
            colors: seriesData.map((s) => s.color),
            xaxis: {
                categories: months,
                labels: { rotate: -45, style: { fontSize: '11px', colors: '#6b7280' } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: { formatter: (v) => window.MD_CHARTS.currency(v), style: { colors: '#6b7280' } },
            },
            stroke: { width: 1.5, curve: 'smooth' },
            dataLabels: { enabled: false },
            fill: { type: 'solid', opacity: 0.85 },
            markers: { size: 0, hover: { size: 6 } },
            tooltip: {
                enabled: true,
                shared: true,
                intersect: false,
                custom: ({ series: dataSeries, dataPointIndex, w }) => {
                    const monthLabel = formatMonthFull(months[dataPointIndex] || '');
                    const names = w.globals.seriesNames || [];
                    const colors = w.globals.colors || [];

                    const items = names.map((name, i) => ({
                        name,
                        color: colors[i],
                        value: Number(dataSeries[i]?.[dataPointIndex]) || 0,
                    })).filter((it) => it.value > 0)
                       .sort((a, b) => b.value - a.value);

                    const total = items.reduce((s, it) => s + it.value, 0);

                    const rows = items.map((it) => `
                        <div class="md-tt-row">
                            <span class="md-tt-dot" style="background:${it.color}"></span>
                            <span class="md-tt-label">${escapeHtml(it.name)}</span>
                            <span class="md-tt-value">${window.MD_CHARTS.currency(it.value)}</span>
                        </div>`).join('');

                    return `
                        <div class="md-tt">
                            <div class="md-tt-title">${escapeHtml(monthLabel)}</div>
                            ${rows}
                            <div class="md-tt-foot"><span>Total</span><span>${window.MD_CHARTS.currency(total)}</span></div>
                        </div>`;
                },
            },
            legend: { show: false },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
        });
    }

    function renderCategoryMatrix(seriesData, months) {
        const host = document.getElementById('mdCategoryTrendSummary');
        if (!host) return;
        if (seriesData.length === 0 || months.length === 0) {
            host.innerHTML = '<div class="md-empty">No category data in range</div>';
            return;
        }

        const enriched = seriesData.map((s) => ({
            ...s,
            total: s.values.reduce((sum, v) => sum + v, 0),
        }));
        const named = enriched.filter((s) => s.id !== 'other').sort((a, b) => b.total - a.total);
        const other = enriched.filter((s) => s.id === 'other');
        const sorted = named.concat(other);

        const monthTotals = months.map((_, i) => sorted.reduce((sum, s) => sum + (s.values[i] || 0), 0));
        const grandTotal = sorted.reduce((sum, s) => sum + s.total, 0);

        const monthHeaders = months.map((m) => `<th class="md-num">${escapeHtml(shortMonth(m))}</th>`).join('');
        const bodyRows = sorted.map((s) => {
            const cells = s.values.map((v) => `<td class="md-num">${v > 0 ? window.MD_CHARTS.currency(v) : '—'}</td>`).join('');
            const share = grandTotal > 0 ? ((s.total / grandTotal) * 100).toFixed(1) : '0.0';
            return `
                <tr>
                    <td class="md-cat-name"><span class="md-cat-swatch" style="background:${s.color}"></span>${escapeHtml(s.name)}</td>
                    ${cells}
                    <td class="md-num md-cat-row-total">${window.MD_CHARTS.currency(s.total)}</td>
                    <td class="md-num md-cat-row-share">${share}%</td>
                </tr>`;
        }).join('');

        const totalCells = monthTotals.map((v) => `<td class="md-num">${window.MD_CHARTS.currency(v)}</td>`).join('');
        const footerRow = `
            <tr class="md-cat-grand">
                <td class="md-cat-name">Total</td>
                ${totalCells}
                <td class="md-num">${window.MD_CHARTS.currency(grandTotal)}</td>
                <td class="md-num">100%</td>
            </tr>`;

        host.innerHTML = `
            <div class="md-cat-summary-head">
                <span class="md-cat-summary-hint">All months × categories below — every cell shows revenue for that month</span>
            </div>
            <div class="md-cat-matrix-wrap">
                <table class="md-cat-matrix">
                    <thead>
                        <tr>
                            <th>Category</th>
                            ${monthHeaders}
                            <th class="md-num">Total</th>
                            <th class="md-num">Share</th>
                        </tr>
                    </thead>
                    <tbody>${bodyRows}</tbody>
                    <tfoot>${footerRow}</tfoot>
                </table>
            </div>`;
    }

    function renderOtherFootnote(members) {
        const host = document.getElementById('mdCategoryTrendOther');
        if (!host) return;
        if (!members || members.length === 0) {
            host.innerHTML = '';
            return;
        }
        const sorted = members.slice().sort((a, b) => (b.total || 0) - (a.total || 0));
        const items = sorted.map((m) => {
            const label = escapeHtml(m.name);
            const value = window.MD_CHARTS.currency(m.total || 0);
            return `<li><span class="md-other-name">${label}</span><span class="md-other-value">${value}</span></li>`;
        }).join('');
        host.innerHTML = `
            <div class="md-other-foot">
                <span class="md-other-swatch" style="background:${OTHER_COLOR}"></span>
                <span class="md-other-head">Other includes ${sorted.length} smaller categor${sorted.length === 1 ? 'y' : 'ies'}:</span>
                <ul class="md-other-list">${items}</ul>
            </div>`;
    }

    function shortMonth(yyyymm) {
        const parts = String(yyyymm).split('-');
        if (parts.length !== 2) return yyyymm;
        const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const mIdx = Math.max(0, Math.min(11, parseInt(parts[1], 10) - 1));
        return monthNames[mIdx] + ' ' + parts[0].slice(2);
    }

    function formatMonthFull(yyyymm) {
        const parts = String(yyyymm).split('-');
        if (parts.length !== 2) return yyyymm;
        const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const mIdx = Math.max(0, Math.min(11, parseInt(parts[1], 10) - 1));
        return monthNames[mIdx] + ' ' + parts[0];
    }

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

    // Expose for overview.js (Branch Leaderboard, New-vs-Returning, and
    // Service Category Trend all live on Overview now — this file remains
    // the single owner of the rendering code). View-toggle wiring uses the
    // shared MD_UI.wireViewToggle helper in shell.js.
    window.MD_BRANCH_WIDGETS = {
        loadLeaderboard,
        loadNewReturning,
        loadCategoryTrend,
        wireLeaderboardSort,
    };
})();
