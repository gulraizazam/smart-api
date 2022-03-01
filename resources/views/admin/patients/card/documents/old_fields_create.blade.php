<input type="hidden" name="patient_id" value="{{$patient->id}}">
<div class="row">
    <div class="form-group col-md-4">
        {!! Form::label('name', 'Name*', ['class' => 'control-label']) !!}
        {!! Form::text('name', old('name'), ['class' => 'form-control inpt-focus', 'placeholder' => 'Enter File Name']) !!}
        @if($errors->has('name'))
            <p class="help-block">
                {{ $errors->first('name') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-8">
        {!! Form::label('file', 'File*', ['class' => 'control-label']) !!}
        <div class="fileinput-new" data-provides="fileinput">
            <div class="input-group input-large">
                <div class="form-control uneditable-input input-fixed input-medium"
                     data-trigger="fileinput">
                    <i class="fa fa-file fileinput-exists"></i>&nbsp;
                    <span class="fileinput-filename"> </span>
                </div>
                <span class="input-group-addon btn default btn-file">
                    <span class="fileinput-new"> Select file </span>
                    <span class="fileinput-exists"> Change </span>
                    <input type="file" name="upload_file">
                </span>
                <a href="javascript:;" class="input-group-addon btn red fileinput-exists" data-dismiss="fileinput"> Remove </a>
            </div>
        </div>
    </div>
</div>