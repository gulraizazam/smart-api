<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_edit')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            {!! Form::model($documents, ['method' => 'POST', 'id' => 'form-validation', 'route' => ['admin.patients.updatedocuments', $documents->id]]) !!}
            <div class="form-body">
                <!-- Starts Form Validation Messages -->
            @include('partials.messages')
            <!-- Ends Form Validation Messages -->

                <div class="row">
                    <div class="form-group col-md-12">
                        {!! Form::label('name', 'Name*', ['class' => 'control-label']) !!}
                        {!! Form::text('name', old('name'), ['class' => 'form-control inpt-focus', 'placeholder' => 'Enter File Name']) !!}
                        @if($errors->has('name'))
                            <p class="help-block">
                                {{ $errors->first('name') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            <div>
                {!! Form::submit(trans('global.app_update'), ['class' => 'btn btn-success']) !!}
            </div>
            {!! Form::close() !!}
        </div>
    </div>
    <script src="{{ url('js/admin/patients/card/documents/fields.js') }}" type="text/javascript"></script>
</div>
