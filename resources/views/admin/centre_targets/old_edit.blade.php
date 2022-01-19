<style>
    .has-y-scroll{
        min-height: inherit;
        max-height: 400px;
        overflow-y: auto;
    }
</style>

<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_create')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            {!! Form::model($center_target, ['method' => 'PUT', 'id' => 'form-validation', 'route' => ['admin.centre_targets.update', $center_target->id]]) !!}
            <div class="form-body">
                <!-- Starts Form Validation Messages -->
                @include('partials.messages')
                <div class="alert alert-warning display-hide" id="centre_require_field"><button class="close" data-close="alert"></button> Please select all options to continue. </div>
                <div class="alert alert-info display-hide" id="centre_edit_perform"><button class="close" data-close="alert"></button> You are going to update existing record </div>
                <!-- Ends Form Validation Messages -->
                <div class="row">
                    <div class="col-md-6 form-group">
                        {!! Form::label('year', 'Year*', ['class' => 'control-label']) !!}
                        <select id="year" name="year" class="form-control" onchange="CreateFormValidation.loadActiveLocation();" required>
                            @foreach($years as $key => $val)
                                <option value="<?php echo $key ?>" @if($center_target->year == $key) selected="selected" @endif><?php echo $val ?></option>
                            @endforeach
                        </select>
                        @if($errors->has('year'))
                            <p class="help-block">
                                {{ $errors->first('year') }}
                            </p>
                        @endif
                    </div>
                    <div class="col-md-6 form-group">
                        {!! Form::label('month', 'Month*', ['class' => 'control-label']) !!}
                        {!! Form::select('month', $months, $center_target->month, ['onchange' => 'CreateFormValidation.loadActiveLocation();', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
                        @if($errors->has('month'))
                            <p class="help-block">
                                {{ $errors->first('month') }}
                            </p>
                        @endif
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 form-group">
                        {!! Form::label('working_days', 'Working Days*', ['class' => 'control-label']) !!}
                        {!! Form::number('working_days', old('working_days')?old('working_days'):0, ['id' => 'working_days','class' => 'form-control inpt-focus', 'placeholder' => '', 'required' => '','min' => 0]) !!}
                        @if($errors->has('working_days'))
                            <p class="help-block">
                                {{ $errors->first('working_days') }}
                            </p>
                        @endif
                    </div>
                </div>
                <div class="table-responsive has-y-scroll" id="table_location"></div>
                <div class="clearfix"></div>
            </div>

            <div style="padding-top: 10px;">
                {!! Form::submit(trans('global.app_update'), ['style' => 'display: none;', 'id' => 'save_btn', 'class' => 'btn btn-success']) !!}
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
<script src="{{ url('js/admin/centretarget/fields.js') }}" type="text/javascript"></script>
<script type="text/javascript">
    $(document).ready(function () {
        CreateFormValidation.loadActiveLocation();
    });
</script>