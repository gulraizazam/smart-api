@extends('admin.layouts.master')

@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Create Roles', 'title' => 'Roles'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">
                <form id="permissions-form" method="post" action="{{route('admin.roles.store')}}">
                    <div class="card card-custom gutter-b example example-compact">
                    <div class="card-header py-3">
                        <div class="card-title">
                                <span class="card-icon">
                                    <span class="svg-icon svg-icon-md svg-icon-primary">
                                        <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                <rect x="0" y="0" width="24" height="24" />
                                                <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                                <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                                <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                                <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                            </g>
                                        </svg>
                                        <!--end::Svg Icon-->
                                    </span>
                                </span>
                            <h3 class="card-label">Create</h3>
                        </div>
                        <div class="col-md-10">
                            <a href="{{route('admin.roles.index')}}" class="btn btn-sm btn-primary mt-3" style="float: right;"><i class="fa fa-arrow-left"></i>Back</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="mt-2 mb-7">
                        <div class="row align-items-center">
                            <div class="col-lg-12 col-xl-12">
                                <div class="row align-items-center">
                                    <div class="col-md-5 my-md-0">
                                        <label>Name <span class="text text-danger">*</span></label>
                                        <input type="text" value="{{$filters['name'] ?? ''}}" class="form-control filter-field" placeholder="Name" id="search_name" />
                                    </div>

                                    <div class="col-md-5 my-md-0">
                                        <label>Commission</label>
                                        <input style="width: 95%;" type="number" min="0" max="100" value="{{$filters['commission'] ?? ''}}" class="form-control filter-field" placeholder="Commission" id="search_commission" />
                                        <div class="input-group-append percentage-align">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>

                                    <div class="col-md-2">
                                        <button class="btn btn-success mt-10 ml-10">Save</button>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                    </div>
                </div>

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
