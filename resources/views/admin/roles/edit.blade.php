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

                    @foreach($categories as $categoryName => $groups)
                        @include('admin.roles.partials._permission-category-card', [
                            'categoryName' => $categoryName,
                            'groups' => $groups,
                            'cardIndex' => $loop->index,
                            'preCheck' => true,
                            'allowed_permissions' => $allowed_permissions,
                        ])
                    @endforeach

                    <div class="card card-custom gutter-b example example-compact">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary spinner-button">
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
