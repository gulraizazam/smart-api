@extends('admin.layouts.master')
@section('title', 'Roles Duplicate')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Duplicate Role', 'title' => 'Roles'])
    <div class="d-flex flex-column-fluid">
        <div class="container">
            <form class="form fv-plugins-bootstrap" method="post" id="permissions-form" action="{{route('admin.roles.duplicate.store')}}">
                @csrf
                @include('admin.roles.fields')
                <div class="card card-custom gutter-b example example-compact">
                    <div class="card-body">
                        <div class="accordion accordion-light accordion-light-borderless accordion-svg-toggle" id="dashboard-collapse">
                            <div class="card">
                                <div class="card-header collapse-header">
                                    <div class="card-title" data-toggle="collapse" data-target="#dashboard-permissions">
                                        <span class="svg-icon svg-icon-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <polygon points="0 0 24 0 24 24 0 24" />
                                                    <path d="M12.2928955,6.70710318 C11.9023712,6.31657888 11.9023712,5.68341391 12.2928955,5.29288961 C12.6834198,4.90236532 13.3165848,4.90236532 13.7071091,5.29288961 L19.7071091,11.2928896 C20.085688,11.6714686 20.0989336,12.281055 19.7371564,12.675721 L14.2371564,18.675721 C13.863964,19.08284 13.2313966,19.1103429 12.8242777,18.7371505 C12.4171587,18.3639581 12.3896557,17.7313908 12.7628481,17.3242718 L17.6158645,12.0300721 L12.2928955,6.70710318 Z" fill="#000000" fill-rule="nonzero" />
                                                    <path d="M3.70710678,15.7071068 C3.31658249,16.0976311 2.68341751,16.0976311 2.29289322,15.7071068 C1.90236893,15.3165825 1.90236893,14.6834175 2.29289322,14.2928932 L8.29289322,8.29289322 C8.67147216,7.91431428 9.28105859,7.90106866 9.67572463,8.26284586 L15.6757246,13.7628459 C16.0828436,14.1360383 16.1103465,14.7686056 15.7371541,15.1757246 C15.3639617,15.5828436 14.7313944,15.6103465 14.3242754,15.2371541 L9.03007575,10.3841378 L3.70710678,15.7071068 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(9.000003, 11.999999) rotate(-270.000000) translate(-9.000003, -11.999999)" />
                                                </g>
                                            </svg>
                                        </span>
                                        <div class="card-label pl-4">Dashboard Permissions</div>
                                    </div>
                                </div>
                                <div id="dashboard-permissions" class="collapse show" data-parent="#dashboard-collapse">
                                    @if(count($dashboard_permissions))
                                        @foreach($dashboard_permissions as $permission)
                                            <div class="form-group row">
                                                <label class="col-2 col-form-label"><strong>{{ $permission['title'] }}</strong></label>
                                                <input id="allow_{{ $permission['name'] }}" type="checkbox" name="permission[]"
                                                       class="allow_all allow {{ $permission['name'] }} allow_{{ $permission['name'] }}"
                                                       value="{{ $permission['name'] }}" 
                                                       @if(isset($allowed_permissions[$permission['id']])) checked="true" @endif
                                                       style="visibility: hidden;"
                                                       onclick="FormValidation.checkMyModule(this,'allow_{{ $permission['name'] }}');">
                                                <div class="col-9 col-form-label">
                                                    <div class="checkbox-inline">
                                                        @foreach($permission['children'] as $child)
                                                            <label class="checkbox permission_checkbox">
                                                                <input id="sub-allow_{{ $child['name'] }}"
                                                                       type="checkbox" name="permission[]"
                                                                       class="allow_all allow {{ $permission['name'] }}  sub-allow_{{ $permission['name'] }}"
                                                                       value="{{ $child['name'] }}"
                                                                       @if(isset($allowed_permissions[$child['id']])) checked="true" @endif
                                                                       onclick="FormValidation.checkMyParent(this,'allow_{{ $permission['name'] }}' , 'sub-allow_{{ $permission['name'] }}', '{{ $child['name'] }}' );">
                                                                <span></span>{{ $child['title'] }}</label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card card-custom gutter-b example example-compact">
                    <div class="card-body">
                        <div class="accordion accordion-light accordion-light-borderless accordion-svg-toggle" id="general-collapse">
                            <div class="card">
                                <div class="card-header collapse-header">
                                    <div class="card-title" data-toggle="collapse" data-target="#general-permissions">
                                            <span class="svg-icon svg-icon-primary">
                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <polygon points="0 0 24 0 24 24 0 24" />
                                                        <path d="M12.2928955,6.70710318 C11.9023712,6.31657888 11.9023712,5.68341391 12.2928955,5.29288961 C12.6834198,4.90236532 13.3165848,4.90236532 13.7071091,5.29288961 L19.7071091,11.2928896 C20.085688,11.6714686 20.0989336,12.281055 19.7371564,12.675721 L14.2371564,18.675721 C13.863964,19.08284 13.2313966,19.1103429 12.8242777,18.7371505 C12.4171587,18.3639581 12.3896557,17.7313908 12.7628481,17.3242718 L17.6158645,12.0300721 L12.2928955,6.70710318 Z" fill="#000000" fill-rule="nonzero" />
                                                        <path d="M3.70710678,15.7071068 C3.31658249,16.0976311 2.68341751,16.0976311 2.29289322,15.7071068 C1.90236893,15.3165825 1.90236893,14.6834175 2.29289322,14.2928932 L8.29289322,8.29289322 C8.67147216,7.91431428 9.28105859,7.90106866 9.67572463,8.26284586 L15.6757246,13.7628459 C16.0828436,14.1360383 16.1103465,14.7686056 15.7371541,15.1757246 C15.3639617,15.5828436 14.7313944,15.6103465 14.3242754,15.2371541 L9.03007575,10.3841378 L3.70710678,15.7071068 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(9.000003, 11.999999) rotate(-270.000000) translate(-9.000003, -11.999999)" />
                                                    </g>
                                                </svg>
                                            </span>
                                        <div class="card-label pl-4">General Permissions</div>
                                    </div>
                                </div>
                                <div id="general-permissions" class="collapse show" data-parent="#general-collapse">
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
                                                               @if(isset($allowed_permissions[$permission['id']])) checked="true" @endif
                                                               onclick="FormValidation.checkMyModule(this,'allow_{{ $permission['name'] }}');">
                                                        <span></span>Display</label>
                                                        @foreach($permission['children'] as $child)
                                                            <label class="checkbox permission_checkbox">
                                                                <input id="sub-allow_{{ $child['name'] }}"
                                                                       type="checkbox" name="permission[]"
                                                                       class="allow_all allow {{ $permission['name'] }}  sub-allow_{{ $permission['name'] }}"
                                                                       value="{{ $child['name'] }}"
                                                                       @if(isset($allowed_permissions[$child['id']])) checked="true" @endif
                                                                       onclick="FormValidation.checkMyParent(this,'allow_{{ $permission['name'] }}' , 'sub-allow_{{ $permission['name'] }}', '{{ $child['name'] }}' );">
                                                                <span></span>{{ $child['title'] }}</label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card card-custom gutter-b example example-compact">
                    <div class="card-body">
                        <div class="accordion accordion-light accordion-light-borderless accordion-svg-toggle" id="report-collapse">
                            <div class="card">
                                <div class="card-header collapse-header">
                                    <div class="card-title" data-toggle="collapse" data-target="#report-permissions">
                                        <span class="svg-icon svg-icon-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <polygon points="0 0 24 0 24 24 0 24" />
                                                    <path d="M12.2928955,6.70710318 C11.9023712,6.31657888 11.9023712,5.68341391 12.2928955,5.29288961 C12.6834198,4.90236532 13.3165848,4.90236532 13.7071091,5.29288961 L19.7071091,11.2928896 C20.085688,11.6714686 20.0989336,12.281055 19.7371564,12.675721 L14.2371564,18.675721 C13.863964,19.08284 13.2313966,19.1103429 12.8242777,18.7371505 C12.4171587,18.3639581 12.3896557,17.7313908 12.7628481,17.3242718 L17.6158645,12.0300721 L12.2928955,6.70710318 Z" fill="#000000" fill-rule="nonzero" />
                                                    <path d="M3.70710678,15.7071068 C3.31658249,16.0976311 2.68341751,16.0976311 2.29289322,15.7071068 C1.90236893,15.3165825 1.90236893,14.6834175 2.29289322,14.2928932 L8.29289322,8.29289322 C8.67147216,7.91431428 9.28105859,7.90106866 9.67572463,8.26284586 L15.6757246,13.7628459 C16.0828436,14.1360383 16.1103465,14.7686056 15.7371541,15.1757246 C15.3639617,15.5828436 14.7313944,15.6103465 14.3242754,15.2371541 L9.03007575,10.3841378 L3.70710678,15.7071068 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(9.000003, 11.999999) rotate(-270.000000) translate(-9.000003, -11.999999)" />
                                                </g>
                                            </svg>
                                        </span>
                                        <div class="card-label pl-4">Reports Permissions</div>
                                    </div>
                                </div>
                                <div id="report-permissions" class="collapse show" data-parent="#report-collapse">
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
                                                                @if(isset($allowed_permissions[$permission['id']])) checked="true" @endif
                                                                onclick="FormValidation.checkMyModule(this,'allow_{{ $permission['name'] }}');">
                                                            <span></span>Display
                                                        </label>
                                                        @foreach($permission['children'] as $child)
                                                            <label class="checkbox permission_checkbox">
                                                                <input id="sub-allow_{{ $child['name'] }}"
                                                                    type="checkbox" name="permission[]"
                                                                    class="allow_all allow {{ $permission['name'] }}  sub-allow_{{ $permission['name'] }}"
                                                                    value="{{ $child['name'] }}"
                                                                    @if(isset($allowed_permissions[$child['id']])) checked="true" @endif
                                                                    onclick="FormValidation.checkMyParent(this,'allow_{{ $permission['name'] }}' , 'sub-allow_{{ $permission['name'] }}', '{{ $child['name'] }}' );">
                                                                <span></span>{{ $child['title'] }}</label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary spinner-button" >
                                <span class="indicator-label">Save</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="modal_add_permission" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup" id="permission-create">
        {{--moel shuold be apend here--}}
    </div>
</div>
@push('datatable-js')
    <script src="{{asset('assets/js/pages/users/role.js')}}"></script>
@endpush
@push('js')
    <script src="{{asset('assets/js/pages/crud/forms/validation/permission/permission-validate.js')}}"></script>
@endpush
@endsection
