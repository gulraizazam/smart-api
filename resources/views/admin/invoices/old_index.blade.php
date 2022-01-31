@inject('request', 'Illuminate\Http\Request')
@inject('Auth', 'Auth')
@inject('filters', 'App\Helpers\Filters')
@extends('layouts.app')

@section('stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
 <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/pages/css/invoice.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- END PAGE LEVEL PLUGINS -->
    <style type="text/css">
        .table-checkable tr > td:first-child, .table-checkable tr > th:first-child {
            text-align: left;
            max-width: 100%;
            min-width: 40px;
            padding-left: 10px;
            padding-right: 10px;
        }
    </style>
@stop

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.invoices.title')</h1>
    <!-- END PAGE TITLE-->
@endsection

@section('content')
    <!-- Begin: Demo Datatable 1 -->
    <div class="portlet light portlet-fit portlet-datatable bordered">
        <div class="portlet-title">
            <div class="caption">
                <i class="icon-list font-dark"></i>
                <span class="caption-subject font-dark sbold uppercase">@lang('global.app_list')</span>
            </div>
            <div class="actions">
            </div>
        </div>
        <div class="portlet-body">
            <div class="table-container">
                <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                    <thead>
                    <tr role="row" class="heading">
                        <th>@lang('global.invoices.fields.patient_id')</th>   
                        <th width="15%">@lang('global.invoices.fields.patient_name')</th>
                        <th>@lang('global.invoices.fields.phone')</th>
                        <th>@lang('global.invoices.fields.region')</th>
                        <th>@lang('global.invoices.fields.city')</th>
                        <th>@lang('global.invoices.fields.location')</th>
                        <th>@lang('global.invoices.fields.service_name')</th>
                        <th>@lang('global.invoices.fields.invoive_status')</th>
                        <th>@lang('global.appointments.fields.appointment_type')</th>
                        <th>@lang('global.invoices.fields.total_price')</th>
                        <th>@lang('global.invoices.fields.created_at')</th>
                        <th width="20%">@lang('global.invoices.fields.actions')</th>
                    </tr>
                    <tr role="row" class="filter">
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="id" value="{{$filters->get(Auth::user()->id, 'invoices', 'id')}}">
                        </td>
                        <td width="20%">
                            <select name="patient_id" id="patient_id" class="form-control form-filter input-sm select2 patient_id">
                                @if(count($patient))
                                    <option value="{{ $patient['id'] }}">{{ $patient['name'] . ' - ' . $patient['phone'] }} </option>
                                @endif
                            </select>
                        </td>
                        <td></td>
                        <td>{{--{!! Form::select('region_id', $regions, '', ['class' => 'form-control form-filter input-sm select2']) !!}--}}</td>
                        <td>{{--{!! Form::select('city_id', $cities, '', ['class' => 'form-control form-filter input-sm select2']) !!}--}}</td>
                        <td>{!! Form::select('location_id', $locations, $filters->get(Auth::user()->id , 'invoices', 'location_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}</td>
                        <td>
                            <select class="form-control form-filter input-sm select2" id="service" name="service">
                                <option value="">Select a Service</option>
                                @foreach($Services as $id => $Service)
                                    @if ($id == 0) @continue; @endif
                                    <option @if ($filters->get(Auth::User()->id, 'invoices', 'service') == $id)  selected="selected" @endif value="@if($id < 0){{ ($id * -1) }}@else{{ $id }}@endif">{!! $Service['name'] !!}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>{!! Form::select('invoice_status_id', $invoicestatus, $filters->get(Auth::user()->id , 'invoices', 'invoice_status_id'), ['class' => 'form-control form-filter input-sm select2']) !!}</td>
                        <td>{!! Form::select('appointment_type_id', $appointment_types, $filters->get(Auth::user()->id,'invoices','appointment_type_id') , ['class' => 'form-control form-filter input-md select2']) !!}</td>
                        <td></td>
                        <td>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                       value="{{ ($filters->get(Auth::User()->id, 'invoices', 'created_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'invoices', 'created_from'))->format('Y-m-d') : '' }}"
                                       class="form-control form-filter input-sm created_from" placeholder="From">
                            </div>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                       value="{{ ($filters->get(Auth::User()->id, 'invoices', 'created_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'invoices', 'created_to'))->format('Y-m-d') : '' }}"
                                       class="form-control form-filter input-sm created_to" placeholder="To">
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
    <!--Sms log for Invoice SMS-->
    <div class="modal fade" id="invoice_sms_logs" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Sms Log End-->
@stop

@section('javascript')
    <script>
        $( ".reset_custom" ).click(function() {
            $('.select2').val(null).trigger('change');
        });
    </script>
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/invoices/datatable.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}" type="text/javascript"></script>
     <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
            type="text/javascript"></script>
    <script src="{{ url('js/admin/users/ajaxbaseselect2.js') }}" type="text/javascript"></script>

@endsection