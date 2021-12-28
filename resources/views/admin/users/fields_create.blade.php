<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('name', 'Name*', ['class' => 'control-label']) !!}
        {!! Form::text('name', old('name'), ['class' => 'form-control inpt-focus', 'placeholder' => '', 'required' => '']) !!}
        <p class="help-block"></p>
        @if($errors->has('name'))
            <p class="help-block">
                {{ $errors->first('name') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-6">
        {!! Form::label('email', 'Email*', ['class' => 'control-label']) !!}
        {!! Form::email('email', old('email'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        <p class="help-block"></p>
        @if($errors->has('email'))
            <p class="help-block">
                {{ $errors->first('email') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="form-group col-md-6 multiselect">
        {!! Form::label('phone', 'Phone*', ['class' => 'control-label']) !!}
        {!! Form::number('phone', (old('phone')) ? old('phone') : $user->phone, ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('phone'))
            <p class="help-block">
                {{ $errors->first('phone') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-6 multiselect">
        {!! Form::label('gender', 'Gender*', ['class' => 'control-label']) !!}
        {!! Form::select('gender', array('' => 'Select a Gender') + Config::get("constants.gender_array"), (old('gender')) ? old('gender') : $user->gender, [ 'class' => 'form-control select2', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('gender'))
            <p class="help-block">
                {{ $errors->first('gender') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('password', 'Password*', ['class' => 'control-label']) !!}
        {!! Form::password('password', ['id' => 'password', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        <p class="help-block"></p>
        @if($errors->has('password'))
            <p class="help-block">
                {{ $errors->first('password') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-6">
        {!! Form::label('roles', 'Roles*', ['class' => 'control-label']) !!}
        <select name="roles[]" multiple class="form-control roles" required>
            @foreach($roles as $role)
                <option value="{{ $role->id }}">{{ $role->name }}</option>
            @endforeach
        </select>
        <p class="help-block"></p>
        @if($errors->has('roles'))
            <p class="help-block">
                {{ $errors->first('roles') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="form-group col-md-6">
        <div class="form-group">
            {!! Form::label('commission', 'Commission*', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::number('commission', old('commission'), ['id' => 'commission', 'min' => '0', 'max' => '100', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
                <span class="input-group-addon">%</span>
            </div>
            @if($errors->has('commission'))
                <p class="help-block">
                    {{ $errors->first('commission') }}
                </p>
            @endif
        </div>
    </div>
    <div class="form-group col-md-6">
        {!! Form::label('centres', 'Centres*', ['class' => 'control-label']) !!}
        <select name="centers[]" class="form-control select2" multiple required>
            @foreach($locations as $locaiton)
                <optgroup label="{{$locaiton['name']}}">
                    @foreach($locaiton['children'] as $child)
                        <option value="{{$child['id']}}"><?php echo $child['name']; ?></option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <p class="help-block"></p>
        @if($errors->has('centres'))
            <p class="help-block">
                {{ $errors->first('centres') }}
            </p>
        @endif
    </div>
</div>


<script>
    $(document).ready(function () {
        $('.btn-group .dropdown-toggle').click(function () {
            console.log("hello");
            $(this).attr("aria-expanded", true);
            $(".btn-group").addClass("open");
        });
    })
</script>