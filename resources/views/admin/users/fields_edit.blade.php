{!! Form::hidden('old_phone',(old('phone')) ? old('phone') : $user->phone) !!}
<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('name', 'Name*', ['class' => 'control-label']) !!}
        {!! Form::text('name', old('name'), ['class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
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
    <div class="form-group col-md-6"><i onclick="phoneReset('phone')" class="fa fa-edit"></i>
        {!! Form::label('phone', 'Phone*', ['class' => 'control-label']) !!}
        {!! Form::text('phone', (old('phone')) ? old('phone') : \App\Helpers\GeneralFunctions::contactStatus($user->phone), ['readonly','id' => 'phone','class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('phone'))
            <p class="help-block">
                {{ $errors->first('phone') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-6">
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
    <div class="col-md-6 form-group">
        {!! Form::label('roles', 'Roles*', ['class' => 'control-label']) !!}
        @php($user_roles = $user->roles()->pluck('id'))
        @if($user_roles)
            @php($user_roles = $user_roles->toArray())
        @else
            @php($user_roles = array())
        @endif
        <select name="roles[]" multiple class="form-control roles" required>
            @foreach($roles as $key => $value)
                <option @if(in_array($key, $user_roles)) selected="selected"
                        @endif value="{{ $key }}">{{ $value }}</option>
            @endforeach
        </select>
        @if($errors->has('roles'))
            <p class="help-block">
                {{ $errors->first('roles') }}
            </p>
        @endif
    </div>
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
</div>
<div class="row">
    <div class="form-group col-md-12">
        {!! Form::label('centres', 'Centres*', ['class' => 'control-label']) !!}
        <select name="centers[]" class="form-control select2" multiple required>
            @foreach($locations as $locaiton)
                <optgroup label="{{$locaiton['name']}}">
                    @foreach($locaiton['children'] as $child)
                        <option @if(in_array($child['id'],$user_has_locations)) selected="selected"
                                @endif value="{{$child['id']}}"><?php echo $child['name']; ?></option>
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
@foreach($roles_commissions as $roles_commission)
    <input type="hidden" id="commission{{ $roles_commission->id }}" value="{{ $roles_commission->commission }}"/>
@endforeach
<script>
    $(document).ready(function () {
        $('.btn-group .dropdown-toggle').click(function () {
            console.log("hello");
            $(this).attr("aria-expanded", true);
            $(".btn-group").addClass("open");
        });
    })
</script>