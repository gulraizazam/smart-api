@extends('admin.layouts.master')
@section('title', 'Doctor Dashboard')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/doctor-dashboard.css') }}?v={{ time() }}">

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    {{-- Sticky Header --}}
    <div class="dd-header" id="ddHeader">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="dd-doctor-profile">
                @if(!empty($doctorInfo['image_src']))
                    <img src="{{ asset($doctorInfo['image_src']) }}" alt="" class="dd-avatar">
                @else
                    @php
                        $initials = collect(explode(' ', $doctorInfo['name'] ?? 'D'))
                            ->filter(fn($w) => strlen($w) > 0)
                            ->map(fn($w) => strtoupper($w[0]))
                            ->take(2)
                            ->implode('');
                    @endphp
                    <div class="dd-avatar dd-avatar-initials">{{ $initials }}</div>
                @endif
                <h4 class="dd-doctor-name">{{ $doctorInfo['name'] ?? 'Doctor' }}</h4>
            </div>
            <div class="dd-toggle-wrap">
                <div class="dd-pill-toggle" id="ddPeriodToggle">
                    <span class="dd-pill-opt active" data-period="this_month">This Month</span>
                    <span class="dd-pill-opt" data-period="last_month">Last Month</span>
                </div>
                <label class="dd-vs-toggle">
                    <input type="checkbox" id="ddVsToggle">
                    <span>vs Last</span>
                </label>
            </div>
        </div>
    </div>

    {{-- Two-column layout: main content + sidebar --}}
    <div class="dd-two-col">

        {{-- Left: Main Content --}}
        <div class="dd-main-col">

            {{-- Hero Motivational Strip --}}
            <div class="dd-hero" id="ddHero">
                {{-- Goal Progress Bar --}}
                <div class="dd-goal" id="ddGoal">
                    <div class="dd-goal-label">Branch Target Progress</div>
                    <div class="dd-goal-bar-wrap">
                        <div class="dd-goal-bar" id="ddGoalBar" style="width: 0%"></div>
                        <span class="dd-goal-pct" id="ddGoalPct">0%</span>
                    </div>
                    <div class="dd-goal-info">
                        <span id="ddGoalRevenue">PKR 0</span>
                        <span id="ddGoalDays">-- days left</span>
                    </div>
                </div>

                {{-- Personal Bests (Last 3 Months) --}}
                <div class="dd-pb-section-label">Personal Bests <span class="dd-pb-period">(Last 3 Months)</span></div>
                <div class="dd-pb-row" id="ddPersonalBests">
                    <div class="dd-pb-item">
                        <div class="dd-pb-icon"><i class="la la-chart-line"></i></div>
                        <div class="dd-pb-value dd-skeleton" style="width:50px;height:16px;" data-pb="highest_revenue">&nbsp;</div>
                        <div class="dd-pb-label">Best Revenue</div>
                    </div>
                    <div class="dd-pb-item">
                        <div class="dd-pb-icon"><i class="la la-percentage"></i></div>
                        <div class="dd-pb-value dd-skeleton" style="width:50px;height:16px;" data-pb="highest_conversion">&nbsp;</div>
                        <div class="dd-pb-label">Best Conv %</div>
                    </div>
                    <div class="dd-pb-item">
                        <div class="dd-pb-icon"><i class="la la-hand-holding-usd"></i></div>
                        <div class="dd-pb-value dd-skeleton" style="width:50px;height:16px;" data-pb="highest_upsell">&nbsp;</div>
                        <div class="dd-pb-label">Best Upsell</div>
                    </div>
                    <div class="dd-pb-item">
                        <div class="dd-pb-icon"><i class="la la-users"></i></div>
                        <div class="dd-pb-value dd-skeleton" style="width:50px;height:16px;" data-pb="most_patients_day">&nbsp;</div>
                        <div class="dd-pb-label">Most Patients/Day</div>
                    </div>
                    <div class="dd-pb-item">
                        <div class="dd-pb-icon"><i class="la la-star"></i></div>
                        <div class="dd-pb-value dd-skeleton" style="width:50px;height:16px;" data-pb="highest_feedback">&nbsp;</div>
                        <div class="dd-pb-label">Best Feedback</div>
                    </div>
                    <div class="dd-pb-item">
                        <div class="dd-pb-icon"><i class="la la-google"></i></div>
                        <div class="dd-pb-value dd-skeleton" style="width:50px;height:16px;" data-pb="most_google_reviews">&nbsp;</div>
                        <div class="dd-pb-label">Best Reviews/Mo</div>
                    </div>
                </div>
            </div>

            {{-- KPI Section: Revenue & Conversion --}}
            <div class="dd-kpi-section dd-section-revenue">
                <div class="dd-kpi-section-title">Revenue & Conversion</div>
                <div class="dd-kpi-grid">
                    <div class="dd-kpi-card" data-kpi="total_revenue">
                        <div class="dd-kpi-label"><i class="la la-dollar-sign"></i> Total Revenue</div>
                        <div class="dd-kpi-value currency dd-skeleton" style="height:24px;width:80px;" id="kpiTotalRevenue">&nbsp;</div>
                        <div class="dd-kpi-sub" id="kpiTotalRevenueSub"></div>
                        <div class="dd-kpi-mom" id="kpiTotalRevenueMom"></div>
                        <div class="dd-kpi-last-month" id="kpiTotalRevenueLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiTotalRevenueBench"></div>
                    </div>
                    <div class="dd-kpi-card" data-kpi="conversion_rate">
                        <div class="dd-kpi-label"><i class="la la-exchange-alt"></i> Conversion Rate</div>
                        <div class="dd-kpi-value dd-skeleton" style="height:24px;width:60px;" id="kpiConvRate">&nbsp;</div>
                        <div class="dd-kpi-sub" id="kpiConvRateSub"></div>
                        <div class="dd-kpi-mom" id="kpiConvRateMom"></div>
                        <div class="dd-kpi-last-month" id="kpiConvRateLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiConvRateBench"></div>
                    </div>
                    <div class="dd-kpi-card" data-kpi="avg_client_value">
                        <div class="dd-kpi-label"><i class="la la-user-tag"></i> Avg Client Value</div>
                        <div class="dd-kpi-value currency dd-skeleton" style="height:24px;width:70px;" id="kpiAvgClient">&nbsp;</div>
                        <div class="dd-kpi-sub" id="kpiAvgClientSub"></div>
                        <div class="dd-kpi-mom" id="kpiAvgClientMom"></div>
                        <div class="dd-kpi-last-month" id="kpiAvgClientLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiAvgClientBench"></div>
                    </div>
                    <div class="dd-kpi-card" data-kpi="product_revenue">
                        <div class="dd-kpi-label"><i class="la la-box"></i> Product Revenue</div>
                        <div class="dd-kpi-value currency dd-skeleton" style="height:24px;width:70px;" id="kpiProductRev">&nbsp;</div>
                        <div class="dd-kpi-sub" id="kpiProductRevSub"></div>
                        <div class="dd-kpi-mom" id="kpiProductRevMom"></div>
                        <div class="dd-kpi-last-month" id="kpiProductRevLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiProductRevBench"></div>
                    </div>
                </div>
            </div>

            {{-- KPI Section: Upselling & Memberships --}}
            <div class="dd-kpi-section dd-section-upsell">
                <div class="dd-kpi-section-title">Upselling & Memberships</div>
                <div class="dd-kpi-grid">
                    <div class="dd-kpi-card" data-kpi="upsell_revenue">
                        <div class="dd-kpi-label"><i class="la la-hand-holding-usd"></i> Upsell Revenue</div>
                        <div class="dd-kpi-value currency dd-skeleton" style="height:24px;width:70px;" id="kpiUpsellRev">&nbsp;</div>
                        <div class="dd-kpi-mom" id="kpiUpsellRevMom"></div>
                        <div class="dd-kpi-last-month" id="kpiUpsellRevLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiUpsellRevBench"></div>
                    </div>
                    <div class="dd-kpi-card" data-kpi="upsell_rate">
                        <div class="dd-kpi-label"><i class="la la-percent"></i> Upsell Rate</div>
                        <div class="dd-kpi-value dd-skeleton" style="height:24px;width:50px;" id="kpiUpsellRate">&nbsp;</div>
                        <div class="dd-kpi-sub" id="kpiUpsellRateSub"></div>
                        <div class="dd-kpi-last-month" id="kpiUpsellRateLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiUpsellRateBench"></div>
                    </div>
                    <div class="dd-kpi-card" data-kpi="gold_memberships">
                        <div class="dd-kpi-label"><i class="la la-medal"></i> Gold Memberships</div>
                        <div class="dd-kpi-value dd-skeleton" style="height:24px;width:40px;" id="kpiGoldMem">&nbsp;</div>
                        <div class="dd-kpi-mom" id="kpiGoldMemMom"></div>
                        <div class="dd-kpi-last-month" id="kpiGoldMemLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiGoldMemBench"></div>
                    </div>
                </div>
            </div>

            {{-- KPI Section: Patient Experience --}}
            <div class="dd-kpi-section dd-section-experience">
                <div class="dd-kpi-section-title">Patient Experience</div>
                <div class="dd-kpi-grid">
                    <div class="dd-kpi-card" data-kpi="feedback_score">
                        <div class="dd-kpi-label"><i class="la la-star"></i> Feedback Score</div>
                        <div class="dd-kpi-value dd-skeleton" style="height:24px;width:50px;" id="kpiFeedback">&nbsp;</div>
                        <div class="dd-kpi-sub" id="kpiFeedbackSub"></div>
                        <div class="dd-kpi-mom" id="kpiFeedbackMom"></div>
                        <div class="dd-kpi-last-month" id="kpiFeedbackLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiFeedbackBench"></div>
                    </div>
                    <div class="dd-kpi-card" data-kpi="google_reviews">
                        <div class="dd-kpi-label"><i class="la la-google"></i> Google Reviews</div>
                        <div class="dd-kpi-value dd-skeleton" style="height:24px;width:40px;" id="kpiGoogleRev">&nbsp;</div>
                        <div class="dd-kpi-mom" id="kpiGoogleRevMom"></div>
                        <div class="dd-kpi-last-month" id="kpiGoogleRevLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiGoogleRevBench"></div>
                    </div>
                    <div class="dd-kpi-card" data-kpi="patient_return_rate">
                        <div class="dd-kpi-label"><i class="la la-redo"></i> Return Rate</div>
                        <div class="dd-kpi-value dd-skeleton" style="height:24px;width:50px;" id="kpiReturnRate">&nbsp;</div>
                        <div class="dd-kpi-sub" id="kpiReturnRateSub"></div>
                        <div class="dd-kpi-mom" id="kpiReturnRateMom"></div>
                        <div class="dd-kpi-last-month" id="kpiReturnRateLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiReturnRateBench"></div>
                    </div>
                    <div class="dd-kpi-card" data-kpi="avg_procedures">
                        <div class="dd-kpi-label"><i class="la la-syringe"></i> Avg Procedures</div>
                        <div class="dd-kpi-value dd-skeleton" style="height:24px;width:40px;" id="kpiAvgProc">&nbsp;</div>
                        <div class="dd-kpi-sub" id="kpiAvgProcSub"></div>
                        <div class="dd-kpi-mom" id="kpiAvgProcMom"></div>
                        <div class="dd-kpi-last-month" id="kpiAvgProcLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiAvgProcBench"></div>
                    </div>
                </div>
            </div>

            {{-- KPI Section: Activity --}}
            <div class="dd-kpi-section dd-section-activity">
                <div class="dd-kpi-section-title">Activity</div>
                <div class="dd-kpi-grid">
                    <div class="dd-kpi-card" data-kpi="patients_seen">
                        <div class="dd-kpi-label"><i class="la la-user-friends"></i> Patients Seen</div>
                        <div class="dd-kpi-value dd-skeleton" style="height:24px;width:40px;" id="kpiPatientsSeen">&nbsp;</div>
                        <div class="dd-kpi-sub" id="kpiPatientsSeenSub"></div>
                        <div class="dd-kpi-mom" id="kpiPatientsSeenMom"></div>
                        <div class="dd-kpi-last-month" id="kpiPatientsSeenLast"></div>
                        <div class="dd-kpi-bench-network" id="kpiPatientsSeenBench"></div>
                    </div>
                    <div class="dd-kpi-card" data-kpi="new_vs_returning">
                        <div class="dd-kpi-label"><i class="la la-user-plus"></i> New vs Returning</div>
                        <div class="dd-kpi-value dd-skeleton" style="height:24px;width:70px;" id="kpiNewReturn">&nbsp;</div>
                        <div class="dd-kpi-sub" id="kpiNewReturnSub"></div>
                    </div>
                </div>
            </div>

            {{-- Charts Section --}}
            <div class="dd-chart-section">
                <div class="dd-chart-card">
                    <div class="dd-chart-title">Revenue Trend</div>
                    <div id="chartRevenueTrend" style="height: 220px;"></div>
                </div>
                <div class="dd-chart-card">
                    <div class="dd-chart-title">Conversion Rate Trend</div>
                    <div id="chartConversionTrend" style="height: 220px;"></div>
                </div>
                <div class="dd-chart-card">
                    <div class="dd-chart-title">New vs Returning Patients</div>
                    <div id="chartNewVsReturning" style="height: 200px;"></div>
                </div>
            </div>

        </div>{{-- /dd-main-col --}}

        {{-- Right: Appointments Sidebar --}}
        <div class="dd-sidebar-col">
            <div class="dd-appt-card" id="ddAppointments">
                <div class="dd-appt-header">
                    <span class="dd-appt-title">Today's Appointments</span>
                    <span class="dd-appt-count" id="ddApptCount">0</span>
                </div>
                <div class="dd-appt-tabs">
                    <button class="dd-appt-tab active" data-tab="treatments">Treatments</button>
                    <button class="dd-appt-tab" data-tab="consultations">Consultations</button>
                </div>
                <div class="dd-appt-summary" id="ddApptSummary">Loading...</div>
                <div class="dd-appt-tab-content" id="ddApptTabTreatments">
                    <ul class="dd-appt-list" id="ddApptListTreatments">
                        <li class="dd-appt-empty">Loading...</li>
                    </ul>
                </div>
                <div class="dd-appt-tab-content" id="ddApptTabConsultations" style="display:none;">
                    <ul class="dd-appt-list" id="ddApptListConsultations">
                        <li class="dd-appt-empty">Loading...</li>
                    </ul>
                </div>
            </div>
        </div>{{-- /dd-sidebar-col --}}

    </div>{{-- /dd-two-col --}}

</div>

{{-- Pass config to JS --}}
<script>
    var DD_CONFIG = {
        routes: {
            kpis: "{{ route('admin.doctor_dashboard.kpis') }}",
            hero: "{{ route('admin.doctor_dashboard.hero') }}",
            appointments: "{{ route('admin.doctor_dashboard.appointments') }}",
            benchmarks: "{{ route('admin.doctor_dashboard.benchmarks') }}"
        },
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="{{ asset('assets/js/pages/doctor_dashboard/dashboard.js') }}?v={{ time() }}"></script>
@endsection
