@extends('admin.layouts.master')

@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Create Roles', 'title' => 'Roles'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <!--begin::Card-->
                <div class="card card-custom">
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
                    </div>
                    <div class="card-body">
                        <!--begin: Search Form-->
                        <!--begin::Search Form-->
                        <div class="mt-2 mb-7">
                            <div class="row align-items-center">
                                <div class="col-lg-12 col-xl-12">
                                    <div class="row align-items-center">
                                        <div class="col-md-5 my-md-0">
                                            <label>Name <span class="text text-danger">*</span></label>
                                            <input type="text" value="{{$filters['name'] ?? ''}}" class="form-control filter-field" placeholder="Name" id="search_name" />
                                        </div>

                                        <div class="col-md-5 my-md-0">
                                            <label>Commission <span class="text text-danger">*</span></label>
                                            <input type="number" min="0" max="100" value="{{$filters['commission'] ?? ''}}" class="form-control filter-field" placeholder="Commission" id="search_commission" />
                                            <span class="input-group-addon">%</span>
                                        </div>

                                        <div class="col-md-2">
                                            <button class="btn btn-success mt-10 ml-10">Save</button>
                                        </div>


                                    </div>
                                </div>

                            </div>
                        </div>

                        <h4 class="mt-10">Dashboard Permissions</h4>
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

                        <h4 class="mt-10">General Permissions</h4>
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
                        <h4 class="mt-10">Reports Permissions</h4>
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

                    </div>
                </div>
                <!--end::Card-->
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
        <script src="{{asset('assets/js/pages/crud/forms/validation/permission//validate.js')}}"></script>
    @endpush

@endsection
