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
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-editable/bootstrap-editable/css/bootstrap-editable.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-editable/inputs-ext/address/address.css') }}"
          rel="stylesheet" type="text/css"/>
    <!-- END PAGE LEVEL PLUGINS -->
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css">
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css')}}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-switch/css/custom.css')}}"
          rel="stylesheet" type="text/css"/>
@stop
@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.resourcerotas.title')</h1>
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
                @if(Gate::allows('resourcerotas_create'))
                    <a class="btn btn-success" href="{{ route('admin.resourcerotas.create')}}"
                       data-target="#ajax_resourcerotas" data-toggle="modal">@lang('global.app_add_new')</a>
                @endif
            </div>
        </div>
        <div class="portlet-body">
            <div class="table-container">
                @if(Gate::allows('resourcerotas_destroy'))
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
                        <th>@lang('global.resourcerotas.fields.name')</th>
                        <th width="20%">@lang('global.resourcerotas.fields.type')</th>
                        <th width="10%">@lang('global.resourcerotas.fields.region')</th>
                        <th width="10%">@lang('global.resourcerotas.fields.city')</th>
                        <th width="20%">@lang('global.resourcerotas.fields.location')</th>
                        <th>@lang('global.resourcerotas.fields.from')</th>
                        <th>@lang('global.resourcerotas.fields.to')</th>
                        <th>@lang('global.resourcerotas.fields.created_at')</th>
                        <th>@lang('global.resourcerotas.fields.status')</th>
                        <th>@lang('global.resourcerotas.fields.actions')</th>
                    </tr>
                    <tr role="row" class="filter">
                        <td></td>
                        <td><input type="text" class="form-control form-filter input-sm" name="resourcename" value="{{$filters->get(Auth::user()->id , 'resourcehasrota' , 'resourcename')}}"></td>
                        <td>{!! Form::select('resource_type_id', $resourcetype, $filters->get(Auth::user()->id, 'resourcehasrota' ,'resource_type_id') , ['class' => 'form-control form-filter input-sm select2',]) !!}</td>
                        <td>{!! Form::select('region_id', $regions, $filters->get(Auth::user()->id , 'resourcehasrota' , 'region_id') , ['class' => 'form-control form-filter input-sm select2']) !!}</td>
                        <td>{!! Form::select('city_id', $city, $filters->get(Auth::user()->id , 'resourcehasrota' , 'city_id' ) , ['class' => 'form-control form-filter input-sm select2',]) !!}</td>
                        <td>{!! Form::select('location_id', $location, $filters->get(Auth::user()->id , 'resourcehasrota' , 'location_id'), ['class' => 'form-control form-filter input-sm select2',]) !!}</td>
                        <td>
                            <div class="input-group date date-picker margin-bottom-5" data-date-format="yyyy-mm-dd">
                                <input type="text" class="form-control form-filter input-sm" readonly=""
                                       name="startdate" value="{{ ($filters->get(Auth::User()->id, 'resourcehasrota', 'startdate')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'resourcehasrota', 'startdate'))->format('Y-m-d') : '' }}"
                                       placeholder="From">
                                <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="input-group date date-picker margin-bottom-5" data-date-format="yyyy-mm-dd">
                                <input type="text" class="form-control form-filter input-sm" readonly="" name="enddate"
                                       value="{{ ($filters->get(Auth::User()->id, 'resourcehasrota', 'enddate')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'resourcehasrota', 'enddate'))->format('Y-m-d') : '' }}"
                                       placeholder="From">
                                <span class="input-group-btn">
                                    <button class="btn btn-sm default" type="button">
                                        <i class="fa fa-calendar"></i>
                                    </button>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_from" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                       class="form-control form-filter input-sm created_from" placeholder="From"
                                       value="{{ ($filters->get(Auth::User()->id, 'resourcehasrota', 'created_from')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'resourcehasrota', 'created_from'))->format('Y-m-d') : '' }}">
                            </div>
                            <div class="input-icon input-icon-sm right margin-bottom-5">
                                <i class="fa fa-calendar"></i>
                                <input name="created_to" data-date-format="yyyy-mm-dd" type="text" readonly=""
                                       class="form-control form-filter input-sm created_to" placeholder="To"
                                       value="{{ ($filters->get(Auth::User()->id, 'resourcehasrota', 'created_to')) ? Carbon\Carbon::parse($filters->get(Auth::User()->id, 'resourcehasrota', 'created_to'))->format('Y-m-d') : '' }}">
                            </div>
                        </td>
                        <td> {!! Form::select('status', ['0' => 'Inactive', '1' => 'Active'] , $filters->get( Auth::user()->id, 'resourcehasrota', 'status') , ['class' => 'form-control form-filter input-sm select2' ,'placeholder' => 'All']) !!} </td>
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
    <!--Add New View model Start-->
    <div class="modal fade" id="ajax_resourcerotas" role="basic" aria-hidden="true">
        <div class="modal-content rota-popup custom-popup">
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
    <script src="{{ url('metronic/assets/global/plugins/jquery-ui/jquery-ui.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/clipboard/clipboard.min.js') }}"></script>
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"
            type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-editable/bootstrap-editable/js/bootstrap-editable.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-editable/inputs-ext/address/address.js') }}"
            type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <!-- BEGIN PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('js/admin/resourcerotas/datatable.js') }}" type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
            type="text/javascript"></script>
@endsection