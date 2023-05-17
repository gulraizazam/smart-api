@extends('admin.layouts.master')
@section('content')
<style>
    .nav-tabs {
        background: 0 0;
        margin: 1px 0 0;
        float: right;
        display: inline-block;
        border: 0;
    }
    .nav:before {
        content: " ";
        display: table;
    }
    .loader-img{
        height: 60px;
        margin-top: 106px;
        margin-left: 244px;
    }
    .nav-tabs>li {
        margin: 0;
        padding: 0;
        background: 0 0;
        border: 0;
        float: left;
        display: block;
        position: relative;
    }
    .nav-tabs>li>a {
        margin: 0;
        padding: 12px 13px 13px;
        font-size: 13px;
        color: #666;
        border: 0;
        background: 0 0;

    }
    .nav-tabs>li a.active {
        background: 0 0;
        border-bottom: 4px solid #35a1d4;
        position: relative;
    }
    .hover-effect {
        border-color: #3598dc !important;
        color: #FFF !important;
        background-color: #3598dc !important;
        border-radius: 25px!important;
        overflow: hidden;
    }
    .dropdown-menu {
        box-shadow: 5px 5px rgb(102 102 102 / 10%);
        left: 0;
        min-width: 175px;
        position: absolute;
        z-index: 1000;
        display: none;
        float: left;
        list-style: none;
        text-shadow: none;
        padding: 0;
        background-color: #fff;
        margin: 10px 0 0;
        border: 1px solid #eee;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        -webkit-border-radius: 4px;
        -moz-border-radius: 4px;
        -ms-border-radius: 4px;
        -o-border-radius: 4px;
        border-radius: 4px;
    }
    .btn-group>.dropdown-menu,.dropdown-toggle>.dropdown-menu, .dropdown>.dropdown-menu{
        margin-top: 10px;
    }
    .btn-group>.dropdown-menu:before, .dropdown-toggle>.dropdown-menu:before, .dropdown>.dropdown-menu:before {
        position: absolute;
        top: -8px;
        left: 9px;
        right: auto;
        display: inline-block!important;
        border-right: 8px solid transparent;
        border-bottom: 8px solid #e0e0e0;
        border-left: 8px solid transparent;
        content: '';
        right: auto;
        left: 9px;
    }
    .btn-group>.dropdown-menu.dropdown-menu-right:before, .dropdown-toggle>.dropdown-menu.dropdown-menu-right:before, .dropdown>.dropdown-menu.dropdown-menu-right:before{
        left: auto;
        right: 9px;
    }
    .dropdown-menu>li>a {
        padding: 8px 16px;
        color: #6f6f6f;
        text-decoration: none;
        display: block;
        clear: both;
        font-weight: 300;
        line-height: 18px;
        white-space: nowrap;
    }
    .centre-name{
        font-size: 14px;
        color: #3598dc;
    }
    .table-cols{
        font-size: 12px;
    }
    .centre_wise_arrival_wrap{
        height: 480px;
        overflow-y: scroll;
    }
    .wise_arrival .dropdown-menu li a{
        cursor:pointer;   
    }
    .wise_arrival .dropdown-menu li a:hover{
        background:#f3f3f3;
    }
