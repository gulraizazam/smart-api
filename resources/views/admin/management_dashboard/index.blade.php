@extends('admin.layouts.master')
@section('title', 'Management Dashboard')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/activity-log.css') }}?v={{ filemtime(public_path('assets/css/activity-log.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/management-dashboard.css') }}?v={{ filemtime(public_path('assets/css/management-dashboard.css')) }}">
@endpush

@section('content')
<div class="md-shell" id="mdShell"
     data-density="comfortable"
     data-active-section="{{ $active_section }}"
     data-is-company-wide="{{ $is_company_wide ? 'true' : 'false' }}">

    <main class="md-main" id="mdMain" role="main">
        @include('admin.management_dashboard.partials.top_bar', [
            'branches' => $branches,
            'is_company_wide' => $is_company_wide,
            'active_section' => $active_section,
        ])

        <div class="md-section-wrap" id="mdSectionWrap" aria-busy="false">
            @switch($active_section)
                @case('overview')
                    @include('admin.management_dashboard.sections.overview')
                    @break
                @case('people')
                    @include('admin.management_dashboard.sections.people')
                    @break
            @endswitch
        </div>
    </main>

    {{-- Toast region for async errors / retry surfaces. aria-live=polite so
         screen readers announce failures without interrupting reading flow. --}}
    <div class="md-toast-region"
         id="mdToastRegion"
         role="status"
         aria-live="polite"
         aria-atomic="true"></div>
</div>

@push('js')
<script>
  window.MD_CONFIG = {
      active_section: @json($active_section),
      is_company_wide: @json($is_company_wide),
      allowed_branch_count: @json($allowed_branch_count),
      branches: @json($branches),
      routes: {
          overview: "{{ route('admin.management_dashboard_api.overview') }}",
          branches: "{{ route('admin.management_dashboard_api.branches') }}",
          people: "{{ route('admin.management_dashboard_api.people') }}",
          patients: "{{ route('admin.management_dashboard_api.patients') }}",
          new_returning: "{{ route('admin.management_dashboard_api.new_returning') }}",
          service_category_trend: "{{ route('admin.management_dashboard_api.service_category_trend') }}",
          avg_transaction_value: "{{ route('admin.management_dashboard_api.avg_transaction_value') }}",
          avg_conversion_value: "{{ route('admin.management_dashboard_api.avg_conversion_value') }}",
          lead_gender_funnel: "{{ route('admin.management_dashboard_api.lead_gender_funnel') }}",
          lead_service_interest: "{{ route('admin.management_dashboard_api.lead_service_interest') }}",
          gender_revenue: "{{ route('admin.management_dashboard_api.gender_revenue') }}",
          today_activities: "{{ route('admin.management_dashboard_api.today_activities') }}",
          section_urls: {
              overview: "{{ route('admin.management_dashboard.overview') }}",
              people: "{{ route('admin.management_dashboard.people') }}",
          },
      },
  };
</script>
{{-- ApexCharts is already loaded by the master layout (apexcharts@3.45.1) — do not double-load. --}}
@php $mdAssets = 'assets/js/pages/management-dashboard'; @endphp
<script src="{{ asset($mdAssets.'/api.js') }}?v={{ filemtime(public_path($mdAssets.'/api.js')) }}"></script>
<script src="{{ asset($mdAssets.'/filters.js') }}?v={{ filemtime(public_path($mdAssets.'/filters.js')) }}"></script>
<script src="{{ asset($mdAssets.'/charts.js') }}?v={{ filemtime(public_path($mdAssets.'/charts.js')) }}"></script>
<script src="{{ asset($mdAssets.'/shell.js') }}?v={{ filemtime(public_path($mdAssets.'/shell.js')) }}"></script>
<script src="{{ asset($mdAssets.'/overview.js') }}?v={{ filemtime(public_path($mdAssets.'/overview.js')) }}"></script>
<script src="{{ asset($mdAssets.'/branches.js') }}?v={{ filemtime(public_path($mdAssets.'/branches.js')) }}"></script>
<script src="{{ asset($mdAssets.'/people.js') }}?v={{ filemtime(public_path($mdAssets.'/people.js')) }}"></script>
<script src="{{ asset($mdAssets.'/patients.js') }}?v={{ filemtime(public_path($mdAssets.'/patients.js')) }}"></script>
<script src="{{ asset($mdAssets.'/keybindings.js') }}?v={{ filemtime(public_path($mdAssets.'/keybindings.js')) }}"></script>
@endpush
@endsection
