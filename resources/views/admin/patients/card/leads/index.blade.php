@extends('admin.patients.card.patient_layout')

@section('patient_stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css')}}" rel="stylesheet" type="text/css"/>
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
@endsection

@section('patient_content')
    <div class="portlet-title tabbable-line">
        <div class="caption caption-md">
            <i class="icon-globe theme-font hide"></i>
            <span class="caption-subject font-blue-madison bold uppercase">Leads</span>
        </div>
    </div>
    <div class="portlet-body">
        <div class="table-container">
            <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                <thead>
                <tr role="row" class="heading">
                    <th width="20%">@lang('global.leads.fields.full_name')</th>
                    <th>@lang('global.leads.fields.phone')</th>
                    <th width="15%">@lang('global.leads.fields.city')</th>
                    <th width="12%">@lang('global.leads.fields.lead_status')</th>
                    <th>@lang('global.leads.fields.service')</th>
                    <th>@lang('global.leads.fields.created_at')</th>
                    <th>@lang('global.leads.fields.created_by')</th>
                </tr>
                <tr role="row" class="filter">
                    <td>
                        <input type="hidden" id="patient_id" value="{{ $patient->id }}" />
                        <input type="text" class="form-control form-filter input-sm" name="name">
                    </td>
                    <td>
                        <input type="text" class="form-control form-filter input-sm" name="phone">
                    </td>
                    <td>
                        {!! Form::select('city_id', $cities, '', ['class' => 'form-control form-filter input-sm select2',]) !!}
                    </td>
                    <td>
                        {!! Form::select('lead_status_id', $lead_statuses, '', ['class' => 'form-control form-filter input-sm select2',]) !!}
                    </td>
                    <td>
                        <select class="form-control form-filter input-sm select2" name="service_id">
                            <option value="">All</option>
                            @foreach($Services as $id => $Service)
                                @if ($id == 0) @continue; @endif
                                @if($id < 0)
                                    @php($tmp_id = ($id * -1))
                                @else
                                    @php($tmp_id = ($id * 1))
                                @endif
                                <option
                                        @if($tmp_id==$leadServices) selected="selected" @endif value="@if($id < 0){{ ($id * -1) }}@else{{ $id }}@endif">
                                        @if($id < 0)<b>{!! $Service['name'] !!}</b>@else{!! $Service['name'] !!}@endif
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <div class="input-group date date-picker margin-bottom-5" data-date-format="yyyy-mm-dd">
                            <input type="text" class="form-control form-filter input-sm" readonly="" name="date_from" placeholder="From">
                            <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                        </div>
                        <div class="input-group date date-picker" data-date-format="yyyy-mm-dd">
                            <input type="text" class="form-control form-filter input-sm" readonly="" name="date_to" placeholder="To">
                            <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                        </div>
                    </td>
                    <td>
                        {!! Form::select('created_by', $users, '', ['class' => 'form-control form-filter input-sm select2 margin-bottom',]) !!}
                        <div class="margin-bottom-5">
                            <button class="btn btn-sm green btn-outline filter-submit margin-bottom">
                                <i class="fa fa-search"></i> Search
                            </button>
                            <button class="btn btn-sm red btn-outline filter-cancel">
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
@stop

@section('patient_javascript')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/patients/card/leads/datatable.js') }}" type="text/javascript"></script>
@stop