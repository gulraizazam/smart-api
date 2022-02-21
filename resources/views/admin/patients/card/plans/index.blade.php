@extends('admin.patients.card.patient_layout')
@inject('Auth', 'Auth')
@inject('filters', 'App\Helpers\Filters')
@section('patient_stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}"
          rel="stylesheet" type="text/css"/>
    <!-- END PAGE LEVEL PLUGINS -->
    <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
          type="text/css"/>
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
            <span class="caption-subject font-blue-madison bold uppercase">@lang('global.packages.title')</span>
        </div>
        <div class="actions">
            @if(Gate::allows('patients_plan_create'))
                <a class="btn btn-success" href="{{ route('admin.plans.createplan',[$patient->id])}}" data-target="#ajax_patient_packages" data-toggle="modal">@lang('global.app_add_new')</a>
            @endif
        </div>
    </div>
    <div class="portlet-body">
        <div class="table-container">
            <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                <thead>
                <tr role="row" class="heading">
                    <th>@lang('global.packages.fields.patient_name')</th>
                    <th>@lang('global.packages.fields.package_id')</th>
                    <th>@lang('global.packages.fields.location')</th>
                    <th>@lang('global.packages.fields.session_count')</th>
                    <th>@lang('global.packages.fields.total')</th>
                    <th>@lang('global.packages.fields.cash_receive')</th>
                    <th>@lang('global.packages.fields.refund')</th>
                    <th>@lang('global.packages.fields.created_at')</th>
                    <th>@lang('global.packages.fields.status')</th>
                    <th width="20%">@lang('global.packages.fields.actions')</th>
                </tr>
                <tr role="row" class="filter">
                    <td><input type="hidden" id="patient_id" value="{{ $patient->id }}"/></td>
                    <td>{!! Form::select('package_id', $package, (request()->get('package_id') ? request()->get('package_id') : ''), ['class' => 'form-control form-filter input-sm select2', 'id' => 'package_id' ]) !!}</td>
                    <td>{!! Form::select('location_id', $locations, $filters->get(Auth::user()->id, 'patient_packages' ,'location_id') , ['class' => 'form-control form-filter input-sm select2',]) !!}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>
                        <div class="input-icon input-icon-sm right margin-bottom-5">
                            <i class="fa fa-calendar"></i>
                            <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                   class="form-control form-filter input-sm created_from" placeholder="From">
                        </div>
                        <div class="input-icon input-icon-sm right margin-bottom-5">
                            <i class="fa fa-calendar"></i>
                            <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                   class="form-control form-filter input-sm created_to" placeholder="To">
                        </div>
                    </td>
                    <td> {!! Form::select('status',['0' => 'Inactive', '1' => 'Active'], $filters->get(Auth::user()->id , 'patient_packages' , 'status'), ['class' => 'form-control form-filter input-sm select2', 'placeholder' => 'All']) !!} </td>
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
    <div class="modal fade" id="ajax_patient_packages" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Edit View model End-->

    <!--Display View model Start-->
    <div class="modal fade" id="ajax_patient_packages_display" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Display View model End-->

    <!--Display View model Start-->
    <div class="modal fade" id="ajax_patient_packages_edit" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Display View model End-->

    <!--Edit for cash View model Start-->
    <div class="modal fade" id="plan_edit_cash" role="basic" aria-hidden="true">
        <div class="modal-content package-custom-popup" style="top: 300px">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Edit for cash View model End-->

    <!-- End: plan sms log -->
    <div class="modal fade" id="patient_plan_sms_logs" role="basic" aria-hidden="true" style=" left:10%; width: 80%; top:2%;">
        <div class="modal-content">
            <div class="modal-body">
                <img src="{{ url('metronic/assets/global/img/loading-spinner-grey.gif') }}" alt="" class="loading">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>

@stop

@section('javascript')
    <script>
        $( ".reset_custom" ).click(function() {
            $('.select2').val(null).trigger('change');
        });
    </script>
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"
            type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/patients/card/plans/datatable.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
            type="text/javascript"></script>
@endsection