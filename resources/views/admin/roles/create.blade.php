@extends('admin.layouts.master')
@section('title', 'Roles Create')
@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Create Roles', 'title' => 'Roles'])
    <div class="d-flex flex-column-fluid">
        <div class="container">
            <form class="form fv-plugins-bootstrap" method="post" id="permissions-form" action="{{route('admin.roles.store')}}">
                @csrf
                @include('admin.roles.fields')

                @foreach($categories as $categoryName => $groups)
                    @include('admin.roles.partials._permission-category-card', [
                        'categoryName' => $categoryName,
                        'groups' => $groups,
                        'cardIndex' => $loop->index,
                        'preCheck' => false,
                        'allowed_permissions' => [],
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
