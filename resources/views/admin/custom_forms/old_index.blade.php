@inject('request', 'Illuminate\Http\Request')
@inject('Auth', 'Auth')
@inject('filters', 'App\Helpers\Filters')
@extends('layouts.app')

@section('stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <!-- END PAGE LEVEL PLUGINS -->
@stop

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.custom_forms.title')</h1>
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
                @if(Gate::allows('custom_forms_create_medical_history_form'))
                    <a class="btn btn-success"
                       href="{{ route('admin.custom_forms.create_medical') }}">@lang('global.app_medical_form')</a>
                @endif
                @if(Gate::allows('custom_forms_create_general'))
                    <a class="btn btn-success"
                       href="{{ route('admin.custom_forms.create') }}">@lang('global.app_general_form')</a>
                @endif
                @if(Gate::allows('custom_forms_create_measurement'))
                    <a class="btn btn-success"
                       href="{{ route('admin.custom_forms.create_measurement') }}">@lang('global.app_measurement_form')</a>
                @endif
            </div>
        </div>
        <div class="portlet-body">
            <div class="table-container">
                @if(Gate::allows('custom_forms_destroy'))
                    <div class="table-actions-wrapper">
                        <span> </span>
                        <select class="table-group-action-input form-control input-inline input-small input-sm">
                            <option value="">Select</option>
                            <option value="Delete">Delete</option>
                        </select>
                        <button class="btn btn-sm red table-group-action-submit">
                            <i class="fa fa-check"></i> Submit
                        </button>
                    </div>
                @endif
                <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                    <thead>
                    <tr role="row" class="heading">
                        <th width="3%">
                            <label class="mt-checkbox mt-checkbox-single mt-checkbox-outline">
                                <input type="checkbox" class="group-checkable" data-set="#sample_2 .checkboxes"/>
                                <span></span>
                            </label>
                        </th>
                        <th>@lang('global.custom_forms.fields.name')</th>
                        <th>@lang('global.custom_forms.fields.form_type')</th>
                        <th>@lang('global.custom_forms.fields.created_at')</th>
                        <th>@lang('global.custom_forms.fields.status')</th>
                        <th width="20%">@lang('global.custom_forms.fields.actions')</th>
                    </tr>
                    <tr role="row" class="filter">
                        <td></td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="name" value="{{ $filters->get(Auth::User()->id, 'custom_forms', 'name') }}">
                        </td>
                        <td>
                            {!! Form::select('form_type_id', array('' => 'All','1' => 'Measurement Form','0' => 'General Form','2' => 'Medical Form'), $filters->get(Auth::User()->id, 'custom_forms', 'form_type_id'), ['class' => 'form-control form-filter input-sm select2']) !!}
                        </td>
                        <td>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly="" class="form-control form-filter input-sm created_from" placeholder="From" value="{{ ($filters->get(Auth::User()->id, 'custom_forms', 'created_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'custom_forms', 'created_from'))->format('Y-m-d') : '' }}">
                            </div>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly="" class="form-control form-filter input-sm created_to" placeholder="To" value="{{ ($filters->get(Auth::User()->id, 'custom_forms', 'created_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'custom_forms', 'created_to'))->format('Y-m-d') : '' }}">
                            </div>
                        </td>
                        <td> {!! Form::select('status', ['0' => 'Inactive', '1' => 'Active'] , $filters->get( Auth::user()->id , 'custom_forms', 'status') , ['class' => 'form-control form-filter input-sm select2', 'placeholder' => 'All']) !!} </td>
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
    <script src="{{ url('js/admin/custom_forms/datatable.js') }}" type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
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

