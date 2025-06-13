@extends('admin.layouts.master')
@section('title', 'Memberships')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
<style>
    .bottom {
        margin-top: 3px;
        margin-left: 15px;
        /* Adjust as needed */
        text-align: right;
        /* Adjust as needed */
    }

    div#memberships_table_length {
        margin-left: 15px;
    }
</style>
<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Memberships Report'])
    <!--begin::Entry-->
    <div class="d-flex flex-column-fluid">
        <!--begin::Container-->
        <div class="container">
            <!--begin::Card-->
            <div class="card card-custom">
                <div class="card-header py-3">
                    <div class="card-title">
                        <span class="card-icon">
                            <span class="svg-icon svg-icon-md svg-icon-primary">
                                <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <rect x="0" y="0" width="24" height="24" />
                                        <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                        <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                        <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                        <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                    </g>
                                </svg>
                                <!--end::Svg Icon-->
                            </span>
                        </span>
                        <h3 class="card-label">Memberships Report</h3>

                    </div>
                    <div class="btn-group">
                        <a href="#" data-href="{{route('admin.memberships.export.excel')}}" class="btn btn-primary" id="csv-leads" data-action="2" class="tool-action" style=" height: 40px;">
                            <i class="fa fa-download"></i>
                            <span class="hidden-xs"> Export </span>
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="mt-2 mb-7">
                        <div class="row align-items-center">
                            <div class="col-lg-12 col-xl-12">
                                <div class="row align-items-center">
                                    <div class="form-group col-md-3 sn-select @if($errors->has('location_id')) has-error @endif" id="locations">
                                        {!! Form::label('location_id', 'Centre:', ['class' => 'control-label']) !!}
                                        <select class="form-control select2" id="location_id" name="service_id">
                                            <option value="">Select Centre</option>
                                            @foreach($locations as $key=>$location)
                                            <option value="{{$key}}">{{$location}}</option>
                                            @endforeach
                                        </select>
                                        <span id="location_id_handler"></span>
                                    </div>
                                    <div class="form-group col-md-3 sn-select @if($errors->has('membership_type')) has-error @endif" id="membership_type_id">
                                        {!! Form::label('membership_type', 'Memberhsip Type:', ['class' => 'control-label']) !!}
                                        <select class="form-control select2" id="membership_type" name="membership_type_id">
                                            <option value="">Select Membership Type</option>
                                            <option value="no_membership">No Membership</option>
                                            @foreach($membershipTypes as $key=>$membershipType)
                                            <option value="{{$key}}">{{$membershipType}}</option>
                                            @endforeach
                                        </select>
                                        <span id="location_id_handler"></span>
                                    </div>
                                    <div class="col-md-3 form-group sn-select @if($errors->has('date_range')) has-error @endif">
                                        {!! Form::label('date_range', 'Assigned Date*', ['class' => 'control-label']) !!}
                                        <div class="input-group">
                                            {!! Form::text('date_range', null, ['id' => 'date_range_membership', 'class' => 'form-control']) !!}
                                        </div>
                                    </div>

                                    <div class="form-group col-md-2 sn-select @if($errors->has('group_id')) has-error @endif">
                                        {!! Form::label('load_report', '&nbsp;', ['class' => 'control-label']) !!}<br />
                                        <button class="btn btn-primary btn-primary--icon" id="apply_filters">Load Report</button>
                                        <!-- <a href="javascript:void(0);" onclick="loadMembershipReport($(this));" id="load_membership_report" class="btn btn-success spinner-button">Load Report</a> -->
                                    </div>
                                    <div class="card-body">
                                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                                        <!-- <div style="overflow: hidden; width: 100%;" id="membership_report_content"></div> -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->
    @include('admin.settings.edit')
    @push('datatable-js')
    <script src="{{asset('assets/js/pages/admin_settings/settings.js')}}"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="{{asset('assets/js/memberhsipsreport.js')}}"></script>
    @endpush
    @endsection