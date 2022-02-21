@extends('admin.patients.card.patient_layout')
@inject('Auth', 'Auth')
@inject('filters', 'App\Helpers\Filters')
@section('patient_stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/pages/css/invoice.min.css') }}" rel="stylesheet" type="text/css" />
    <style type="text/css">
        .table-checkable tr > td:first-child, .table-checkable tr > th:first-child {
            text-align: left;
            max-width: 100%;
            min-width: 40px;
            padding-left: 10px;
            padding-right: 10px;
        }
    </style>
@endsection

@section('patient_content')
    <div class="portlet-title tabbable-line">
        <div class="caption caption-md">
            <i class="icon-globe theme-font hide"></i>
            <span class="caption-subject font-blue-madison bold uppercase">@lang('global.invoices.title')</span>
        </div>
        {{--<div class="actions">--}}
            {{--<a class="btn btn-success" href="{{ route('admin.plans.createplan',[$patient->id])}}" data-target="#ajax_packages_addnew" data-toggle="modal">@lang('global.app_add_new')</a>--}}
        {{--</div>--}}
    </div>
    <div class="portlet-body">
        <div class="table-container">
            <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                <thead>
                <tr role="row" class="heading">
                    <th>@lang('global.invoices.fields.patient_name')</th>
                    <th>@lang('global.invoices.fields.phone')</th>
                    <th>@lang('global.invoices.fields.region')</th>
                    <th>@lang('global.invoices.fields.city')</th>
                    <th>@lang('global.invoices.fields.location')</th>
                    <th>@lang('global.invoices.fields.service_name')</th>
                    <th>@lang('global.invoices.fields.invoive_status')</th>
                    <th>@lang('global.invoices.fields.total_price')</th>
                    <th>@lang('global.invoices.fields.created_at')</th>
                    <th width="20%">@lang('global.invoices.fields.actions')</th>
                </tr>
                <tr role="row" class="filter">
                    <td><input type="hidden" id="patient_id" value="{{ $patient->id }}" /></td>
                    <td></td>
                    <td>{{--{!! Form::select('region_id', $regions, $filters->get(Auth::user()->id, 'patient_invoices', 'region_id'), ['class' => 'form-control form-filter input-sm select2']) !!}--}}</td>
                    <td>{{--{!! Form::select('city_id', $cities, $filters->get(Auth::user()->id, 'patient_invoices', 'city_id'), ['class' => 'form-control form-filter input-sm select2']) !!}--}}</td>
                    <td>{!! Form::select('location_id', $locations, $filters->get(Auth::user()->id, 'patient_invoices','location_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}</td>
                    <td>
                        <select class="form-control form-filter input-sm select2" id="service" name="service">
                            <option value="">Select a Service</option>
                            @foreach($Services as $id => $Service)
                                @if ($id == 0) @continue; @endif
                                <option @if ($filters->get(Auth::User()->id, 'invoices', 'service') == $id)  selected="selected" @endif value="@if($id < 0){{ ($id * -1) }}@else{{ $id }}@endif">{!! $Service['name'] !!}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>{!! Form::select('invoice_status_id', $invoicestatus, $filters->get(Auth::user()->id,'patient_invoices','invoice_status_id'), ['class' => 'form-control form-filter input-sm select2']) !!}</td>
                    <td></td>
                    <td>
                        <div class="input-icon input-icon-sm right margin-bottom-5">
                            <i class="fa fa-calendar"></i>
                            <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly="" class="form-control form-filter input-sm created_from" placeholder="From"
                                   value="{{ ($filters->get(Auth::User()->id, 'patient_invoices', 'created_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patient_invoices', 'created_from'))->format('Y-m-d') : '' }}">
                        </div>
                        <div class="input-icon input-icon-sm right margin-bottom-5">
                            <i class="fa fa-calendar"></i>
                            <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly="" class="form-control form-filter input-sm created_to" placeholder="To"
                                   value="{{ ($filters->get(Auth::User()->id, 'patient_invoices', 'created_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patient_invoices', 'created_to'))->format('Y-m-d') : '' }}">
                        </div>
                    </td>
                    <td>
                        <div class="margin-bottom-5">
                            <button class="btn btn-sm green btn-outline filter-submit margin-bottom">
                                <i class="fa fa-search"></i> Search
                            </button>
                            <button class="btn btn-sm red btn-outline filter-cancel reset_custom">
                                <i class="fa fa-times"></i> Reset
                            </button>
                        </div>
                    </td>
                </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
    <!-- End: Demo Datatable 1 -->

    <!--Edit View model Start-->
    <div class="modal fade" id="ajax_invoice_display" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Edit View model End-->

    <!--patient invoice sms log-->
    <div class="modal fade" id="patient_invoice_sms_logs" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--patient invoice sms log-->
@stop

@section('javascript')
    <script>
        $( ".reset_custom" ).click(function() {
            $('.select2').val(null).trigger('change');
        });
    </script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/patients/card/invoices/datatable.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}" type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}" type="text/javascript"></script>
@endsection