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
    /* top: 180px; */
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
</style>
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb')

        <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">
                <!--begin::Dashboard-->
                <!--begin::Row-->
                <div class="row">
                    <div class="col-lg-6 col-xxl-6">
                        <!--begin::Mixed Widget 1-->
                        <div class="card card-custom bg-gray-100 card-stretch gutter-b">
                            <!--begin::Header-->
                            <div class="card-header border-0 bg-danger py-5">
                                <h3 class="card-title font-weight-bolder text-white">Stats</h3>
                                <div class="card-toolbar">
                                    <div class="dropdown dropdown-inline">
                                        <!-- <form id="dashboard-states" method="get" action="{{route('admin.home')}}"> -->

                                           <select class="form-control" name="type" onchange="changeDate();" id="recordfilter">
                                               <option value="today"  {{request('type') == 'today' ? 'selected' : ''}}>Today</option>
                                               <option value="yesterday" {{request('type') == 'yesterday' ? 'selected' : ''}}>Yesterday</option>
                                               <option value="week" {{request('type') == 'week' ? 'selected' : ''}}>This Week</option>
                                               <option value="month" {{request('type') == 'month' ? 'selected' : ''}}>This Month</option>
                                               <option value="lastmonth" {{request('type') == 'lastmonth' ? 'selected' : ''}}>Last Month</option>
                                           </select>

                                        <!-- </form> -->

                                    </div>
                                </div>
                            </div>
                            <!--end::Header-->
                            <!--begin::Body-->
                            <div class="card-body p-0 position-relative overflow-hidden">
                                <!--begin::Chart-->
                                <div id="kt_mixed_widget_1_chart" class="card-rounded-bottom bg-danger" style="height: 200px"></div>
                                <!--end::Chart-->
                                <!--begin::Stats-->
                                <div class="card-spacer mt-n25">
                                    <!--begin::Row-->
                                    <div class="row m-0">
                                    <div class="col bg-light-success px-6 py-8 rounded-xl mr-7 mb-7">
                                        <span class="svg-icon svg-icon-3x svg-icon-primary d-block my-2">
                                            <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Communication/Add-user.svg-->
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24" />
                                                    <rect fill="#000000" opacity="0.3" x="13" y="4" width="3" height="16" rx="1.5" />
                                                    <rect fill="#000000" x="8" y="9" width="3" height="11" rx="1.5" />
                                                    <rect fill="#000000" x="18" y="11" width="3" height="9" rx="1.5" />
                                                    <rect fill="#000000" x="3" y="13" width="3" height="7" rx="1.5" />
                                                </g>
                                            </svg>
                                            <!--end::Svg Icon-->
                                            <span class="dashboard-counter" id="allleads">{{isset($todaycollection[0]) && $todaycollection[0] !== false ? 'PKR:' . $todaycollection[0] : 'Your are not authorized'}}</span>
                                        </span>

                                            <a href="{{route('admin.leads.index', ['from' => $start_date, 'to' => $end_date])}}" style="cursor: pointer;" class="text-success font-weight-bold font-size-h6 mt-2">Sales</a>
                                        </div>

                                        <div class="col bg-light-warning px-6 py-8 rounded-xl  mb-7">
                                                    <span class="svg-icon svg-icon-3x svg-icon-warning d-block my-2">
                                                        <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Media/Equalizer.svg-->
                                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                <rect x="0" y="0" width="24" height="24" />
                                                                <rect fill="#000000" opacity="0.3" x="13" y="4" width="3" height="16" rx="1.5" />
                                                                <rect fill="#000000" x="8" y="9" width="3" height="11" rx="1.5" />
                                                                <rect fill="#000000" x="18" y="11" width="3" height="9" rx="1.5" />
                                                                <rect fill="#000000" x="3" y="13" width="3" height="7" rx="1.5" />
                                                            </g>
                                                        </svg>
                                                        <!--end::Svg Icon-->

                                                          <span class="dashboard-counter" id="allrevenue">{{!is_null($revenue) ? 'PKR: ' . number_format($revenue) : 'Your are not authorized'}}</span>
                                                    </span>
                                            <a href="javascript:void(0);" style="cursor: pointer;" class="text-warning font-weight-bold font-size-h6">Revenue Consumed</a>
                                        </div>
                                       
                                    </div>
                                    <!--end::Row-->
                                    <!--begin::Row-->
                                    <div class="row m-0">
                                        
                                        <div class="col bg-light-primary px-6 py-8 rounded-xl ">
                                                <span class="svg-icon svg-icon-3x svg-icon-primary d-block my-2">

                                                    <i class="la la-stethoscope" style="font-size: 40px;"></i>

                                                      <span class="dashboard-counter" id="allconsult">{{!is_null($done_consultancies) && !is_null($all_consultancies) ? $done_consultancies .'/'.$all_consultancies : 'Your are not authorized'}}</span>
                                                </span>
                                            @if(!is_null($done_consultancies) && !is_null($all_consultancies))
                                                <a href="{{route('admin.consultancy.index', ['type' => '1', 'from' => $start_date, 'to' => $end_date, 'center_id' => implode(',', $location_id)])}}" class="text-primary font-weight-bold font-size-h6 mt-2">Consultancies</a>
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
                                                <a href="{{route('admin.treatment.index', ['type' => '2', 'from' => $start_date, 'to' => $end_date, 'center_id' => implode(',', $location_id)])}}" class="text-danger font-weight-bold font-size-h6 mt-2">Treatments</a>
                                            @else
                                            <a href="javascript:void(0);" class="text-danger font-weight-bold font-size-h6 mt-2">Treatments</a>
                                            @endif
                                        </div>
                                    </div>
                                    <!--end::Row-->
                                </div>
                                <!--end::Stats-->
                            </div>
                            <!--end::Body-->
                        </div>
                        <!--end::Mixed Widget 1-->

                    </div>

                    <div class="modal fade" id="modal_change_appointment_status" tabindex="-1" aria-hidden="true">
                        <!--begin::Modal dialog-->
                        <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_status_change">

                            @include('admin.appointments.appointment-forms.change-status')

                        </div>
                    </div>

                    <div class="modal fade" id="modal_change_appointment_schedule" tabindex="-1" aria-hidden="true">
                        <!--begin::Modal dialog-->
                        <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_schedule_change">

                            @include('admin.appointments.appointment-forms.schedule')

                        </div>
                    </div>

                    {{--Activity--}}
                    <div class="col-lg-6 col-xxl-6" id="activitydiv">
                    <div class="card card-custom card-stretch gutter-b" style="height: 600px; overflow-y: auto;">
                    <!--begin::Header-->
                    <div class="card-header align-items-center border-0 mt-4">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="font-weight-bolder text-dark">Recent Activity</span>
                            <span class="text-muted mt-3 font-weight-bold font-size-sm" id="totalactivities">0 activities</span>
                        </h3>
                    </div>
                    <!--end::Header-->
                    <!--begin::Body-->
                    <div class="card-body pt-4">
                        <!--begin::Timeline-->
                        
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
                

                        <!--end::Timeline-->
                        </div>
                        <!--end: Card Body-->
                    </div>
                    </div>

                    {{--Collections by centers--}}
                    @if(\Illuminate\Support\Facades\Gate::allows("dashboard_collection_by_centre"))
                    <div class="col-lg-12 col-xxl-12">
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;" id="collectionbycenter">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    
                                    @if(count(\App\Helpers\ACL::getUserCentres())> 1)
                                        <span class="dashboard-counter text-uppercase" >Collection by Centre</span>
                                    @else
                                        <span class="dashboard-counter text-uppercase" >My Centre Collection</span>
                                    @endif
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
                                            onclick="initCollectionByCentre('today', '', '', '','');">Today</a>
                                        </li>
                                        <li>
                                            <a href="#location_collection_2" data-toggle="tab"
                                            onclick="initCollectionByCentre('', 'yesterday', '', '','');">Yesterday</a>
                                        </li>
                                        <li>
                                            <a href="#location_collection_3" data-toggle="tab"
                                            onclick="initCollectionByCentre('', '', 'last7days', '','');">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="#location_collection_4" data-toggle="tab"
                                            onclick="initCollectionByCentre('', '', '', 'thismonth','');">This Month</a>
                                        </li>
                                        <li>
                                            <a href="#location_collection_4" data-toggle="tab"
                                            onclick="initCollectionByCentre('', '', '','', 'lastmonth');">Last Month</a>
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
                    {{--My Collections by centers--}}
                    @if(\Illuminate\Support\Facades\Gate::allows('dashboard_my_collection_by_centre'))
                    <div class="col-lg-12 col-xxl-12">
                        <!--My Collection by centre-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <!--begin::Body-->
                            
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    <span class="dashboard-counter text-uppercase">My Collection by Centre</span>
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
                                            <a class="active" href="#location_my_collection_1" data-toggle="tab"
                                            onclick="initMyCollectionByCentre('today', '', '', '','');">Today</a>
                                        </li>
                                        <li>
                                            <a href="#location_my_collection_2" data-toggle="tab"
                                            onclick="initMyCollectionByCentre('', 'yesterday', '', '','');">Yesterday</a>
                                        </li>
                                        <li>
                                            <a href="#location_my_collection_3" data-toggle="tab"
                                            onclick="initMyCollectionByCentre('', '', 'last7days', '','');">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="#location_my_collection_4" data-toggle="tab"
                                            onclick="initMyCollectionByCentre('', '', '', 'thismonth','');">This Month</a>
                                        </li>
                                        <li>
                                            <a href="#location_my_collection_4" data-toggle="tab"
                                            onclick="initMyCollectionByCentre('', '', '','', 'lastmonth');">Last Month</a>
                                        </li>
                                    </ul>
                                    <div class="d-none flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 my-total-collection-center"></span>
                                            <span class="text-muted font-weight-bold mt-2 my-collection-title"></span>
                                        </div>
                                    </div>
                                    <div id="my-collection-by-centre"></div>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if(\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_centre'))
                        <div class="col-lg-12 col-xxl-12">
                            <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    @if(count(\App\Helpers\ACL::getUserCentres())> 1)
                                        <span class="dashboard-counter text-uppercase" >Revenue by Centre</span>
                                    @else
                                        <span class="dashboard-counter text-uppercase" >My Centre Revenue</span>
                                    @endif
                                       
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
                    @if(\Illuminate\Support\Facades\Gate::allows('dashboard_my_revenue_by_centre'))
                        <div class="col-lg-12 col-xxl-12">
                            <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                        <span class="dashboard-counter text-uppercase">My Revenue by Centre</span>
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
                                                <a class="active" href="#my_location_revenue_4" data-toggle="tab"
                                                onclick="initMyRevenueByCentre('today');">Today</a>
                                            </li>
                                            <li>
                                                <a href="#my_location_revenue_1" data-toggle="tab"
                                                onclick="initMyRevenueByCentre('yesterday');">Yesterday</a>
                                            </li>
                                            <li>
                                                <a href="#my_location_revenue_2" data-toggle="tab"
                                                onclick="initMyRevenueByCentre('last7days');">Last 7 Days</a>
                                            </li>
                                            <li>
                                                <a href="#my_location_revenue_3" data-toggle="tab"
                                                onclick="initMyRevenueByCentre('thismonth');">This Month</a>
                                            </li>
                                            <li>
                                                <a href="#my_location_revenue_3" data-toggle="tab"
                                                onclick="initMyRevenueByCentre('lastmonth');">Last Month</a>
                                            </li>
                                        </ul>
                                        <div class="d-none flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-my-revenue-centre"></span>
                                            <span class="text-muted font-weight-bold mt-2 my-revenue-centre-title">Weekly Income</span>
                                        </div>
                                    </div>
                                    <div id="my-revenue-centre"></div>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if(\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_service'))
                    <div class="col-lg-12 col-xxl-12">
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
                                                onclick="initRevenueByService('today', '', '', '','');">Today</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_1" data-toggle="tab"
                                                onclick="initRevenueByService('', 'yesterday', '', '','');">Yesterday</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_2" data-toggle="tab"
                                                onclick="initRevenueByService('', '', 'last7days', '','');">Last 7 Days</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_3" data-toggle="tab"
                                                onclick="initRevenueByService('', '', '', 'thismonth','');">This Month</a>
                                            </li>
                                            <li>
                                                <a href="#service_revenue_3" data-toggle="tab"
                                                onclick="initRevenueByService('', '', '','', 'lastmonth');">Last Month</a>
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
                    @endif
                    @if(\Illuminate\Support\Facades\Gate::allows('dashboard_my_revenue_by_service'))
                    <div class="col-lg-12 col-xxl-12">
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                    <span class="dashboard-counter text-uppercase">My Revenue by Service</span>
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
                                            <a class="active" href="#my_service_revenue_4" data-toggle="tab"
                                            onclick="initMyRevenueByService('today');">Today</a>
                                        </li>
                                        <li>
                                            <a href="#my_service_revenue_1" data-toggle="tab"
                                            onclick="initMyRevenueByService('yesterday');">Yesterday</a>
                                        </li>
                                        <li>
                                            <a href="#my_service_revenue_2" data-toggle="tab"
                                            onclick="initMyRevenueByService('last7days');">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="#my_service_revenue_3" data-toggle="tab"
                                            onclick="initMyRevenueByService('thismonth');">This Month</a>
                                        </li>
                                        <li>
                                            <a href="#my_service_revenue_3" data-toggle="tab"
                                            onclick="initMyRevenueByService('lastmonth');">Last Month</a>
                                        </li>
                                    </ul>
                                    <div class="d-none flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-my-service"></span>
                                        <span class="text-muted font-weight-bold mt-2 my-service-title"></span>
                                    </div>
                                </div>
                                <div id="my-revenue-service"></div>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if(\Illuminate\Support\Facades\Gate::allows('dashboard_appointment_by_status'))
                        <div class="col-lg-12 col-xxl-12">
                            <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                                <div class="card-body p-0">
                                        <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                            <span class="dashboard-counter text-uppercase">Appointments by Status</span>
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
                                                    <a class="active" href="#appointment_by_status_4" data-toggle="tab"
                                                    onclick="initAppointmentsByStatus('today');">Today</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_1" data-toggle="tab"
                                                    onclick="initAppointmentsByStatus('yesterday');">Yesterday</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_2" data-toggle="tab"
                                                    onclick="initAppointmentsByStatus('last7days');">Last 7 Days</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_3" data-toggle="tab"
                                                    onclick="initAppointmentsByStatus('thismonth');">This Month</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_3" data-toggle="tab"
                                                    onclick="initAppointmentsByStatus('lastmonth');">Last Month</a>
                                                </li>
                                            </ul>
                                            <div class="d-none flex-column text-right">
                                                <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-status"></span>
                                                <span class="text-muted font-weight-bold mt-2 appointment-by-status-title"></span>
                                            </div>
                                        </div>
                                        <div id="appointment_status_today"></div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if(\Illuminate\Support\Facades\Gate::allows('dashboard_my_appointment_by_status'))
                            <div class="col-lg-12 col-xxl-12">
                                <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                                    <div class="card-body p-0">
                                        <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                            <span class="dashboard-counter text-uppercase">My Appointments by Status</span>
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
                                                    <a class="active" href="#appointment_by_status_4" data-toggle="tab"
                                                    onclick="initMyAppointmentsByStatus('today');">Today</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_1" data-toggle="tab"
                                                    onclick="initMyAppointmentsByStatus('yesterday');">Yesterday</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_2" data-toggle="tab"
                                                    onclick="initMyAppointmentsByStatus('last7days');">Last 7 Days</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_3" data-toggle="tab"
                                                    onclick="initMyAppointmentsByStatus('thismonth');">This Month</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_3" data-toggle="tab"
                                                    onclick="initMyAppointmentsByStatus('lastmonth');">Last Month</a>
                                                </li>
                                            </ul>
                                            <div class="d-none flex-column text-right">
                                                <span class="text-dark-75 font-weight-bolder font-size-h3 my-total-appointment-by-status"></span>
                                                <span class="text-muted font-weight-bold mt-2 my-appointment-by-status-title"></span>
                                            </div>
                                        </div>
                                        <div id="my_appointment_status_today"></div>
                                    </div>
                                </div>
                           
                            </div>
                        @endif
                        @if(\Illuminate\Support\Facades\Gate::allows('dashboard_appointment_by_type'))
                            <div class="col-lg-12 col-xxl-12">
                                <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                                    <div class="card-body p-0">
                                        <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                            <span class="dashboard-counter text-uppercase">Appointments by Type</span>
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
                                                    <a class="active" href="#appointment_by_status_4" data-toggle="tab"
                                                    onclick="initAppointmentsByType('today');">Today</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_1" data-toggle="tab"
                                                    onclick="initAppointmentsByType('yesterday');">Yesterday</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_2" data-toggle="tab"
                                                    onclick="initAppointmentsByType('last7days');">Last 7 Days</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_3" data-toggle="tab"
                                                    onclick="initAppointmentsByType('thismonth');">This Month</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_3" data-toggle="tab"
                                                    onclick="initAppointmentsByType('lastmonth');">Last Month</a>
                                                </li>
                                            </ul>
                                            <div class="d-none flex-column text-right">
                                                <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointment-by-type"></span>
                                                <span class="text-muted font-weight-bold mt-2 appointment-by-type-title"></span>
                                            </div>
                                        </div>
                                        <div id="appointment_type_today"></div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if(\Illuminate\Support\Facades\Gate::allows('dashboard_my_appointment_by_type'))
                            <div class="col-lg-12 col-xxl-12">
                                <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                                    <div class="card-body p-0">
                                        <div class="d-flex align-items-center justify-content-between card-spacer2 flex-grow-1">
                                            <span class="dashboard-counter text-uppercase">My Appointments by Type</span>
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
                                                    <a class="active" href="#appointment_by_status_4" data-toggle="tab"
                                                    onclick="initMyAppointmentsByType('today');">Today</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_1" data-toggle="tab"
                                                    onclick="initMyAppointmentsByType('yesterday');">Yesterday</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_2" data-toggle="tab"
                                                    onclick="initMyAppointmentsByType('last7days');">Last 7 Days</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_3" data-toggle="tab"
                                                    onclick="initMyAppointmentsByType('thismonth');">This Month</a>
                                                </li>
                                                <li>
                                                    <a href="#appointment_by_status_3" data-toggle="tab"
                                                    onclick="initMyAppointmentsByType('lastmonth');">Last Month</a>
                                                </li>
                                            </ul>
                                            <div class="d-none flex-column text-right">
                                                <span class="text-dark-75 font-weight-bolder font-size-h3 my-total-appointment-by-type"></span>
                                                <span class="text-muted font-weight-bold mt-2 my-appointment-by-type-title"></span>
                                            </div>
                                        </div>
                                        <div id="my_appointment_type_today"></div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                
                <!-- @can('dashboard_upcomings')
                    <div class="row">
                        <div class="col-lg-12 col-xxl-12">
                            <div class="card card-custom card-stretch gutter-b">
                                
                                <div class="card-header border-0 pt-5">
                                    <h3 class="card-title align-items-start flex-column">
                                        <span class="card-label font-weight-bolder text-dark">Upcomings</span>
                                        <span class="text-muted mt-3 font-weight-bold font-size-sm">Total <span class=" badge badge-circle badge-info total-members"></span> Upcomings</span>
                                    </h3>
                                    <div class="card-toolbar">
                                        <div class="dropdown dropdown-inline" data-toggle="tooltip" title="Quick actions" data-placement="left">
                                            <div class="btn-location">
                                                <button class="arrival-btn btn btn-default" onclick="getArrivalsByDate($(this), '{{$month}}', '{{$currentTime}}', 'month');">Month</button>
                                                <button class="arrival-btn btn btn-default" onclick="getArrivalsByDate($(this), '{{$startWeek}}', '{{$currentTime}}', 'week');">Week</button>
                                                <button class="arrival-btn btn btn-primary" onclick="getArrivalsByDate($(this), '{{$today}}', '{{$currentTime}}', 'today');">Today</button>
                                            </div>


                                        </div>
                                    </div>
                                </div>

                                <div class="card-body">

                                   
                                    <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                                    
                                </div>

                            </div>
                        
                            
                            </div>
                    </div>
                @endcan -->


</div>
<!--end::Container-->
</div>
<!--end::Entry-->
</div>
<!--end::Content-->
<script src="{{asset('assets/js/home.js')}}"></script>
@push('datatable-js')
<script src="{{asset('assets/js/pages/crud/forms/validation/appointment/validation.js')}}"></script>
<script src="{{asset('assets/js/pages/dashboard/datatable.js')}}"></script>
<script src="{{asset('assets/js/jsapi.js')}}"></script>

<script src="{{asset('assets/js/pie.js')}}"></script>


<script>
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
   });
    var collection_by_center= false; 
    var revenue_by_center= false;
    var revenue_by_service=false;
    var appointment_by_status=false;
    var appointment_by_type=false;
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
                    console.log(response);
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
        if (($(window).scrollTop() >= ($(document).height() - $(window).height())*0.50) && !appointment_by_status){
            appointment_by_status= true; 
            $.ajax({
                url: route('admin.dashboard.appointment_by_status'),
                type: "GET",
                data: {'period': '{{request('type')}}'},
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
                    AppointmentByStatus(pie, colors);
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    errorMessage(xhr);
                }
            });
        }
        if (($(window).scrollTop() >= ($(document).height() - $(window).height())*0.66) && !appointment_by_type){
            appointment_by_type= true; 
            $.ajax({
                url: route('admin.dashboard.appointment_by_type'),
                type: "GET",
                data: {'period': '{{request('type')}}'},
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
                    AppointmentByType(pie, colors);
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    errorMessage(xhr);
                }
            });
        }
    });
    function AppointmentByType(pie,colors) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(pie);
        var options = {
            colors: colors
        };
        var chart = new google.visualization.PieChart(document.getElementById('appointment_type_today'));
            chart.draw(data, options);
        });
        if (pie.length > 1) {
            $("#appointment_type_today").css("height", "500px");
        }
    }
    function MyAppointmentByType(pie,colors) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(pie);
        var options = {
            colors: colors
        };
        var chart = new google.visualization.PieChart(document.getElementById('my_appointment_type_today'));
            chart.draw(data, options);
        });
        if (pie.length > 1) {
            $("#my_appointment_type_today").css("height", "500px");
        }
    }
    function MyAppointmentByStatus(pie,colors) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(pie);
        var options = {
            colors: colors
        };
        var chart = new google.visualization.PieChart(document.getElementById('my_appointment_status_today'));
            chart.draw(data, options);
        });
        if (pie.length > 1) {
            $("#my_appointment_status_today").css("height", "500px");
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
    function myCollectionCentreChart(pie) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(pie);
        var options = {
            colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
        };
        var chart = new google.visualization.PieChart(document.getElementById('my-collection-by-centre'));
            chart.draw(data, options);
        });
        if (pie.length > 1) {
            $("#my-collection-by-centre").css("height", "500px");
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
    function myRevenueCentreChart(centerRevenue) {
        google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(centerRevenue);
        var options = {
            colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
        };
        var chart = new google.visualization.PieChart(document.getElementById('my-revenue-centre'));
            chart.draw(data, options);
        });
        if (centerRevenue.length > 1) {
            $("#my-revenue-centre").css("height", "500px");
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
    function myrevenueByService(service, colors) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(service);
        var options = {
            colors: colors
        };
        var chart = new google.visualization.PieChart(document.getElementById('my-revenue-service'));
            chart.draw(data, options);
        });
        if (typeof service !== 'undefined' && service.length > 1) {
            $("#my-revenue-service").css("height", "500px");
        }
    }
</script>
@endpush

@endsection
