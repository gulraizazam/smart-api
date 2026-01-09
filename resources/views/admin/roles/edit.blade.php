@extends('admin.layouts.master')
@section('title', 'Roles Edit')
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
                            @if(count($dashboard_permissions))
                                @foreach($dashboard_permissions as $permission)

                                    <div class="form-group row">

                                        <label class="col-2 col-form-label"><strong>{{ $permission['title'] }}</strong></label>
                                        <input id="allow_{{ $permission['name'] }}" type="checkbox" name="permission[]"
                                               class="allow_all allow {{ $permission['name'] }} allow_{{ $permission['name'] }}"
                                               value="{{ $permission['name'] }}" checked="true" style="visibility: hidden;"
                                               onclick="FormValidation.checkMyModule(this,'allow_{{ $permission['name'] }}');">

                                        <div class="col-9 col-form-label">
                                            <div class="checkbox-inline">
                                                @foreach($permission['children'] as $child)
                                                    <label class="checkbox permission_checkbox">
                                                        <input id="sub-allow_{{ $child['name'] }}"
                                                               type="checkbox" name="permission[]"
                                                               class="allow_all allow {{ $permission['name'] }}  sub-allow_{{ $permission['name'] }}"
                                                               value="{{ $child['name'] }}"
                                                               @if(isset($allowed_permissions[$child['id']])) checked="true"
                                                               @endif onclick="FormValidation.checkMyParent(this,'allow_{{ $permission['name'] }}' , 'sub-allow_{{ $permission['name'] }}', '{{ $child['name'] }}' );">
                                                        <span></span>{{ $child['title'] }}</label>
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
                            @if(count($permissions))
                                @foreach($permissions as $permission)

                                    <div class="form-group row">

                                        <label class="col-2 col-form-label"><strong>{{ $permission['title'] }}</strong></label>

                                        <div class="col-9 col-form-label">
                                            <div class="checkbox-inline">

                                                <label class="checkbox permission_checkbox">
                                                    <input id="allow_{{ $permission['name'] }}" type="checkbox" name="permission[]"
                                                           class="allow_all allow {{ $permission['name'] }} allow_{{ $permission['name'] }}"
                                                           value="{{ $permission['name'] }}"
                                                           @if(isset($allowed_permissions[$permission['id']])) checked="true"
                                                           @endif onclick="FormValidation.checkMyModule(this,'allow_{{ $permission['name'] }}');">
                                                    <span></span>Display</label>

                                                @foreach($permission['children'] as $child)
                                                    <label class="checkbox permission_checkbox">
                                                        <input id="sub-allow_{{ $child['name'] }}"
                                                               type="checkbox" name="permission[]"
                                                               class="allow_all allow {{ $permission['name'] }}  sub-allow_{{ $permission['name'] }}"
                                                               value="{{ $child['name'] }}"
                                                               @if(isset($allowed_permissions[$child['id']])) checked="true"
                                                               @endif onclick="FormValidation.checkMyParent(this,'allow_{{ $permission['name'] }}' , 'sub-allow_{{ $permission['name'] }}', '{{ $child['name'] }}' );">
                                                        <span></span>{{ $child['title'] }}</label>
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
                            @if(count($reports_permissions))
                                @foreach($reports_permissions as $permission)

                                    <div class="form-group row">

                                        <label class="col-2 col-form-label"><strong>{{ $permission['title'] }}</strong></label>

                                        <div class="col-9 col-form-label">
                                            <div class="checkbox-inline">

                                                <label class="checkbox permission_checkbox">
                                                    <input id="allow_{{ $permission['name'] }}" type="checkbox" name="permission[]"
                                                           class="allow_all allow {{ $permission['name'] }} allow_{{ $permission['name'] }}"
                                                           value="{{ $permission['name'] }}"
                                                           @if(isset($allowed_permissions[$permission['id']])) checked="true"
                                                           @endif onclick="FormValidation.checkMyModule(this,'allow_{{ $permission['name'] }}');">
                                                    <span></span>Display</label>

                                                @foreach($permission['children'] as $child)
                                                    <label class="checkbox permission_checkbox">
                                                        <input id="sub-allow_{{ $child['name'] }}"
                                                               type="checkbox" name="permission[]"
                                                               class="allow_all allow {{ $permission['name'] }}  sub-allow_{{ $permission['name'] }}"
                                                               value="{{ $child['name'] }}"
                                                               @if(isset($allowed_permissions[$child['id']])) checked="true"
                                                               @endif onclick="FormValidation.checkMyParent(this,'allow_{{ $permission['name'] }}' , 'sub-allow_{{ $permission['name'] }}', '{{ $child['name'] }}' );">
                                                        <span></span>{{ $child['title'] }}</label>
                                                @endforeach
                                            </div>
                                        </div>

                                    </div>

                            @endforeach
                        @endif
                        <!--end::Form-->

                            <button type="submit" class="btn btn-primary spinner-button" >
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
