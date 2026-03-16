/**
 * Doctor Performance Dashboard
 * Mobile-first, VIEW ONLY dashboard with ApexCharts
 */
(function () {
    'use strict';

    // State
    let currentPeriod = 'this_month';
    let vsToggle = false;
    let kpiData = null;
    let heroData = null;
    let benchmarkData = null;

    // Charts
    let chartRevenue = null;
    let chartConversion = null;
    let chartNewReturn = null;

    // ===================== Init =====================
    function init() {
        bindEvents();
        loadAllData();
    }

    function bindEvents() {
        // Period toggle
        document.querySelectorAll('#ddPeriodToggle .dd-pill-opt').forEach(function (el) {
            el.addEventListener('click', function () {
                document.querySelectorAll('#ddPeriodToggle .dd-pill-opt').forEach(function (o) { o.classList.remove('active'); });
                this.classList.add('active');
                currentPeriod = this.dataset.period;
                loadKpis();
                loadBenchmarks();
            });
        });

        // Vs last month toggle
        var vsToggleEl = document.getElementById('ddVsToggle');
        if (vsToggleEl) {
            vsToggleEl.addEventListener('change', function () {
                vsToggle = this.checked;
                toggleLastMonthDisplay();
            });
        }
    }

    // ===================== Data Loading =====================
    function loadAllData() {
        loadKpis();
        loadHero();
        loadAppointments();
        loadBenchmarks();
    }

    function loadKpis() {
        var url = DD_CONFIG.routes.kpis + '?period=' + currentPeriod;
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.status && res.data) {
                    kpiData = res.data;
                    renderKpis(res.data.kpis);
                }
            })
            .catch(function (err) { console.error('KPI load error:', err); });
    }

    function loadHero() {
        fetch(DD_CONFIG.routes.hero, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.status && res.data) {
                    heroData = res.data;
                    renderHero(res.data);
                }
            })
            .catch(function (err) { console.error('Hero load error:', err); });
    }

    function loadAppointments() {
        fetch(DD_CONFIG.routes.appointments, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.status && res.data) {
                    renderAppointments(res.data);
                }
            })
            .catch(function (err) { console.error('Appointments load error:', err); });
    }

    function loadBenchmarks() {
        var url = DD_CONFIG.routes.benchmarks + '?period=' + currentPeriod;
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.status && res.data) {
                    benchmarkData = res.data;
                    renderBenchmarkIndicators(res.data);
                }
            })
            .catch(function (err) { console.error('Benchmark load error:', err); });
    }

    // ===================== Render KPIs =====================
    function renderKpis(kpis) {
        // Revenue & Conversion
        setKpi('kpiTotalRevenue', formatCurrency(kpis.total_revenue.value), 'currency');
        setKpiMom('kpiTotalRevenueMom', kpis.total_revenue.mom);
        setKpiLastMonth('kpiTotalRevenueLast', 'PKR ' + formatCurrency(kpis.total_revenue.last_month));

        setKpi('kpiConvRate', kpis.conversion_rate.value + '%');
        setKpiSub('kpiConvRateSub', kpis.conversion_rate.total_converted + '/' + kpis.conversion_rate.total_arrived + ' converted');
        setKpiMom('kpiConvRateMom', kpis.conversion_rate.mom);
        setKpiLastMonth('kpiConvRateLast', kpis.conversion_rate.last_month + '%');
        setKpiTarget('kpiConvRateTarget', kpis.conversion_rate.value, DD_CONFIG.targets.conversion_pct || 50, '%');

        setKpi('kpiAvgClient', formatCurrency(kpis.avg_client_value.value), 'currency');
        setKpiMom('kpiAvgClientMom', kpis.avg_client_value.mom);
        setKpiLastMonth('kpiAvgClientLast', 'PKR ' + formatCurrency(kpis.avg_client_value.last_month));
        setKpiTarget('kpiAvgClientTarget', kpis.avg_client_value.value, DD_CONFIG.targets.avg_conversion_revenue || 15000);

        setKpi('kpiProductRev', formatCurrency(kpis.product_revenue.value), 'currency');
        setKpiSub('kpiProductRevSub', kpis.product_revenue.total_orders + ' orders');
        setKpiMom('kpiProductRevMom', kpis.product_revenue.mom);
        setKpiLastMonth('kpiProductRevLast', 'PKR ' + formatCurrency(kpis.product_revenue.last_month));

        // Upselling & Memberships
        setKpi('kpiUpsellRev', formatCurrency(kpis.upsell_revenue.value), 'currency');
        setKpiMom('kpiUpsellRevMom', kpis.upsell_revenue.mom);
        setKpiLastMonth('kpiUpsellRevLast', 'PKR ' + formatCurrency(kpis.upsell_revenue.last_month));

        if (kpis.upsell_rate.value === null) {
            setKpi('kpiUpsellRate', 'N/A', 'na');
            setKpiSub('kpiUpsellRateSub', 'No treated patients');
        } else {
            setKpi('kpiUpsellRate', kpis.upsell_rate.value + '%');
            setKpiSub('kpiUpsellRateSub', kpis.upsell_rate.unique_upsold + '/' + kpis.upsell_rate.unique_treated + ' upsold');
        }
        setKpiLastMonth('kpiUpsellRateLast', kpis.upsell_rate.last_month !== null ? kpis.upsell_rate.last_month + '%' : 'N/A');

        setKpi('kpiGoldMem', kpis.gold_memberships.value);
        setKpiMom('kpiGoldMemMom', kpis.gold_memberships.mom);
        setKpiLastMonth('kpiGoldMemLast', kpis.gold_memberships.last_month);

        // Patient Experience
        setKpi('kpiFeedback', kpis.feedback_score.value ? kpis.feedback_score.value.toFixed(1) : '0');
        setKpiSub('kpiFeedbackSub', kpis.feedback_score.total_feedback + ' reviews');
        setKpiMom('kpiFeedbackMom', kpis.feedback_score.mom);
        setKpiLastMonth('kpiFeedbackLast', kpis.feedback_score.last_month ? kpis.feedback_score.last_month.toFixed(1) : '0');
        setKpiTarget('kpiFeedbackTarget', kpis.feedback_score.value, DD_CONFIG.targets.feedback_score || 9.5);

        setKpi('kpiGoogleRev', kpis.google_reviews.value);
        setKpiMom('kpiGoogleRevMom', kpis.google_reviews.mom);
        setKpiLastMonth('kpiGoogleRevLast', kpis.google_reviews.last_month);

        setKpi('kpiReturnRate', kpis.patient_return_rate.value + '%');
        setKpiSub('kpiReturnRateSub', kpis.patient_return_rate.patients_returned + '/' + kpis.patient_return_rate.total_unique + ' returned');
        setKpiMom('kpiReturnRateMom', kpis.patient_return_rate.mom);
        setKpiLastMonth('kpiReturnRateLast', kpis.patient_return_rate.last_month + '%');

        setKpi('kpiAvgProc', kpis.avg_procedures.value);
        setKpiSub('kpiAvgProcSub', kpis.avg_procedures.total_procedures + ' procedures, ' + kpis.avg_procedures.unique_patients + ' patients');
        setKpiMom('kpiAvgProcMom', kpis.avg_procedures.mom);
        setKpiLastMonth('kpiAvgProcLast', kpis.avg_procedures.last_month);

        // Activity
        setKpi('kpiPatientsSeen', kpis.patients_seen.value);
        setKpiSub('kpiPatientsSeenSub', kpis.patients_seen.consultations + ' consults, ' + kpis.patients_seen.treatments + ' treatments');
        setKpiMom('kpiPatientsSeenMom', kpis.patients_seen.mom);
        setKpiLastMonth('kpiPatientsSeenLast', kpis.patients_seen.last_month);

        setKpi('kpiNewReturn', kpis.new_vs_returning.new + ' / ' + kpis.new_vs_returning.returning);
        setKpiSub('kpiNewReturnSub', 'New / Returning');

        // Render charts
        renderCharts(kpis);

        // Toggle last month display
        toggleLastMonthDisplay();
    }

    // ===================== Render Hero =====================
    function renderHero(data) {
        // Goal Progress
        var goal = data.goal_progress;
        var barEl = document.getElementById('ddGoalBar');
        var pctEl = document.getElementById('ddGoalPct');
        var revEl = document.getElementById('ddGoalRevenue');
        var daysEl = document.getElementById('ddGoalDays');

        if (goal && goal.has_target) {
            var pct = Math.min(goal.percentage, 100);
            barEl.style.width = pct + '%';
            barEl.className = 'dd-goal-bar ' + goal.color;
            pctEl.textContent = goal.percentage + '%';
            revEl.textContent = 'PKR ' + formatCurrency(goal.doctor_revenue) + ' / ' + formatCurrency(goal.branch_target);
            daysEl.textContent = goal.days_remaining + ' days left';
        } else {
            pctEl.textContent = '';
            revEl.textContent = goal ? goal.message : 'No target set';
            daysEl.textContent = '';
        }

        // Streak
        var streak = data.streak;
        document.getElementById('ddStreakCount').textContent = streak.current_streak;
        document.getElementById('ddStreakBest').textContent = streak.best_streak;

        // Personal Bests
        var pb = data.personal_bests;
        setPb('highest_revenue', pb.highest_revenue, function (v) { return formatCurrency(v.value); });
        setPb('highest_conversion', pb.highest_conversion, function (v) { return v.value + '%'; });
        setPb('highest_upsell', pb.highest_upsell, function (v) { return formatCurrency(v.value); });
        setPb('most_patients_day', pb.most_patients_day, function (v) { return v.value; });
        setPb('longest_streak', pb.longest_streak, function (v) { return v.value + 'W'; });
        setPb('highest_feedback', pb.highest_feedback, function (v) { return v.avg_rating; });
        setPb('most_google_reviews', pb.most_google_reviews, function (v) { return v.value; });
    }

    // ===================== Render Appointments =====================
    function renderAppointments(data) {
        document.getElementById('ddApptCount').textContent = data.total;
        var listEl = document.getElementById('ddApptList');

        if (!data.list || data.list.length === 0) {
            listEl.innerHTML = '<li class="dd-appt-empty">No appointments today</li>';
            return;
        }

        var html = '';
        data.list.forEach(function (apt) {
            var typeClass = apt.type === 'Consultation' ? 'consultation' : 'treatment';
            html += '<li class="dd-appt-item">' +
                '<div class="dd-appt-type ' + typeClass + '"></div>' +
                '<div class="dd-appt-info">' +
                '<div class="dd-appt-patient">' + escHtml(apt.patient) + '</div>' +
                '<div class="dd-appt-service">' + escHtml(apt.service) + '</div>' +
                '</div>' +
                '<span class="dd-appt-time">' + escHtml(apt.time) + '</span>' +
                '<span class="dd-appt-status">' + escHtml(apt.status) + '</span>' +
                '</li>';
        });
        listEl.innerHTML = html;
    }

    // ===================== Render Benchmark Indicators =====================
    function renderBenchmarkIndicators(bench) {
        if (!kpiData || !bench || bench.doctor_count === 0) return;

        var kpis = kpiData.kpis;
        setBenchDot('kpiTotalRevenueBench', kpis.total_revenue.value, bench.total_revenue.avg);
        setBenchDot('kpiConvRateBench', kpis.conversion_rate.value, bench.conversion_rate.avg);
        setBenchDot('kpiAvgClientBench', kpis.avg_client_value.value, bench.avg_client_value.avg);
        setBenchDot('kpiFeedbackBench', kpis.feedback_score.value, bench.feedback_score.avg);
    }

    // ===================== Render Charts =====================
    function renderCharts(kpis) {
        // Revenue trend — simple bar comparing current vs last month
        renderRevenueChart(kpis);
        renderConversionChart(kpis);
        renderNewVsReturningChart(kpis);
    }

    function renderRevenueChart(kpis) {
        var el = document.getElementById('chartRevenueTrend');
        if (!el) return;

        var series = [{
            name: 'This Month',
            data: [kpis.total_revenue.value]
        }];

        if (vsToggle) {
            series.push({
                name: 'Last Month',
                data: [kpis.total_revenue.last_month]
            });
        }

        var options = {
            chart: { type: 'bar', height: 220, toolbar: { show: false } },
            series: series,
            xaxis: { categories: ['Revenue'] },
            colors: ['#3699ff', '#e4e6ef'],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '40%' } },
            legend: { show: vsToggle },
            dataLabels: { enabled: true, formatter: function (v) { return formatCurrency(v); } },
            yaxis: { labels: { formatter: function (v) { return formatCurrency(v); } } },
            tooltip: { y: { formatter: function (v) { return 'PKR ' + numberFormat(v); } } }
        };

        if (chartRevenue) chartRevenue.destroy();
        chartRevenue = new ApexCharts(el, options);
        chartRevenue.render();
    }

    function renderConversionChart(kpis) {
        var el = document.getElementById('chartConversionTrend');
        if (!el) return;

        var series = [
            { name: 'Converted', data: [kpis.conversion_rate.total_converted] },
            { name: 'Not Converted', data: [kpis.conversion_rate.total_arrived - kpis.conversion_rate.total_converted] }
        ];

        var options = {
            chart: { type: 'bar', height: 220, stacked: true, toolbar: { show: false } },
            series: series,
            xaxis: { categories: ['Consultations'] },
            colors: ['#1bc5bd', '#e4e6ef'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '35%' } },
            legend: { position: 'bottom' },
            dataLabels: { enabled: true },
            annotations: {
                yaxis: [{
                    y: DD_CONFIG.targets.conversion_pct ? (DD_CONFIG.targets.conversion_pct / 100 * kpis.conversion_rate.total_arrived) : 0,
                    borderColor: '#f64e60',
                    strokeDashArray: 4,
                    label: { text: 'Target', style: { color: '#f64e60', background: 'transparent' } }
                }]
            }
        };

        if (chartConversion) chartConversion.destroy();
        chartConversion = new ApexCharts(el, options);
        chartConversion.render();
    }

    function renderNewVsReturningChart(kpis) {
        var el = document.getElementById('chartNewVsReturning');
        if (!el) return;

        var nr = kpis.new_vs_returning;
        if (nr.total === 0) {
            el.innerHTML = '<div class="dd-appt-empty">No patient data</div>';
            return;
        }

        var options = {
            chart: { type: 'donut', height: 200 },
            series: [nr.new, nr.returning],
            labels: ['New Patients', 'Returning Patients'],
            colors: ['#3699ff', '#1bc5bd'],
            legend: { position: 'bottom', fontSize: '12px' },
            dataLabels: { enabled: true },
            plotOptions: { pie: { donut: { size: '55%' } } }
        };

        if (chartNewReturn) chartNewReturn.destroy();
        chartNewReturn = new ApexCharts(el, options);
        chartNewReturn.render();
    }

    // ===================== Helpers =====================
    function setKpi(id, value, cls) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = value;
        el.classList.remove('dd-skeleton');
        el.style.width = '';
        el.style.height = '';
        if (cls === 'na') el.classList.add('na');
        else el.classList.remove('na');
        if (cls === 'currency') el.classList.add('currency');
    }

    function setKpiSub(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function setKpiMom(id, mom) {
        var el = document.getElementById(id);
        if (!el || !mom) return;
        var arrow = mom.direction === 'up' ? '▲' : (mom.direction === 'down' ? '▼' : '—');
        el.textContent = arrow + ' ' + mom.value + '%';
        el.className = 'dd-kpi-mom ' + mom.direction;
    }

    function setKpiLastMonth(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = 'Last month: ' + text;
    }

    function setKpiTarget(id, value, target, suffix) {
        var el = document.getElementById(id);
        if (!el || !target) return;
        suffix = suffix || '';
        var met = value >= target;
        el.innerHTML = 'Target: ' + target + suffix +
            ' <span class="' + (met ? 'target-met' : 'target-missed') + '">' +
            (met ? '✓' : '✗') + '</span>';
    }

    function setBenchDot(id, value, avg) {
        var el = document.getElementById(id);
        if (!el) return;
        if (value > avg * 1.05) {
            el.className = 'dd-kpi-bench above';
            el.title = 'Above avg (' + numberFormat(avg) + ')';
        } else if (value < avg * 0.95) {
            el.className = 'dd-kpi-bench below';
            el.title = 'Below avg (' + numberFormat(avg) + ')';
        } else {
            el.className = 'dd-kpi-bench at';
            el.title = 'At avg (' + numberFormat(avg) + ')';
        }
        el.style.display = 'block';
    }

    function setPb(key, data, formatter) {
        var el = document.querySelector('[data-pb="' + key + '"]');
        if (!el) return;
        el.classList.remove('dd-skeleton');
        el.style.width = '';
        el.style.height = '';
        if (data) {
            el.textContent = formatter(data);
            el.title = data.label || '';
        } else {
            el.textContent = '—';
        }
    }

    function toggleLastMonthDisplay() {
        document.querySelectorAll('.dd-kpi-last-month').forEach(function (el) {
            if (vsToggle) el.classList.add('visible');
            else el.classList.remove('visible');
        });
        // Re-render charts with vs overlay
        if (kpiData) renderCharts(kpiData.kpis);
    }

    function formatCurrency(val) {
        if (val === null || val === undefined) return '0';
        val = parseFloat(val);
        if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
        if (val >= 1000) return (val / 1000).toFixed(1) + 'K';
        return numberFormat(val);
    }

    function numberFormat(val) {
        if (val === null || val === undefined) return '0';
        return parseFloat(val).toLocaleString('en-US', { maximumFractionDigits: 0 });
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ===================== Boot =====================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
