/**
 * Overview section renderer. Subscribes to filter changes + section-ready.
 * Fetches the composite overview payload (stats + revenue_trend), today's
 * activities feed, and cross-section widgets owned by branches.js.
 */
(function () {
    'use strict';

    let todayActivitiesCursor = null;

    async function load() {
        const params = window.MD_FILTERS.toParams();
        try {
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.overview, params);
            if (resp && resp.data) {
                renderStats(resp.data);
                renderCentreSales(resp.data.centre_sales);
                renderSalesDeltas(resp.data.sales_deltas);
            }
        } catch (e) { console.error('overview fetch failed', e); }

        loadLeadGenderFunnel();
        loadLeadServiceInterest();
        loadGenderRevenue();
        loadAvgValues();
        loadTodayActivities();

        // Branch Leaderboard, New-vs-Returning and Service Category Trend
        // panels live on Overview now, but branches.js still owns the
        // rendering code — call through it.
        window.MD_BRANCH_WIDGETS?.loadLeaderboard();
        window.MD_BRANCH_WIDGETS?.loadNewReturning();
        window.MD_BRANCH_WIDGETS?.loadCategoryTrend();

        // Retention cohorts + revenue concentration — owned by patients.js.
        window.MD_PATIENT_WIDGETS?.loadCohort();
        window.MD_PATIENT_WIDGETS?.loadConcentration();
    }

    function renderStats(data) {
        const s = data.stats || {};
        setText('mdStatsSales', window.MD_CHARTS.currencyFull(s.sales));
        setText('mdStatsRevenue', window.MD_CHARTS.currencyFull(s.revenue));
        setText('mdStatsConsultations', (s.consultations_arrived || 0) + ' / ' + (s.consultations_total || 0));
        setText('mdStatsTreatments', (s.treatments_arrived || 0) + ' / ' + (s.treatments_total || 0));
        setText('mdStatsPeriodLabel', periodLabel());
    }

    function renderCentreSales(data) {
        const host = document.getElementById('mdCentreSalesList');
        if (!host) return;
        const rows = (data && data.rows) || [];
        if (rows.length === 0) {
            host.innerHTML = '<li class="md-empty">No centre sales in range</li>';
            return;
        }
        const total = (data && data.total) || 0;
        // Bar widths normalised to the leader so every centre has a visible
        // bar even when shares are small (a 13% leader still shows as 100%
        // bar width; the numeric share-of-total is shown in the text).
        const max = rows.reduce((m, r) => Math.max(m, r.sales), 0) || 1;
        host.innerHTML = rows.map((r, i) => {
            const share = total > 0 ? ((r.sales / total) * 100) : 0;
            const barPct = Math.max(2, (r.sales / max) * 100);
            return '<li class="md-cs-row">'
                + '<div class="md-cs-head">'
                +   '<span class="md-cs-rank">' + (i + 1) + '</span>'
                +   '<span class="md-cs-name" title="' + escapeHtml(r.branch_name) + '">' + escapeHtml(r.branch_name) + '</span>'
                +   '<span class="md-cs-value">' + window.MD_CHARTS.currencyFull(r.sales) + '</span>'
                +   '<span class="md-cs-share">' + share.toFixed(1) + '%</span>'
                + '</div>'
                + '<div class="md-cs-track"><div class="md-cs-bar" style="width:' + barPct.toFixed(2) + '%"></div></div>'
                + '</li>';
        }).join('');
    }

    function renderSalesDeltas(deltas) {
        if (!deltas) return;
        setText('mdDeltaPeriodLabel', periodLabel());
        setText('mdDeltaCurrent', window.MD_CHARTS.currencyFull(deltas.current));
        renderDelta('mdDeltaMom', deltas.mom);
        renderDelta('mdDeltaYoy', deltas.yoy);
        const momLabel = deltas.mom ? '<span class="md-delta-tag">MoM</span>' + formatRangeLabel(deltas.mom.from, deltas.mom.to) : 'prev period';
        const yoyLabel = deltas.yoy ? '<span class="md-delta-tag">YoY</span>' + formatRangeLabel(deltas.yoy.from, deltas.yoy.to) : 'last year';
        const momEl = document.getElementById('mdDeltaMomLabel');
        const yoyEl = document.getElementById('mdDeltaYoyLabel');
        if (momEl) momEl.innerHTML = momLabel;
        if (yoyEl) yoyEl.innerHTML = yoyLabel;
        renderDeltaBars(deltas);
    }

    // Three tiny vertical bars (Now · MoM · YoY) alongside the headline —
    // heights normalized to the tallest value so you can eye-ball which
    // period is biggest without reading the numbers.
    function renderDeltaBars(deltas) {
        const host = document.getElementById('mdDeltaBars');
        if (!host) return;
        const cur = deltas.current || 0;
        const mom = (deltas.mom && deltas.mom.previous) || 0;
        const yoy = (deltas.yoy && deltas.yoy.previous) || 0;
        const max = Math.max(cur, mom, yoy, 1);
        const pct = (v) => Math.max(6, (v / max) * 100).toFixed(1);
        const fmt = window.MD_CHARTS.currencyFull;

        // Bar with a letter-per-line caption stacked below it
        // (N\nO\nW form). Keeps each group column narrow while still
        // showing full labels.
        const stackLetters = (label) => label.split('').map((c) => '<span>' + c + '</span>').join('');
        const cell = (cls, h, label, tip) => ''
            + '<div class="md-delta-bar-group">'
            +   '<div class="md-delta-bar-slot">'
            +     '<div class="md-delta-bar ' + cls + '" style="height:' + h + '%" title="' + tip + '"></div>'
            +   '</div>'
            +   '<span class="md-delta-bar-cap">' + stackLetters(label) + '</span>'
            + '</div>';

        host.innerHTML = ''
            + cell('md-delta-bar-cur',  pct(cur), 'Now', 'Now: ' + fmt(cur))
            + cell('md-delta-bar-base', pct(mom), 'MoM', 'MoM baseline: ' + fmt(mom))
            + cell('md-delta-bar-base', pct(yoy), 'YoY', 'YoY baseline: ' + fmt(yoy));
    }

    // Compact range label from YYYY-MM-DD strings. Uses 2-digit year ('25)
    // when the range isn't in the current calendar year, to keep the label
    // tight inside the Sales Momentum tile.
    // Same month same year:    "Mar 1–15"
    // Same year, diff months:  "Feb 20 – Mar 15"
    // Different year:          "Apr 1–15 '25"
    function formatRangeLabel(fromStr, toStr) {
        if (!fromStr || !toStr) return '';
        const [fy, fm, fd] = fromStr.split('-').map(Number);
        const [ty, tm, td] = toStr.split('-').map(Number);
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const thisYear = new Date().getFullYear();
        const shortYear = (y) => "'" + String(y).slice(-2);

        if (fy === ty && fm === tm) {
            const base = months[fm - 1] + ' ' + fd + '–' + td;
            return fy === thisYear ? base : base + ' ' + shortYear(fy);
        }
        if (fy === ty) {
            const base = months[fm - 1] + ' ' + fd + ' – ' + months[tm - 1] + ' ' + td;
            return fy === thisYear ? base : base + ' ' + shortYear(fy);
        }
        return months[fm - 1] + ' ' + fd + ' ' + shortYear(fy) + ' – ' + months[tm - 1] + ' ' + td + ' ' + shortYear(ty);
    }

    function renderDelta(elId, delta) {
        const el = document.getElementById(elId);
        if (!el) return;
        const pct = delta && delta.delta_pct;
        if (pct === null || pct === undefined) {
            el.textContent = 'n/a';
            el.className = 'md-delta-value md-delta-neutral';
            el.title = 'No baseline sales to compare against';
            return;
        }
        const sign = pct > 0 ? '+' : '';
        el.textContent = sign + pct.toFixed(1) + '%';
        el.className = 'md-delta-value ' + (pct > 0 ? 'md-delta-up' : pct < 0 ? 'md-delta-down' : 'md-delta-neutral');
        el.title = 'Previous: ' + window.MD_CHARTS.currencyFull(delta.previous);
    }

    // ATV (txn-level average, trailing 12mo) and ACV (converted-patient value,
    // trailing 12mo) share one sparkline and one pair of stat rows. Fetched
    // in parallel; sparkline renders once both are available (ApexCharts
    // doesn't merge series after render, and a single .render() avoids
    // flicker).
    async function loadAvgValues() {
        const params = { branches: window.MD_FILTERS.state.branches };
        try {
            const [atvResp, acvResp] = await Promise.all([
                window.MD_API.get(window.MD_CONFIG.routes.avg_transaction_value, params).catch(() => null),
                window.MD_API.get(window.MD_CONFIG.routes.avg_conversion_value, params).catch(() => null),
            ]);
            renderAvgValues(atvResp && atvResp.data, acvResp && acvResp.data);
        } catch (e) { console.error('avg-values fetch failed', e); }
    }

    function renderAvgValues(atv, acv) {
        renderAvgHalf({
            data: atv,
            valueField: 'atv',
            chartId: 'mdAtvSparkline',
            headlineId: 'mdAtvHeadline',
            deltaId: 'mdAtvDelta',
            deltaField: 'prior3_delta_pct',
            deltaSuffix: 'vs 3mo',
            color: '#6366f1',
        });
        renderAvgHalf({
            data: acv,
            valueField: 'acv',
            chartId: 'mdAcvSparkline',
            headlineId: 'mdAcvHeadline',
            deltaId: 'mdAcvDelta',
            deltaField: 'prior3_delta_pct',
            deltaSuffix: 'vs 3mo',
            color: '#10b981',
        });
    }

    // Renders one of the two split-halves (ATV or ACV). Same sparkline shape
    // for both — only the value field, colour, and delta semantics differ.
    function renderAvgHalf({ data, valueField, chartId, headlineId, deltaId, deltaField, deltaSuffix, color }) {
        const series = (data && data.series) || [];
        const latest = data && data.latest;
        const latestValue = latest ? latest[valueField] : 0;

        if (!latest || !latestValue) {
            setText(headlineId, '—');
            setText(deltaId, '');
            const host = document.getElementById(chartId);
            if (host) host.innerHTML = '';
            return;
        }

        setText(headlineId, window.MD_CHARTS.currencyFull(latestValue));
        renderAvgDelta(deltaId, data[deltaField], deltaSuffix);

        const months = series.map((p) => p.month);
        const values = series.map((p) => p[valueField] || 0);

        window.MD_CHARTS.render(chartId, {
            chart: { type: 'line', height: 36, sparkline: { enabled: true } },
            series: [{ data: values }],
            xaxis: { categories: months },
            stroke: { width: 2, curve: 'smooth' },
            colors: [color],
            tooltip: {
                custom: ({ series: s, dataPointIndex }) => {
                    const month = months[dataPointIndex] || '';
                    const value = window.MD_CHARTS.currencyFull(s[0][dataPointIndex]);
                    return `<div class="md-tt md-tt-inline">
                        <span class="md-tt-dot" style="background:${color}"></span>
                        <span class="md-tt-label">${month}</span>
                        <span class="md-tt-value">${value}</span>
                    </div>`;
                },
            },
        });
    }

    function renderAvgDelta(elId, pct, suffix) {
        const el = document.getElementById(elId);
        if (!el) return;
        if (pct === null || pct === undefined) {
            el.textContent = '';
            return;
        }
        const sign = pct > 0 ? '+' : '';
        el.textContent = sign + pct.toFixed(1) + '% ' + suffix;
        el.className = 'md-avg-delta ' + (pct > 0 ? 'md-delta-up' : pct < 0 ? 'md-delta-down' : 'md-delta-neutral');
    }

    // Populate the entire half-width deep-dive panel from the enriched
    // lead_gender_funnel payload: funnel view (hero + funnels + leaks +
    // trend) AND rescue / economics toggle views.
    function hydrateDeepDivePanel(data) {
        if (!data) return;
        setText('mdLeadDeepPeriodLabel', periodLabel());
        renderDdHero(data);
        renderDdFunnels(data);
        renderDdLeaks(data);
        renderDdTrend(data.trend || { series: [] });
        renderDdRescue(data.rescue || null);
        renderDdEconomics(data.economics || null);
        renderDdCategories((data.economics && data.economics.by_category) || []);
    }

    // Verdict hero — the one sentence a CEO should leave with.
    function renderDdHero(data) {
        const host = document.getElementById('mdDdHero');
        if (!host) return;
        const fGen = (data.generated && data.generated.female) || 0;
        const mGen = (data.generated && data.generated.male) || 0;
        const fConv = (data.converted && data.converted.female) || 0;
        const mConv = (data.converted && data.converted.male) || 0;
        const fRate = fGen > 0 ? (fConv / fGen) * 100 : 0;
        const mRate = mGen > 0 ? (mConv / mGen) * 100 : 0;

        if (fRate === 0 && mRate === 0) {
            host.innerHTML = '<div class="md-dd-hero-empty">No lead conversions in range</div>';
            return;
        }

        let winner, loser, ratio;
        if (fRate >= mRate) {
            winner = { label: 'Female', rate: fRate, cls: 'md-dd-f-ink' };
            loser = { label: 'Male', rate: mRate, cls: 'md-dd-m-ink' };
            ratio = mRate > 0 ? (fRate / mRate) : null;
        } else {
            winner = { label: 'Male', rate: mRate, cls: 'md-dd-m-ink' };
            loser = { label: 'Female', rate: fRate, cls: 'md-dd-f-ink' };
            ratio = fRate > 0 ? (mRate / fRate) : null;
        }
        const verdict = ratio && ratio >= 1.1
            ? '<strong class="' + winner.cls + '">' + winner.label + '</strong> leads convert <strong>' + ratio.toFixed(1) + '×</strong> better'
            : 'Conversion roughly on par across genders';

        host.innerHTML = ''
            + '<div class="md-dd-hero-verdict">' + verdict + '</div>'
            + '<div class="md-dd-hero-stats">'
            +   '<div class="md-dd-hero-stat ' + winner.cls + '"><span class="md-dd-hero-num">' + winner.rate.toFixed(1) + '%</span><span class="md-dd-hero-lbl">' + winner.label.charAt(0) + ' conversion</span></div>'
            +   '<div class="md-dd-hero-vs">vs</div>'
            +   '<div class="md-dd-hero-stat ' + loser.cls + '"><span class="md-dd-hero-num">' + loser.rate.toFixed(1) + '%</span><span class="md-dd-hero-lbl">' + loser.label.charAt(0) + ' conversion</span></div>'
            + '</div>';
    }

    // Side-by-side funnels — one per gender. Each bar's width is relative
    // to that gender's "Leads" total (so both funnels visually start at 100%
    // width and tell the retention-shape story at a glance).
    function renderDdFunnels(funnel) {
        const host = document.getElementById('mdDdFunnels');
        if (!host) return;
        const stages = [
            { key: 'generated', label: 'Leads' },
            { key: 'booked', label: 'Booked' },
            { key: 'arrived', label: 'Arrived' },
            { key: 'converted', label: 'Converted' },
        ];
        const col = (gender, label, className) => {
            const top = (funnel.generated && funnel.generated[gender]) || 0;
            if (top === 0) {
                return '<div class="md-dd-col"><div class="md-dd-col-head ' + className + '">' + label + '</div><div class="md-dd-empty">no data</div></div>';
            }
            const rows = stages.map((s) => {
                const v = (funnel[s.key] && funnel[s.key][gender]) || 0;
                const pct = (v / top) * 100;
                const ratePct = s.key === 'generated' ? '100%' : pct.toFixed(1) + '%';
                return ''
                    + '<div class="md-dd-row">'
                    +   '<div class="md-dd-row-head"><span>' + s.label + '</span><span class="md-dd-row-pct">' + ratePct + '</span></div>'
                    +   '<div class="md-dd-bar-wrap"><div class="md-dd-bar ' + className + '" style="width:' + Math.max(3, pct).toFixed(2) + '%"></div></div>'
                    +   '<div class="md-dd-row-count">' + v.toLocaleString() + '</div>'
                    + '</div>';
            }).join('');
            return '<div class="md-dd-col"><div class="md-dd-col-head ' + className + '">' + label + ' · ' + top.toLocaleString() + '</div>' + rows + '</div>';
        };
        host.innerHTML = col('female', 'Female', 'md-dd-f') + col('male', 'Male', 'md-dd-m');
    }

    // Detect the biggest retention drop per gender and render callouts.
    function renderDdLeaks(funnel) {
        const host = document.getElementById('mdDdLeaks');
        if (!host) return;

        const findLeak = (gender) => {
            const stages = ['generated', 'booked', 'arrived', 'converted'];
            let worst = null;
            for (let i = 1; i < stages.length; i++) {
                const prev = (funnel[stages[i - 1]] && funnel[stages[i - 1]][gender]) || 0;
                const cur = (funnel[stages[i]] && funnel[stages[i]][gender]) || 0;
                if (prev === 0) continue;
                const retention = (cur / prev) * 100;
                const drop = 100 - retention;
                if (!worst || drop > worst.drop) {
                    worst = {
                        from: stages[i - 1], to: stages[i],
                        retention: retention, drop: drop,
                    };
                }
            }
            return worst;
        };

        const label = { generated: 'Leads', booked: 'Booked', arrived: 'Arrived', converted: 'Converted' };
        const fLeak = findLeak('female');
        const mLeak = findLeak('male');

        const card = (gender, leak, className) => {
            if (!leak) return '<div class="md-dd-leak md-dd-leak-empty">No ' + gender + ' data</div>';
            return ''
                + '<div class="md-dd-leak ' + className + '">'
                +   '<div class="md-dd-leak-head">' + gender + ' leak</div>'
                +   '<div class="md-dd-leak-body">'
                +     '<strong>' + label[leak.from] + ' → ' + label[leak.to] + '</strong>'
                +     '<span class="md-dd-leak-pct">' + leak.retention.toFixed(1) + '% retained</span>'
                +   '</div>'
                + '</div>';
        };
        host.innerHTML = card('Female', fLeak, 'md-dd-leak-f') + card('Male', mLeak, 'md-dd-leak-m');
    }

    function renderDdSources(sources) {
        const host = document.getElementById('mdDdSources');
        if (!host) return;
        if (sources.length === 0) {
            host.innerHTML = '<div class="md-dd-empty">No lead source data</div>';
            return;
        }
        // Scale rates to max visible on bars so small differences stay readable.
        const maxRate = sources.reduce((m, s) => Math.max(m, s.female.rate, s.male.rate), 0.5);

        host.innerHTML = sources.map((s) => {
            const fW = Math.min(100, (s.female.rate / maxRate) * 100);
            const mW = Math.min(100, (s.male.rate / maxRate) * 100);
            return ''
                + '<div class="md-dd-source">'
                +   '<div class="md-dd-source-head">'
                +     '<span class="md-dd-source-name" title="' + escapeHtml(s.source_name) + '">' + escapeHtml(s.source_name) + '</span>'
                +     '<span class="md-dd-source-total">' + s.total.toLocaleString() + ' leads</span>'
                +   '</div>'
                +   '<div class="md-dd-source-row">'
                +     '<span class="md-dd-source-label md-dd-f-ink">F</span>'
                +     '<div class="md-dd-source-bar-wrap"><div class="md-dd-source-bar md-dd-f" style="width:' + fW.toFixed(1) + '%"></div></div>'
                +     '<span class="md-dd-source-rate">' + s.female.rate.toFixed(1) + '%</span>'
                +     '<span class="md-dd-source-abs">(' + s.female.conv + '/' + s.female.gen + ')</span>'
                +   '</div>'
                +   '<div class="md-dd-source-row">'
                +     '<span class="md-dd-source-label md-dd-m-ink">M</span>'
                +     '<div class="md-dd-source-bar-wrap"><div class="md-dd-source-bar md-dd-m" style="width:' + mW.toFixed(1) + '%"></div></div>'
                +     '<span class="md-dd-source-rate">' + s.male.rate.toFixed(1) + '%</span>'
                +     '<span class="md-dd-source-abs">(' + s.male.conv + '/' + s.male.gen + ')</span>'
                +   '</div>'
                + '</div>';
        }).join('');
    }

    // RESCUE view — rupee potential of stuck leads. The anchor metric is
    // a big recoverable-revenue headline; supported by per-gender breakdown
    // and a stacked "iceberg" bar showing Converted / Stuck / Lost share.
    function renderDdRescue(rescue) {
        const host = document.getElementById('mdDdRescue');
        if (!host) return;
        if (!rescue || rescue.stuck_total === 0) {
            host.innerHTML = '<div class="md-dd-empty">No stuck leads in range</div>';
            return;
        }

        const fmt = window.MD_CHARTS.currencyFull;
        const f = rescue.female || {};
        const m = rescue.male || {};
        const rate = rescue.rescue_rate_pct;

        // Stacked bar: segments are F rescue potential, M rescue potential.
        const totalPotential = rescue.potential_total;
        const fShare = totalPotential > 0 ? (f.potential / totalPotential) * 100 : 0;
        const mShare = totalPotential > 0 ? (m.potential / totalPotential) * 100 : 0;

        host.innerHTML = ''
            + '<div class="md-dd-rescue-hero">'
            +   '<div class="md-dd-rescue-label">Recoverable revenue at risk</div>'
            +   '<div class="md-dd-rescue-amount">' + fmt(totalPotential) + '</div>'
            +   '<div class="md-dd-rescue-sub">'
            +     rescue.stuck_total.toLocaleString() + ' leads stuck in funnel · '
            +     '<span title="Conservative re-engagement success rate">' + rate.toFixed(0) + '% rescue rate</span>'
            +   '</div>'
            + '</div>'
            + '<div class="md-dd-rescue-bar">'
            +   '<div class="md-dd-rescue-seg md-dd-f" style="width:' + fShare.toFixed(1) + '%" title="Female: ' + fmt(f.potential) + '"></div>'
            +   '<div class="md-dd-rescue-seg md-dd-m" style="width:' + mShare.toFixed(1) + '%" title="Male: ' + fmt(m.potential) + '"></div>'
            + '</div>'
            + '<div class="md-dd-rescue-cols">'
            +   rescueCol('Female', f, 'md-dd-f')
            +   rescueCol('Male', m, 'md-dd-m')
            + '</div>'
            + '<div class="md-dd-rescue-note">'
            +   '<strong>How it\'s computed:</strong> stuck leads × avg revenue per converted lead × 20% rescue rate. '
            +   'Conservative estimate — actual recovery depends on re-engagement execution.'
            + '</div>';
    }

    function rescueCol(label, side, cls) {
        const fmt = window.MD_CHARTS.currencyFull;
        return ''
            + '<div class="md-dd-rescue-col">'
            +   '<div class="md-dd-rescue-col-head ' + cls + '">' + label + ' · ' + fmt(side.potential || 0) + '</div>'
            +   '<div class="md-dd-rescue-row"><span>No-show after booking</span><strong>' + (side.stuck_booked || 0).toLocaleString() + ' leads</strong></div>'
            +   '<div class="md-dd-rescue-row"><span>Arrived, didn\'t convert</span><strong>' + (side.stuck_arrived || 0).toLocaleString() + ' leads</strong></div>'
            +   '<div class="md-dd-rescue-row md-dd-rescue-row-muted"><span>Avg revenue / conversion</span><strong>' + fmt(side.rev_per_conv || 0) + '</strong></div>'
            + '</div>';
    }

    // ECONOMICS view — revenue per lead by gender. Combines conversion rate
    // and avg deal size into one efficiency metric: "what's each lead worth
    // to us generated?"
    function renderDdEconomics(eco) {
        const host = document.getElementById('mdDdEconomics');
        if (!host) return;
        if (!eco) {
            host.innerHTML = '<div class="md-dd-empty">No economics data</div>';
            return;
        }

        const f = eco.female || {};
        const m = eco.male || {};
        const fRate = f.leads > 0 ? (f.conversions / f.leads) * 100 : 0;
        const mRate = m.leads > 0 ? (m.conversions / m.leads) * 100 : 0;

        // Two-sided verdict: efficiency (who generates more per lead) AND
        // deal-size (who buys bigger when they close). Single side is often
        // misleading — male revenue can exceed female revenue overall while
        // female RPL is higher, because the two metrics measure different things.
        const verdict = buildEconomicsVerdict(f, m);

        // Maturity caveat: leads created in the last `maturity_days` are
        // excluded from category rate/RPL denominators — surface this so
        // users know why recent leads don't appear in the breakdown.
        const maturityDays = eco.maturity_days || 0;
        const maturityNote = maturityDays > 0
            ? '<div class="md-dd-maturity-note" role="note">Category rates & RPL exclude leads younger than ' + maturityDays + ' days (they haven\'t had time to close yet).</div>'
            : '';

        host.innerHTML = ''
            + (verdict ? '<div class="md-dd-econ-verdict" role="note">' + verdict + '</div>' : '')
            + '<div class="md-dd-econ-cards">'
            +   econCard('Female', f, fRate, 'md-dd-f', 'md-dd-f-ink')
            +   econCard('Male', m, mRate, 'md-dd-m', 'md-dd-m-ink')
            + '</div>'
            + maturityNote;
    }

    function buildEconomicsVerdict(f, m) {
        if (!(f.rev_per_lead > 0 && m.rev_per_lead > 0)) return '';

        const ratio = f.rev_per_lead / m.rev_per_lead;
        const dealRatio = (m.rev_per_conv > 0 && f.rev_per_conv > 0)
            ? (m.rev_per_conv / f.rev_per_conv) : null;

        let primary = '';
        if (ratio >= 1.1) {
            primary = '<strong class="md-dd-f-ink">Female</strong> leads generate <strong>' + ratio.toFixed(1) + '× more revenue per lead</strong>';
        } else if (ratio <= 0.9) {
            primary = '<strong class="md-dd-m-ink">Male</strong> leads generate <strong>' + (1 / ratio).toFixed(1) + '× more revenue per lead</strong>';
        } else {
            primary = 'Per-lead economics are on par across genders';
        }

        let secondary = '';
        if (dealRatio !== null) {
            if (dealRatio >= 1.3) {
                secondary = ' — but <strong class="md-dd-m-ink">male</strong> conversions are ~' + dealRatio.toFixed(1) + '× larger each.';
            } else if (dealRatio <= 0.77) {
                secondary = ' — and <strong class="md-dd-f-ink">female</strong> conversions are ~' + (1 / dealRatio).toFixed(1) + '× larger each.';
            } else {
                secondary = '.';
            }
        }

        return primary + secondary;
    }

    // Render a prior-period delta chip (↑ 4.2% / ↓ 2.1% / flat / n/a).
    function deltaChip(pct) {
        if (pct === null || pct === undefined) {
            return '<span class="md-dd-delta md-dd-delta--na" title="No prior-period baseline">n/a</span>';
        }
        if (pct >= 1) {
            return '<span class="md-dd-delta md-dd-delta--up">↑ ' + pct.toFixed(1) + '%</span>';
        }
        if (pct <= -1) {
            return '<span class="md-dd-delta md-dd-delta--down">↓ ' + Math.abs(pct).toFixed(1) + '%</span>';
        }
        return '<span class="md-dd-delta md-dd-delta--flat">flat</span>';
    }

    // Gender card — hero rev/lead + 4 structured detail rows so the
    // components behind the headline are scannable at a glance.
    function econCard(label, side, rate, cls, inkCls) {
        const fmt = window.MD_CHARTS.currencyFull;
        const pill = (label === 'Female' ? '♀' : '♂') + ' ' + label;
        const leads = (side.leads || 0).toLocaleString();
        const converted = (side.conversions || 0).toLocaleString();
        const rplDelta = side.delta ? deltaChip(side.delta.rev_per_lead_pct) : '';
        return ''
            + '<div class="md-dd-econ-card ' + cls + '" role="group" aria-label="' + label + ' economics">'
            +   '<div class="md-dd-econ-head ' + inkCls + '"><span class="md-gender-pill">' + pill + '</span></div>'
            +   '<div class="md-dd-econ-hero">'
            +     '<span class="md-dd-econ-amount">' + fmt(side.rev_per_lead || 0) + '</span>'
            +     '<span class="md-dd-econ-unit">per lead · ' + leads + ' leads ' + rplDelta + '</span>'
            +   '</div>'
            +   '<dl class="md-dd-econ-facts">'
            +     '<dt>Converted</dt><dd>' + converted + ' <em>· ' + rate.toFixed(1) + '% rate</em></dd>'
            +     '<dt>Revenue</dt><dd>' + fmt(side.revenue || 0) + '</dd>'
            +     '<dt>Value per conversion</dt><dd>' + fmt(side.rev_per_conv || 0) + '</dd>'
            +   '</dl>'
            + '</div>';
    }

    // 12-week F/M conversion rate trend (dual-line chart in the Funnel view).
    function renderDdTrend(trend) {
        const series = (trend && trend.series) || [];
        if (series.length === 0) return;
        const weeks = series.map((p) => p.week_start);
        const fSeries = series.map((p) => p.f_rate);
        const mSeries = series.map((p) => p.m_rate);

        window.MD_CHARTS.render('mdDdTrendChart', {
            chart: { type: 'line', height: 120, toolbar: { show: false }, zoom: { enabled: false } },
            series: [
                { name: 'Female %', data: fSeries },
                { name: 'Male %', data: mSeries },
            ],
            xaxis: {
                categories: weeks,
                labels: {
                    rotate: 0, hideOverlappingLabels: true,
                    style: { fontSize: '9px', colors: '#7b7890' },
                    formatter: (v) => {
                        const d = new Date(v + 'T00:00:00');
                        return (d.getMonth() + 1) + '/' + d.getDate();
                    },
                },
                tickAmount: 6,
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                labels: { formatter: (v) => v.toFixed(0) + '%', style: { fontSize: '9px', colors: '#7b7890' } },
                min: 0,
            },
            stroke: { width: 2, curve: 'smooth' },
            markers: { size: 3 },
            colors: ['#f472b6', '#60a5fa'],
            dataLabels: { enabled: false },
            legend: { show: true, position: 'top', horizontalAlign: 'right', fontSize: '10px', markers: { width: 8, height: 8, radius: 2 } },
            grid: { strokeDashArray: 3, borderColor: '#ece9f2' },
            tooltip: {
                y: { formatter: (v) => v.toFixed(1) + '%' },
                x: { formatter: (_, ctx) => 'Week of ' + (weeks[ctx.dataPointIndex] || '') },
            },
        });
    }

    // Category-level F vs M breakdown — renders either conversion rate or
    // revenue-per-lead bars depending on the active metric toggle.
    //
    // Low-volume services (<MIN_LEADS total) are collapsed into a
    // summary footer — a 0% vs 0% or 0 RPL vs 0 RPL row on 1-2 leads is
    // noise, not signal.
    let _ddCatData = null;
    let _ddCatMetric = 'rate';

    function renderDdCategories(categories) {
        _ddCatData = categories;
        renderDdCategoriesMetric();
        wireDdCatMetricToggle();
    }

    function renderDdCategoriesMetric() {
        const host = document.getElementById('mdDdCategories');
        if (!host) return;
        const categories = _ddCatData;
        if (!categories || categories.length === 0) {
            host.innerHTML = '<div class="md-dd-empty">No category data</div>';
            return;
        }

        const MIN_LEADS = 20;
        const significant = [];
        let lowVolume = 0;
        categories.forEach((c) => {
            const totalLeads = (c.female.leads || 0) + (c.male.leads || 0);
            if (totalLeads >= MIN_LEADS) { significant.push(c); } else { lowVolume++; }
        });

        if (significant.length === 0) {
            host.innerHTML = '<div class="md-dd-empty">No high-volume categories in range</div>';
            return;
        }

        const metric = _ddCatMetric; // 'rate' | 'rpl'
        const getF = metric === 'rpl' ? (c) => c.female.rev_per_lead || 0 : (c) => c.female.rate || 0;
        const getM = metric === 'rpl' ? (c) => c.male.rev_per_lead || 0 : (c) => c.male.rate || 0;
        // In RPL mode, PKR 0 can mean either "no conversions at all" or
        // "converted but bought out-of-category" — surface the latter as
        // an inline "cross-sold" tag so users don't misread silent zeros.
        const fmtSide = metric === 'rpl'
            ? (v, side) => (side && side.cross_sold
                ? '<span class="md-dd-cross-sold" title="' + (side.conv || 0) + ' converted leads bought services outside this category">cross-sold</span>'
                : window.MD_CHARTS.currencyFull(v || 0))
            : (v) => (v || 0).toFixed(1) + '%';

        const max = significant.reduce((m, c) => Math.max(m, getF(c), getM(c)), 1);

        const winnerKey = metric === 'rpl' ? 'rpl_winner' : 'rate_winner';
        const gapKey = metric === 'rpl' ? 'rpl_gap_factor' : 'rate_gap_factor';

        const rowsHtml = significant.map((c) => {
            const fVal = getF(c);
            const mVal = getM(c);
            const fW = Math.max(2, (fVal / max) * 100);
            const mW = Math.max(2, (mVal / max) * 100);
            const badge = buildCategoryBadge(c, winnerKey, gapKey);
            const totalLeads = (c.female.leads || 0) + (c.male.leads || 0);
            const fDisplay = metric === 'rpl' ? fmtSide(fVal, c.female) : fmtSide(fVal);
            const mDisplay = metric === 'rpl' ? fmtSide(mVal, c.male) : fmtSide(mVal);
            const tip = metric === 'rpl'
                ? c.service_name
                    + ' — Female ' + (c.female.cross_sold ? 'cross-sold (' + c.female.conv + ' closed outside category)' : 'PKR ' + (fVal || 0).toLocaleString() + ' per lead')
                    + ' · Male ' + (c.male.cross_sold ? 'cross-sold (' + c.male.conv + ' closed outside category)' : 'PKR ' + (mVal || 0).toLocaleString() + ' per lead')
                : c.service_name
                    + ' — Female ' + c.female.conv + '/' + c.female.leads + ' (' + c.female.rate.toFixed(1) + '%)'
                    + ' · Male ' + c.male.conv + '/' + c.male.leads + ' (' + c.male.rate.toFixed(1) + '%)';
            const rowClass = 'md-dd-cat-line'
                + (c.low_confidence ? ' md-dd-cat-line--low-conf' : '')
                + (c.no_conversions ? ' md-dd-cat-line--no-conv' : '');
            const lowConfTag = c.low_confidence
                ? ' <span class="md-dd-cat-conf" title="Fewer than ' + 15 + ' leads in at least one gender — result may flip on a handful of new leads">low confidence</span>'
                : '';
            const unknownNote = (c.unknown_pct || 0) >= 5
                ? ' <span class="md-dd-cat-unknown" title="' + (c.unknown_leads || 0) + ' leads with unknown gender not shown in the F/M bars">' + c.unknown_pct.toFixed(0) + '% unknown</span>'
                : '';
            return ''
                + '<div class="' + rowClass + '" title="' + escapeHtml(tip) + '" role="row" aria-label="' + escapeHtml(tip) + '">'
                +   '<span class="md-dd-cat-name">' + escapeHtml(c.service_name) + ' <small class="md-dd-cat-vol">· ' + totalLeads.toLocaleString() + ' leads</small>' + lowConfTag + unknownNote + '</span>'
                +   '<span class="md-dd-cat-pair">'
                +     '<span class="md-dd-cat-mini-wrap"><span class="md-dd-cat-mini md-dd-f" style="width:' + fW.toFixed(1) + '%"></span></span>'
                +     '<span class="md-dd-cat-pct md-dd-f-ink">' + fDisplay + '</span>'
                +   '</span>'
                +   '<span class="md-dd-cat-pair">'
                +     '<span class="md-dd-cat-mini-wrap"><span class="md-dd-cat-mini md-dd-m" style="width:' + mW.toFixed(1) + '%"></span></span>'
                +     '<span class="md-dd-cat-pct md-dd-m-ink">' + mDisplay + '</span>'
                +   '</span>'
                +   badge
                + '</div>';
        }).join('');

        const hiddenHint = lowVolume > 0
            ? '<div class="md-dd-cat-lowvol" role="note">' + lowVolume + ' low-volume categor' + (lowVolume === 1 ? 'y' : 'ies') + ' hidden (&lt;' + MIN_LEADS + ' leads)</div>'
            : '';

        host.innerHTML = rowsHtml + hiddenHint;

        const sub = document.getElementById('mdDdCategoriesSub');
        if (sub) {
            sub.textContent = metric === 'rpl'
                ? 'Revenue per lead per category · in range'
                : 'Conversion rate per category · in range';
        }
    }

    // Wire the Rate/RPL sub-toggle. Idempotent — safe to call per render.
    function wireDdCatMetricToggle() {
        const group = document.getElementById('mdDdCatMetric');
        if (!group || group.dataset.mdWired === '1') return;
        group.dataset.mdWired = '1';
        const buttons = group.querySelectorAll('button[data-metric]');
        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const next = btn.dataset.metric;
                if (next === _ddCatMetric) return;
                _ddCatMetric = next;
                buttons.forEach((b) => {
                    const active = b.dataset.metric === next;
                    b.classList.toggle('is-active', active);
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                renderDdCategoriesMetric();
            });
        });
    }

    // Unified winner-chip format: "F · 7.4× higher" / "M · 2.3× higher"
    // / "Tied" / "No conversions". Accepts winner/gap keys so the same
    // chip works for both rate and RPL comparisons.
    //
    // Winner values:
    //   'female' / 'male' — one gender clearly ahead
    //   'tie'             — both non-zero and within 10% of each other
    //   'none'            — both zero (distinct from tie; shows "No conversions")
    function buildCategoryBadge(c, winnerKey, gapKey) {
        winnerKey = winnerKey || 'winner';
        gapKey = gapKey || 'gap_factor';
        const winner = c[winnerKey];
        const gap = c[gapKey];

        // In RPL mode, when BOTH sides converted but bought out-of-category,
        // show a dedicated "cross-sold" badge instead of a misleading winner.
        if (winnerKey === 'rpl_winner'
            && c.female && c.male
            && c.female.cross_sold && c.male.cross_sold) {
            return '<span class="md-dd-cat-badge md-dd-cat-badge-crosssold" title="Both genders converted on these enquiries but bought out-of-category" aria-label="Cross-sold">Cross-sold</span>';
        }

        if (winner === 'none' || c.no_conversions) {
            return '<span class="md-dd-cat-badge md-dd-cat-badge-none" aria-label="No conversions yet">No conversions</span>';
        }
        if (!winner || winner === 'tie') {
            return '<span class="md-dd-cat-badge md-dd-cat-badge-tie" aria-label="Tied">Tied</span>';
        }
        const isF = winner === 'female';
        const label = isF ? 'F' : 'M';
        const cls = isF ? 'md-dd-cat-badge-f' : 'md-dd-cat-badge-m';
        const ariaGender = isF ? 'Female' : 'Male';
        const gapText = gap ? gap.toFixed(1) + '× higher' : 'higher';
        const text = label + ' · ' + gapText;
        return '<span class="md-dd-cat-badge ' + cls + '" aria-label="' + ariaGender + ' ' + gapText + '">' + text + '</span>';
    }

    async function loadLeadServiceInterest() {
        try {
            const params = window.MD_FILTERS.toParams();
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.lead_service_interest, params);
            if (resp && resp.data) renderLeadServiceInterest(resp.data);
        } catch (e) { console.error('service-interest fetch failed', e); }
    }

    // Service Interest Mix tile — top 5 services leads enquire about, each
    // split as a Female/Male stacked bar. Compact tile format matches the
    // other 3 tiles in the top strip.
    function renderLeadServiceInterest(data) {
        setText('mdServiceInterestPeriodLabel', periodLabel());
        const host = document.getElementById('mdServiceInterestList');
        if (!host) return;
        const rows = (data && data.rows) || [];
        if (rows.length === 0) {
            host.innerHTML = '<div class="md-si-empty">No service interest data</div>';
            return;
        }

        const max = rows.reduce((mx, r) => Math.max(mx, r.total), 0) || 1;

        host.innerHTML = rows.map((r) => {
            const outerPct = Math.max(6, (r.total / max) * 100);
            const fPct = r.total > 0 ? (r.female / r.total) * 100 : 0;
            const mPct = r.total > 0 ? (r.male / r.total) * 100 : 0;
            const uPct = r.total > 0 ? (r.unknown / r.total) * 100 : 0;
            return ''
                + '<div class="md-si-row" title="' + escapeHtml(r.service_name) + ': ' + r.total + ' (F ' + r.female + ' · M ' + r.male + (r.unknown ? ' · U ' + r.unknown : '') + ')">'
                +   '<div class="md-si-name">' + escapeHtml(r.service_name) + '</div>'
                +   '<div class="md-si-bar-wrap">'
                +     '<div class="md-si-bar" style="width:' + outerPct.toFixed(2) + '%">'
                +       '<div class="md-si-seg md-dd-f" style="width:' + fPct.toFixed(2) + '%"></div>'
                +       '<div class="md-si-seg md-dd-m" style="width:' + mPct.toFixed(2) + '%"></div>'
                +       (uPct > 0 ? '<div class="md-si-seg md-dd-u" style="width:' + uPct.toFixed(2) + '%"></div>' : '')
                +     '</div>'
                +   '</div>'
                +   '<div class="md-si-count">' + r.total.toLocaleString() + '</div>'
                + '</div>';
        }).join('');
    }

    async function loadLeadGenderFunnel() {
        try {
            const params = { ...window.MD_FILTERS.toParams(), _t: Date.now() };
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.lead_gender_funnel, params, { noCache: true });
            if (resp && resp.data) hydrateDeepDivePanel(resp.data);
        } catch (e) { console.error('lead-funnel fetch failed', e); }
    }

    function renderLeadGenderFunnel(data) {
        setText('mdLeadFunnelPeriodLabel', periodLabel());
        const host = document.getElementById('mdLeadFunnel');
        const insight = document.getElementById('mdLeadInsight');
        if (!host) return;

        const stages = [
            { key: 'generated', label: 'Leads' },
            { key: 'booked', label: 'Booked' },
            { key: 'arrived', label: 'Arrived' },
            { key: 'converted', label: 'Converted' },
        ];

        const top = (data.generated && data.generated.total) || 0;
        if (top === 0) {
            host.innerHTML = '<div class="md-lead-empty">No leads in range</div>';
            if (insight) insight.textContent = '';
            return;
        }

        // Pre-compute each stage row (bar + count) and each transition
        // (stage-to-stage retention % per gender).
        const rows = stages.map((s) => data[s.key] || { male: 0, female: 0, unknown: 0, total: 0 });
        const barHtml = stages.map((s, i) => {
            const row = rows[i];
            const outerPct = (row.total / top) * 100;
            const fPct = row.total > 0 ? (row.female / row.total) * 100 : 0;
            const mPct = row.total > 0 ? (row.male / row.total) * 100 : 0;
            const uPct = row.total > 0 ? (row.unknown / row.total) * 100 : 0;
            const shareLabel = s.key === 'generated' ? '' : ' · ' + outerPct.toFixed(1) + '%';

            return ''
                + '<div class="md-lead-row" title="' + s.label + ': ' + row.total.toLocaleString() + ' (F ' + row.female + ' · M ' + row.male + (row.unknown ? ' · U ' + row.unknown : '') + ')">'
                +   '<div class="md-lead-label">' + s.label + '</div>'
                +   '<div class="md-lead-bar-wrap">'
                +     '<div class="md-lead-bar" style="width:' + outerPct.toFixed(2) + '%">'
                +       '<div class="md-lead-seg md-lead-f" style="width:' + fPct.toFixed(2) + '%"></div>'
                +       '<div class="md-lead-seg md-lead-m" style="width:' + mPct.toFixed(2) + '%"></div>'
                +       (uPct > 0 ? '<div class="md-lead-seg md-lead-u" style="width:' + uPct.toFixed(2) + '%"></div>' : '')
                +     '</div>'
                +   '</div>'
                +   '<div class="md-lead-count">'
                +     row.total.toLocaleString()
                +     '<span class="md-lead-share">' + shareLabel + '</span>'
                +   '</div>'
                + '</div>';
        });

        // Transitions: Leads→Booked, Booked→Arrived, Arrived→Converted.
        const transitions = [];
        for (let i = 1; i < rows.length; i++) {
            const prev = rows[i - 1];
            const cur = rows[i];
            const fRate = prev.female > 0 ? (cur.female / prev.female) * 100 : null;
            const mRate = prev.male > 0 ? (cur.male / prev.male) * 100 : null;
            transitions.push({
                label: stages[i - 1].label + ' → ' + stages[i].label,
                fRate, mRate,
                // gap lets us highlight the biggest M leak (or F leak) for the insight line.
                gap: (fRate !== null && mRate !== null) ? fRate - mRate : 0,
            });
        }

        // Interleave bars with transition badges.
        const parts = [];
        for (let i = 0; i < barHtml.length; i++) {
            parts.push(barHtml[i]);
            if (i < transitions.length) {
                const t = transitions[i];
                const f = t.fRate === null ? '—' : t.fRate.toFixed(0) + '%';
                const m = t.mRate === null ? '—' : t.mRate.toFixed(0) + '%';
                parts.push(
                    '<div class="md-lead-transition" title="' + t.label + '">'
                    +   '<span class="md-lead-tr-arrow">↓</span>'
                    +   '<span class="md-lead-ink-f">F ' + f + '</span>'
                    +   '<span class="md-lead-tr-sep">·</span>'
                    +   '<span class="md-lead-ink-m">M ' + m + '</span>'
                    + '</div>'
                );
            }
        }
        host.innerHTML = parts.join('');

        if (insight) {
            const fGen = rows[0].female;
            const mGen = rows[0].male;
            const fConv = rows[3].female;
            const mConv = rows[3].male;
            const fRate = fGen > 0 ? (fConv / fGen) * 100 : 0;
            const mRate = mGen > 0 ? (mConv / mGen) * 100 : 0;

            let multiplier = '';
            if (fRate > 0 && mRate > 0) {
                const ratio = fRate / mRate;
                if (ratio >= 1.05) multiplier = ' · <strong>F×' + ratio.toFixed(1) + '</strong> better';
                else if (ratio <= 0.95) multiplier = ' · <strong>M×' + (1/ratio).toFixed(1) + '</strong> better';
                else multiplier = ' · on par';
            }

            insight.innerHTML = ''
                + '<span class="md-lead-ink-f">F ' + fRate.toFixed(1) + '%</span>'
                + ' · '
                + '<span class="md-lead-ink-m">M ' + mRate.toFixed(1) + '%</span>'
                + multiplier;
        }
    }

    async function loadGenderRevenue() {
        try {
            const params = window.MD_FILTERS.toParams();
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.gender_revenue, params);
            if (resp && resp.data) renderGenderRevenue(resp.data);
        } catch (e) { console.error('gender-revenue fetch failed', e); }
    }

    function renderGenderRevenue(data) {
        const female = (data.female && data.female.revenue) || 0;
        const male = (data.male && data.male.revenue) || 0;
        const unknown = (data.unknown && data.unknown.revenue) || 0;
        const totalRevenue = (data.total && data.total.revenue) || 0;

        setText('mdGenderPeriodLabel', periodLabel());

        if (totalRevenue === 0) {
            setText('mdGenderHeadline', '—');
            setText('mdGenderSub', 'No revenue in range');
            window.MD_CHARTS.render('mdGenderDonutChart', emptyDonutConfig());
            return;
        }

        // Headline = dominant gender share; sub = the counterpart + avg revenue
        // per patient per gender (more actionable than raw txn counts —
        // controls for one customer paying multiple times).
        const femaleShare = (data.female && data.female.share) || 0;
        const maleShare = (data.male && data.male.share) || 0;
        const unknownShare = (data.unknown && data.unknown.share) || 0;
        const [domLabel, domShare] = femaleShare >= maleShare
            ? ['Female', femaleShare]
            : ['Male', maleShare];
        const otherLabel = domLabel === 'Female' ? 'Male' : 'Female';
        const otherShare = domLabel === 'Female' ? maleShare : femaleShare;
        const unknownNote = unknownShare >= 5 ? ` · ${unknownShare.toFixed(1)}% unknown` : '';

        const femaleAvg = (data.female && data.female.avg_per_patient) || 0;
        const maleAvg = (data.male && data.male.avg_per_patient) || 0;

        setText('mdGenderHeadline', `${domShare.toFixed(1)}% ${domLabel}`);
        const sub = document.getElementById('mdGenderSub');
        if (sub) {
            sub.innerHTML =
                `<div>${otherShare.toFixed(1)}% ${otherLabel}${unknownNote}</div>` +
                `<div class="md-mini-sub-alt">Avg ${window.MD_CHARTS.currency(femaleAvg)}/F</div>` +
                `<div class="md-mini-sub-alt">Avg ${window.MD_CHARTS.currency(maleAvg)}/M</div>`;
        }

        window.MD_CHARTS.render('mdGenderDonutChart', {
            chart: { type: 'donut', height: 100, sparkline: { enabled: true } },
            series: [female, male, unknown],
            labels: ['Female', 'Male', 'Unknown'],
            colors: ['#f472b6', '#60a5fa', '#d1d5db'],
            stroke: { width: 0 },
            plotOptions: { pie: { donut: { size: '70%' } } },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: { y: { formatter: (v) => window.MD_CHARTS.currency(v) } },
        });
    }

    function emptyDonutConfig() {
        return {
            chart: { type: 'donut', height: 100, sparkline: { enabled: true } },
            series: [1],
            labels: ['No data'],
            colors: ['#e5e7eb'],
            stroke: { width: 0 },
            plotOptions: { pie: { donut: { size: '70%' } } },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: { enabled: false },
        };
    }

    async function loadTodayActivities() {
        try {
            // Request the backend max (50) so the panel fills the space the
            // Stats-panel sibling gives us — 20 rows was sized for a shorter
            // panel and left a large blank area below.
            const params = { branches: window.MD_FILTERS.state.branches, limit: 50 };
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.today_activities, params, { noCache: true });
            if (resp && resp.data) renderTodayActivities(resp.data, { append: false });
        } catch (e) { console.error('today-activities fetch failed', e); }
    }

    async function loadMoreTodayActivities() {
        if (!todayActivitiesCursor) return;
        try {
            const params = { branches: window.MD_FILTERS.state.branches, cursor: todayActivitiesCursor };
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.today_activities, params, { noCache: true });
            if (resp && resp.data) renderTodayActivities(resp.data, { append: true });
        } catch (e) { console.error('today-activities load-more failed', e); }
    }

    function renderTodayActivities(data, { append }) {
        const list = document.getElementById('mdTodayActivitiesList');
        const empty = document.getElementById('mdTodayActivitiesEmpty');
        const more = document.getElementById('mdTodayActivitiesLoadMore');
        const count = document.getElementById('mdTodayActivitiesCount');
        if (!list) return;

        const rows = data.rows || [];
        const html = rows.map(activityRowHtml).join('');

        if (append) {
            list.insertAdjacentHTML('beforeend', html);
        } else {
            list.innerHTML = html;
            if (count) count.textContent = rows.length ? rows.length + (data.has_more ? '+' : '') + ' activities' : '0 activities';
        }

        if (empty) empty.hidden = (list.children.length > 0);
        todayActivitiesCursor = data.next_cursor || null;
        if (more) more.hidden = !data.has_more;
    }

    function activityRowHtml(r) {
        const sev = r.severity || 'neutral';
        // description_html is server-rendered via ActivityLogRenderer (same
        // format as the audit-log report); trust it and render raw. Plain
        // `description` is kept as a fallback for legacy payloads.
        const body = r.description_html || escapeHtml(r.description || r.activity_type || '');
        return '<li class="md-pulse-row md-pulse-' + escapeHtml(sev) + '" data-id="' + (r.id || 0) + '">'
            + '<span class="md-pulse-dot"></span>'
            + '<span class="md-pulse-body"><span class="md-pulse-text">' + body + '</span></span>'
            + '<span class="md-pulse-time" title="' + escapeHtml(r.time || '') + '">' + escapeHtml(r.time_for_humans || '') + '</span>'
            + '</li>';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    function periodLabel() {
        const st = window.MD_FILTERS?.state || {};
        const map = {
            today: 'Today',
            yesterday: 'Yesterday',
            last_7_days: 'Last 7 Days',
            this_week: 'This Week',
            this_month: 'This Month',
        };
        return map[st.range] || '';
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    document.addEventListener('md:section-ready', (e) => {
        if (e.detail.section !== 'overview') return;
        document.getElementById('mdTodayActivitiesLoadMore')?.addEventListener('click', loadMoreTodayActivities);
        window.MD_BRANCH_WIDGETS?.wireLeaderboardSort();
        window.MD_UI?.wireViewToggle('[data-md-toggle]');
        window.MD_PATIENT_WIDGETS?.wireCohortToggle();
        load();
    });
    document.addEventListener('md:filter-changed', () => {
        if (document.getElementById('mdSectionOverview')) load();
    });
})();
