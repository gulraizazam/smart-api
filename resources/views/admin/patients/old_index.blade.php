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
    <!-- END PAGE LEVEL PLUGINS -->
@stop

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.patients.title')</h1>
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
                @if(Gate::allows('patients_create'))
                    <a class="btn btn-success" href="{{ route('admin.patients.create') }}" data-target="#ajax_patients" data-toggle="modal">@lang('global.app_add_new')</a>
                @endif
            </div>
        </div>
        <div class="portlet-body">
            <div class="table-container">
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
                <table class="table table-striped table-bordered table-hover table-checkable" id="datatable_ajax">
                    <thead>
                    <tr role="row" class="heading">
                        <th width="3%">
                            <label class="mt-checkbox mt-checkbox-single mt-checkbox-outline">
                                <input type="checkbox" class="group-checkable" data-set="#sample_2 .checkboxes"/>
                                <span></span>
                            </label>
                        </th>
                        <th>@lang('global.patients.fields.patient_id')</th>
                        <th>@lang('global.patients.fields.name')</th>
                        <th>@lang('global.patients.fields.email')</th>
                        <th>@lang('global.patients.fields.phone')</th>
                        <th>@lang('global.patients.fields.gender')</th>
                        <th>@lang('global.patients.fields.created_at')</th>
                        <th>@lang('global.patients.fields.status')</th>
                        <th width="20%">@lang('global.patients.fields.actions')</th>
                    </tr>
                    <tr role="row" class="filter">
                        <td></td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="id" value="{{$filters->get(Auth::user()->id, 'patients', 'id')}}">
                        </td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="name" value="{{$filters->get(Auth::user()->id, 'patients', 'name')}}">
                        </td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="email" value="{{$filters->get(Auth::user()->id, 'patients', 'email')}}">
                        </td>
                        <td>
                            <input type="text" class="form-control form-filter input-sm" name="phone" value="{{$filters->get(Auth::user()->id, 'patients', 'phone')}}">
                        </td>
                        <td>
                            {!! Form::select('gender',['1'=>'Male','2'=>'Female'],$filters->get(Auth::user()->id, 'patients','gender'), ['class' => 'form-control form-filter input-sm select2','placeholder'=>'All']) !!}
                        </td>
                        <td>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                       value="{{ ($filters->get(Auth::User()->id, 'patients', 'created_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patients', 'created_from'))->format('Y-m-d') : '' }}"
                                       class="form-control form-filter input-sm created_from" placeholder="From">
                            </div>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                       value="{{ ($filters->get(Auth::User()->id, 'patients', 'created_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'patients', 'created_to'))->format('Y-m-d') : '' }}"
                                       class="form-control form-filter input-sm created_to" placeholder="To">
                            </div>
                        </td>
                        <td>{!! Form::select('status', ['0' => 'Inactive', '1' => 'Active'], $filters->get(Auth::user()->id, 'patients','status'), ['class' => 'form-control form-filter input-sm select2' , 'placeholder' => 'All']) !!}</td>
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
    <div class="modal fade" id="ajax_leadsource_edit" role="basic" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--Edit View model End-->
    <!--add new View model Start-->
    <div class="modal fade" id="ajax_patients" role="basic" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-body">
                <span> &nbsp;&nbsp;Loading... </span>
            </div>
        </div>
    </div>
    <!--add new View model End-->
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
    <script src="{{ url('js/admin/patients/datatable.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
@endsection