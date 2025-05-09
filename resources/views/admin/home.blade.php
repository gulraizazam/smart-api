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
                                            <span class="dashboard-counter" id="allleads">{{ isset($todaycollection[0]) && $todaycollection[0] !== false ?
                                                'PKR:' . $todaycollection[0] : 'Your are not authorized' }}</span>
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
                                            <span class="dashboard-counter" id="allrevenue">{{ !is_null($revenue) ? 'PKR: ' . number_format($revenue) : 'Your
                                                are not authorized' }}</span>
                                        </span>
                                        <a href="javascript:void(0);" style="cursor: pointer;" class="text-warning font-weight-bold font-size-h6">Revenue
                                            Consumed</a>
                                    </div>
                                </div>
                                <div class="row m-0">
                                    <div class="col bg-light-primary px-6 py-8 rounded-xl ">
                                        <span class="svg-icon svg-icon-3x svg-icon-primary d-block my-2">
                                            <i class="la la-stethoscope" style="font-size: 40px;"></i>
                                            <span class="dashboard-counter" id="allconsult">{{ !is_null($done_consultancies) && !is_null($all_consultancies) ?
                                                $done_consultancies . '/' . $all_consultancies : 'Your are not authorized' }}</span>
                                        </span>
                                        @if (!is_null($done_consultancies) && !is_null($all_consultancies))
                                        <a id="allconsultantdate"
                                            href="{{ route('admin.consultancy.index', ['type' => '1', 'from' => $start_date, 'to' => $end_date, 'center_id' => implode(',', $location_id)]) }}"
                                            class="text-primary font-weight-bold font-size-h6 mt-2">Consultancies</a>
                                        @else
                                        <a href="javascript:void(0);" class="text-primary font-weight-bold font-size-h6 mt-2">Consultancies</a>
                                        @endif
                                    </div>
                                    <div class="col bg-light-danger px-6 py-8 rounded-xl ml-7">
                                        <span class="svg-icon svg-icon-3x svg-icon-danger d-block my-2">
                                            <i class="la la-medkit" style="font-size: 40px;"></i>
                                            <span class="dashboard-counter" id="alltreat">{{ !is_null($done_treatments) && !is_null($all_treatments) ?
                                                $done_treatments . '/' . $all_treatments : 'Your are not authorized' }}</span>
                                        </span>
                                        @if (!is_null($done_treatments) && !is_null($all_treatments))
                                        <a id="alltreatmentdate"
                                            href="{{ route('admin.treatment.index', ['type' => '2', 'from' => $start_date, 'to' => $end_date, 'center_id' => implode(',', $location_id)]) }}"
                                            class="text-danger font-weight-bold font-size-h6 mt-2">Treatments</a>
                                        @else
                                        <a href="javascript:void(0);" class="text-danger font-weight-bold font-size-h6 mt-2">Treatments</a>
                                        @endif
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
                    <div class="card card-custom card-stretch gutter-b" style="height: 600px; overflow-y: auto;">
                        <div class="card-header align-items-center border-0 mt-4">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="font-weight-bolder text-dark">Today's Activities!</span>
                                <span class="text-muted mt-3 font-weight-bold font-size-sm" id="totalactivities">0
                                    activities</span>
                            </h3>
                        </div>
                        <div class="card-body pt-4">
                            @if (isset($unauthorized))
                            <div class="text-center">
                                <span>Your are not authorized</span>
                            </div>
                            @else
                            <img src="{{ asset('assets/media/loader.gif') }}" class="loader-img">
                            <div class="text-center">
                                {{-- <span>No Activity Found</span> --}}
                                <span style="color: #000;text-align:center;font-size: 12px;padding: 80px 0px 0px;font-family: Arial;">No Activity Found</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_collection_by_centre'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style">
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
                                                <!-- <option value="lastmonth"
                                                            {{ request('type') == 'lastmonth' ? 'selected' : '' }}>Last Month</option> -->
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
                            <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_unattended_report'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style" style="height: 605px;" id="unattended_payments">
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
                                <div class="card-spacer2 table_v_scroll">
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
                                        <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-unattended">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_centre'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style">
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
                                                <!-- <option value="lastmonth"
                                                            {{ request('type') == 'lastmonth' ? 'selected' : '' }}>Last Month</option> -->
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
                            <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_overdue_treatments'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style" style="height: 605px;" id="attended_payments">
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
                                <div class="card-spacer2 table_v_scroll">
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
                                        <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-attended">
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_service'))
                <div class="col-lg-6 col-xxl-6 mt-6">
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
                            <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-xxl-6 custom_tabs_style mt-6">
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
                                                <!-- <option value="lastmonth"
                                                            {{ request('type') == 'lastmonth' ? 'selected' : '' }}>Last Month</option> -->
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
                            <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_appointment_by_status'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style">
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
                                                <!-- <option value="lastmonth"
                                                            {{ request('type') == 'lastmonth' ? 'selected' : '' }}>Last Month</option> -->
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
                            <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-attended">
                        </div>
                    </div>
                </div>
                @endif
                @if (\Illuminate\Support\Facades\Gate::allows('dashboard_appointment_by_type'))
                <div class="col-lg-6 col-xxl-6 custom_tabs_style">
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
                                                <!-- <option value="lastmonth"
                                                            {{ request('type') == 'lastmonth' ? 'selected' : '' }}>Last Month</option> -->
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
                            <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-attended">
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
                                            @if (Auth::user()->hasRole('Administrator') ||
                                            Auth::user()->hasRole('Super-Admin') ||
                                            Auth::user()->hasRole('Head of Operations') ||
                                            Auth::user()->hasRole('Finance') ||
                                            Auth::user()->hasRole('HRM'))
                                            @php
                                            $centres_array = ['All South Region', 'All Central Region', 'All Centres'];
                                            $locations = \App\Helpers\ACL::getUserCentres();
                                            $centres = \App\Models\Locations::whereIn('id', $locations)
                                            ->whereNotIn('name', $centres_array)
                                            ->where('active', 1)
                                            ->get();
                                            @endphp
                                            <div class="btn-group">
                                                {{-- <a data-id="" class="btn form-control btndropdown btn_Report centre_name arrivalbtn" href="javascript:;"
                                                    data-toggle="dropdown" data-hover="dropdown" data-close-others="true" aria-expanded="false"> All Centres
                                                    <i class="fa fa-angle-down"></i>
                                                </a> --}}
                                                <select class="dropdown-menu dropdown-menu-right centre_name_ul" id="centervise_center">
                                                    <option data-period="thismonth" value="All">All Centres</a>
                                                    </option>
                                                    @foreach ($centres as $centre)
                                                    <option data-period="thismonth" value="{{ $centre->id }}">{{ $centre->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            @elseif(Auth::user()->hasRole('CSR Supervisor') || Auth::user()->hasRole('Social Lead') ||
                                            Auth::user()->hasRole('CSR'))
                                            @php
                                            $all_csr = \App\Models\RoleHasUsers::whereIn('role_id', [2, 3, 24])->pluck('user_id');
                                            $csr_users = \App\Models\User::whereIn('id', $all_csr)
                                            ->where('active', 1)
                                            ->get();

                                            @endphp
                                            <div class="btn-group">
                                                {{-- <a data-id="All" class="btn form-control btndropdown btn_Report centre_name arrivalbtn" href="javascript:;"
                                                    data-toggle="dropdown" data-hover="dropdown" data-close-others="true" aria-expanded="false">All
                                                    <i class="fa fa-angle-down"></i>
                                                </a> --}}
                                                <select class="dropdown-menu dropdown-menu-right" id="userwise_arrival">
                                                    <option onclick="initUserWiseArrival('thismonth', 'All')" value="">All
                                                    </option>
                                                    @foreach ($csr_users as $user)
                                                    <option onclick="initUserWiseArrival('thismonth', {{ $user->id }})" value="{{ $user->id }}"
                                                        data-period="thismonth">{{ $user->name }}</a>
                                                    </option>
                                                    @endforeach

                                                </select>
                                            </div>
                                            @else
                                            @php
                                            $centres_array = ['All South Region', 'All Central Region'];
                                            $locations = \App\Helpers\ACL::getUserCentres();
                                            $centres = \App\Models\Locations::whereIn('id', $locations)
                                            ->whereNotIn('name', $centres_array)
                                            ->first();
                                            @endphp
                                            <div class="btn-group">
                                                <select name="" id="centervise_center">
                                                    <option value="" class="btn form-control btndropdown btn_Report centre_name arrivalbtn" href="javascript:;"
                                                        data-toggle="dropdown" data-hover="dropdown" data-close-others="true" aria-expanded="false">
                                                        {{ $centres ? $centres->name : 'No Centre Assigned' }}
                                                    </option>
                                                </select>

                                            </div>
                                            @endif
                                        </div>
                                    </li>
                                    @if (Auth::user()->hasRole('CSR Supervisor') || Auth::user()->hasRole('Social Lead') || Auth::user()->hasRole('CSR'))
                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="center_wise_arrival" class="form-control" name="type">
                                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                                </option>
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
                                                <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                                </option>
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
                                                    @if (Auth::user()->hasRole('CSR Supervisor') || Auth::user()->hasRole('Social Lead') ||
                                                    Auth::user()->hasRole('CSR'))
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
                                <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-attended">
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
                                            @php
                                            $centres_array = ['All South Region', 'All Central Region', 'All Centres'];
                                            $locations = \App\Helpers\ACL::getUserCentres();
                                            $centres = \App\Models\Locations::whereIn('id', $locations)
                                            ->whereNotIn('name', $centres_array)
                                            ->where('active', 1)
                                            ->get();
                                            @endphp
                                            <div class="btn-group">
                                                {{-- @if (Auth::user()->hasRole('Administrator') ||
                                                Auth::user()->hasRole('Super-Admin') ||
                                                Auth::user()->hasRole('Head of Operations') ||
                                                Auth::user()->hasRole('Finance'))
                                                <a class="btn form-control btndropdown btn_Report doctorwiseconversion" href="javascript:;"
                                                    data-toggle="dropdown" data-hover="dropdown" data-close-others="true" aria-expanded="false" data-id="all">
                                                    All Centres
                                                    <i class="fa fa-angle-down"></i>
                                                </a>
                                                @else
                                                <a class="btn form-control btndropdown btn_Report doctorwiseconversion" href="javascript:;"
                                                    data-toggle="dropdown" data-hover="dropdown" data-close-others="true" aria-expanded="false"
                                                    data-id="{{ count($centres) > 0 ? $centres[0]->id : '' }}">
                                                    {{ count($centres) > 0 ? $centres[0]->name : 'No Centre Assigned' }}
                                                    <i class="fa fa-angle-down"></i>
                                                </a>
                                                @endif --}}
                                                <select class="form-control btndropdown btn_Report doctorwiseconversion selectcenter"
                                                    data-placeholder="Select Centre" data-dropdown-css-class="select2-dropdown">
                                                    @if (Auth::user()->hasRole('Administrator') ||
                                                    Auth::user()->hasRole('Super-Admin') ||
                                                    Auth::user()->hasRole('Head of Operations') ||
                                                    Auth::user()->hasRole('Finance'))
                                                    <option value="all" data-period="thismonth" {{-- onclick="changeCenterDoct('thismonth', 'all')" --}}>
                                                        {{-- <a class="dropdown-item doctor_wise_centre_id" data-id="all"></a> --}}
                                                        All
                                                        Centres
                                                    </option>
                                                    @endif
                                                    @foreach ($centres as $centre)
                                                    <option value="{{ $centre->id }}" data-period="thismonth">
                                                        {{-- <a class="dropdown-item doctor_wise_centre_id" data-id="{{ $centre->id }}"></a> --}}
                                                        {{ $centre->name }}
                                                    </option>
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
                                <div class="col-7">
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
                                <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-attended">
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
                                <span class="dashboard-counter text-uppercase">Doctors Feedback</span>
                                <ul class="nav nav-tabs d-flex align-items-center  doc_feedback_ul">
                                    <li style="border-bottom: none;">
                                        <div class="actions action-style p-3 mr-3">
                                            @php
                                            $centres_array = ['All South Region', 'All Central Region', 'All Centres'];
                                            $locations = \App\Helpers\ACL::getUserCentres();
                                            $centres = \App\Models\Locations::whereIn('id', $locations)
                                            ->whereNotIn('name', $centres_array)
                                            ->where('active', 1)
                                            ->get();
                                            @endphp
                                            <div class="btn-group">
                                                {{-- @if (Auth::user()->hasRole('Administrator') ||
                                                Auth::user()->hasRole('Super-Admin') ||
                                                Auth::user()->hasRole('Head of Operations') ||
                                                Auth::user()->hasRole('Finance'))
                                                <a class="btn form-control btndropdown btn_Report doctorwisefeedback" href="javascript:;"
                                                    data-toggle="dropdown" data-hover="dropdown" data-close-others="true" aria-expanded="false" data-id="all">
                                                    All Centres
                                                    <i class="fa fa-angle-down"></i>
                                                </a>
                                                @else
                                                <a class="btn form-control btndropdown btn_Report doctorwisefeedback" href="javascript:;"
                                                    data-toggle="dropdown" data-hover="dropdown" data-close-others="true" aria-expanded="false"
                                                    data-id="{{ count($centres) > 0 ? $centres[0]->id : '' }}">
                                                    {{ count($centres) > 0 ? $centres[0]->name : 'No Centre Assigned' }}
                                                    <i class="fa fa-angle-down"></i>
                                                </a>
                                                @endif --}}
                                                <select class="form-control btndropdown btn_Report doctorwisefeedback selectcenterfeedback"
                                                    data-placeholder="Select Centre" data-dropdown-css-class="select2-dropdown">
                                                    @if (Auth::user()->hasRole('Administrator') ||
                                                    Auth::user()->hasRole('Super-Admin') ||
                                                    Auth::user()->hasRole('Head of Operations') ||
                                                    Auth::user()->hasRole('Finance'))
                                                    <option value="all" data-period="thismonth" {{-- onclick="changeCenterDoct('thismonth', 'all')" --}}>
                                                        {{-- <a class="dropdown-item doctor_wise_feedback_centre_id" data-id="all"></a> --}}
                                                        All
                                                        Centres
                                                    </option>
                                                    @endif
                                                    @foreach ($centres as $centre)
                                                    <option value="{{ $centre->id }}" data-period="thismonth">
                                                        {{-- <a class="dropdown-item doctor_wise_feedback_centre_id" data-id="{{ $centre->id }}"></a> --}}
                                                        {{ $centre->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </li>

                                    <li style="border-bottom: none;">
                                        <div class="actions date_action_dropdown action-style py-3 mr-0">
                                            <select id="dr_wise_fed" class="form-control" name="type">
                                                 <!-- <option value="today" {{ request('type')=='today' ? 'selected' : '' }}>Today
                                                </option>
                                                <option value="yesterday" {{ request('type')=='yesterday' ? 'selected' : '' }}>Yesterday</option> -->
                                                <!-- <option value="last7days" {{ request('type')=='last7days' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="week" {{ request('type')=='week' ? 'selected' : '' }}>This
                                                    Week</option> -->
                                                <option value="all" {{ request('type')=='thismonth' ? 'selected' : '' }}>Life Time
                                                    </option>
                                                <option value="thismonth" {{ request('type')=='lastmonth' ? 'selected' : '' }}>This Month</option>
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
                                <div class="col-12">
                                    <div id="doc_wise_feedback_data"></div>
                                </div>

                                <img src="{{ asset('assets/media/loader.gif') }}" class="custom_loader loader-img-attended">
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
    jQuery('.btn.arrivalbtn + .dropdown-menu li a').on('click', function() {
                var dataID = jQuery(this).attr('data-id');
                var dataText = jQuery(this).text();
                jQuery('.btn.arrivalbtn').attr('data-id', dataID);
                jQuery('.btn.arrivalbtn').html(dataText + '<i class="fa fa-angle-down"></i>')
                jQuery('.wise_arrival_ul li a').removeClass('active');
                jQuery('.wise_arrival_ul li.thismonth a').addClass('active');
            });
            jQuery('.btn.doctorwiseconversion + .dropdown-menu li a').on('click', function() {
                var dataID = jQuery(this).attr('data-id');
                var dataText = jQuery(this).text();
                jQuery('.btn.doctorwiseconversion').attr('data-id', dataID);
                jQuery('.btn.doctorwiseconversion').html(dataText + '<i class="fa fa-angle-down"></i>')
                jQuery('.doc_wise_arrival_ul li a').removeClass('active');
                jQuery('.doc_wise_arrival_ul li.thismonth a').addClass('active');
            });
            jQuery('.btn.doctorwisefeedback + .dropdown-menu li a').on('click', function() {
                var dataID = jQuery(this).attr('data-id');
                var dataText = jQuery(this).text();
                jQuery('.btn.doctorwisefeedback').attr('data-id', dataID);
                jQuery('.btn.doctorwisefeedback').html(dataText + '<i class="fa fa-angle-down"></i>')
                jQuery('.doc_wise_arrival_ul li a').removeClass('active');
                jQuery('.doc_wise_arrival_ul li.thismonth a').addClass('active');
            });
            $(document).ready(function() {
                period = "today";
                // activities
                $.ajax({
                    url: route('admin.home.getactivity'),
                    type: "GET",
                    data: {
                        'type': period
                    },
                    cache: false,
                    success: function(response) {
                        $('.loader-img').css('display', "none");
                        $("#activitydiv").html(response);
                    },
                });

                @if (Auth::user()->hasRole('CSR Supervisor') || Auth::user()->hasRole('Social Lead')){
                    var centre_id = $(".doctorwiseconversion").attr('data-id');
                    initUserWiseArrival('today', '', 'firsttime');
                    @if (!auth()->user()->hasRole('CSR'))
                    initDoctorWiseConversion('today', centre_id, 'firsttime');
                    @endif
                }
                @else
                    var centre_id = $(".doctorwiseconversion").attr('data-id');
                    initCentreWiseArrival('today', '', 'firsttime');
                    @if (!auth()->user()->hasRole('CSR'))
                    initDoctorWiseConversion('today', centre_id, 'firsttime');
                    @endif
                @endif

                    var centre_id = $(".doctorwisefeedback").attr('data-id');

                    @if (!auth()->user()->hasRole('CSR'))
                    initDoctorWiseFeedback('today', centre_id, 'firsttime');
                    @endif


            });

            var collection_by_center = false;
            var revenue_by_center = false;
            var revenue_by_service = false;
            var revenue_by_service_category = false;
            var collection_by_service_category = false;
            var consultancy_by_status = false;
            var treatment_by_status = false;
            var centre_wise_arrival = false;
            var patient_follow_up = false;
            var patient_follow_up_one_month = false;
            $(window).scroll(function() {
                if (($(window).scrollTop() >= ($(document).height() - $(window).height()) * 0.07) && ! collection_by_center) {
                    collection_by_center = true;
                    var currentVal = $('#collection_centre').val();
                    initCollectionByCentre($('#collection_centre').val());
                }
                if (($(window).scrollTop() >= ($(document).height() - $(window).height()) * 0.10) && ! revenue_by_center) {
                    revenue_by_center = true;
                    initRevenueByCentre($('#revenue_centre').val());
                }

                if (($(window).scrollTop() >= ($(document).height() - $(window).height()) * 0.15) && ! revenue_by_service_category) {
                    revenue_by_service_category = true;
                    @if (!auth()->user()->hasRole('CSR'))
                        InitRevenueByServiceCategory($('#revenue_service_cate').val());
                    @endif
                }

                if (($(window).scrollTop() >= ($(document).height() - $(window).height()) * 0.20) && ! revenue_by_service) {
                    revenue_by_service = true;
                    @if (!auth()->user()->hasRole('CSR'))
                    initRevenueByService($('#revenue_service').val());
                    @endif
                }

                if (($(window).scrollTop() >= ($(document).height() - $(window).height()) * 0.24) && !  collection_by_service_category) {
                    collection_by_service_category = true;
                    $.ajax({
                        url: route('admin.home.CollectionByServiceCategory'),
                        type: "GET",
                        data: {
                            'type': '{{ request('type') }}'
                        },
                        cache: false,
                        success: function(response) {
                            let colors = response.data.colors;
                            let total = response.data.total;

                            @if (request('type') == 'today')
                                var pie = response.data.pie.today;
                            @endif
                            @if (request('type') == 'yesterday')
                                var pie = response.data.pie.yesterday;
                            @endif
                            @if (request('type') == 'week')
                                var pie = response.data.pie.week;
                            @endif
                            @if (request('type') == 'thismonth')
                                var pie = response.data.pie.month;
                            @endif
                            @if (request('type') == '')
                                var pie = response.data.pie.today;
                            @endif
                            CollectionByServiceCategory(pie, colors);
                        },
                        error: function(xhr, ajaxOptions, thrownError) {
                            errorMessage(xhr);
                        }
                    });
                }

                if (($(window).scrollTop() >= ($(document).height() - $(window).height()) * 0.30) && !
                    consultancy_by_status) {
                    consultancy_by_status = true;
                    $.ajax({
                        url: route('admin.dashboard.appointment_by_status'),
                        type: "GET",
                        data: {
                            // 'period': '{{ request('type') }}',
                            'period':$('#consultancy_status').val(),
                            'type': '1'
                        },
                        cache: false,
                        success: function(response) {
                            $("#consultancy_status1 .loader-img-attended").css('display', 'none');
                            $("#consultancy_status1 #consultancy_by_status").css('display', '');
                            let colors = response.data.colors;
                            @if (request('type') == 'today')
                                var pie = response.data.pie.today;
                            @endif
                            @if (request('type') == 'yesterday')
                                var pie = response.data.pie.yesterday;
                            @endif
                            @if (request('type') == 'week')
                                var pie = response.data.pie.week;
                            @endif
                            @if (request('type') == 'thismonth')
                                var pie = response.data.pie.month;
                            @endif
                            @if (request('type') == '')
                                var pie = response.data.pie.today;
                            @endif
                            setTimeout(() => {
                                ConsultancyByStatus(pie, colors);
                            }, 500);

                        },
                        error: function(xhr, ajaxOptions, thrownError) {
                            errorMessage(xhr);
                        }
                    });
                }

                if (($(window).scrollTop() >= ($(document).height() - $(window).height()) * 0.30) && !
                    treatment_by_status) {
                    treatment_by_status = true;
                    $.ajax({
                        url: route('admin.dashboard.appointment_by_status'),
                        type: "GET",
                        data: {
                            // 'period': '{{ request('type') }}',
                            'period':$('#treatment_status').val(),
                            'type': '2'
                        },
                        cache: false,
                        success: function(response) {
                            $("#treatment_status1 .loader-img-attended").css('display', 'none');
                            $("#treatment_status1 #treatment_by_status").css('display', '');
                            let colors = response.data.colors;
                            @if (request('type') == 'today')
                                var pie = response.data.pie.today;
                            @endif
                            @if (request('type') == 'yesterday')
                                var pie = response.data.pie.yesterday;
                            @endif
                            @if (request('type') == 'week')
                                var pie = response.data.pie.week;
                            @endif
                            @if (request('type') == 'thismonth')
                                var pie = response.data.pie.month;
                            @endif
                            @if (request('type') == '')
                                var pie = response.data.pie.today;
                            @endif
                            setTimeout(() => {
                                TreatmentByStatus(pie, colors);
                            }, 500);
                        },
                        error: function(xhr, ajaxOptions, thrownError) {
                            errorMessage(xhr);
                        }
                    });
                }

                if (($(window).scrollTop() >= ($(document).height() - $(window).height()) * 0.07) && !
                    patient_follow_up) {
                    patient_follow_up = true;
                    initPatientFollowUp('thismonth', '');
                }

                if (($(window).scrollTop() >= ($(document).height() - $(window).height()) * 0.07) && !
                    patient_follow_up_one_month) {
                    patient_follow_up_one_month = true;
                    initPatientFollowUpOneMonth();
                }
            });

            function TreatmentByStatus(pie, colors) {
                google.load('visualization', '1', {
                    packages: ['corechart', 'bar', 'line']
                });
                google.setOnLoadCallback(function() {
                    var data = google.visualization.arrayToDataTable(pie);
                    var options = {
                        colors: colors
                    };
                    var chart = new google.visualization.PieChart(document.getElementById('treatment_by_status'));
                    chart.draw(data, options);
                });
                if (pie.length > 1) {
                    $("#treatment_by_status").css("height", "500px");
                }
            }

            function ConsultancyByStatus(pie, colors) {
                google.load('visualization', '1', {
                    packages: ['corechart', 'bar', 'line']
                });
                google.setOnLoadCallback(function() {
                    var data = google.visualization.arrayToDataTable(pie);
                    var options = {
                        colors: colors
                    };
                    var chart = new google.visualization.PieChart(document.getElementById('consultancy_by_status'));
                    chart.draw(data, options);
                });
                if (pie.length > 1) {
                    $("#consultancy_by_status").css("height", "500px");
                }
            }

            function collectionCentreChart(pie) {
                google.load('visualization', '1', {
                    packages: ['corechart', 'bar', 'line']
                });
                google.setOnLoadCallback(function() {
                    var data = google.visualization.arrayToDataTable(pie);
                    var chart = new google.visualization.PieChart(document.getElementById('collection-by-centre'));
                    chart.draw(data);
                });
                if (pie.length > 1) {
                    $("#collection-by-centre").css("height", "500px");
                }
            }

            function revenueCentreChart(pie) {
                google.load('visualization', '1', {
                    packages: ['corechart', 'bar', 'line']
                });
                google.setOnLoadCallback(function() {
                    var data = google.visualization.arrayToDataTable(pie);
                    var chart = new google.visualization.PieChart(document.getElementById('revenue-centre'));
                    chart.draw(data);
                });
                if (pie.length > 1) {
                    $("#revenue-centre").css("height", "500px");
                }
            }

            function revenueByService(service, colors) {
                google.load('visualization', '1', {
                    packages: ['corechart', 'bar', 'line']
                });
                google.setOnLoadCallback(function() {
                    var data = google.visualization.arrayToDataTable(service);
                    var options = {
                        colors: colors
                    };
                    var chart = new google.visualization.PieChart(document.getElementById('revenue-service'));
                    chart.draw(data, options);
                });
                if (typeof service !== 'undefined' && service.length > 1) {
                    $("#revenue-service").css("height", "500px");
                }
            }

            // function RevenueByServiceCategory(pie) {
            //     console.log('here',pie);
            //     google.load('visualization', '1', {
            //         packages: ['corechart', 'bar', 'line']
            //     });
            //     google.setOnLoadCallback(function() {
            //         var data = google.visualization.arrayToDataTable(pie);
            //         var chart = new google.visualization.PieChart(document.getElementById('revenue-service-category'));
            //         chart.draw(data);
            //     });
            //     if (pie && pie.length > 1) {
            //         $("#revenue-service-category").css("height", "500px");
            //     }
            // }

            function CollectionByServiceCategory(service) {
                google.load('visualization', '1', {
                    packages: ['corechart', 'bar', 'line']
                });
                google.setOnLoadCallback(function() {
                    var data = google.visualization.arrayToDataTable(service);
                    var chart = new google.visualization.PieChart(document.getElementById(
                        'revenue-service-collection'));
                    chart.draw(data);
                });
                if (typeof service !== 'undefined' && service.length > 1) {
                    $("#revenue-service-collection").css("height", "500px");
                }
            }

            function BarChart(service) {
                const primary = '#6993FF';
                const success = '#1BC5BD';
                const info = '#8950FC';
                const warning = '#FFA800';
                const danger = '#F64E60';
                let locations = service.data.bar;
                let modifiedLocations;
                if (locations.length > 0) {
                    if (locations.some(str => str.includes('CUTERA,'))) {
                        modifiedLocations = locations.map(location => location.replace('CUTERA, ', ''));
                    } else {
                        modifiedLocations = locations;
                    }

                } else {
                    modifiedLocations = ['Bahadurabad Karachi', 'Gulshan Johar', 'DHA Karachi', 'Johar Town Lahore',
                        'Gulberg Lahore', 'DHA Lahore'
                    ];
                }
                for (var i = 0; i < service.data.walkin.length; i++) {
                    service.data.total[i] -= service.data.walkin[i];
                    service.data.arrived[i] -= service.data.walkin[i];
                }
                var options = {
                    series: [{
                        name: 'Total Appointments',
                        data: service.data.total
                    }, {
                        name: 'Arrived',
                        data: service.data.arrived
                    }, {
                        name: 'Walk-in',
                        data: service.data.walkin
                    }],
                    chart: {
                        type: 'bar',
                        height: 350,

                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '55%',
                            endingShape: 'rounded'
                        },
                    },
                    stroke: {
                        show: true,
                        width: 1,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: service.data.bar,
                    },
                    colors: [primary, success, warning]
                };
                var chart = new ApexCharts(document.querySelector("#centre_wise_arrival"), options);
                chart.render();
            }

            $(document).on('click', '.planIdText', function () {
                $('.planIdText').tooltip();
                var planId = $(this).text();
                var tempInput = $('<input>');
                $('body').append(tempInput);
                tempInput.val(planId).select();
                document.execCommand('copy');
                tempInput.remove();

                // Show a tooltip to indicate that the plan ID has been copied
                $(this).attr('data-original-title', 'Copied! '+ planId).tooltip('show');
                setTimeout(() => {
                    $(this).attr('data-original-title', 'Click to copy');
                }, 5000);
            });


</script>
@endpush
@endsection
