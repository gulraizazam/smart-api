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
            <span class="caption-subject font-blue-madison bold uppercase">@lang('global.refunds.title')</span>
        </div>
    </div>
    <div class="portlet-body">
        <div class="table-container">
            <input type="hidden" id="patient_id" value="{{ $patient->id }}" />
            <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                <thead>
                <tr role="row" class="heading">
                    <th>@lang('global.packages.fields.patient_name')</th>
                    <th>@lang('global.packages.fields.phone')</th>
                    <th>@lang('global.packages.fields.package_id')</th>
                    <th>@lang('global.packages.fields.location')</th>
                    <th>@lang('global.packages.fields.session_count')</th>
                    <th>@lang('global.packages.fields.total')</th>
                    <th>@lang('global.packages.fields.cash_receive')</th>
                    <th>@lang('global.packages.fields.created_at')</th>
                    <th width="20%">@lang('global.packages.fields.actions')</th>
                </tr>
                <tr role="row" class="filter">
                    <td></td>
                    <td></td>
                    <td>{!! Form::select('package_id', $package, $filters->get(Auth::user()->id, 'patient_refunds', 'package_id'), ['class' => 'form-control form-filter input-sm select2', 'id' => 'package_id' ]) !!}</td>
                    <td>{!! Form::select('location_id', $locations, $filters->get(Auth::user()->id, 'patient_refunds', 'location_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>
                        <div class="input-icon input-icon-sm right margin-bottom-5">
                            <i class="fa fa-calendar"></i>
                            <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                   value="{{ ($filters->get(Auth::User()->id, 'patient_refunds', 'created_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patient_refunds', 'created_from'))->format('Y-m-d') : '' }}"
                                   class="form-control form-filter input-sm created_from" placeholder="From">
                        </div>
                        <div class="input-icon input-icon-sm right margin-bottom-5">
                            <i class="fa fa-calendar"></i>
                            <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                   value="{{ ($filters->get(Auth::User()->id, 'patient_refunds', 'created_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patient_refunds', 'created_to'))->format('Y-m-d') : '' }}"
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
    <!-- End: Demo Datatable 1 -->
    <!--Edit View model Start-->
    <div class="modal fade" id="ajax_refunds_create" role="basic" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Edit View model End-->
    <!--Add New View model Start-->
    <div class="modal fade" id="ajax_refunds_detail" role="basic" aria-hidden="true">
        <div class="modal-content" style="width: 1100px;">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Add new View model End-->
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
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/patients/card/refunds/datatable.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}" type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}" type="text/javascript"></script>
@endsection