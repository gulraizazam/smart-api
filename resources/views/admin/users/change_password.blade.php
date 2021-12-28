<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_change_password')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            {!! Form::model($user, ['method' => 'PATCH', 'id' => 'form-validation', 'route' => ['admin.users.save_password']]) !!}
            <div class="form-body">
                <div class="row">
                    <div class="alert alert-danger display-hide">
                        <button class="close" data-close="alert"></button>
                        You have some form errors. Please check below.
                    </div>
                    <div class="alert alert-success display-hide">
                        <button class="close" data-close="alert"></button>
                        Your validation is complete, fomm!
                    </div>

                    {!! Form::hidden('id', encrypt((old('id')) ? old('id') : $user->id)) !!}
                    <div class="col-md-6">
                        <div class="form-group @if($errors->has('password')) has-error @endif">
                            {!! Form::label('password', 'New password*', ['class' => 'control-label']) !!}
                            {!! Form::password('password', ['class' => 'form-control', 'placeholder' => '']) !!}
                            @if($errors->has('password'))
                                <span class="help-block help-block-error">
                                    {{ $errors->first('password') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">

                        <div class="form-group @if($errors->has('password_confirmation')) has-error @endif">
                            {!! Form::label('password_confirmation', 'New password confirmation*', ['class' => 'control-label']) !!}
                            {!! Form::password('password_confirmation', ['class' => 'form-control', 'placeholder' => '']) !!}
                            @if($errors->has('password_confirmation'))
                                <span class="help-block help-block-error">
                                    {{ $errors->first('password_confirmation') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div>
                {!! Form::submit(trans('global.app_save'), ['class' => 'btn btn-success']) !!}
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
<script src="{{ url('js/admin/users/change_password.js') }}" type="text/javascript"></script>



