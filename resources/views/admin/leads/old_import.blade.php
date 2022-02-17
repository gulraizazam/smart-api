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
                <i class="icon-plus font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.leads.import')</span>
            </div>
            <div class="actions">
                <a href="{{ route('admin.leads.index') }}" class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body form">
            <div class="form-group">
                {!! Form::open(['method' => 'POST', 'enctype' => 'multipart/form-data', 'id' => 'form-validation', 'route' => ['admin.leads.upload']]) !!}
                    <div class="form-body">
                        <!-- Starts Form Validation Messages -->
                        @include('partials.messages')
                        <!-- Ends Form Validation Messages -->

                        <div class="form-group">
                            {!! Form::label('leads_file', 'File*', ['class' => 'control-label']) !!}
                            {!! Form::file('leads_file', old('leads_file'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
                            <p class="help-block">To download sample file <a href="{{ url('SampleLeads.xlsx') }}" target="_blank">click here</a> .</p>
                            @if($errors->has('leads_file'))
                                <p class="help-block">
                                    {{ $errors->first('leads_file') }}
                                </p>
                            @endif
                        </div>
                        <div class="form-group">
                            <div class="mt-checkbox-inline">
                                <label class="mt-checkbox mt-checkbox-outline"> Update existing records
                                    <input type="checkbox" value="1" id="update_records" name="update_records">
                                    <span></span>
                                </label>
                                <label class="mt-checkbox mt-checkbox-outline mt-checkbox-disabled"> Skip Lead Statuses
                                    <input type="checkbox" disabled value="1" id="skip_lead_statuses" name="skip_lead_statuses">
                                    <span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        {!! Form::submit('Upload', ['class' => 'btn btn-success']) !!}
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}" type="text/javascript"></script>
    <script src="{{ url('js/admin/leads/import.js') }}" type="text/javascript"></script>
@endsection

