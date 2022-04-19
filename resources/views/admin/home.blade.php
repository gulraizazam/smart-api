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
                    <div class="col-lg-6 col-xxl-4">
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
                                               <option value="week" {{request('type') == 'week' ? 'selected' : ''}}>Last 7 Days</option>
                                               <option value="month" {{request('type') == 'month' ? 'selected' : ''}}>This Month</option>
                                           </select>

                                        </form>

                                        {{--<a href="#" class="btn btn-transparent-white btn-sm font-weight-bolder dropdown-toggle px-5" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Today</a>
                                        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">
                                            <!--begin::Navigation-->
                                            <ul class="navi navi-hover">
                                                <li class="navi-item">
                                                    <a href="#" class="navi-link">
                                                        <span class="navi-icon">
                                                            <i class="la la-chart-bar"></i>
                                                        </span>
                                                        <span class="navi-text">Yesterday</span>
                                                    </a>
                                                </li>
                                                <li class="navi-item">
                                                    <a href="#" class="navi-link">
                                                        <span class="navi-icon">
                                                            <i class="la la-chart-bar"></i>
                                                        </span>
                                                        <span class="navi-text">2 days ago</span>
                                                    </a>
                                                </li>
                                            </ul>
                                            <!--end::Navigation-->
                                        </div>--}}
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

                                                          <span class="dashboard-counter">{{number_format($revenue)}}</span>
                                                    </span>
                                            <a href="javascript:void(0);" style="cursor: none;" class="text-warning font-weight-bold font-size-h6">Sales</a>
                                        </div>
                                        <div class="col bg-light-primary px-6 py-8 rounded-xl mb-7">
                                                <span class="svg-icon svg-icon-3x svg-icon-primary d-block my-2">

                                                    <i class="la la-stethoscope" style="font-size: 40px;"></i>

                                                      <span class="dashboard-counter">{{$done_consultancies}}/{{$all_consultancies}}</span>
                                                </span>
                                            <a href="{{route('admin.appointments.index', ['tab' => 'appointment', 'type' => '1', 'from' => $start_date, 'to' => $end_date, 'center_id' => $location_id, 'appoint_status' => $appointment_status_arrived])}}" class="text-primary font-weight-bold font-size-h6 mt-2">Consultancies</a>
                                        </div>
                                    </div>
                                    <!--end::Row-->
                                    <!--begin::Row-->
                                    <div class="row m-0">
                                        <div class="col bg-light-danger px-6 py-8 rounded-xl mr-7">
                                                    <span class="svg-icon svg-icon-3x svg-icon-danger d-block my-2">
                                                       <i class="la la-medkit" style="font-size: 40px;"></i>
                                                         <span class="dashboard-counter">{{$done_treatments}}/{{$all_treatments}}</span>
                                                    </span>
                                            <a href="{{route('admin.appointments.index', ['tab' => 'appointment', 'type' => '2', 'from' => $start_date, 'to' => $end_date, 'center_id' => $location_id, 'appoint_status' => $appointment_status_arrived])}}" class="text-danger font-weight-bold font-size-h6 mt-2">Treatments</a>
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
                                                      <span class="dashboard-counter">{{$leads}}</span>
                                                    </span>

                                            <a  href="javascript:void(0);" style="cursor: none;" class="text-success font-weight-bold font-size-h6 mt-2">Leads</a>
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


                    <div class="col-lg-6 col-xxl-4">
                        <!--begin::List Widget 9-->
                        <div class="card card-custom card-stretch gutter-b">
                            <!--begin::Header-->
                            <div class="card-header align-items-center border-0 mt-4">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="font-weight-bolder text-dark">My Activity</span>
                                    <span class="text-muted mt-3 font-weight-bold font-size-sm">890,344 Sales</span>
                                </h3>
                                <div class="card-toolbar">
                                    <div class="dropdown dropdown-inline">
                                        <a href="#" class="btn btn-clean btn-hover-light-primary btn-sm btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="ki ki-bold-more-hor"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-md dropdown-menu-right">
                                            <!--begin::Navigation-->
                                            <ul class="navi navi-hover">
                                                <li class="navi-header font-weight-bold py-4">
                                                    <span class="font-size-lg">Choose Label:</span>
                                                    <i class="flaticon2-information icon-md text-muted" data-toggle="tooltip" data-placement="right" title="Click to learn more..."></i>
                                                </li>
                                                <li class="navi-separator mb-3 opacity-70"></li>
                                                <li class="navi-item">
                                                    <a href="#" class="navi-link">
                                                                <span class="navi-text">
                                                                    <span class="label label-xl label-inline label-light-success">Customer</span>
                                                                </span>
                                                    </a>
                                                </li>
                                                <li class="navi-item">
                                                    <a href="#" class="navi-link">
                                                                <span class="navi-text">
                                                                    <span class="label label-xl label-inline label-light-danger">Partner</span>
                                                                </span>
                                                    </a>
                                                </li>
                                                <li class="navi-item">
                                                    <a href="#" class="navi-link">
                                                                <span class="navi-text">
                                                                    <span class="label label-xl label-inline label-light-warning">Suplier</span>
                                                                </span>
                                                    </a>
                                                </li>
                                                <li class="navi-item">
                                                    <a href="#" class="navi-link">
                                                                <span class="navi-text">
                                                                    <span class="label label-xl label-inline label-light-primary">Member</span>
                                                                </span>
                                                    </a>
                                                </li>
                                                <li class="navi-item">
                                                    <a href="#" class="navi-link">
                                                                <span class="navi-text">
                                                                    <span class="label label-xl label-inline label-light-dark">Staff</span>
                                                                </span>
                                                    </a>
                                                </li>
                                                <li class="navi-separator mt-3 opacity-70"></li>
                                                <li class="navi-footer py-4">
                                                    <a class="btn btn-clean font-weight-bold btn-sm" href="#">
                                                        <i class="ki ki-plus icon-sm"></i>Add new</a>
                                                </li>
                                            </ul>
                                            <!--end::Navigation-->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Header-->
                            <!--begin::Body-->
                            <div class="card-body pt-4">
                                <!--begin::Timeline-->
                                <div class="timeline timeline-6 mt-3">
                                    <!--begin::Item-->
                                    <div class="timeline-item align-items-start">
                                        <!--begin::Label-->
                                        <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">08:42</div>
                                        <!--end::Label-->
                                        <!--begin::Badge-->
                                        <div class="timeline-badge">
                                            <i class="fa fa-genderless text-warning icon-xl"></i>
                                        </div>
                                        <!--end::Badge-->
                                        <!--begin::Text-->
                                        <div class="font-weight-mormal font-size-lg timeline-content text-muted pl-3">Outlines keep you honest. And keep structure</div>
                                        <!--end::Text-->
                                    </div>
                                    <!--end::Item-->
                                    <!--begin::Item-->
                                    <div class="timeline-item align-items-start">
                                        <!--begin::Label-->
                                        <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">10:00</div>
                                        <!--end::Label-->
                                        <!--begin::Badge-->
                                        <div class="timeline-badge">
                                            <i class="fa fa-genderless text-success icon-xl"></i>
                                        </div>
                                        <!--end::Badge-->
                                        <!--begin::Content-->
                                        <div class="timeline-content d-flex">
                                            <span class="font-weight-bolder text-dark-75 pl-3 font-size-lg">AEOL meeting</span>
                                        </div>
                                        <!--end::Content-->
                                    </div>
                                    <!--end::Item-->
                                    <!--begin::Item-->
                                    <div class="timeline-item align-items-start">
                                        <!--begin::Label-->
                                        <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">14:37</div>
                                        <!--end::Label-->
                                        <!--begin::Badge-->
                                        <div class="timeline-badge">
                                            <i class="fa fa-genderless text-danger icon-xl"></i>
                                        </div>
                                        <!--end::Badge-->
                                        <!--begin::Desc-->
                                        <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">Make deposit
                                            <a href="#" class="text-primary">USD 700</a>. to ESL</div>
                                        <!--end::Desc-->
                                    </div>
                                    <!--end::Item-->
                                    <!--begin::Item-->
                                    <div class="timeline-item align-items-start">
                                        <!--begin::Label-->
                                        <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">16:50</div>
                                        <!--end::Label-->
                                        <!--begin::Badge-->
                                        <div class="timeline-badge">
                                            <i class="fa fa-genderless text-primary icon-xl"></i>
                                        </div>
                                        <!--end::Badge-->
                                        <!--begin::Text-->
                                        <div class="timeline-content font-weight-mormal font-size-lg text-muted pl-3">Indulging in poorly driving and keep structure keep great</div>
                                        <!--end::Text-->
                                    </div>
                                    <!--end::Item-->
                                    <!--begin::Item-->
                                    <div class="timeline-item align-items-start">
                                        <!--begin::Label-->
                                        <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">21:03</div>
                                        <!--end::Label-->
                                        <!--begin::Badge-->
                                        <div class="timeline-badge">
                                            <i class="fa fa-genderless text-danger icon-xl"></i>
                                        </div>
                                        <!--end::Badge-->
                                        <!--begin::Desc-->
                                        <div class="timeline-content font-weight-bolder text-dark-75 pl-3 font-size-lg">New order placed
                                            <a href="#" class="text-primary">#XF-2356</a>.</div>
                                        <!--end::Desc-->
                                    </div>
                                    <!--end::Item-->
                                    <!--begin::Item-->
                                    <div class="timeline-item align-items-start">
                                        <!--begin::Label-->
                                        <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">23:07</div>
                                        <!--end::Label-->
                                        <!--begin::Badge-->
                                        <div class="timeline-badge">
                                            <i class="fa fa-genderless text-info icon-xl"></i>
                                        </div>
                                        <!--end::Badge-->
                                        <!--begin::Text-->
                                        <div class="timeline-content font-weight-mormal font-size-lg text-muted pl-3">Outlines keep and you honest. Indulging in poorly driving</div>
                                        <!--end::Text-->
                                    </div>
                                    <!--end::Item-->
                                    <!--begin::Item-->
                                    <div class="timeline-item align-items-start">
                                        <!--begin::Label-->
                                        <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">16:50</div>
                                        <!--end::Label-->
                                        <!--begin::Badge-->
                                        <div class="timeline-badge">
                                            <i class="fa fa-genderless text-primary icon-xl"></i>
                                        </div>
                                        <!--end::Badge-->
                                        <!--begin::Text-->
                                        <div class="timeline-content font-weight-mormal font-size-lg text-muted pl-3">Indulging in poorly driving and keep structure keep great</div>
                                        <!--end::Text-->
                                    </div>
                                    <!--end::Item-->
                                    <!--begin::Item-->
                                    <div class="timeline-item align-items-start">
                                        <!--begin::Label-->
                                        <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">21:03</div>
                                        <!--end::Label-->
                                        <!--begin::Badge-->
                                        <div class="timeline-badge">
                                            <i class="fa fa-genderless text-danger icon-xl"></i>
                                        </div>
                                        <!--end::Badge-->
                                        <!--begin::Desc-->
                                        <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">New order placed
                                            <a href="#" class="text-primary">#XF-2356</a>.</div>
                                        <!--end::Desc-->
                                    </div>
                                    <!--end::Item-->
                                </div>
                                <!--end::Timeline-->
                            </div>
                            <!--end: Card Body-->
                        </div>
                        <!--end: List Widget 9-->
                    </div>

                    <div class="col-lg-6 col-xxl-4">
                        <!--begin::Stats Widget 11-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b">
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
                        </div>
                        <!--end::Stats Widget 11-->
                        <!--begin::Stats Widget 12-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b">
                            <!--begin::Body-->
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
                            <!--end::Body-->
                        </div>
                        <!--end::Stats Widget 12-->
                    </div>

                    {{--start datatable--}}
                <!--begin::List Widget 1-->
                    <div class="col-lg-8 col-xxl-8">
                    <div class="card card-custom card-stretch gutter-b">
                        <!--begin::Header-->
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label font-weight-bolder text-dark">Up coming</span>
                                <span class="text-muted mt-3 font-weight-bold font-size-sm">More than <span class=" badge badge-circle badge-info total-members"></span> up coming</span>
                            </h3>
                            <div class="card-toolbar">
                                <div class="dropdown dropdown-inline" data-toggle="tooltip" title="Quick actions" data-placement="left">
                                    <div class="btn-location">
                                        <button class="arrival-btn btn btn-default" onclick="getArrivalsByDate($(this), '{{$month}}', '{{$currentTime}}', 'month');">This Month</button>
                                        <button class="arrival-btn btn btn-default" onclick="getArrivalsByDate($(this), '{{$startWeek}}', '{{$currentTime}}', 'week');">This Week</button>
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

                    <div class="col-lg-6 col-xxl-4">
                        <!--begin::Stats Widget 11-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b">
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

                                    <span class="dashboard-counter" style="margin-left: -40px;">Collection by Service</span>

                                    <div class="d-flex flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-service"></span>
                                        <span class="text-muted font-weight-bold mt-2 service-title">Weekly Income</span>
                                    </div>
                                </div>

                                <div id="revenue-service"></div>

                            </div>
                            <!--end::Body-->
                        </div>
                        <!--end::Stats Widget 11-->

                        <!--begin::Stats Widget 11-->
                        <div class="card card-custom card-stretch card-stretch-half gutter-b">
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

                                    <span class="dashboard-counter" style="margin-left: -30px;">Appointments By Status</span>

                                    <div class="d-flex flex-column text-right">
                                        <span class="text-dark-75 font-weight-bolder font-size-h3 total-appointments"></span>
                                        <span class="text-muted font-weight-bold mt-2 appointments-title"></span>
                                    </div>
                                </div>

                                <div id="appointments-status"></div>

                            </div>
                            <!--end::Body-->
                        </div>
                        <!--end::Stats Widget 11-->
                    </div>

                </div>
                <!--end::Row-->

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
        <script src="{{asset('assets/js/bar-chart.js')}}"></script>


        <script>

            $(document).ready( function () {

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

                /*revenue by service*/
                $.ajax({
                    url: route('admin.home.revenueByService'),
                    type: "GET",
                    data: {'type': '{{request('type')}}'},
                    cache: false,
                    success: function (response) {
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

                        revenueByService(pie);
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        errorMessage(xhr);
                    }
                });

               /*Appointment status*/
                $.ajax({
                    url: route('admin.home.appointmentByStatus'),
                    type: "GET",
                    data: {'type': '{{request('type')}}'},
                    cache: false,
                    success: function (response) {
                        let total = response.data.total;
                        let appointment = response.data.appointment;
                        $(".total-appointments").text(total)
                        @if(request('type') == 'today')
                            $(".appointments-title").text('Today status')
                        @endif
                        @if(request('type') == 'yesterday')
                            $(".appointments-title").text('Yesterday status')
                        @endif
                        @if(request('type') == 'week')
                            $(".appointments-title").text('Weekly status')
                        @endif
                        @if(request('type') == 'month')
                            $(".appointments-title").text('Monthly status')
                        @endif

                        appoitmentsByStatus(appointment);
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        errorMessage(xhr);
                    }
                });

            });


            /*Center pie chart*/
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
            }

            /*Appointments*/
            function revenueByService(service) {

                google.load('visualization', '1', {
                    packages: ['corechart', 'bar', 'line']
                });

                google.setOnLoadCallback(function () {

                    var data = google.visualization.arrayToDataTable(service);

                    var options = {
                        title: 'Collections',
                        colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
                    };

                    var chart = new google.visualization.PieChart(document.getElementById('revenue-service'));
                    chart.draw(data, options);
                });
            }


            function appoitmentsByStatus(appointment) {

                google.load('visualization', '1', {
                    packages: ['corechart', 'bar', 'line']
                });

                google.setOnLoadCallback(function () {

                    var data = google.visualization.arrayToDataTable(appointment);

                    var options = {
                        title: 'Statuses',
                        colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
                    };

                    var chart = new google.visualization.PieChart(document.getElementById('appointments-status'));
                    chart.draw(data, options);
                });
            }

            /*not in use*/
           function barChart(bar) {

                if (bar.length) {
                    $(".bar-chart-no-date").remove();
                    /*bar chart*/
                    const ctx = document.getElementById('barChart');
                    const myChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: bar[0],
                            datasets: [{
                                label: 'Revenue',
                                data: bar[1],
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.2)',
                                    'rgba(54, 162, 235, 0.2)',
                                    'rgba(255, 206, 86, 0.2)',
                                    'rgba(75, 192, 192, 0.2)',
                                    'rgba(153, 102, 255, 0.2)',
                                    'rgba(255, 159, 64, 0.2)'
                                ],
                                borderColor: [
                                    'rgba(255, 99, 132, 1)',
                                    'rgba(54, 162, 235, 1)',
                                    'rgba(255, 206, 86, 1)',
                                    'rgba(75, 192, 192, 1)',
                                    'rgba(153, 102, 255, 1)',
                                    'rgba(255, 159, 64, 1)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }

           }



        </script>
    @endpush

@endsection
