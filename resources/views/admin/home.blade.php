@extends('admin.layouts.master')

@section('content')

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
                                        <form id="dashboard-states" method="get" action="{{route('admin.home')}}">

                                           <select class="form-control" name="type" onchange="changeDate();">
                                               <option value="today"  {{request('type') == 'today' ? 'selected' : ''}}>Today</option>
                                               <option value="yesterday" {{request('type') == 'yesterday' ? 'selected' : ''}}>Yesterday</option>
                                               <option value="week" {{request('type') == 'week' ? 'selected' : ''}}>This Week</option>
                                               <option value="month" {{request('type') == 'month' ? 'selected' : ''}}>This Month</option>
                                           </select>

                                        </form>

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
                                        <div class="col bg-light-warning px-6 py-8 rounded-xl mr-7 mb-7">
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

                                                          <span class="dashboard-counter">{{!is_null($revenue) ? number_format($revenue) : 'Your are not authorized'}}</span>
                                                    </span>
                                            <a href="javascript:void(0);" style="cursor: pointer;" class="text-warning font-weight-bold font-size-h6">Sales</a>
                                        </div>
                                        <div class="col bg-light-primary px-6 py-8 rounded-xl mb-7">
                                                <span class="svg-icon svg-icon-3x svg-icon-primary d-block my-2">

                                                    <i class="la la-stethoscope" style="font-size: 40px;"></i>

                                                      <span class="dashboard-counter">{{!is_null($done_consultancies) && !is_null($all_consultancies) ? $done_consultancies .'/'.$all_consultancies : 'Your are not authorized'}}</span>
                                                </span>
                                            @if(!is_null($done_consultancies) && !is_null($all_consultancies))
                                                <a href="{{route('admin.appointments.index', ['tab' => 'appointment', 'type' => '1', 'from' => $start_date, 'to' => $end_date, 'center_id' => implode(',', $location_id)])}}" class="text-primary font-weight-bold font-size-h6 mt-2">Consultancies</a>
                                            @else

                                            <a href="javascript:void(0);" class="text-primary font-weight-bold font-size-h6 mt-2">Consultancies</a>
                                            @endif
                                        </div>
                                    </div>
                                    <!--end::Row-->
                                    <!--begin::Row-->
                                    <div class="row m-0">
                                        <div class="col bg-light-danger px-6 py-8 rounded-xl mr-7">
                                                    <span class="svg-icon svg-icon-3x svg-icon-danger d-block my-2">
                                                       <i class="la la-medkit" style="font-size: 40px;"></i>
                                                         <span class="dashboard-counter">{{!is_null($done_treatments) && !is_null($all_treatments) ? $done_treatments .'/'. $all_treatments : 'Your are not authorized'}}</span>
                                                    </span>
                                            @if(!is_null($done_treatments) && !is_null($all_treatments))
                                                <a href="{{route('admin.appointments.index', ['tab' => 'appointment', 'type' => '2', 'from' => $start_date, 'to' => $end_date, 'center_id' => implode(',', $location_id)])}}" class="text-danger font-weight-bold font-size-h6 mt-2">Treatments</a>
                                            @else
                                            <a href="javascript:void(0);" class="text-danger font-weight-bold font-size-h6 mt-2">Treatments</a>
                                            @endif
                                        </div>
                                        <div class="col bg-light-success px-6 py-8 rounded-xl">
                                                  <span class="svg-icon svg-icon-3x svg-icon-primary d-block my-2">
                                                        <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Communication/Add-user.svg-->
                                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                <polygon points="0 0 24 0 24 24 0 24" />
                                                                <path d="M18,8 L16,8 C15.4477153,8 15,7.55228475 15,7 C15,6.44771525 15.4477153,6 16,6 L18,6 L18,4 C18,3.44771525 18.4477153,3 19,3 C19.5522847,3 20,3.44771525 20,4 L20,6 L22,6 C22.5522847,6 23,6.44771525 23,7 C23,7.55228475 22.5522847,8 22,8 L20,8 L20,10 C20,10.5522847 19.5522847,11 19,11 C18.4477153,11 18,10.5522847 18,10 L18,8 Z M9,11 C6.790861,11 5,9.209139 5,7 C5,4.790861 6.790861,3 9,3 C11.209139,3 13,4.790861 13,7 C13,9.209139 11.209139,11 9,11 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" />
                                                                <path d="M0.00065168429,20.1992055 C0.388258525,15.4265159 4.26191235,13 8.98334134,13 C13.7712164,13 17.7048837,15.2931929 17.9979143,20.2 C18.0095879,20.3954741 17.9979143,21 17.2466999,21 C13.541124,21 8.03472472,21 0.727502227,21 C0.476712155,21 -0.0204617505,20.45918 0.00065168429,20.1992055 Z" fill="#000000" fill-rule="nonzero" />
                                                            </g>
                                                        </svg>
                                                      <!--end::Svg Icon-->
                                                      <span class="dashboard-counter">{{$leads !== false && $totalLeads !== false ? $leads .'/'. $totalLeads : 'Your are not authorized'}}</span>
                                                    </span>

                                            <a href="{{route('admin.leads.index', ['from' => $start_date, 'to' => $end_date])}}" style="cursor: pointer;" class="text-success font-weight-bold font-size-h6 mt-2">Leads</a>
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
                    <div class="col-lg-6 col-xxl-6">
                        <!--begin::List Widget 9-->
                        <div class="card card-custom card-stretch gutter-b" style="height: 600px; overflow-y: auto;">
                            <!--begin::Header-->
                            <div class="card-header align-items-center border-0 mt-4">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="font-weight-bolder text-dark">Recent Activity</span>
                                    <span class="text-muted mt-3 font-weight-bold font-size-sm">{{count($finance_log) + count($appointment_log)}} activities</span>
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

                                @if(count($finance_log) + count($appointment_log) > 0)
                                    <div class="timeline timeline-6 mt-3">


                                        @foreach($appointment_log as $appoint_log)

                                            <div class="timeline-item align-items-start">
                                                    <!--begin::Label-->
                                                    <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">{{\Illuminate\Support\Carbon::parse($appoint_log['time'])->format("h:i")}}</div>
                                                    <!--end::Label-->
                                                    <!--begin::Badge-->
                                                    <div class="timeline-badge">
                                                        <i class="fa fa-genderless text-success icon-xl"></i>
                                                    </div>
                                                    <!--end::Badge-->
                                                    <!--begin::Content-->
                                                    <div class="timeline-content d-flex">
                                                   <span class="font-weight-bolder text-dark-75 pl-3 font-size-lg">
                                                       @if($appoint_log['type'] == 'rescheduled')
                                                           <span style="color: #056FBF;">{{$appoint_log['action_by'] ?? 'N/A'}}</span>
                                                           {{$appoint_log['action'] ?? 'N/A'}} <span style="color: #F5B183;">{{$appoint_log['screen'] ?? 'N/A'}}</span>
                                                           for <span style="color: #3E7FBB;">{{$appoint_log['action_for']}}</span>
                                                           to {{\Illuminate\Support\Carbon::parse($appoint_log['date'])->format("d/m/Y") ?? 'N/A'}}
                                                       @elseif($appoint_log['type'] == 'booked')

                                                           <span style="color: #056FBF;">{{$appoint_log['action_by'] ?? 'N/A'}}</span>
                                                           a {{$appoint_log['action'] ?? 'N/A'}}
                                                           <span style="color: #F5B183;">{{$appoint_log['screen'] ?? 'N/A'}}</span>
                                                           for <span style="color: #3E7FBB;">{{$appoint_log['action_for']}}</span>
                                                           at <span style="color: #F5B183;">{{\Illuminate\Support\Carbon::parse($appoint_log['time'])->format("h:s A") ?? 'N/A'}} {{\Illuminate\Support\Carbon::parse($appoint_log['date'])->format("d/m/Y") ?? 'N/A'}} </span>
                                                           in {{$appoint_log['address'] ?? 'N/A'}}

                                                       @else
                                                           <span style="color: #056FBF;">{{$appoint_log['action_by'] ?? 'N/A'}}</span>
                                                           {{$appoint_log['action'] ?? 'N/A'}} <span style="color: #F5B183;">{{$appoint_log['screen'] ?? 'N/A'}}</span>
                                                           for <span style="color: #3E7FBB;">{{$appoint_log['action_for']}}</span>
                                                           in {{$appoint_log['address'] ?? 'N/A'}}
                                                       @endif


                                                   </span>
                                                    </div>
                                                    <!--end::Content-->
                                                </div>

                                        @endforeach

                                        @foreach($finance_log as $log)

                                        <div class="timeline-item align-items-start">
                                            <!--begin::Label-->
                                            <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">{{\Illuminate\Support\Carbon::parse($log['created_at'])->format("h:i")}}</div>
                                            <!--end::Label-->
                                            <!--begin::Badge-->
                                            <div class="timeline-badge">
                                                <i class="fa fa-genderless text-danger icon-xl"></i>
                                            </div>
                                            <!--end::Badge-->
                                            <!--begin::Desc-->
                                            <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">
                                                <span class="text-primary">{{$log['user_id'] ?? 'N/A'}}</span>
                                                {{$log['action'] ?? 'N/A'}} a payment of
                                                 <strong class="text-dark">{{ $log['cash_amount'] }}</strong> for
                                                <span  class="text-primary"> {{$log['patient_id']}}</span> against
                                                <span  class="text-info">{{$log['appointment_type_id'] ?? 'Appointment'}}</span>
                                                 In  {{$log['location_id']}} Centre
                                            </div>
                                            <!--end::Desc-->
                                        </div>

                                    @endforeach

                                </div>

                                @else
                                    <div class="text-center">
                                        <span >No Activity Found</span>
                                    </div>
                                @endif
                                @endif

                            <!--end::Timeline-->
                            </div>
                            <!--end: Card Body-->
                        </div>
                        <!--end: List Widget 9-->
                    </div>

                    {{--Collections by centers--}}
                    <div class="col-lg-6 col-xxl-6">
                        <!--begin::Stats Widget 11-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">

                            @if(\Illuminate\Support\Facades\Gate::allows("dashboard_collection_by_centre"))

                            <!--begin::Body-->
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                    <span class="symbol symbol-50 symbol-light-success mr-2">
                                        <span class="symbol-label">
                                            <span class="svg-icon svg-icon-xl svg-icon-success">
                                                <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <rect x="0" y="0" width="24" height="24" />
                                                        <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                                        <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                                    </g>
                                                </svg>
                                                <!--end::Svg Icon-->
                                            </span>
                                        </span>
                                    </span>

                                    <span class="dashboard-counter" style="margin-left: -40px;">Collection by Centre</span>

                                    <div class="d-flex flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-pie-chart"></span>
                                        <span class="text-muted font-weight-bold mt-2 pie-income-title">Weekly Income</span>
                                    </div>

                                </div>

                                <div id="collection-by-centre"></div>

                            </div>
                            <!--end::Body-->
                            @else
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                <span class="symbol symbol-50 symbol-light-success mr-2">
                                    <span class="symbol-label">
                                        <span class="svg-icon svg-icon-xl svg-icon-success">
                                            <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <rect x="0" y="0" width="24" height="24" />
                                                    <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                                    <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                                </g>
                                            </svg>
                                            <!--end::Svg Icon-->
                                        </span>
                                    </span>
                                </span>

                                        <span class="dashboard-counter" style="margin-left: -40px;">Collection by Centre</span>

                                        <div class="d-flex flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3">N/A</span>
                                            <span class="text-muted font-weight-bold mt-2 pie-income-title">Income</span>
                                        </div>


                                    </div>

                                    <div class="text-center">Your are not authorized</div>

                                </div>
                            @endif

                        </div>
                        <!--end::Stats Widget 11-->
                    </div>

                    {{--My Collections by centers--}}
                    <div class="col-lg-6 col-xxl-6">
                        <!--My Collection by centre-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <!--begin::Body-->
                            @if(\Illuminate\Support\Facades\Gate::allows('dashboard_my_collection_by_centre'))
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                        <span class="symbol symbol-50 symbol-light-success mr-2">
                                            <span class="symbol-label">
                                                <span class="svg-icon svg-icon-xl svg-icon-success">
                                                    <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                            <rect x="0" y="0" width="24" height="24" />
                                                            <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                                            <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                                        </g>
                                                    </svg>
                                                    <!--end::Svg Icon-->
                                                </span>
                                            </span>
                                        </span>

                                        <span class="dashboard-counter" style="margin-left: -30px;">My Collection by Centre</span>

                                        <div class="d-flex flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 my-total-collection-center"></span>
                                            <span class="text-muted font-weight-bold mt-2 my-collection-title"></span>
                                        </div>
                                    </div>

                                    <div id="my-collection-by-centre"></div>

                                </div>
                            @else

                                <div class="card-body p-0 " style="height: 400px;">
                                    <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                                <span class="symbol symbol-50 symbol-light-success mr-2">
                                                    <span class="symbol-label">
                                                        <span class="svg-icon svg-icon-xl svg-icon-success">
                                                            <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                    <rect x="0" y="0" width="24" height="24" />
                                                                    <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                                                    <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                                                </g>
                                                            </svg>
                                                            <!--end::Svg Icon-->
                                                        </span>
                                                    </span>
                                                </span>

                                        <span class="dashboard-counter" style="margin-left: -30px;">My Collection by Centre</span>

                                        <div class="d-flex flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3">N/A</span>
                                            <span class="text-muted font-weight-bold mt-2 ">My Collection</span>
                                        </div>
                                    </div>

                                    <div class="text-center">Your are not authorized</div>

                                </div>

                        @endif
                        <!--end::Body-->
                        </div>
                        <!--end::My Collection by centre-->
                    </div>

                    <div class="col-lg-6 col-xxl-6">
                        <!--begin::Revenue by center-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <!--begin::Body-->
                            @if(\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_centre'))
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                            <span class="symbol symbol-50 symbol-light-primary mr-2">
                                                <span class="symbol-label">
                                                    <span class="svg-icon svg-icon-xl svg-icon-primary">
                                                        <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Communication/Group.svg-->
                                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                <polygon points="0 0 24 0 24 24 0 24" />
                                                                <path d="M18,14 C16.3431458,14 15,12.6568542 15,11 C15,9.34314575 16.3431458,8 18,8 C19.6568542,8 21,9.34314575 21,11 C21,12.6568542 19.6568542,14 18,14 Z M9,11 C6.790861,11 5,9.209139 5,7 C5,4.790861 6.790861,3 9,3 C11.209139,3 13,4.790861 13,7 C13,9.209139 11.209139,11 9,11 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" />
                                                                <path d="M17.6011961,15.0006174 C21.0077043,15.0378534 23.7891749,16.7601418 23.9984937,20.4 C24.0069246,20.5466056 23.9984937,21 23.4559499,21 L19.6,21 C19.6,18.7490654 18.8562935,16.6718327 17.6011961,15.0006174 Z M0.00065168429,20.1992055 C0.388258525,15.4265159 4.26191235,13 8.98334134,13 C13.7712164,13 17.7048837,15.2931929 17.9979143,20.2 C18.0095879,20.3954741 17.9979143,21 17.2466999,21 C13.541124,21 8.03472472,21 0.727502227,21 C0.476712155,21 -0.0204617505,20.45918 0.00065168429,20.1992055 Z" fill="#000000" fill-rule="nonzero" />
                                                            </g>
                                                        </svg>
                                                        <!--end::Svg Icon-->
                                                    </span>
                                                </span>
                                            </span>
                                        <span class="dashboard-counter" style="margin-left: -40px;">Revenue by Centre</span>
                                        <div class="d-flex flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-centre"></span>
                                            <span class="text-muted font-weight-bold mt-2 revenue-centre-title">Today Revenue</span>
                                        </div>
                                    </div>
                                    <div id="revenue-centre"></div>

                                </div>

                            @else

                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                            <span class="symbol symbol-50 symbol-light-primary mr-2">
                                                <span class="symbol-label">
                                                    <span class="svg-icon svg-icon-xl svg-icon-primary">
                                                        <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Communication/Group.svg-->
                                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                <polygon points="0 0 24 0 24 24 0 24" />
                                                                <path d="M18,14 C16.3431458,14 15,12.6568542 15,11 C15,9.34314575 16.3431458,8 18,8 C19.6568542,8 21,9.34314575 21,11 C21,12.6568542 19.6568542,14 18,14 Z M9,11 C6.790861,11 5,9.209139 5,7 C5,4.790861 6.790861,3 9,3 C11.209139,3 13,4.790861 13,7 C13,9.209139 11.209139,11 9,11 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" />
                                                                <path d="M17.6011961,15.0006174 C21.0077043,15.0378534 23.7891749,16.7601418 23.9984937,20.4 C24.0069246,20.5466056 23.9984937,21 23.4559499,21 L19.6,21 C19.6,18.7490654 18.8562935,16.6718327 17.6011961,15.0006174 Z M0.00065168429,20.1992055 C0.388258525,15.4265159 4.26191235,13 8.98334134,13 C13.7712164,13 17.7048837,15.2931929 17.9979143,20.2 C18.0095879,20.3954741 17.9979143,21 17.2466999,21 C13.541124,21 8.03472472,21 0.727502227,21 C0.476712155,21 -0.0204617505,20.45918 0.00065168429,20.1992055 Z" fill="#000000" fill-rule="nonzero" />
                                                            </g>
                                                        </svg>
                                                        <!--end::Svg Icon-->
                                                    </span>
                                                </span>
                                            </span>
                                        <span class="dashboard-counter" style="margin-left: -40px;">Revenue by Centre</span>
                                        <div class="d-flex flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3"> N/A</span>
                                            <span class="text-muted font-weight-bold mt-2 revenue-centre-title">Revenue</span>
                                        </div>
                                    </div>
                                    <div class="text-center">Your are not authorized</div>

                                </div>

                        @endif


                        <!--end::Body-->
                        </div>
                        <!--end::Revenue by center2-->
                    </div>

                    <div class="col-lg-6 col-xxl-6">

                        <!--begin::My Revenue by center-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <!--begin::Body-->
                            @if(\Illuminate\Support\Facades\Gate::allows('dashboard_my_revenue_by_centre'))
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                    <span class="symbol symbol-50 symbol-light-success mr-2">
                                        <span class="symbol-label">
                                            <span class="svg-icon svg-icon-xl svg-icon-success">
                                                <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <rect x="0" y="0" width="24" height="24" />
                                                        <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                                        <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                                    </g>
                                                </svg>
                                                <!--end::Svg Icon-->
                                            </span>
                                        </span>
                                    </span>

                                    <span class="dashboard-counter" style="margin-left: -40px;">My Revenue by Centre</span>

                                    <div class="d-flex flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-my-revenue-centre"></span>
                                        <span class="text-muted font-weight-bold mt-2 my-revenue-centre-title">Weekly Income</span>
                                    </div>
                                </div>

                                <div id="my-revenue-centre"></div>

                            </div>

                            @else

                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                            <span class="symbol symbol-50 symbol-light-success mr-2">
                                                <span class="symbol-label">
                                                    <span class="svg-icon svg-icon-xl svg-icon-success">
                                                        <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                <rect x="0" y="0" width="24" height="24" />
                                                                <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                                                <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                                            </g>
                                                        </svg>
                                                        <!--end::Svg Icon-->
                                                    </span>
                                                </span>
                                            </span>

                                        <span class="dashboard-counter" style="margin-left: -40px;">My Revenue by Centre</span>

                                        <div class="d-flex flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 e">N/A</span>
                                            <span class="text-muted font-weight-bold mt-2 service-title">Revenue</span>
                                        </div>
                                    </div>

                                    <div class="text-center">Your are not authorized</div>

                                </div>

                            @endif
                            <!--end::Body-->
                        </div>
                        <!--end::My Revenue by center-->

                    </div>


                    <div class="col-lg-6 col-xxl-6">

                        <!--begin::REVENUE BY SERVICE-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <!--begin::Body-->
                            @if(\Illuminate\Support\Facades\Gate::allows('dashboard_revenue_by_service'))
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                            <span class="symbol symbol-50 symbol-light-success mr-2">
                                                <span class="symbol-label">
                                                    <span class="svg-icon svg-icon-xl svg-icon-success">
                                                        <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                <rect x="0" y="0" width="24" height="24" />
                                                                <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                                                <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                                            </g>
                                                        </svg>
                                                        <!--end::Svg Icon-->
                                                    </span>
                                                </span>
                                            </span>

                                        <span class="dashboard-counter" style="margin-left: -40px;">REVENUE BY SERVICE</span>

                                        <div class="d-flex flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-service"></span>
                                            <span class="text-muted font-weight-bold mt-2 service-title">Weekly Income</span>
                                        </div>
                                    </div>

                                    <div id="revenue-service"></div>

                                </div>

                            @else

                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                            <span class="symbol symbol-50 symbol-light-success mr-2">
                                                <span class="symbol-label">
                                                    <span class="svg-icon svg-icon-xl svg-icon-success">
                                                        <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                <rect x="0" y="0" width="24" height="24" />
                                                                <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                                                <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                                            </g>
                                                        </svg>
                                                        <!--end::Svg Icon-->
                                                    </span>
                                                </span>
                                            </span>

                                        <span class="dashboard-counter" style="margin-left: -40px;">REVENUE BY SERVICE</span>

                                        <div class="d-flex flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 e">N/A</span>
                                            <span class="text-muted font-weight-bold mt-2 service-title">Income</span>
                                        </div>
                                    </div>

                                    <div class="text-center">Your are not authorized</div>

                                </div>

                        @endif
                        <!--end::Body-->
                        </div>
                        <!--end::REVENUE BY SERVICE-->

                    </div>

                    <div class="col-lg-6 col-xxl-6">
                        <!--begin::MY REVENUE BY SERVICE-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b" style="min-height: 605px;">
                            <!--begin::Body-->
                            @if(\Illuminate\Support\Facades\Gate::allows('dashboard_my_revenue_by_service'))
                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                            <span class="symbol symbol-50 symbol-light-success mr-2">
                                                <span class="symbol-label">
                                                    <span class="svg-icon svg-icon-xl svg-icon-success">
                                                        <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                <rect x="0" y="0" width="24" height="24" />
                                                                <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                                                <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                                            </g>
                                                        </svg>
                                                        <!--end::Svg Icon-->
                                                    </span>
                                                </span>
                                            </span>

                                        <span class="dashboard-counter" style="margin-left: -40px;">My REVENUE BY SERVICE</span>

                                        <div class="d-flex flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 total-my-service"></span>
                                            <span class="text-muted font-weight-bold mt-2 my-service-title">Weekly Income</span>
                                        </div>
                                    </div>

                                    <div id="my-revenue-service"></div>

                                </div>

                            @else

                                <div class="card-body p-0">
                                    <div class="d-flex align-items-center justify-content-between card-spacer flex-grow-1">
                                            <span class="symbol symbol-50 symbol-light-success mr-2">
                                                <span class="symbol-label">
                                                    <span class="svg-icon svg-icon-xl svg-icon-success">
                                                        <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Layout/Layout-4-blocks.svg-->
                                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                <rect x="0" y="0" width="24" height="24" />
                                                                <rect fill="#000000" x="4" y="4" width="7" height="7" rx="1.5" />
                                                                <path d="M5.5,13 L9.5,13 C10.3284271,13 11,13.6715729 11,14.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,14.5 C4,13.6715729 4.67157288,13 5.5,13 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,9.5 C20,10.3284271 19.3284271,11 18.5,11 L14.5,11 C13.6715729,11 13,10.3284271 13,9.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z M14.5,13 L18.5,13 C19.3284271,13 20,13.6715729 20,14.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,14.5 C13,13.6715729 13.6715729,13 14.5,13 Z" fill="#000000" opacity="0.3" />
                                                            </g>
                                                        </svg>
                                                        <!--end::Svg Icon-->
                                                    </span>
                                                </span>
                                            </span>

                                        <span class="dashboard-counter" style="margin-left: -40px;">My REVENUE BY SERVICE</span>

                                        <div class="d-flex flex-column text-right">
                                            <span class="text-dark-75 font-weight-bolder font-size-h3 e">N/A</span>
                                            <span class="text-muted font-weight-bold mt-2 my-service-title">Income</span>
                                        </div>
                                    </div>

                                    <div class="text-center">Your are not authorized</div>

                                </div>

                        @endif
                        <!--end::Body-->
                        </div>
                        <!--end::MY REVENUE BY SERVICE-->
                    </div>


                </div>

                @can('dashboard_upcomings')
                    <div class="col-lg-12 col-xxl-12">
                    <div class="card card-custom card-stretch gutter-b">
                        <!--begin::Header-->
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

                            <!--begin: Datatable-->
                            <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                            <!--end: Datatable-->
                        </div>

                    </div>
                    <!--end::List Widget 1-->
                    {{--end datatable--}}
                </div>
                @endcan


</div>
<!--end::Container-->
</div>
<!--end::Entry-->
</div>
<!--end::Content-->

@push('datatable-js')
<script src="{{asset('assets/js/pages/crud/forms/validation/appointment/validation.js')}}"></script>
<script src="{{asset('assets/js/pages/dashboard/datatable.js')}}"></script>
<script src="{{asset('assets/js/jsapi.js')}}"></script>

<script src="{{asset('assets/js/pie.js')}}"></script>


<script>

    $(document).ready( function () {

        /*collection by center*/
        $.ajax({
            url: route('admin.home.collectionByCentre'),
            type: "GET",
            data: {'type': '{{request('type')}}'},
            cache: false,
            success: function (response) {
                let total = response.data.total;
                $(".total-pie-chart").text(total)
                @if(request('type') == 'today')
                    $(".pie-income-title").text('Today Income')
                    var pie = response.data.pie.today;
                @endif
                @if(request('type') == 'yesterday')
                $(".pie-income-title").text('Yesterday Income')
                    var pie = response.data.pie.yesterday;
                @endif
                @if(request('type') == 'week')
                $(".pie-income-title").text('Weekly Income')
                    var pie = response.data.pie.week;
                @endif
                @if(request('type') == 'month')
                $(".pie-income-title").text('Monthly Income')
                    var pie = response.data.pie.month;
                @endif

                collectionCentreChart(pie);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });

        /*my collection by center*/
        $.ajax({
            url: route('admin.home.myCollectionByCentre'),
            type: "GET",
            data: {'type': '{{request('type')}}'},
            cache: false,
            success: function (response) {
                let total = response.data.total;
                $(".my-total-collection-center").text(total)
                @if(request('type') == 'today')
                    $(".my-collection-title").text('Today Income')
                    var pie = response.data.pie.today;
                @endif
                @if(request('type') == 'yesterday')
                    $(".my-collection-title").text('Yesterday Income')
                    var pie = response.data.pie.yesterday;
                @endif
                @if(request('type') == 'week')
                    $(".my-collection-title").text('Weekly Income')
                    var pie = response.data.pie.week;
                @endif
                @if(request('type') == 'month')
                    $(".my-collection-title").text('Monthly Income')
                    var pie = response.data.pie.month;
                @endif

                myCollectionCentreChart(pie);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });
        /*end pie chart*/

        /*bar chart*/
        $.ajax({
            url: route('admin.home.revenueByCentre'),
            type: "GET",
            data: {'type': '{{request('type')}}'},
            cache: false,
            success: function (response) {

                let total = response.data.total;
                let pie = response.data.pie;
                $(".total-centre").text(total)
                @if(request('type') == 'today')
                    $(".revenue-centre-title").text('Today Income')
                @endif
                @if(request('type') == 'yesterday')
                    $(".revenue-centre-title").text('Yesterday Income')

                @endif
                @if(request('type') == 'week')
                    $(".revenue-centre-title").text('Weekly Income')

                @endif
                @if(request('type') == 'month')
                    $(".revenue-centre-title").text('Monthly Income')
                @endif

                revenueCentreChart(pie);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });

        $.ajax({
            url: route('admin.home.myRevenueByCentre'),
            type: "GET",
            data: {'type': '{{request('type')}}'},
            cache: false,
            success: function (response) {

                let total = response.data.total;
                let pie = response.data.pie;
                $(".total-my-revenue-centre").text(total)
                @if(request('type') == 'today')
                    $(".my-revenue-centre-title").text('Today Income')
                @endif
                @if(request('type') == 'yesterday')
                    $(".my-revenue-centre-title").text('Yesterday Income')

                @endif
                @if(request('type') == 'week')
                    $(".my-revenue-centre-title").text('Weekly Income')

                @endif
                @if(request('type') == 'month')
                    $(".my-revenue-centre-title").text('Monthly Income')
                @endif

                myRevenueCentreChart(pie);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });

        /*revenue by service*/
        $.ajax({
            url: route('admin.home.revenueByService'),
            type: "GET",
            data: {'type': '{{request('type')}}'},
            cache: false,
            success: function (response) {

                let colors = response.data.colors;
                let total = response.data.total;
                $(".total-service").text(total)
                @if(request('type') == 'today')
                    $(".service-title").text('Today Income')
                var pie = response.data.pie.today;
                @endif
                @if(request('type') == 'yesterday')
                    $(".service-title").text('Yesterday Income')
                    var pie = response.data.pie.yesterday;
                @endif
                @if(request('type') == 'week')
                    $(".service-title").text('Weekly Income')
                    var pie = response.data.pie.week;
                @endif
                @if(request('type') == 'month')
                    $(".service-title").text('Monthly Income')
                    var pie = response.data.pie.month;
                @endif

                revenueByService(pie, colors);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });

       /*My revenue by service*/
        $.ajax({
            url: route('admin.home.myRevenueByService'),
            type: "GET",
            data: {'type': '{{request('type')}}'},
            cache: false,
            success: function (response) {

                let colors = response.data.colors;
                let total = response.data.total;
                $(".total-my-service").text(total)
                @if(request('type') == 'today')
                    $(".my-service-title").text('Today Income')
                var pie = response.data.pie.today;
                @endif
                @if(request('type') == 'yesterday')
                    $(".my-service-title").text('Yesterday Income')
                    var pie = response.data.pie.yesterday;
                @endif
                @if(request('type') == 'week')
                    $(".my-service-title").text('Weekly Income')
                    var pie = response.data.pie.week;
                @endif
                @if(request('type') == 'month')
                    $(".my-service-title").text('Monthly Income')
                    var pie = response.data.pie.month;
                @endif

                myrevenueByService(pie, colors);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });


    });


    /*Collection by cneter*/
    function collectionCentreChart(pie) {

        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });

        google.setOnLoadCallback(function () {

        var data = google.visualization.arrayToDataTable(pie);

        var options = {
            title: 'Collections',
            colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
        };

        var chart = new google.visualization.PieChart(document.getElementById('collection-by-centre'));
            chart.draw(data, options);
        });

        if (pie.length > 1) {
            $("#collection-by-centre").css("height", "500px");
        }
    }

    /*My Collection by cneter*/
    function myCollectionCentreChart(pie) {

        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });

        google.setOnLoadCallback(function () {

        var data = google.visualization.arrayToDataTable(pie);

        var options = {
            title: 'My Collections',
            colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
        };

        var chart = new google.visualization.PieChart(document.getElementById('my-collection-by-centre'));
            chart.draw(data, options);
        });

        if (pie.length > 1) {
            $("#my-collection-by-centre").css("height", "500px");
        }
    }

    /*revenue pie chart*/
    function revenueCentreChart(centerRevenue) {

        google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
        });

        google.setOnLoadCallback(function () {

        var data = google.visualization.arrayToDataTable(centerRevenue);

        var options = {
            title: 'Revenue',
            colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
        };

        var chart = new google.visualization.PieChart(document.getElementById('revenue-centre'));
            chart.draw(data, options);
        });

        if (centerRevenue.length > 1) {
            $("#revenue-centre").css("height", "500px");
        }

    }

    /*my revenue pie chart*/
    function myRevenueCentreChart(centerRevenue) {

        google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
        });

        google.setOnLoadCallback(function () {

        var data = google.visualization.arrayToDataTable(centerRevenue);

        var options = {
            title: 'My Revenue',
            colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
        };

        var chart = new google.visualization.PieChart(document.getElementById('my-revenue-centre'));
            chart.draw(data, options);
        });

        if (centerRevenue.length > 1) {
            $("#my-revenue-centre").css("height", "500px");
        }

    }

    /*Appointments*/
    function revenueByService(service, colors) {

        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });

        google.setOnLoadCallback(function () {

        var data = google.visualization.arrayToDataTable(service);

        var options = {
            title: 'Revenue',
            colors: colors
        };

        var chart = new google.visualization.PieChart(document.getElementById('revenue-service'));
            chart.draw(data, options);
        });

        if (service.length > 1) {
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
            title: 'My Revenue',
            colors: colors
        };

        var chart = new google.visualization.PieChart(document.getElementById('my-revenue-service'));
            chart.draw(data, options);
        });

        if (service.length > 1) {
            $("#my-revenue-service").css("height", "500px");
        }
    }

</script>
@endpush

@endsection