</style>
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
                                    <div class="dropdown dropdown-inline">
                                        <select class="form-control" name="type" onchange="changeDate();" id="recordfilter">
                                            <option value="today"  {{request('type') == 'today' ? 'selected' : ''}}>Today</option>
                                            <option value="yesterday" {{request('type') == 'yesterday' ? 'selected' : ''}}>Yesterday</option>
                                            <option value="last7days" {{request('type') == 'last7days' ? 'selected' : ''}}>Last 7 Days</option>
                                            <option value="week" {{request('type') == 'week' ? 'selected' : ''}}>This Week</option>
                                            <option value="month" {{request('type') == 'month' ? 'selected' : ''}}>This Month</option>
                                            <option value="lastmonth" {{request('type') == 'lastmonth' ? 'selected' : ''}}>Last Month</option>
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
                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <rect x="0" y="0" width="24" height="24" />
                                                        <rect fill="#000000" opacity="0.3" x="13" y="4" width="3" height="16" rx="1.5" />
                                                        <rect fill="#000000" x="8" y="9" width="3" height="11" rx="1.5" />
                                                        <rect fill="#000000" x="18" y="11" width="3" height="9" rx="1.5" />
                                                        <rect fill="#000000" x="3" y="13" width="3" height="7" rx="1.5" />
                                                    </g>
                                                </svg>
                                                <span class="dashboard-counter" id="allleads">{{isset($todaycollection[0]) && $todaycollection[0] !== false ? 'PKR:' . $todaycollection[0] : 'Your are not authorized'}}</span>
                                            </span>
                                            <a href="javascript:void(0);" style="cursor: pointer;" class="text-success font-weight-bold font-size-h6 mt-2">Sales</a>
                                        </div>
                                        <div class="col bg-light-warning px-6 py-8 rounded-xl  mb-7">
                                            <span class="svg-icon svg-icon-3x svg-icon-warning d-block my-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <rect x="0" y="0" width="24" height="24" />
                                                        <rect fill="#000000" opacity="0.3" x="13" y="4" width="3" height="16" rx="1.5" />
                                                        <rect fill="#000000" x="8" y="9" width="3" height="11" rx="1.5" />
                                                        <rect fill="#000000" x="18" y="11" width="3" height="9" rx="1.5" />
                                                        <rect fill="#000000" x="3" y="13" width="3" height="7" rx="1.5" />
                                                    </g>
                                                </svg>
                                                <span class="dashboard-counter" id="allrevenue">{{!is_null($revenue) ? 'PKR: ' . number_format($revenue) : 'Your are not authorized'}}</span>
                                            </span>
                                            <a href="javascript:void(0);" style="cursor: pointer;" class="text-warning font-weight-bold font-size-h6">Revenue Consumed</a>
                                        </div>
                                    </div>
                                    <div class="row m-0">
                                        <div class="col bg-light-primary px-6 py-8 rounded-xl ">
                                            <span class="svg-icon svg-icon-3x svg-icon-primary d-block my-2">
                                                <i class="la la-stethoscope" style="font-size: 40px;"></i>
                                                <span class="dashboard-counter" id="allconsult">{{!is_null($done_consultancies) && !is_null($all_consultancies) ? $done_consultancies .'/'.$all_consultancies : 'Your are not authorized'}}</span>
                                            </span>
                                            @if(!is_null($done_consultancies) && !is_null($all_consultancies))
                                                <a id="allconsultantdate" href="{{route('admin.consultancy.index', ['type' => '1', 'from' => $start_date, 'to' => $end_date, 'center_id' => implode(',', $location_id)])}}" class="text-primary font-weight-bold font-size-h6 mt-2">Consultancies</a>
                                            @else
                                            <a href="javascript:void(0);" class="text-primary font-weight-bold font-size-h6 mt-2">Consultancies</a>
                                            @endif
                                        </div>
                                        <div class="col bg-light-danger px-6 py-8 rounded-xl ml-7">
                                            <span class="svg-icon svg-icon-3x svg-icon-danger d-block my-2">
                                                <i class="la la-medkit" style="font-size: 40px;"></i>
                                                <span class="dashboard-counter" id="alltreat">{{!is_null($done_treatments) && !is_null($all_treatments) ? $done_treatments .'/'. $all_treatments : 'Your are not authorized'}}</span>
                                            </span>
                                            @if(!is_null($done_treatments) && !is_null($all_treatments))
                                                <a id="alltreatmentdate" href="{{route('admin.treatment.index', ['type' => '2', 'from' => $start_date, 'to' => $end_date, 'center_id' => implode(',', $location_id)])}}" class="text-danger font-weight-bold font-size-h6 mt-2">Treatments</a>
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
                                    <span class="text-muted mt-3 font-weight-bold font-size-sm" id="totalactivities">0 activities</span>
                                </h3>
                            </div>
                            <div class="card-body pt-4">
                                @if(isset($unauthorized))
                                    <div class="text-center">
                                        <span >Your are not authorized</span>
                                    </div>
                                @else
                                <img src="{{asset('assets/media/loader.gif')}}" class="loader-img">
                                    <div class="text-center">
                                        <span >No Activity Found</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if(\Illuminate\Support\Facades\Gate::allows("dashboard_collection_by_centre"))
                    <div class="col-lg-12 col-xxl-12 custom_tabs_style">
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;" id="collectionbycenter">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    <span class="dashboard-counter text-uppercase" >Collection by Centre</span>
                                    <ul class="nav nav-tabs d-flex align-items-center">
                                        <li style="border-bottom: none;">
                                            <div class="actions action-style p-3 mr-3">
                                                <div class="btn-group">
                                                    <a class="btn blue btn-outline btn-circle btn-sm hover-effect btn_Report"
                                                    href="javascript:;" data-toggle="dropdown" data-hover="dropdown"
                                                    data-close-others="true" aria-expanded="false"> Report
                                                        <i class="fa fa-angle-down"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-right">
                                                        <li>
                                                            <a href="#"
                                                            target="_blank">Today</a>
                                                        </li>
                                                        <li>
                                                            <a href="#"
                                                            target="_blank">Yesterday</a>
                                                        </li>
                                                        <li>
                                                            <a href="#"
                                                            target="_blank">Last 7 Days</a>
                                                        </li>
                                                        <li>
                                                            <a href="#"
                                                            target="_blank">This Week</a>
                                                        </li>
                                                        <li>
                                                            <a href="#"
                                                            target="_blank">This Month</a>
                                                        </li>
                                                        <li>
                                                            <a href="#"
                                                            target="_blank">Last Month</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </li>
                                        <li >
                                            <a class="active" href="#location_collection_1" data-toggle="tab"
                                            onclick="initCollectionByCentre('today', '', '','','','');">Today</a>
                                        </li>
                                        <li>
                                            <a href="#location_collection_2" data-toggle="tab"
                                            onclick="initCollectionByCentre('', 'yesterday', '','','','');">Yesterday</a>
                                        </li>
                                        <li>
                                            <a href="#location_collection_3" data-toggle="tab"
                                            onclick="initCollectionByCentre('', '', 'last7days','', '','');">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="#location_collection_3" data-toggle="tab"
                                            onclick="initCollectionByCentre('', '','', 'week', '','');">This Week</a>
                                        </li>
                                        <li>
                                            <a href="#location_collection_4" data-toggle="tab"
                                            onclick="initCollectionByCentre('', '', '','', 'thismonth','');">This Month</a>
                                        </li>
                                        <li>
                                            <a href="#location_collection_4" data-toggle="tab"
                                            onclick="initCollectionByCentre('', '', '','','','lastmonth');">Last Month</a>
                                        </li>
                                    </ul>
                                    <div class=" flex-column text-right d-none">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-pie-chart"></span>
                                        <span class="text-muted font-weight-bold mt-2 pie-income-title">Weekly Income</span>
                                    </div>
                                </div>
                                <div id="collection-by-centre"></div>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if(\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_centre'))
                    <div class="col-lg-12 col-xxl-12 custom_tabs_style">
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    <span class="dashboard-counter text-uppercase" >Revenue by Centre</span>
                                    <ul class="nav nav-tabs d-flex align-items-center">
                                        <li style="border-bottom: none;">
                                            <div class="actions action-style p-3 mr-3">
                                                <div class="btn-group">
                                                    <a class="btn blue btn-outline btn-circle btn-sm hover-effect btn_Report"
                                                        href="javascript:;" data-toggle="dropdown" data-hover="dropdown"
                                                        data-close-others="true" aria-expanded="false"> Report
                                                        <i class="fa fa-angle-down"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-right">
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Today</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Yesterday</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Last 7 Days</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">This Week</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">This Month</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Last Month</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </li>
                                        <li >
                                            <a class="active" href="#location_revenue_4" data-toggle="tab"
                                            onclick="initRevenueByCentre('today');">Today</a>
                                        </li>
                                        <li>
                                            <a href="#location_revenue_1" data-toggle="tab"
                                            onclick="initRevenueByCentre('yesterday');">Yesterday</a>
                                        </li>
                                        <li>
                                            <a href="#location_revenue_2" data-toggle="tab"
                                            onclick="initRevenueByCentre('last7days');">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="#location_revenue_2" data-toggle="tab"
                                            onclick="initRevenueByCentre('week');">This Week</a>
                                        </li>
                                        <li>
                                            <a href="#location_revenue_3" data-toggle="tab"
                                            onclick="initRevenueByCentre('thismonth');">This Month</a>
                                        </li>
                                        <li>
                                            <a href="#location_revenue_3" data-toggle="tab"
                                            onclick="initRevenueByCentre('lastmonth');">Last Month</a>
                                        </li>
                                    </ul>
                                    <div class="d-none flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-centre"></span>
                                        <span class="text-muted font-weight-bold mt-2 revenue-centre-title">Today Revenue</span>
                                    </div>
                                </div>
                                <div id="revenue-centre"></div>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if(\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_service'))
                    <div class="col-lg-12 col-xxl-12 custom_tabs_style">
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    <span class="dashboard-counter text-uppercase" >Revenue by Service</span>
                                    <ul class="nav nav-tabs d-flex align-items-center">
                                        <li style="border-bottom: none;">
                                            <div class="actions action-style p-3 mr-3">
                                                <div class="btn-group">
                                                    <a class="btn blue btn-outline btn-circle btn-sm hover-effect btn_Report"
                                                        href="javascript:;" data-toggle="dropdown" data-hover="dropdown"
                                                        data-close-others="true" aria-expanded="false"> Report
                                                        <i class="fa fa-angle-down"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-right">
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Today</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Yesterday</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Last 7 Days</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">This Week</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">This Month</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">LAst Month</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </li>
                                        <li >
                                            <a class="active" href="#service_revenue_4" data-toggle="tab"
                                            onclick="initRevenueByService('today', '', '','','','');">Today</a>
                                        </li>
                                        <li>
                                            <a href="#service_revenue_1" data-toggle="tab"
                                            onclick="initRevenueByService('', 'yesterday', '','', '','');">Yesterday</a>
                                        </li>
                                        <li>
                                            <a href="#service_revenue_2" data-toggle="tab"
                                            onclick="initRevenueByService('', '', 'last7days','', '','');">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="#service_revenue_2" data-toggle="tab"
                                            onclick="initRevenueByService('', '', '','week', '','');">This Week</a>
                                        </li>
                                        <li>
                                            <a href="#service_revenue_3" data-toggle="tab"
                                            onclick="initRevenueByService('', '', '','', 'thismonth','');">This Month</a>
                                        </li>
                                        <li>
                                            <a href="#service_revenue_3" data-toggle="tab"
                                            onclick="initRevenueByService('', '', '','','', 'lastmonth');">Last Month</a>
                                        </li>
                                    </ul>
                                    <div class="d-none flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-service"></span>
                                        <span class="text-muted font-weight-bold mt-2 service-title"></span>
                                    </div>
                                </div>
                                <div id="revenue-service"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-12 col-xxl-12">
                            <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                        <span class="dashboard-counter text-uppercase" >Revenue by Service Category</span>
                                        <ul class="nav nav-tabs d-flex align-items-center">
                                            <li style="border-bottom: none;">
                                                <div class="actions action-style p-3 mr-3">
                                                    <div class="btn-group">
                                                        <a class="btn blue btn-outline btn-circle btn-sm hover-effect btn_Report"
                                                            href="javascript:;" data-toggle="dropdown" data-hover="dropdown"
                                                            data-close-others="true" aria-expanded="false"> Report
                                                            <i class="fa fa-angle-down"></i>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-right">
                                                            <li>
                                                                <a href=""
                                                                target="_blank">Today</a>
                                                            </li>
                                                            <li>
                                                                <a href=""
                                                                target="_blank">Yesterday</a>
                                                            </li>
                                                            <li>
                                                                <a href=""
                                                                target="_blank">Last 7 Days</a>
                                                            </li>
                                                            <li>
                                                                <a href=""
                                                                target="_blank">This Month</a>
                                                            </li>
                                                            <li>
                                                                <a href=""
                                                                target="_blank">LAst Month</a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </li>
                                            <li >
                                                <a class="active" href="#service_revenue_4" data-toggle="tab"
                                                onclick="InitRevenueByServiceCategory('today', '', '', '','');">Today</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_1" data-toggle="tab"
                                                onclick="InitRevenueByServiceCategory('', 'yesterday', '', '','');">Yesterday</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_2" data-toggle="tab"
                                                onclick="InitRevenueByServiceCategory('', '', 'last7days', '','');">Last 7 Days</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_3" data-toggle="tab"
                                                onclick="InitRevenueByServiceCategory('', '', '', 'thismonth','');">This Month</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_3" data-toggle="tab"
                                                onclick="InitRevenueByServiceCategory('', '', '','', 'lastmonth');">Last Month</a>
                                            </li>
                                        </ul>
                                        <div class="d-none flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-category-service"></span>
                                            <span class="text-muted font-weight-bold mt-2 service-category-title"></span>
                                        </div>
                                    </div>
                                    <div id="revenue-service-category"></div>
                                </div>
                            </div>
                        </div>
                    @endif
                    <!-- @if(\Illuminate\Support\Facades\Gate::allows('dashboard_test'))
                    <div class="col-lg-12 col-xxl-12">
                            <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                        <span class="dashboard-counter text-uppercase" >Collection by Service Category</span>
                                        <ul class="nav nav-tabs d-flex align-items-center">
                                            <li style="border-bottom: none;">
                                                <div class="actions action-style p-3 mr-3">
                                                    <div class="btn-group">
                                                        <a class="btn blue btn-outline btn-circle btn-sm hover-effect btn_Report"
                                                            href="javascript:;" data-toggle="dropdown" data-hover="dropdown"
                                                            data-close-others="true" aria-expanded="false"> Report
                                                            <i class="fa fa-angle-down"></i>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-right">
                                                            <li>
                                                                <a href=""
                                                                target="_blank">Today</a>
                                                            </li>
                                                            <li>
                                                                <a href=""
                                                                target="_blank">Yesterday</a>
                                                            </li>
                                                            <li>
                                                                <a href=""
                                                                target="_blank">Last 7 Days</a>
                                                            </li>
                                                            <li>
                                                                <a href=""
                                                                target="_blank">This Month</a>
                                                            </li>
                                                            <li>
                                                                <a href=""
                                                                target="_blank">Last Month</a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </li>
                                            <li >
                                                <a class="active" href="#service_revenue_4" data-toggle="tab"
                                                onclick="InitCollectionByServiceCategory('today', '', '', '','');">Today</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_1" data-toggle="tab"
                                                onclick="InitCollectionByServiceCategory('', 'yesterday', '', '','');">Yesterday</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_2" data-toggle="tab"
                                                onclick="InitCollectionByServiceCategory('', '', 'last7days', '','');">Last 7 Days</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_3" data-toggle="tab"
                                                onclick="InitCollectionByServiceCategory('', '', '', 'thismonth','');">This Month</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_3" data-toggle="tab"
                                                onclick="InitCollectionByServiceCategory('', '', '','', 'lastmonth');">Last Month</a>
                                            </li>
                                        </ul>
                                        <div class="d-none flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-service"></span>
                                            <span class="text-muted font-weight-bold mt-2 service-title"></span>
                                        </div>
                                    </div>
                                    <div id="revenue-service-collection"></div>
                                </div>
                            </div>
                        </div>
                        @endif -->
                    @if(\Illuminate\Support\Facades\Gate::allows('dashboard_appointment_by_status'))
                    <div class="col-lg-12 col-xxl-12 custom_tabs_style">
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    <span class="dashboard-counter text-uppercase">Consultancy by Status</span>
                                    <ul class="nav nav-tabs d-flex align-items-center">
                                        <li style="border-bottom: none;">
                                            <div class="actions action-style p-3 mr-3">
                                                <div class="btn-group">
                                                    <a class="btn blue btn-outline btn-circle btn-sm hover-effect btn_Report"
                                                    href="javascript:;" data-toggle="dropdown" data-hover="dropdown"
                                                    data-close-others="true" aria-expanded="false"> Report
                                                        <i class="fa fa-angle-down"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-right">
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Today</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Yesterday</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Last 7 Days</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">This Week</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">This Month</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Last Month</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </li>
                                        <li >
                                            <a class="active" href="#appointment_by_status_4" data-toggle="tab"
                                            onclick="initConsultancyByStatus('today','1');">Today</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_1" data-toggle="tab"
                                            onclick="initConsultancyByStatus('yesterday','1');">Yesterday</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_2" data-toggle="tab"
                                            onclick="initConsultancyByStatus('last7days','1');">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_2" data-toggle="tab"
                                            onclick="initConsultancyByStatus('week','1');">This Week</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_3" data-toggle="tab"
                                            onclick="initConsultancyByStatus('thismonth','1');">This Month</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_3" data-toggle="tab"
                                            onclick="initConsultancyByStatus('lastmonth','1');">Last Month</a>
                                        </li>
                                    </ul>
                                    <div class="d-none flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                                        <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                                    </div>
                                </div>
                                <div id="consultancy_by_status"></div>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if(\Illuminate\Support\Facades\Gate::allows('dashboard_appointment_by_type'))
                    <div class="col-lg-12 col-xxl-12 custom_tabs_style">
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    <span class="dashboard-counter text-uppercase">Treatment by Status</span>
                                    <ul class="nav nav-tabs d-flex align-items-center">
                                        <li style="border-bottom: none;">
                                            <div class="actions action-style p-3 mr-3">
                                                <div class="btn-group">
                                                    <a class="btn blue btn-outline btn-circle btn-sm hover-effect btn_Report"
                                                    href="javascript:;" data-toggle="dropdown" data-hover="dropdown"
                                                    data-close-others="true" aria-expanded="false"> Report
                                                        <i class="fa fa-angle-down"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-right">
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Today</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Yesterday</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Last 7 Days</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">This Week</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">This Month</a>
                                                        </li>
                                                        <li>
                                                            <a href=""
                                                            target="_blank">Last Month</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </li>
                                        <li >
                                            <a class="active" href="#appointment_by_status_4" data-toggle="tab"
                                            onclick="initTreatmentByStatus('today','2');">Today</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_1" data-toggle="tab"
                                            onclick="initTreatmentByStatus('yesterday','2');">Yesterday</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_2" data-toggle="tab"
                                            onclick="initTreatmentByStatus('last7days','2');">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_2" data-toggle="tab"
                                            onclick="initTreatmentByStatus('week','2');">This Week</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_3" data-toggle="tab"
                                            onclick="initTreatmentByStatus('thismonth','2');">This Month</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_3" data-toggle="tab"
                                            onclick="initTreatmentByStatus('lastmonth','2');">Last Month</a>
                                        </li>
                                    </ul>
                                    <div class="d-none flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                                        <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                                    </div>
                                </div>
                                <div id="treatment_by_status"></div>
                            </div>
                        </div>
                    </div>
                   
                    <div class="col-lg-12 col-xxl-12 custom_tabs_style">
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    <span class="dashboard-counter text-uppercase">Centre Wise Arrival</span>
                                    <ul class="nav nav-tabs d-flex align-items-center wise_arrival_ul">
                                        <li style="border-bottom: none;">
                                            <div class="wise_arrival actions action-style p-3 mr-3">
                                            @if(Auth::user()->hasRole('Administrator')  || Auth::user()->hasRole('Super-Admin') || Auth::user()->hasRole('CSR')) 
                                                @php
                                                    $centres_array =['All South Region','All Central Region'];
                                                    $locations = \App\Helpers\ACL::getUserCentres();
                                                    $centres = \App\Models\Locations::whereIn('id',$locations)->whereNotIn('name',$centres_array)->get();
                                                @endphp
                                                <div class="btn-group">
                                                    <a data-id="" class="btn blue btn-outline btn-circle btn-sm hover-effect btn_Report arrivalbtn"
                                                    href="javascript:;" data-toggle="dropdown" data-hover="dropdown"
                                                    data-close-others="true" aria-expanded="false"> All
                                                        <i class="fa fa-angle-down"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-right">
                                                    
                                                        @foreach($centres as $centre)
                                                        <li>
                                                            <a 
                                                             data-id="{{$centre->id}}" onclick="LoadBarChart({{$centre->id}},'yesterday')">{{$centre->name}}</a>
                                                        </li>
                                                        @endforeach
                                                        
                                                    </ul>
                                                </div>
                                            </div>
                                            @elseif(Auth::user()->hasRole('CSR Supervisor')|| Auth::user()->hasRole('Social Lead'))
                                                @php
                                                    $all_csr = \App\Models\RoleHasUsers::where('role_id',2)->pluck('user_id');
                                                    $csr_users = \App\Models\User::whereIn('id',$all_csr)->get();
                                                @endphp
                                                <div class="btn-group">
                                                    <a data-id="All" class="btn blue btn-outline btn-circle btn-sm hover-effect btn_Report arrivalbtn" 
                                                    href="javascript:;" data-toggle="dropdown" data-hover="dropdown"
                                                    data-close-others="true" aria-expanded="false"> Select User
                                                        <i class="fa fa-angle-down"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-right">
                                                        <li>
                                                            <a data-id="All" onclick="LoadBarChartUserWise('All','yesterday')">All CSR</a>
                                                        </li>
                                                        @foreach($csr_users as $user)
                                                        <li>
                                                            <a 
                                                             data-id="{{$user->id}}" onclick="LoadBarChartUserWise({{$user->id}},'yesterday')">{{$user->name}}</a>
                                                        </li>
                                                        @endforeach
                                                        
                                                    </ul>
                                                </div>
                                            @else
                                            @php
                                                    $centres_array =['All South Region','All Central Region'];
                                                    $locations = \App\Helpers\ACL::getUserCentres();
                                                    $centres = \App\Models\Locations::whereIn('id',$locations)->whereNotIn('name',$centres_array)->first();
                                            @endphp
                                            <div class="btn-group">
                                                    <a data-id="" class="btn blue btn-outline btn-circle btn-sm hover-effect btn_Report arrivalbtn"
                                                    href="javascript:;" data-toggle="dropdown" data-hover="dropdown"
                                                    data-close-others="true" aria-expanded="false"> {{$centres->name}}
                                                        <i class="fa fa-angle-down"></i>
                                                    </a>
                                                    
                                                </div>
                                            </div>
                                            @endif
                                        </li>
                                        @if(Auth::user()->hasRole('CSR Supervisor')|| Auth::user()->hasRole('Social Lead'))
                                        <li>
                                            <a href="#appointment_by_status_1" data-toggle="tab"
                                            onclick="initUserWiseArrival('yesterday');">Yesterday</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_2" data-toggle="tab"
                                            onclick="initUserWiseArrival('last7days');">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_2" data-toggle="tab"
                                            onclick="initUserWiseArrival('week');">This Week</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_3" data-toggle="tab"
                                            onclick="initUserWiseArrival('thismonth');">This Month</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_3" data-toggle="tab"
                                            onclick="initUserWiseArrival('lastmonth');">Last Month</a>
                                        </li>
                                        @else
                                        <li>
                                            <a href="#appointment_by_status_1" data-toggle="tab"
                                            onclick="initCentreWiseArrival('yesterday');">Yesterday</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_2" data-toggle="tab"
                                            onclick="initCentreWiseArrival('last7days');">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_2" data-toggle="tab"
                                            onclick="initCentreWiseArrival('week');">This Week</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_3" data-toggle="tab"
                                            onclick="initCentreWiseArrival('thismonth');">This Month</a>
                                        </li>
                                        <li>
                                            <a href="#appointment_by_status_3" data-toggle="tab"
                                            onclick="initCentreWiseArrival('lastmonth');">Last Month</a>
                                        </li>
                                        @endif
                                    </ul>
                                    <div class="d-none flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                                        <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                                    </div>
                                </div>
                                <div class="row pt-7">
                                     <div class="col-8">
                                        <div id="centre_wise_arrival">
                                        
                                        </div>
                                    </div>
                                    <div class="col-4 centre_wise_arrival_wrap">
                                        <div class="row" id="centre_wise_arrival_02">
                                            
                                        </div>   
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script src="{{asset('assets/js/home.js')}}"></script>
    @push('datatable-js')
    <script src="{{asset('assets/js/pages/crud/forms/validation/appointment/validation.js')}}"></script>
    <script src="{{asset('assets/js/pages/dashboard/datatable.js')}}"></script>
    <script src="{{asset('assets/js/jsapi.js')}}"></script>
    <script src="{{asset('assets/js/pie.js')}}"></script>
    <script>
        jQuery('.btn.arrivalbtn + .dropdown-menu li a').on('click', function(){
            var dataID = jQuery(this).attr('data-id');
            var dataText = jQuery(this).text();
            jQuery('.btn.arrivalbtn').attr('data-id', dataID);
            jQuery('.btn.arrivalbtn').html(dataText+'<i class="fa fa-angle-down"></i>');
            jQuery('.wise_arrival_ul li a').removeClass('active');
            jQuery('.wise_arrival_ul li:nth-child(2) a').addClass('active');

        });
        $(document).ready(function(){
            period="today";
            $.ajax({
                url: route('admin.home.getactivity'),
                type: "GET",
                data: {'type': period},
                cache: false,
                success: function (response) {
                    $('.loader-img').css('display',"none");
                    $("#activitydiv").html(response);
                },
            });
            
            $.ajax({
                    url: route('admin.dashboard.centre_wise_arrival'),
                    type: "GET",
                    data: {'period': '{{request('type')}}','type':'2'},
                    cache: false,
                    success: function (response) {

                        console.log(response);
                        var TABLE_HTML = "";
                        var barLenght = response.data.bar;
                        for(var i = 0; i < barLenght.length; i++){
                            var walkin = "";
                            if(response.data.walkin[i] === undefined) {
                                walkin = "0";
                            } else {
                                walkin = response.data.walkin[i];
                            }
                            TABLE_HTML += "<div class='col-6'><h6 class='centre-name'>"+barLenght[i]+"</h6><div class='table-responsive'><table class='table'><thead><tr><th class='table-cols'>Total</th><th class='table-cols'>Arrived</th><th class=table-cols'>Walk in</th></tr></thead><tbody><tr><td>"+response.data.total[i]+"</td><td>"+response.data.arrived[i]+"</td><td>"+walkin+"</td></tr></tbody></table></div></div>";
             }
                        jQuery('#centre_wise_arrival_02').append(TABLE_HTML);
                        BarChart(response);
                        
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        errorMessage(xhr);
                    }
                });
        });
        var collection_by_center= false; 
        var revenue_by_center= false;
        var revenue_by_service=false;
        var revenue_by_service_category=false;
        var collection_by_service_category=false;
        var consultancy_by_status=false;
        var treatment_by_status=false;
        var centre_wise_arrival=false;
        $(window).scroll(function(){
            if (($(window).scrollTop() >= ($(document).height() - $(window).height())*0.07) && !collection_by_center){
                collection_by_center= true; 
                $.ajax({
                    url: route('admin.home.collectionByCentre'),
                    type: "GET",
                    data: {'type': '{{request('type')}}'},
                    cache: false,
                    success: function (response) {
                        @if(request('type') == 'today')
                            var pie = response.data.pie.today;
                        @endif
                        @if(request('type') == 'yesterday')
                            var pie = response.data.pie.yesterday;
                        @endif
                        @if(request('type') == 'week')
                            var pie = response.data.pie.week;
                        @endif
                        @if(request('type') == 'month')
                            var pie = response.data.pie.month;
                        @endif
                        @if(request('type') == '')
                            var pie = response.data.pie.today;
                        @endif
                        collectionCentreChart(pie);
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        errorMessage(xhr);
                    }
                });
            }
            if (($(window).scrollTop() >= ($(document).height() - $(window).height())*0.14) && !revenue_by_center){
                revenue_by_center= true; 
                $.ajax({
                    url: route('admin.home.revenueByCentre'),
                    type: "GET",
                    data: {'type': '{{request('type')}}'},
                    cache: false,
                    success: function (response) {
                        let pie = response.data.pie;
                        revenueCentreChart(pie);
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        errorMessage(xhr);
                    }
                });
            }
            if (($(window).scrollTop() >= ($(document).height() - $(window).height())*0.32) && !revenue_by_service){
                revenue_by_service= true; 
                $.ajax({
                    url: route('admin.home.revenueByService'),
                    type: "GET",
                    data: {'type': '{{request('type')}}'},
                    cache: false,
                    success: function (response) {
                        let colors = response.data.colors;
                        @if(request('type') == 'today')
                        var pie = response.data.pie.today;
                        @endif
                        @if(request('type') == 'yesterday')
                            var pie = response.data.pie.yesterday;
                        @endif
                        @if(request('type') == 'week')
                            var pie = response.data.pie.week;
                        @endif
                        @if(request('type') == 'month')
                            var pie = response.data.pie.month;
                        @endif
                        @if(request('type') == '')
                            var pie = response.data.pie.today;
                        @endif
                        revenueByService(pie, colors);
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        errorMessage(xhr);
                    }
                });
            }
            if (($(window).scrollTop() >= ($(document).height() - $(window).height())*0.32) && !revenue_by_service_category){
                revenue_by_service_category= true; 
                $.ajax({
                    url: route('admin.home.RevenueByServiceCategory'),
                    type: "GET",
                    data: {'type': '{{request('type')}}'},
                    cache: false,
                    success: function (response) {
                    
                        @if(request('type') == 'today')
                            var pie = response.data.pie.today;
                        @endif
                        @if(request('type') == 'yesterday')
                            var pie = response.data.pie.yesterday;
                        @endif
                        @if(request('type') == 'week')
                            var pie = response.data.pie.week;
                        @endif
                        @if(request('type') == 'month')
                            var pie = response.data.pie.month;
                        @endif
                        @if(request('type') == '')
                            var pie = response.data.pie.today;
                        @endif
                        RevenueByServiceCategory(pie);
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        errorMessage(xhr);
                    }
                });
            }
            if (($(window).scrollTop() >= ($(document).height() - $(window).height())*0.33) && !collection_by_service_category){
                collection_by_service_category= true; 
                $.ajax({
                        url: route('admin.home.CollectionByServiceCategory'),
                        type: "GET",
                        data: {'type': '{{request('type')}}'},
                        cache: false,
                        success: function (response) {
                            let colors = response.data.colors;
                            let total = response.data.total;
                            
                            @if(request('type') == 'today')
                            var pie = response.data.pie.today;
                            @endif
                            @if(request('type') == 'yesterday')
                                var pie = response.data.pie.yesterday;
                            @endif
                            @if(request('type') == 'week')
                                var pie = response.data.pie.week;
                            @endif
                            @if(request('type') == 'month')
                                var pie = response.data.pie.month;
                            @endif
                            @if(request('type') == '')
                                var pie = response.data.pie.today;
                            @endif
                            CollectionByServiceCategory(pie, colors);
                        },
                        error: function (xhr, ajaxOptions, thrownError) {
                            errorMessage(xhr);
                        }
                    });
            }
            if (($(window).scrollTop() >= ($(document).height() - $(window).height())*0.50) && !consultancy_by_status){
                consultancy_by_status= true; 
                $.ajax({
                    url: route('admin.dashboard.appointment_by_status'),
                    type: "GET",
                    data: {'period': '{{request('type')}}','type':'1'},
                    cache: false,
                    success: function (response) {
                        
                        let colors = response.data.colors;
                        @if(request('type') == 'today')
                            var pie = response.data.pie.today;
                        @endif
                        @if(request('type') == 'yesterday')
                            var pie = response.data.pie.yesterday;
                        @endif
                        @if(request('type') == 'week')
                            var pie = response.data.pie.week;
                        @endif
                        @if(request('type') == 'month')
                            var pie = response.data.pie.month;
                        @endif
                        @if(request('type') == '')
                            var pie = response.data.pie.today;
                        @endif
                        ConsultancyByStatus(pie, colors);
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        errorMessage(xhr);
                    }
                });
            }
            if (($(window).scrollTop() >= ($(document).height() - $(window).height())*0.50) && !treatment_by_status){
                treatment_by_status= true; 
                $.ajax({
                    url: route('admin.dashboard.appointment_by_status'),
                    type: "GET",
                    data: {'period': '{{request('type')}}','type':'2'},
                    cache: false,
                    success: function (response) {
                        
                        let colors = response.data.colors;
                        @if(request('type') == 'today')
                            var pie = response.data.pie.today;
                        @endif
                        @if(request('type') == 'yesterday')
                            var pie = response.data.pie.yesterday;
                        @endif
                        @if(request('type') == 'week')
                            var pie = response.data.pie.week;
                        @endif
                        @if(request('type') == 'month')
                            var pie = response.data.pie.month;
                        @endif
                        @if(request('type') == '')
                            var pie = response.data.pie.today;
                        @endif
                        TreatmentByStatus(pie, colors);
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        errorMessage(xhr);
                    }
                });
            }
            
        });
        function TreatmentByStatus(pie,colors) {
            google.load('visualization', '1', {
                packages: ['corechart', 'bar', 'line']
            });
            google.setOnLoadCallback(function () {
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
        function ConsultancyByStatus(pie,colors) {
            google.load('visualization', '1', {
                packages: ['corechart', 'bar', 'line']
            });
            google.setOnLoadCallback(function () {
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
            google.setOnLoadCallback(function () {
            var data = google.visualization.arrayToDataTable(pie);
            var options = {
                colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
            };
            var chart = new google.visualization.PieChart(document.getElementById('collection-by-centre'));
                chart.draw(data, options);
            });
            if (pie.length > 1) {
                $("#collection-by-centre").css("height", "500px");
            }
        }
        function revenueCentreChart(centerRevenue) {
            google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
            });
            google.setOnLoadCallback(function () {
            var data = google.visualization.arrayToDataTable(centerRevenue);
            var options = {
                colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
            };
            var chart = new google.visualization.PieChart(document.getElementById('revenue-centre'));
                chart.draw(data, options);
            });
            if (centerRevenue.length > 1) {
                $("#revenue-centre").css("height", "500px");
            }
        }
        function revenueByService(service, colors) {
            google.load('visualization', '1', {
                packages: ['corechart', 'bar', 'line']
            });
            google.setOnLoadCallback(function () {
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
        function RevenueByServiceCategory(pie) {
            google.load('visualization', '1', {
                packages: ['corechart', 'bar', 'line']
            });
            google.setOnLoadCallback(function () {
            var data = google.visualization.arrayToDataTable(pie);
            var options = {
                colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
            };
            var chart = new google.visualization.PieChart(document.getElementById('revenue-service-category'));
                chart.draw(data, options);
            });
            if (pie.length > 1) {
                $("#revenue-service-category").css("height", "500px");
            }
        }
        function CollectionByServiceCategory(service) {
                google.load('visualization', '1', {
                    packages: ['corechart', 'bar', 'line']
                });
                google.setOnLoadCallback(function () {
                var data = google.visualization.arrayToDataTable(service);
                var options = {
                    colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
                };
                var chart = new google.visualization.PieChart(document.getElementById('revenue-service-collection'));
                    chart.draw(data, options);
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
            if(locations.length > 0){
                if (locations.some(str => str.includes('CUTERA,'))) {
                    modifiedLocations = locations.map(location => location.replace('CUTERA, ', ''));
                }else{
                    modifiedLocations = locations;
                }
                
            }else{
                modifiedLocations =['Bahadurabad Karachi','Gulshan Johar','DHA Karachi','Johar Town Lahore','Gulberg Lahore','DHA Lahore'];
            }
            var options = {
                series: [{
                    name: 'Total Appointments',
                    data: service.data.total
                }, {
                    name: 'Arrived',
                    data: service.data.arrived
                },{
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
                    width: 2,
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
        
    </script>
@endpush
@endsection
