@extends('layouts.app')

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.leads.title')</h1>
    <!-- END PAGE TITLE-->
@endsection

@section('content')
    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="caption font-green-sharp">
                <i class="icon-pencil font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.app_edit')</span>
            </div>
            <div class="actions">
                <a href="{{ route('admin.leads.index') }}" class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body form">
            <div class="form-group">
                {!! Form::model($lead, ['method' => 'PUT', 'id' => 'form-validation', 'route' => ['admin.leads.update', $lead->id]]) !!}
                    <div class="form-body">
                        <!-- Starts Form Validation Messages -->
                    @include('partials.messages')
                    <!-- Ends Form Validation Messages -->

                        @include('admin.leads.fields')
                    </div>
                    <div class="form-actions">
                        {!! Form::submit(trans('global.app_update'), ['class' => 'btn btn-success']) !!}
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/leads/fields.js') }}" type="text/javascript"></script>
@endsection
