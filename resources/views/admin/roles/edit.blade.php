@extends('admin.layouts.master')

@section('content')


    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Edit Roles', 'title' => 'Roles'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <form class="form fv-plugins-bootstrap" method="post" id="permissions-form" action="{{route('admin.roles.update', $role)}}">
                    @method('put')
                    @csrf

                    @include('admin.roles.fields')

                    <!--begin::Card-->
                    <div class="card card-custom gutter-b example example-compact">

                        {{--For dashboard--}}
                        <div class="card-header">
                            <h3 class="card-title">Dashboard Permissions</h3>
                        </div>
                        <div class="card-body">
                            <!--begin::Form-->
                            @if(count($DashboardPermissions))
                                @foreach($DashboardPermissions as $Permission)

                                    <div class="form-group row">

                                        <label class="col-2 col-form-label"><strong>{{ $Permission['title'] }}</strong></label>
                                        <input id="allow_{{ $Permission['name'] }}" type="checkbox" name="permission[]"
                                               class="allow_all allow {{ $Permission['name'] }} allow_{{ $Permission['name'] }}"
                                               value="{{ $Permission['name'] }}" checked="true" style="visibility: hidden;"
                                               onclick="FormValidation.checkMyModule(this,'allow_{{ $Permission['name'] }}');">

                                        <div class="col-9 col-form-label">
                                            <div class="checkbox-inline">
                                                @foreach($dashboardPermissionsMapping as $key => $name)

                                                    @if(array_key_exists($Permission['key'] . $key, $Permission['children']))
                                                        <label class="checkbox permission_checkbox">
                                                            <input id="sub-allow_{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                                                   type="checkbox" name="permission[]"
                                                                   class="allow_all allow {{ $Permission['name'] }}  sub-allow_{{ $Permission['name'] }}"
                                                                   value="{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                                                   @if(isset($AllowedPermissions[$Permission['children'][$Permission['key'] . $key]['id']])) checked="true"
                                                                   @endif onclick="FormValidation.checkMyParent(this,'allow_{{ $Permission['name'] }}' , 'sub-allow_{{ $Permission['name'] }}', '{{ $Permission['children'][$Permission['key'] . $key]['name'] }}' );">
                                                            <span></span>{{$name}}</label>
                                                    @endif

                                                @endforeach
                                            </div>
                                        </div>

                                    </div>

                            @endforeach
                        @endif
                        <!--end::Form-->
                        </div>
                        {{--end dashboard--}}
                    </div>

                    <div class="card card-custom gutter-b example example-compact">
                        {{--For General--}}
                        <div class="card-header">
                            <h3 class="card-title">General Permissions</h3>
                        </div>
                        <div class="card-body">
                            <!--begin::Form-->
                            @if(count($Permissions))
                                @foreach($Permissions as $Permission)

                                    <div class="form-group row">

                                        <label class="col-2 col-form-label"><strong>{{ $Permission['title'] }}</strong></label>

                                        <div class="col-9 col-form-label">
                                            <div class="checkbox-inline">

                                                <label class="checkbox permission_checkbox">
                                                    <input id="allow_{{ $Permission['name'] }}" type="checkbox" name="permission[]"
                                                           class="allow_all allow {{ $Permission['name'] }} allow_{{ $Permission['name'] }}"
                                                           value="{{ $Permission['name'] }}"
                                                           @if(isset($AllowedPermissions[$Permission['id']])) checked="true"
                                                           @endif onclick="FormValidation.checkMyModule(this,'allow_{{ $Permission['name'] }}');">
                                                    <span></span>Display</label>

                                                @foreach($permissionsMapping as $key => $name)

                                                    @if(array_key_exists($Permission['key'] . $key, $Permission['children']))
                                                        <label class="checkbox permission_checkbox">
                                                            <input id="sub-allow_{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                                                   type="checkbox" name="permission[]"
                                                                   class="allow_all allow {{ $Permission['name'] }}  sub-allow_{{ $Permission['name'] }}"
                                                                   value="{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                                                   @if(isset($AllowedPermissions[$Permission['children'][$Permission['key'] . $key]['id']])) checked="true"
                                                                   @endif onclick="FormValidation.checkMyParent(this,'allow_{{ $Permission['name'] }}' , 'sub-allow_{{ $Permission['name'] }}', '{{ $Permission['children'][$Permission['key'] . $key]['name'] }}' );">
                                                            <span></span>{{$name}}</label>
                                                    @endif

                                                @endforeach
                                            </div>
                                        </div>

                                    </div>

                            @endforeach
                        @endif
                        <!--end::Form-->
                        </div>
                        {{--end General--}}
                    </div>

                    <div class="card card-custom gutter-b example example-compact">
                        {{--For reports--}}
                        <div class="card-header">
                            <h3 class="card-title">Reports Permissions</h3>
                        </div>
                        <div class="card-body">
                            <!--begin::Form-->
                            @if(count($ReportsPermissions))
                                @foreach($ReportsPermissions as $Permission)

                                    <div class="form-group row">

                                        <label class="col-2 col-form-label"><strong>{{ $Permission['title'] }}</strong></label>

                                        <div class="col-9 col-form-label">
                                            <div class="checkbox-inline">

                                                <label class="checkbox permission_checkbox">
                                                    <input id="allow_{{ $Permission['name'] }}" type="checkbox" name="permission[]"
                                                           class="allow_all allow {{ $Permission['name'] }} allow_{{ $Permission['name'] }}"
                                                           value="{{ $Permission['name'] }}"
                                                           @if(isset($AllowedPermissions[$Permission['id']])) checked="true"
                                                           @endif onclick="FormValidation.checkMyModule(this,'allow_{{ $Permission['name'] }}');">
                                                    <span></span>Display</label>

                                                @foreach($reportsPermissionsMapping as $key => $name)
                                                    @if(array_key_exists($Permission['key'] . $key, $Permission['children']))
                                                        <label class="checkbox permission_checkbox">
                                                            <input id="sub-allow_{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                                                   type="checkbox" name="permission[]"
                                                                   class="allow_all allow {{ $Permission['name'] }}  sub-allow_{{ $Permission['name'] }}"
                                                                   value="{{ $Permission['children'][$Permission['key'] . $key]['name'] }}"
                                                                   @if(isset($AllowedPermissions[$Permission['children'][$Permission['key'] . $key]['id']])) checked="true"
                                                                   @endif onclick="FormValidation.checkMyParent(this,'allow_{{ $Permission['name'] }}' , 'sub-allow_{{ $Permission['name'] }}', '{{ $Permission['children'][$Permission['key'] . $key]['name'] }}' );">
                                                            <span></span>{{$name}}</label>
                                                    @endif

                                                @endforeach
                                            </div>
                                        </div>

                                    </div>

                            @endforeach
                        @endif
                        <!--end::Form-->

                            <button type="submit" class="btn btn-primary" >
                                <span class="indicator-label">Save</span>
                            </button>
                        </div>
                    </div>

                </form>

            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    <div class="modal fade" id="modal_add_permission" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="permission-create">
            {{--moel shuold be apend here--}}
        </div>
        <!--end::Modal dialog-->
    </div>
    <!--end::Modal - Add task-->



    @push('datatable-js')
        <script src="{{asset('assets/js/pages/users/role.js')}}"></script>
    @endpush

    @push('js')
        <script src="{{asset('assets/js/pages/crud/forms/validation/permission/permission-validate.js')}}"></script>
    @endpush

@endsection
