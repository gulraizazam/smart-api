{{-- Overview: command-center layout. 4 hero tiles + trend/alerts + contribution/today + performers/pulse --}}
<section class="md-section md-section-overview" id="mdSectionOverview" data-section="overview">

    {{-- Row 1: Stats (6) + Alerts (6) --}}
    <div class="md-row md-row-split md-row-6-6">
        <div class="md-panel md-panel-stats">
            <div class="md-panel-head">
                <h3>Stats</h3>
                <span class="md-panel-sub" id="mdStatsPeriodLabel">—</span>
            </div>
            <div class="md-stats-grid">
                <div class="md-stats-tile">
                    <span class="md-stats-label">Sales</span>
                    <span class="md-stats-value" id="mdStatsSales">0</span>
                </div>
                <div class="md-stats-tile">
                    <span class="md-stats-label">Revenue</span>
                    <span class="md-stats-value" id="mdStatsRevenue">—</span>
                </div>
                <div class="md-stats-tile">
                    <span class="md-stats-label">Consultations (arr / total)</span>
                    <span class="md-stats-value" id="mdStatsConsultations">0 / 0</span>
                </div>
                <div class="md-stats-tile">
                    <span class="md-stats-label">Treatments (arr / total)</span>
                    <span class="md-stats-value" id="mdStatsTreatments">0 / 0</span>
                </div>
            </div>

            {{-- Centre-wise sales breakdown (fills the space under the tiles).
                 Shares the same SalesLedgerQuery filter as the Sales tile. --}}
            <div class="md-stats-centre-sales">
                <div class="md-stats-subhead">Sales by centre</div>
                <ol class="md-cs-list" id="mdCentreSalesList"></ol>
            </div>
        </div>

        <div class="md-panel md-panel-today-activities">
            <div class="md-panel-head">
                <h3>Today's Activities</h3>
                <span class="md-panel-sub" id="mdTodayActivitiesCount">—</span>
            </div>
            <ul class="md-pulse-list" id="mdTodayActivitiesList" role="list"></ul>
            <div class="md-pulse-empty" id="mdTodayActivitiesEmpty" hidden>
                <i class="la la-history"></i>
                <span>No activity yet today</span>
            </div>
            <button type="button" class="md-link" id="mdTodayActivitiesLoadMore" hidden>Load more</button>
        </div>
    </div>

    {{-- Row 2: compact 4-KPI strip. Slim cards for at-a-glance insights. --}}
    <div class="md-row md-row-4">
        <div class="md-panel md-panel-mini md-panel-service-interest">
            <div class="md-panel-head md-panel-head-mini">
                <h3>Service Interest</h3>
                <span class="md-panel-sub" id="mdServiceInterestPeriodLabel">—</span>
            </div>
            <div class="md-service-interest" id="mdServiceInterestList">
                {{-- Populated by renderLeadServiceInterest --}}
            </div>
        </div>

        <div class="md-panel md-panel-mini md-panel-gender">
            <div class="md-panel-head md-panel-head-mini">
                <h3>Revenue by Gender</h3>
                <span class="md-panel-sub" id="mdGenderPeriodLabel">—</span>
            </div>
            <div class="md-mini-body">
                <div class="md-mini-chart" id="mdGenderDonutChart"></div>
                <div class="md-mini-stat">
                    <div class="md-mini-headline" id="mdGenderHeadline">—</div>
                    <div class="md-mini-sub" id="mdGenderSub">—</div>
                </div>
            </div>
        </div>

        <div class="md-panel md-panel-mini md-panel-deltas">
            <div class="md-panel-head md-panel-head-mini">
                <h3>Sales Momentum</h3>
                <span class="md-panel-sub" id="mdDeltaPeriodLabel">—</span>
            </div>
            <div class="md-mini-body md-mini-body-col">
                <div class="md-delta-top">
                    <div class="md-delta-headline" id="mdDeltaCurrent">—</div>
                    <div class="md-delta-bars" id="mdDeltaBars" aria-hidden="true"></div>
                </div>
                <div class="md-delta-rows">
                    <span class="md-delta-label" id="mdDeltaMomLabel">vs prev period</span>
                    <span class="md-delta-value" id="mdDeltaMom">—</span>
                    <span class="md-delta-label" id="mdDeltaYoyLabel">vs last year</span>
                    <span class="md-delta-value" id="mdDeltaYoy">—</span>
                </div>
            </div>
        </div>

        <div class="md-panel md-panel-mini md-panel-atv">
            <div class="md-avg-split">
                <span class="md-avg-split-corner">Trailing 12 mo</span>
                <div class="md-avg-split-row md-avg-split-atv">
                    <div class="md-avg-split-title">Avg Transaction Value</div>
                    <div class="md-avg-split-body">
                        <div class="md-avg-split-chart" id="mdAtvSparkline"></div>
                        <div class="md-avg-split-stat">
                            <span class="md-avg-value" id="mdAtvHeadline">—</span>
                            <span class="md-avg-delta" id="mdAtvDelta"></span>
                        </div>
                    </div>
                </div>
                <div class="md-avg-split-row md-avg-split-acv">
                    <div class="md-avg-split-title">Avg Conversion Value</div>
                    <div class="md-avg-split-body">
                        <div class="md-avg-split-chart" id="mdAcvSparkline"></div>
                        <div class="md-avg-split-stat">
                            <span class="md-avg-value" id="mdAcvHeadline">—</span>
                            <span class="md-avg-delta" id="mdAcvDelta"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 3: branch leaderboard (6) + new vs returning (6) --}}
    <div class="md-row md-row-split md-row-6-6">
        <div class="md-panel">
            <div class="md-panel-head">
                <h3>Branch Leaderboard</h3>
                <label class="md-sort-label">
                    Sort
                    <select id="mdBranchesSort">
                        <option value="net_revenue" selected>Revenue</option>
                        <option value="conversion_rate">Conversion</option>
                        <option value="avg_value">Avg value</option>
                    </select>
                </label>
            </div>
            <div class="md-blb md-blb-scroll" id="mdBranchesCards"></div>
        </div>

        <div class="md-panel">
            <div class="md-panel-head">
                <div class="md-panel-title">
                    <h3 data-md-toggle-title>New vs Returning</h3>
                </div>
                <div class="md-panel-actions">
                    <div class="md-toggle" role="tablist" aria-label="New vs Returning view" data-md-toggle="new-returning">
                        <button type="button" class="md-toggle-btn is-active" data-view="split" data-title="New vs Returning" role="tab" aria-selected="true">Split</button>
                        <button type="button" class="md-toggle-btn" data-view="return-rate" data-title="Return Rate" role="tab" aria-selected="false">Return rate</button>
                    </div>
                </div>
            </div>
            <div class="md-view-stack">
                <div class="md-view is-active" data-view="split">
                    <div class="md-chart md-chart-lg" id="mdNewReturningChart" role="img" aria-label="New vs returning patients trend"></div>
                </div>
                <div class="md-view" data-view="return-rate">
                    <div class="md-chart md-chart-lg" id="mdReturnRateChart" role="img" aria-label="Return rate trend"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 4: service category trend (half width) — chart and matrix toggle into the same panel. Right half reserved for a future widget. --}}
    <div class="md-row md-row-split md-row-6-6">
        <div class="md-panel">
            <div class="md-panel-head">
                <div class="md-panel-title">
                    <h3>Service Category Trend</h3>
                    <span class="md-panel-sub">Trailing 6 months · top 10 + Other</span>
                </div>
                <div class="md-panel-actions">
                    <div class="md-toggle" role="tablist" aria-label="Category trend view" data-md-toggle="category-trend">
                        <button type="button" class="md-toggle-btn is-active" data-view="chart" role="tab" aria-selected="true">Chart</button>
                        <button type="button" class="md-toggle-btn" data-view="table" role="tab" aria-selected="false">Table</button>
                    </div>
                </div>
            </div>
            <div class="md-view-stack">
                <div class="md-view is-active" data-view="chart">
                    <div class="md-chart md-chart-lg" style="--md-chart-h:360px;height:var(--md-chart-h)" id="mdCategoryTrendChart" role="img" aria-label="Service category revenue trend"></div>
                    <div class="md-other-foot-host" id="mdCategoryTrendOther"></div>
                </div>
                <div class="md-view" data-view="table">
                    <div class="md-cat-summary-host" id="mdCategoryTrendSummary"></div>
                </div>
            </div>
        </div>

        {{-- Lead Conversion Deep-Dive (paired with Service Category Trend).
             Side-by-side gender funnels + leak callouts + 12-week trend +
             top-source conversion matrix. --}}
        <div class="md-panel md-panel-lead-deepdive">
            <div class="md-panel-head">
                <div class="md-panel-title">
                    <h3 data-md-toggle-title>Lead Conversion · Gender</h3>
                    <span class="md-panel-sub" id="mdLeadDeepPeriodLabel">—</span>
                </div>
                <div class="md-panel-actions">
                    <div class="md-toggle" role="tablist" aria-label="Lead conversion view" data-md-toggle="lead-views">
                        <button type="button" class="md-toggle-btn is-active" data-view="funnel" data-title="Lead Conversion · Gender" role="tab" aria-selected="true">Funnel</button>
                        <button type="button" class="md-toggle-btn" data-view="rescue" data-title="Lead Rescue Potential" role="tab" aria-selected="false">Rescue $</button>
                        <button type="button" class="md-toggle-btn" data-view="economics" data-title="Lead Economics · Gender" role="tab" aria-selected="false">Economics</button>
                    </div>
                </div>
            </div>

            <div class="md-view-stack">
                {{-- FUNNEL view (default) — verdict hero + side-by-side funnels + leak callouts --}}
                <div class="md-view is-active" data-view="funnel">
                    <div class="md-dd-hero" id="mdDdHero"></div>
                    <div class="md-dd-funnels" id="mdDdFunnels"></div>
                    <div class="md-dd-leaks" id="mdDdLeaks"></div>
                </div>

                {{-- RESCUE view — rupee value of stuck leads --}}
                <div class="md-view" data-view="rescue">
                    <div class="md-dd-rescue" id="mdDdRescue">
                        {{-- Populated by renderDdRescue --}}
                    </div>
                </div>

                {{-- ECONOMICS view — revenue per lead by gender + category breakdown --}}
                <div class="md-view" data-view="economics">
                    <div class="md-dd-economics" id="mdDdEconomics">
                        {{-- Populated by renderDdEconomics --}}
                    </div>
                    <div class="md-dd-section">
                        <div class="md-dd-section-head">
                            <span class="md-dd-section-title">By service area</span>
                            <span class="md-dd-section-sub" id="mdDdCategoriesSub">Conversion rate per category · in range</span>
                            <div class="md-segmented md-segmented--mini"
                                 role="tablist"
                                 aria-label="Category metric"
                                 id="mdDdCatMetric">
                                <button type="button"
                                        class="is-active"
                                        role="tab"
                                        aria-selected="true"
                                        data-metric="rate">Rate</button>
                                <button type="button"
                                        role="tab"
                                        aria-selected="false"
                                        data-metric="rpl"
                                        title="Revenue per lead">RPL</button>
                            </div>
                        </div>
                        <div class="md-dd-categories" id="mdDdCategories">
                            {{-- Populated by renderDdCategories --}}
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Row 5: Retention cohorts (full width) — long-term health,
         leading indicator of revenue dips. --}}
    <div class="md-row">
        <div class="md-panel">
            <div class="md-panel-head">
                <h3>Retention cohorts</h3>
                <div class="md-panel-actions">
                    <label class="md-check">
                        <input type="checkbox" id="mdCohort24">
                        <span>Show 24 months</span>
                    </label>
                </div>
            </div>
            <div class="md-cohort-grid-wrap">
                <table class="md-cohort-grid" id="mdCohortTable">
                    <thead id="mdCohortThead"></thead>
                    <tbody id="mdCohortTbody"></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Row 6: top performers (6) + revenue concentration (6) --}}
    <div class="md-row md-row-split md-row-6-6">
        <div class="md-panel">
            <div class="md-panel-head">
                <h3>Top Performers</h3>
                <div class="md-segmented" id="mdPerformersSegmented">
                    <button type="button" class="active" data-segment="doctors">Doctors</button>
                    <button type="button" data-segment="branches">Branches</button>
                </div>
            </div>
            <ul class="md-leaderboard-mini" id="mdTopPerformersList"></ul>
        </div>

        <div class="md-panel">
            <div class="md-panel-head"><h3>Revenue Concentration</h3></div>
            <div class="md-concentration">
                <div class="md-concentration-stat">
                    <div class="md-concentration-headline" id="mdConcentrationHeadline">—</div>
                    <div class="md-concentration-sub" id="mdConcentrationSub">—</div>
                </div>
                <div class="md-chart md-chart-sm" style="--md-chart-h:160px;height:var(--md-chart-h)" id="mdLorenzChart" role="img" aria-label="Revenue concentration curve"></div>
            </div>
        </div>
    </div>
</section>
