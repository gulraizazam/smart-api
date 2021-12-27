<div class="row">
    <div class="form-group col-md-6">
        <div class="form-group">
            {!! Form::label('name', 'Name*', ['class' => 'control-label']) !!}
            {!! Form::text('name', old('name'), ['class' => 'form-control inpt-focus', 'placeholder' => '', 'required' => '']) !!}
            <p class="help-block"></p>
            @if($errors->has('name'))
                <p class="help-block">
                    {{ $errors->first('name') }}
                </p>
            @endif
        </div>
    </div>
    <div class="form-group col-md-6">
        <div class="form-group">
            {!! Form::label('commission', 'Commission*', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::number('commission', old('commission'), ['min' => '0', 'max' => '100', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
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
<h4>Dashboard Permissions</h4>
<table class="table table-striped table-bordered table-hover order-column">
    <thead>
    <tr>
        <th style="width: 171px;">Module</th>
        @foreach($dashboardPermissionsMapping as $key => $name)
            <th style="width: 100px;">{{ $name }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @if(count($DashboardPermissions))
        @foreach($DashboardPermissions as $Permission)
            <tr>
                <th style="width: 171px;">
                    {{ $Permission['title'] }}
                    <input id="allow_{{ $Permission['name'] }}" type="checkbox" name="permission[]"
                           class="allow_all allow {{ $Permission['name'] }} allow_{{ $Permission['name'] }}"
                           value="{{ $Permission['name'] }}" checked="true" style="visibility: hidden;" onclick="FormValidation.checkMyModule(this,'allow_{{ $Permission['name'] }}');">
                </th>
                @foreach($dashboardPermissionsMapping as $key => $name)
                    <td style="width: 100px;">
                        @if(array_key_exists($Permission['key'] . $key, $Permission['children']))
                            <input id="sub-allow_{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                   type="checkbox" name="permission[]"
                                   class="allow_all allow {{ $Permission['name'] }}  sub-allow_{{ $Permission['name'] }}"
                                   value="{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                   @if(isset($AllowedPermissions[$Permission['children'][$Permission['key'] . $key]['id']])) checked="true"
                                   @endif onclick="FormValidation.checkMyParent(this,'allow_{{ $Permission['name'] }}' , 'sub-allow_{{ $Permission['name'] }}', '{{ $Permission['children'][$Permission['key'] . $key]['name'] }}' );">
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    @endif
    </tbody>
</table>
<h4>General Permissions</h4>
<table class="table table-striped table-bordered table-hover order-column role_datatable">
    <thead>
    <tr>
        <th style="width: 171px;">Module</th>
        <th style="width: 100px;">Display</th>
        @foreach($permissionsMapping as $key => $name)
            <th style="width: 100px;">{{ $name }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @if(count($Permissions))
        @foreach($Permissions as $Permission)
            <tr>
                <th style="width: 171px;">{{ $Permission['title'] }}</th>
                <td style="width: 100px;">
                    <input id="allow_{{ $Permission['name'] }}" type="checkbox" name="permission[]"
                           class="allow_all allow {{ $Permission['name'] }} allow_{{ $Permission['name'] }}"
                           value="{{ $Permission['name'] }}"
                           @if(isset($AllowedPermissions[$Permission['id']])) checked="true"
                           @endif onclick="FormValidation.checkMyModule(this,'allow_{{ $Permission['name'] }}');">
                </td>
                @foreach($permissionsMapping as $key => $name)
                    <td style="width: 100px;">
                        @if(array_key_exists($Permission['key'] . $key, $Permission['children']))
                            <input id="sub-allow_{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                   type="checkbox" name="permission[]"
                                   class="allow_all allow {{ $Permission['name'] }}  sub-allow_{{ $Permission['name'] }}"
                                   value="{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                   @if(isset($AllowedPermissions[$Permission['children'][$Permission['key'] . $key]['id']])) checked="true"
                                   @endif onclick="FormValidation.checkMyParent(this,'allow_{{ $Permission['name'] }}' , 'sub-allow_{{ $Permission['name'] }}', '{{ $Permission['children'][$Permission['key'] . $key]['name'] }}' );">
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    @endif
    </tbody>
</table>
<h4>Reports Permissions</h4>
<div class="table-scrollable" id="topscroll">
    <table class="table table-striped table-bordered table-hover order-column">
    <thead>
    <tr>
        <th width="20%">Module</th>
        <th>Reports</th>
    </tr>
    </thead>
    <tbody>
    @if(count($ReportsPermissions))
        @foreach($ReportsPermissions as $Permission)
            <tr>
                <th style="text-align: center; vertical-align: middle;">{{ $Permission['title'] }}</th>
                <td>
                    <table class="table table-striped table-bordered table-hover order-column">
                        <tr>
                            <th>Display</th>
                            @foreach($reportsPermissionsMapping as $key => $name)
                                @if(array_key_exists($Permission['key'] . $key, $Permission['children']))
                                    <th>{{ $name }}</th>
                                @endif
                            @endforeach
                        </tr>
                        <tr>
                            <td>
                                <input id="allow_{{ $Permission['name'] }}" type="checkbox" name="permission[]"
                                       class="allow_all allow {{ $Permission['name'] }} allow_{{ $Permission['name'] }}"
                                       value="{{ $Permission['name'] }}"
                                       @if(isset($AllowedPermissions[$Permission['id']])) checked="true"
                                       @endif onclick="FormValidation.checkMyModule(this,'allow_{{ $Permission['name'] }}');">
                            </td>
                            @foreach($reportsPermissionsMapping as $key => $name)
                                @if(array_key_exists($Permission['key'] . $key, $Permission['children']))
                                    <td>
                                        <input id="sub-allow_{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                               type="checkbox" name="permission[]"
                                               class="allow_all allow {{ $Permission['name'] }}  sub-allow_{{ $Permission['name'] }}"
                                               value="{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                               @if(isset($AllowedPermissions[$Permission['children'][$Permission['key'] . $key]['id']])) checked="true"
                                               @endif onclick="FormValidation.checkMyParent(this,'allow_{{ $Permission['name'] }}' , 'sub-allow_{{ $Permission['name'] }}', '{{ $Permission['children'][$Permission['key'] . $key]['name'] }}' );">
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    </table>
                </td>
            </tr>
        @endforeach
    @endif
    </tbody>
</table>
</div>
