@extends('admin.layouts.master')
@section('title', 'Dashboard')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/home.css') }}">

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb')
    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-xxl-6">
                    <div class="card card-custom bg-gray-100 card-stretch gutter-b">
                        <div class="card-header border-0 bg-danger py-5">
                            <h3 class="card-title font-weight-bolder text-white">Stats</h3>
                            <div class="card-toolbar">
                                <div class="dropdown  dropdown-inline">
                                    <select class="form-control" name="type" onchange="changeDate();" id="recordfilter">
                                        <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                        </option>
                                        <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                        <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                        <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                            Week</option>
                                        <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This
                                            Month</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0 position-relative overflow-hidden">
                            <div id="kt_mixed_widget_1_chart" class="card-rounded-bottom bg-danger" style="height: 200px"></div>
                            <div class="card-spacer mt-n25">
                                <div class="row m-0">
                                    <div class="col bg-light-success px-6 py-8 rounded-xl mr-7 mb-7">
                                        <span class="svg-icon svg-icon-3x svg-icon-primary d-block my-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px"
                                                viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24" />
                                                    <rect fill="#000000" opacity="0.3" x="13" y="4" width="3" height="16" rx="1.5" />
                                                    <rect fill="#000000" x="8" y="9" width="3" height="11" rx="1.5" />
                                                    <rect fill="#000000" x="18" y="11" width="3" height="9" rx="1.5" />
                                                    <rect fill="#000000" x="3" y="13" width="3" height="7" rx="1.5" />
                                                </g>
                                            </svg>
                                            <span class="dashboard-counter" id="allleads"><span class="skeleton-loader">Loading...</span></span>
                                        </span>
                                        <a href="javascript:void(0);" style="cursor: pointer;" class="text-success font-weight-bold font-size-h6 mt-2">Sales</a>
                                    </div>
                                    <div class="col bg-light-warning px-6 py-8 rounded-xl  mb-7">
                                        <span class="svg-icon svg-icon-3x svg-icon-warning d-block my-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px"
                                                viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24" />
                                                    <rect fill="#000000" opacity="0.3" x="13" y="4" width="3" height="16" rx="1.5" />
                                                    <rect fill="#000000" x="8" y="9" width="3" height="11" rx="1.5" />
                                                    <rect fill="#000000" x="18" y="11" width="3" height="9" rx="1.5" />
                                                    <rect fill="#000000" x="3" y="13" width="3" height="7" rx="1.5" />
                                                </g>
                                            </svg>
                                            <span class="dashboard-counter" id="allrevenue"><span class="skeleton-loader">Loading...</span></span>
                                        </span>
                                        <a href="javascript:void(0);" style="cursor: pointer;" class="text-warning font-weight-bold font-size-h6">Revenue Consumed</a>
                                    </div>
                                </div>
                                <div class="row m-0">
                                    <div class="col bg-light-primary px-6 py-8 rounded-xl ">
                                        <span class="svg-icon svg-icon-3x svg-icon-primary d-block my-2">
                                            <i class="la la-stethoscope" style="font-size: 40px;"></i>
                                            <span class="dashboard-counter" id="allconsult"><span class="skeleton-loader">Loading...</span></span>
                                        </span>
                                        <a id="allconsultantdate" href="javascript:void(0);" class="text-primary font-weight-bold font-size-h6 mt-2">Consultancies</a>
                                    </div>
                                    <div class="col bg-light-danger px-6 py-8 rounded-xl ml-7">
                                        <span class="svg-icon svg-icon-3x svg-icon-danger d-block my-2">
                                            <i class="la la-medkit" style="font-size: 40px;"></i>
                                            <span class="dashboard-counter" id="alltreat"><span class="skeleton-loader">Loading...</span></span>
                                        </span>
                                        <a id="alltreatmentdate" href="javascript:void(0);" class="text-danger font-weight-bold font-size-h6 mt-2">Treatments</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="modal_change_appointment_status" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_status_change">
                        @include('admin.appointments.appointment-forms.change-status')
                    </div>
                </div>
                <div class="modal fade" id="modal_change_appointment_schedule" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_schedule_change">
                        @include('admin.appointments.appointment-forms.schedule')
                    </div>
                </div>
                <div class="col-lg-6 col-xxl-6" id="activitydiv">
                    <div class="card card-custom card-stretch gutter-b" style="height: 600px; overflow-y: auto;" id="activities-container">
                        <div class="card-header align-items-center border-0 mt-4">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="font-weight-bolder text-dark">Today's Activities!</span>
                                <span class="text-muted mt-3 font-weight-bold font-size-sm" id="totalactivities">0 activities</span>
                            </h3>
                        </div>
                        <div class="card-body pt-4" id="activities-body">
                            <div class="text-center" id="activities-loader">
                                <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" style="width: 50px;">
                            </div>
                            <div class="timeline timeline-6 mt-3" id="activities-timeline" style="display: none;"></div>
                            <div class="text-center" id="activities-empty" style="display: none;">
                                <span style="color: #000;text-align:center;font-size: 12px;padding: 80px 0px 0px;font-family: Arial; display:block;">No Activity Found</span>
                            </div>
                            <div class="text-center" id="activities-unauthorized" style="display: none;">
                                <span>You are not authorized</span>
                            </div>
                            <div class="text-center py-3" id="load-more-container" style="display: none;">
                                <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading more" id="load-more-spinner" style="width: 30px; display: none;">
                            </div>
                        </div>
                    </div>
                </div>
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_collection_by_centre'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style" id="collection-by-centre-section">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;" id="collectionbycenter">
                        <div class="card-body p-0">
                            <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                <span class="dashboard-counter text-uppercase">Collection by Centre</span>
                                <ul class="nav nav-tabs d-flex align-items-center">
                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="collection_centre" class="form-control collection_centre" name="type">
                                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                                </option>
                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                                    Week</option>
                                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This
                                                    Month</option>
                                                                                            </select>
                                        </div>
                                    </li>
                                </ul>
                                <div class="flex-column text-right d-none">
                                    <span class="text-dark-75 font-weight-bolder font-size-h3 total-pie-chart"></span>
                                    <span class="text-muted font-weight-bold mt-2 pie-income-title">Weekly
                                        Income</span>
                                </div>
                            </div>
                            <div id="collection-by-centre"></div>
                            <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_unattended_report'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style" style="height: 605px;" id="patient-followup-section">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;overflow-y: hidden;">
                        <div class="card card-custom card-stretch gutter-b" style="min-height: 605px">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1 wrap_unattended_payment">
                                    <span class="dashboard-counter text-uppercase">Unattended Payments</span>
                                    <ul class="nav nav-tabs d-flex align-items-center">
                                        <li style="border-bottom: none;">
                                            <div class="actions action-style p-3 mr-3">
                                                <div class="btn-group">
                                                    <a class="form-control btndropdown btn_Report dashboard_unattended_report"
                                                        href="{{ route('admin.reports.follow_up') }}"> View Report
                                                        <i class="fa fa-angle-right"></i>
                                                    </a>
                                                </div>
                                                <div class="btn-group">
                                                    <a class="form-control btndropdown btn_Report dashboard_unattended_report"
                                                        href="{{ route('admin.follow_up.download') }}"> Download
                                                        <i class="fa fa-download ml-2"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-spacer2" id="unattended-payments-scroll" style="max-height: 400px; overflow-y: auto;">
                                    <div class='table-responsive'>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th class='table-cols'>ID</th>
                                                    <th class='table-cols'>Name</th>
                                                    <th class='table-cols'>Treatment</th>
                                                    <th class='table-cols'>Balance</th>
                                                    <th class='table-cols'>Conversion Date</th>
                                                </tr>
                                            </thead>

                                            <tbody id="patient-follow-up"></tbody>
                                        </table>
                                        <div class="text-center py-2" id="unattended-loader" style="display: none;">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                <span class="sr-only">Loading...</span>
                                            </div>
                                        </div>
                                        <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-unattended" style="width: 50px; display: block; margin: 50px auto;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_centre'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style" id="revenue-by-centre-section">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;" id="revenue_by_centre">
                        <div class="card-body p-0">
                            <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                <span class="dashboard-counter text-uppercase">Revenue by Centre</span>
                                <ul class="nav nav-tabs d-flex align-items-center">
                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="revenue_centre" class="form-control" name="type">
                                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                                </option>
                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                                    Week</option>
                                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This
                                                    Month</option>
                                                                                            </select>
                                        </div>
                                    </li>
                                </ul>
                                <div class="d-none flex-column text-right">
                                    <span class="text-dark-75 font-weight-bolder font-size-h3 total-centre"></span>
                                    <span class="text-muted font-weight-bold mt-2 revenue-centre-title">Today
                                        Revenue</span>
                                </div>
                            </div>
                            <div id="revenue-centre"></div>
                            <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_overdue_treatments'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style" style="height: 605px;" id="patient-followup-onemonth-section">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;overflow-y: hidden;">
                        <div class="card card-custom card-stretch gutter-b" style="min-height: 605px;">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1 wrap_unattended_payment">
                                    <span class="dashboard-counter text-uppercase">Overdue Treatments</span>
                                    <ul class="nav nav-tabs d-flex align-items-center">
                                        <li style="border-bottom: none;">
                                            <div class="actions action-style p-3 mr-3">
                                                <div class="btn-group">
                                                    <a class="form-control btndropdown btn_Report dashboard_overdue_treatments"
                                                        href="{{ route('admin.reports.follow_up') }}"> View Report
                                                        <i class="fa fa-angle-right"></i>
                                                    </a>
                                                </div>
                                                <div class="btn-group">
                                                    <a class="form-control btndropdown btn_Report dashboard_overdue_treatments"
                                                        href="{{ route('admin.monthly_follow_up.download') }}">
                                                        Download
                                                        <i class="fa fa-download ml-2"></i>
                                                    </a>

                                                </div>
                                            </div>
                                        </li>
                                    </ul>

                                </div>
                                <div class="card-spacer2" id="overdue-treatments-scroll" style="max-height: 400px; overflow-y: auto;">
                                    <div class='table-responsive'>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th class='table-cols'>ID</th>
                                                    <th class='table-cols'>Name</th>
                                                    <th class='table-cols'>Balance</th>
                                                    <th class='table-cols'>Last Arrived</th>
                                                </tr>
                                            </thead>
                                            <tbody id="patient-follow-up-one-month"></tbody>
                                        </table>
                                        <div class="text-center py-2" id="overdue-loader" style="display: none;">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                <span class="sr-only">Loading...</span>
                                            </div>
                                        </div>
                                        <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-overdue" style="width: 50px; display: block; margin: 50px auto;">
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_service'))
                <div class="col-lg-6 col-xxl-6 mt-6" id="revenue-service-category-section">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;" id="revenue_by_service_category">
                        <div class="card-body p-0">
                            <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                <span class="dashboard-counter text-uppercase">Revenue by Service Category</span>
                                <ul class="nav nav-tabs d-flex align-items-center">
                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="revenue_service_cate" class="form-control" name="type">
                                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                                </option>
                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                                    Week</option>
                                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This
                                                    Month</option>
                                                <!-- <option value="lastmonth"
                                                        {{ request('type') == 'lastmonth' ? 'selected' : '' }}>Last Month</option> -->
                                            </select>
                                        </div>
                                    </li>
                                </ul>
                                <div class="d-none flex-column text-right">
                                    <span class="text-dark-75 font-weight-bolder font-size-h3 total-category-service"></span>
                                    <span class="text-muted font-weight-bold mt-2 service-category-title"></span>
                                </div>
                            </div>
                            <div id="revenue-service-category"></div>
                            <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-xxl-6 custom_tabs_style mt-6" id="revenue-service-section">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;" id="revenue_by_service">
                        <div class="card-body p-0">
                            <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                <span class="dashboard-counter text-uppercase">Revenue by Service</span>
                                <ul class="nav nav-tabs d-flex align-items-center">
                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="revenue_service" class="form-control" name="type">
                                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                                </option>
                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                                    Week</option>
                                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This
                                                    Month</option>
                                                                                            </select>
                                        </div>
                                    </li>
                                </ul>
                                <div class="d-none flex-column text-right">
                                    <span class="text-dark-75 font-weight-bolder font-size-h3 total-service"></span>
                                    <span class="text-muted font-weight-bold mt-2 service-title"></span>
                                </div>
                            </div>
                            <div id="revenue-service"></div>
                            <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_appointment_by_status'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style" id="consultancy-status-section">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;" id="consultancy_status1">
                        <div class="card-body p-0">
                            <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                <span class="dashboard-counter text-uppercase">Consultancy by Status</span>
                                <ul class="nav nav-tabs d-flex align-items-center">
                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="consultancy_status" class="form-control" name="type">
                                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                                </option>
                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                                    Week</option>
                                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This
                                                    Month</option>
                                                                                            </select>
                                        </div>
                                    </li>
                                </ul>
                                <div class="d-none flex-column text-right">
                                    <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                                    <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                                </div>
                            </div>
                            <div id="consultancy_by_status"></div>
                            <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_appointment_by_type'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style" id="treatment-status-section">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;" id="treatment_status1">
                        <div class="card-body p-0">
                            <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                <span class="dashboard-counter text-uppercase">Treatment by Status</span>
                                <ul class="nav nav-tabs d-flex align-items-center custom_hover_effect">
                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="treatment_status" class="form-control" name="type">
                                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                                </option>
                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                                    Week</option>
                                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This
                                                    Month</option>
                                                                                            </select>
                                        </div>
                                    </li>
                                </ul>
                                <div class="d-none flex-column text-right">
                                    <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                                    <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                                </div>
                            </div>
                            <div id="treatment_by_status"></div>
                            <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_staff_wise_arrival'))
                <div class="col-lg-12 col-xxl-12 custom_tabs_style" id="staff_wise_arrival">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                        <div class="card-body p-0">
                            <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                <span class="dashboard-counter text-uppercase">Centre Wise Arrival</span>
                                <ul class="nav nav-tabs d-flex align-items-center wise_arrival_ul">
                                    <li style="border-bottom: none;">
                                        <div class="actions action-style p-3 mr-3">
                                            @if ($isAdmin)
                                            <div class="btn-group">
                                                <select class="dropdown-menu dropdown-menu-right centre_name_ul" id="centervise_center">
                                                    <option data-period="thismonth" value="All">All Centres</option>
                                                    @foreach ($centres as $centre)
                                                    <option data-period="thismonth" value="{{ $centre->id }}">{{ $centre->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            @elseif($isCSRRole)
                                            <div class="btn-group">
                                                <select class="dropdown-menu dropdown-menu-right" id="userwise_arrival">
                                                    <option onclick="initUserWiseArrival('thismonth', 'All')" value="">All</option>
                                                    @foreach ($csrUsers as $user)
                                                    <option onclick="initUserWiseArrival('thismonth', {{ $user->id }})" value="{{ $user->id }}" data-period="thismonth">{{ $user->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            @else
                                            <div class="btn-group">
                                                <select name="" id="centervise_center">
                                                    <option value="" class="btn form-control btndropdown btn_Report centre_name arrivalbtn">
                                                        {{ $firstCentre ? $firstCentre->name : 'No Centre Assigned' }}
                                                    </option>
                                                </select>
                                            </div>
                                            @endif
                                        </div>
                                    </li>
                                    @if ($isCSRRole)
                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="center_wise_arrival" class="form-control" name="type">


                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                                    Week</option>
                                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This
                                                    Month</option>
                                                <option value="lastmonth" {{ request('type')=='lastmonth' ? 'selected' : '' }}>Last Month</option>
                                            </select>
                                        </div>
                                    </li>
                                    @else
                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="initCentreWiseArrival" class="form-control" name="type">


                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                                    Week</option>
                                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This
                                                    Month</option>
                                                <option value="lastmonth" {{ request('type')=='lastmonth' ? 'selected' : '' }}>Last Month</option>
                                            </select>
                                        </div>
                                    </li>
                                    @endif
                                </ul>
                                <div class="d-none flex-column text-right">
                                    <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                                    <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                                </div>
                            </div>
                            <div class="row pt-7">
                                <div class="col-7">
                                    <div id="centre_wise_arrival"></div>
                                </div>
                                <div class="col-5 centre_wise_arrival_wrap">
                                    <div class="row" id="centre_wise_arrival_02">
                                        <div class='table-responsive' style="overflow-y: scroll; height: 475px;">
                                            <table class='table'>
                                                <thead>
                                                    @if ($isCSRRole)
                                                    <tr>
                                                        <th class='table-cols'>CSR Name</th>
                                                        <th class='table-cols'>Arrived</th>
                                                        <th class='table-cols'>Percentage</th>
                                                    </tr>
                                                    @else
                                                    <tr>
                                                        <th class='table-cols'></th>
                                                        <th class='table-cols'>Arrived</th>
                                                        <th class='table-cols'>WalkIn</th>
                                                        <th class='table-cols'>Percentage</th>
                                                    </tr>
                                                    @endif
                                                </thead>
                                                <tbody id="table-body"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_doctor_wise_conversion'))
                <div class="col-lg-12 col-xxl-12 custom_tabs_style" id="doctor_wise_conversion_section">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                        <div class="card-body p-0">
                            <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                <span class="dashboard-counter text-uppercase">Doctor Wise Conversion</span>
                                <ul class="nav nav-tabs d-flex align-items-center  doc_wise_arrival_ul">
                                    <li style="border-bottom: none;">
                                        <div class="actions action-style p-3 mr-3">
                                            <div class="btn-group">
                                                <select class="form-control btndropdown btn_Report doctorwiseconversion selectcenter"
                                                    data-placeholder="Select Centre" data-dropdown-css-class="select2-dropdown">
                                                    @if ($hasMultipleCentres)
                                                    <option value="all" data-period="thismonth">All Centres</option>
                                                    @endif
                                                    @foreach ($centres as $centre)
                                                    <option value="{{ $centre->id }}" data-period="thismonth">{{ $centre->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </li>
                                    <li style="border-bottom: none;">
                                        <div class="actions action-style p-3 mr-3">
                                            <div class="btn-group">
                                                {{-- <a data-id="all-docs" class="btn form-control btndropdown btn_Report doctorname" href="javascript:;"
                                                    data-toggle="dropdown" data-hover="dropdown" data-close-others="true" aria-expanded="false" id="all_docs">
                                                    All Doctors
                                                    <i class="fa fa-angle-down"></i>
                                                </a> --}}
                                                <select class="form-control btndropdown btn_Report doctorname" data-dropdown-css-class="select2-dropdown"
                                                    id="doc_nav">
                                                </select>
                                            </div>
                                        </div>
                                    </li>
                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="dr_wise_con" class="form-control" name="type">
                                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                                </option>
                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                                    Week</option>
                                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This
                                                    Month</option>
                                                {{-- <option value="lastmonth" {{ request('type')=='lastmonth' ? 'selected' : '' }}>Last Month</option> --}}
                                            </select>
                                        </div>
                                    </li>
                                </ul>

                                <div class="d-none flex-column text-right">
                                    <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                                    <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                                </div>
                            </div>

                            <div class="row pt-7">
                                <div class="col-7" style="overflow-x: auto;">
                                    <div id="doc_wise_conversion"></div>
                                </div>
                                <div class="col-5 appenddoctorlist" id="centre_wise_arrival_02">
                                    <div class='table-responsive' style="overflow-y: scroll; height: 475px;">
                                        <table class='table'>
                                            <thead>
                                                <tr>
                                                    <th class='table-cols'></th>
                                                    <th class='table-cols'>Con. Ratio</th>
                                                    <th class='table-cols'>% avg</th>
                                                    <th class='table-cols'>Avg Value</th>
                                                </tr>
                                            </thead>
                                            <tbody id="categories-table-body"></tbody>
                                        </table>
                                    </div>
                                </div>
                                <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_doctor_wise_feedback'))
                <div class="col-lg-12 col-xxl-12 custom_tabs_style" id="doctor_wise_feedback_section">
                    <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                        <div class="card-body p-0">
                            <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                <span class="dashboard-counter text-uppercase">Doctor Ratings - Based on Client Insight</span>
                                <ul class="nav nav-tabs d-flex align-items-center  doc_feedback_ul">
                                    <li style="border-bottom: none;">
                                        <div class="actions action-style p-3 mr-3">
                                            <div class="btn-group">
                                                <select class="form-control btndropdown btn_Report doctorwisefeedback selectcenterfeedback"
                                                    data-placeholder="Select Centre" data-dropdown-css-class="select2-dropdown">
                                                    @if ($hasMultipleCentres)
                                                    <option value="all" data-period="thismonth">All Centres</option>
                                                    @endif
                                                    @foreach ($centres as $centre)
                                                    <option value="{{ $centre->id }}" data-period="thismonth">{{ $centre->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </li>

                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="dr_wise_fed" class="form-control" name="type">
                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                                <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                                                <option value="all" {{ request('type')=='all' ? 'selected' : '' }}>Life Time</option>
                                            </select>
                                        </div>
                                    </li>
                                </ul>

                                <div class="d-none flex-column text-right">
                                    <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                                    <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                                </div>
                            </div>

                            <div class="row pt-7">
                                <div class="col-12" style="overflow-x: auto; overflow-y: hidden;">
                                    <div id="doc_wise_feedback_data"></div>
                                </div>

                                <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-attended">
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_upselling_report'))
                    <div class="col-lg-12 col-xxl-12 custom_tabs_style" id="doctor_upselling_section">
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    <span class="dashboard-counter text-uppercase">Doctor Upselling</span>
                                    <ul class="nav nav-tabs d-flex align-items-center  doc_upselling_ul">
                                    <li style="border-bottom: none;">
                                        <div class="actions action-style p-3 mr-3">
                                            <div class="btn-group">
                                                <select class="form-control btndropdown btn_Report doctorUpselling selectcenterupselling"
                                                    id="doctor_upselling_centre_select"
                                                    data-placeholder="Select Centre" data-dropdown-css-class="select2-dropdown">
                                                    @if ($hasMultipleCentres)
                                                    <option value="all" data-period="thismonth" selected>All Centres</option>
                                                    @endif
                                                    @foreach ($centres as $centre)
                                                    <option value="{{ $centre->id }}" data-period="thismonth" {{ (!$hasMultipleCentres && count($centres) == 1) ? 'selected' : '' }}>{{ $centre->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </li>

                                        <li style="border-bottom: none;">
                                            <div class="actions date_action_dropdown action-style py-3 mr-0">
                                                <select id="dr_wise_upselling_period" class="form-control" name="type">
                                                    <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today</option>
                                                    <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option>
                                                    <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                    <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This Week</option>
                                                    <option value="thismonth" {{ request('type')=='thismonth' ? 'selected' : '' }}>This Month</option>
                                                    <option value="lastmonth" {{ request('type')=='lastmonth' ? 'selected' : '' }}>Last Month</option>
                                                </select>
                                            </div>
                                        </li>
                                    </ul>

                                    <div class="d-none flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                                        <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                                    </div>
                                </div>

                                <div class="row pt-7" style="position: relative;">
                                    <div class="col-7">
                                        <div id="doctor_upselling_chart" style="min-height: 400px;">
                                            <div class="d-flex align-items-center justify-content-center" style="height: 400px;" id="doctor_upselling_placeholder">
                                                <div class="text-center text-muted">
                                                    <i class="fas fa-info-circle mb-2"></i><br>
                                                    Select a centre to view doctor upselling data
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-5">
                                        <div class="table-responsive" style="overflow-y: scroll; height: 475px;">
                                            <table class="table" id="doctor_upselling_table">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th class="text-left">Doctor Name</th>
                                                        <th class="text-right">Upselling Amount</th>
                                                        
                                                    </tr>
                                                </thead>
                                                <tbody id="doctor_upselling_tbody">
                                                    <tr id="no_data_row">
                                                        <td colspan="3" class="text-center text-muted py-5">
                                                            <i class="fas fa-info-circle mb-2"></i><br>
                                                            Select a centre to view data
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tfoot id="doctor_upselling_tfoot" style="display: none;">
                                                    <tr class="font-weight-bold bg-light">
                                                        <td>Total</td>
                                                        <td class="text-right" id="total_upselling_amount">0.00</td>
                                                        
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                    <img src="{{ asset('assets/media/loader.gif') }}" alt="Loading" class="custom_loader loader-img-upselling" style="height: 60px;">
                                </div>
                            </div>
                        </div>
                    </div>

                @endif
            </div>
        </div>
    </div>
</div>

@push('datatable-js')
<script src="{{ asset('assets/js/pages/crud/forms/validation/appointment/validation.js') }}"></script>
<script src="{{ asset('assets/js/pages/dashboard/datatable.js') }}"></script>
<script src="{{ asset('assets/js/jsapi.js') }}"></script>
<script src="{{ asset('assets/js/pie.js') }}"></script>
<script src="{{ asset('assets/js/home.js') }}"></script>
<script>
// Dashboard configuration for lazy loading and routes
window.dashboardConfig = {
    requestType: '{{ $requestType ?? 'today' }}',
    locationIds: {!! json_encode($location_id) !!},
    startDate: '{{ $today ?? '' }}',
    endDate: '{{ $today ?? '' }}',
    isCSR: {{ auth()->user()->hasRole('CSR') ? 'true' : 'false' }},
    isCSRSupervisor: {{ auth()->user()->hasRole('CSR Supervisor') ? 'true' : 'false' }},
    isSocialLead: {{ auth()->user()->hasRole('Social Lead') ? 'true' : 'false' }},
    routes: {
        doctorUpsellingData: '/api/dashboard/doctor-upselling-data'
    }
};
</script>
<script src="{{ asset('assets/js/dashboard.js') }}"></script>
<script src="{{ asset('assets/js/dashboard-charts.js') }}"></script>
@endpush
@endsection
