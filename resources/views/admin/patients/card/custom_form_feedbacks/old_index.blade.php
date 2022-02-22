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
    <style type="text/css">
        .table-checkable tr > td:first-child, .table-checkable tr > th:first-child {
            text-align: left;
            max-width: 50px;
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
            <span class="caption-subject font-blue-madison bold uppercase">@lang('global.custom_form_feedbacks.title')</span>
        </div>
        <div class="actions">
            @if(Gate::allows('patients_customform_create'))
                <a class="btn btn-success" href="{{ route('admin.customformfeedbackspatient.addnew',[$patient->id])}}" data-target="#ajax_customform_addnew" data-toggle="modal">@lang('global.app_add_new')</a>
            @endif
        </div>
    </div>
    <div class="portlet-body">
        <div class="table-container">
            <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                <thead>
                <tr role="row" class="heading">
                    <th>@lang('global.custom_form_feedbacks.fields.name')</th>
                    <th>@lang('global.custom_form_feedbacks.fields.patient_name')</th>
                    <th>@lang('global.custom_form_feedbacks.fields.created_at')</th>
                    <th width="20%">@lang('global.custom_form_feedbacks.fields.actions')</th>
                </tr>
                <tr role="row" class="filter">
                    <td>
                        <input type="text" class="form-control form-filter input-sm" name="name" value="{{$filters->get(Auth::user()->id,'patient_custom_form_feedbacks', 'name')}}">
                    </td>
                    <td>
                        <input type="hidden" id="patient_id" value="{{ $patient->id }}"/>
                    </td>
                    <td>
                        <div class="input-icon input-icon-sm right margin-bottom-5">
                            <i class="fa fa-calendar"></i>
                            <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                   value="{{ ($filters->get(Auth::User()->id, 'patient_custom_form_feedbacks', 'created_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patient_custom_form_feedbacks', 'created_from'))->format('Y-m-d') : '' }}"
                                   class="form-control form-filter input-sm created_from" placeholder="From">
                        </div>
                        <div class="input-icon input-icon-sm right margin-bottom-5">
                            <i class="fa fa-calendar"></i>
                            <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                   value="{{ ($filters->get(Auth::User()->id, 'patient_custom_form_feedbacks', 'created_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patient_custom_form_feedbacks', 'created_to'))->format('Y-m-d') : '' }}"
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
    <div class="modal fade" id="ajax_customform_addnew" role="basic" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Edit View model End-->
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
    <script src="{{ url('js/admin/patients/card/custom_form_feedbacks/datatable.js') }}"
            type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
    <script>

        $(document).ready(function () {

            ShortCutKeys = function () {


                function searchOnEnter() {
                    input = document.querySelector('.form-filter')
                    input.addEventListener("keyup", function (event) {
                        event.preventDefault();
                        if (event.keyCode === 13) {
                            filter_button = document.querySelector('.filter-submit')
                            filter_button.click();
                        }
                    });
                }

                /**
                 * on Escape reset search
                 */
                function resetOnEscape() {
                    document.addEventListener("keyup", function (event) {
                        event.preventDefault();
                        if (event.keyCode === 27) {
                            filter_button = document.querySelector('.filter-cancel')
                            filter_button.click();
                        }
                    });
                }

                return {
                    init: function () {
                        searchOnEnter();
                        resetOnEscape()
                    }
                }
            }();
            ShortCutKeys.init();

        });

    </script>
@endsection