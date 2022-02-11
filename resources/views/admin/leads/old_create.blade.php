@extends('layouts.app')
@section('stylesheets')
    <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}"
          rel="stylesheet" type="text/css"/>

    <style type="text/css">
        .center-check label {
            display: block;
            width: 100%;
            margin: 0;
            text-align: center;
        }
        .center-check label input[type="checkbox"],
        .center-check label input[type="radio"] {
            width: 13px;
            height: 13px;
            display: inline-block;
            vertical-align: top;
            margin: 4px 3px 0 0;
        }

        .center-check label span {
            display: inline-block;
            vertical-align: top;
        }
    </style>
@stop
@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.leads.title')</h1>
    <!-- END PAGE TITLE-->
@endsection

@section('content')
    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="caption font-green-sharp">
                <i class="icon-plus font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.app_create')</span>
            </div>
            <div class="actions">
                <a href="{{ route('admin.leads.index') }}" class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body form">
            <div class="form-group">
                {!! Form::open(['method' => 'POST', 'id' => 'form-validation', 'route' => ['admin.leads.store']]) !!}
                <div class="form-body">
                    <!-- Starts Form Validation Messages -->
                @include('partials.messages')
                <!-- Ends Form Validation Messages -->

                    @include('admin.leads.fields')
                </div>
                <div class="form-actions">
                    {!! Form::submit(trans('global.app_save'), ['class' => 'btn btn-success']) !!}
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $('#new_patient').change(function () {
            if ($(this).is(":checked")) {
                $('#new_patient').val('1');
                $('#mess_new_pati').show();
            } else {
                $('#new_patient').val('0');
                $('#mess_new_pati').hide();
            }
        });
    </script>
    <script src="{{ url('js/admin/leads/fields.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('js/admin/leads/phone.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}"
            type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('js/admin/users/ajaxbaseselect2.js') }}" type="text/javascript"></script>
@endsection
