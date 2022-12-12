@extends('layouts.app')

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.towns.title')</h1>
    <!-- END PAGE TITLE-->
@endsection

@section('content')
    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="caption font-green-sharp">
                <i class="icon-plus font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.towns.import')</span>
            </div>
            <div class="actions">
                <a href="{{ route('admin.towns.index') }}" class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body form">
            <div class="form-group">
                {!! Form::open(['method' => 'POST', 'enctype' => 'multipart/form-data', 'id' => 'form-validation', 'route' => ['admin.towns.upload']]) !!}
                <div class="form-body">
                    <!-- Starts Form Validation Messages -->
                @include('partials.messages')
                <!-- Ends Form Validation Messages -->

                    <div class="form-group">
                        {!! Form::label('towns_file', 'File*', ['class' => 'control-label']) !!}
                        {!! Form::file('towns_file', old('towns_file'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
                        @if($errors->has('towns_file'))
                            <p class="help-block">
                                {{ $errors->first('towns_file') }}
                            </p>
                        @endif
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
    <script src="{{ url('js/admin/towns/import.js') }}" type="text/javascript"></script>
@endsection

