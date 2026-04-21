{{-- Top bar: section tabs (left) + date range + branch filter (right).
     Uses project-standard Select2 auto-init (see custom.js). --}}
<header class="md-topbar" role="banner">
    <h1 class="md-sr-only">Management Dashboard</h1>
    <nav class="md-tabs" aria-label="Management Dashboard sections">
        <a href="{{ route('admin.management_dashboard.overview') }}"
           data-section="overview"
           class="md-tab @if($active_section === 'overview') active @endif"
           @if($active_section === 'overview') aria-current="page" @endif>
            <i class="la la-home" aria-hidden="true"></i><span>Overview</span>
        </a>
        <a href="{{ route('admin.management_dashboard.people') }}"
           data-section="people"
           class="md-tab @if($active_section === 'people') active @endif"
           @if($active_section === 'people') aria-current="page" @endif>
            <i class="la la-stethoscope" aria-hidden="true"></i><span>Practitioners</span>
        </a>
    </nav>

    <div class="md-topbar-right" role="group" aria-label="Dashboard filters">
        <div class="md-filter-cell">
            <label for="mdDateRange" class="md-filter-label">Date range</label>
            <select id="mdDateRange"
                    class="form-control select2"
                    data-minimum-results-for-search="-1"
                    data-dropdown-css-class="md-filter-dropdown">
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last_7_days">Last 7 Days</option>
                <option value="last_30_days">Last 30 Days</option>
                <option value="this_week">This Week</option>
                <option value="this_month" selected>This Month</option>
                <option value="last_month">Last Month</option>
                <option value="custom">Custom range…</option>
            </select>
        </div>

        <div class="md-filter-cell md-filter-cell--custom" id="mdCustomRange" hidden>
            <label for="mdCustomFrom" class="md-filter-label">From</label>
            <input type="date" id="mdCustomFrom" class="form-control md-custom-date">
            <span class="md-custom-sep" aria-hidden="true">→</span>
            <label for="mdCustomTo" class="md-filter-label">To</label>
            <input type="date" id="mdCustomTo" class="form-control md-custom-date">
        </div>

        <div class="md-filter-cell md-filter-cell--branch">
            <label for="mdBranchSelect" class="md-filter-label">Branch</label>
            <select id="mdBranchSelect"
                    class="form-control select2"
                    data-dropdown-css-class="md-filter-dropdown">
                <option value="">All branches</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</header>
