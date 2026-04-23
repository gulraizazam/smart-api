@extends('admin.layouts.master')
@section('title', 'Employees')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Employees'])

        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <i class="la la-users icon-lg text-primary"></i>
                                </span>
                            </span>
                            <h3 class="card-label">Employees</h3>
                        </div>
                    </div>

                    <div class="card-body">
                        {{-- Filters --}}
                        <div class="mt-2 mb-7">
                            <div class="row mb-6">
                                <div class="col-lg-3 mb-lg-0 mb-6">
                                    <label>Name:</label>
                                    <input type="text" class="form-control filter-field" placeholder="Search by name" id="search_name" />
                                </div>
                                <div class="col-lg-3 mb-lg-0 mb-6">
                                    <label>Designation:</label>
                                    <select class="form-control filter-field select2" id="search_designation">
                                        <option value="">All</option>
                                        @foreach($designations as $d)
                                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3 mb-lg-0 mb-6">
                                    <label>Centre:</label>
                                    <select class="form-control filter-field select2" id="search_location">
                                        <option value="">All</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3 mb-lg-0 mb-6 mt-8">
                                    @include('admin.partials.filter-buttons')
                                </div>
                            </div>
                        </div>

                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('datatable-js')
    <script>
        var table_url = '{{ route("admin.api.hr.employees.datatable") }}';

        var table_columns = [
            {
                field: 'name',
                title: 'Name',
                width: 'auto',
                sortable: false,
                template: function (data) {
                    var url = '{{ route("admin.hr.employees.show", ":id") }}'.replace(':id', data.id);
                    return '<a href="' + url + '" class="text-dark font-weight-bolder text-hover-primary">' + $('<span>').text(data.name).html() + '</a>';
                }
            },
            {
                field: 'email',
                title: 'Email',
                width: 'auto',
                sortable: false,
            },
            {
                field: 'phone',
                title: 'Phone',
                width: 120,
                sortable: false,
            },
            {
                field: 'designation',
                title: 'Designation',
                width: 'auto',
                sortable: false,
            },
            {
                field: 'hire_date',
                title: 'Hire Date',
                width: 120,
                sortable: false,
            },
            {
                field: 'active',
                title: 'Status',
                width: 80,
                sortable: false,
                template: function (data) {
                    if (data.active == 1) {
                        return '<span class="label label-lg label-light-success label-inline">Active</span>';
                    }
                    return '<span class="label label-lg label-light-danger label-inline">Inactive</span>';
                }
            },
            {
                field: 'actions',
                title: 'Actions',
                sortable: false,
                width: 80,
                overflow: 'visible',
                autoHide: false,
                template: function (data) {
                    return actions(data);
                }
            }
        ];

        function actions(data) {
            let viewUrl = '{{ route("admin.hr.employees.show", ":id") }}'.replace(':id', data.id);
            let html = '';

            if (permissions.view) {
                html += '<a href="' + viewUrl + '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View Profile">';
                html += '<i class="la la-eye"></i>';
                html += '</a>';
            }

            if (permissions.edit) {
                html += '<a href="' + viewUrl + '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit HR Details">';
                html += '<i class="la la-pencil"></i>';
                html += '</a>';
            }

            return html;
        }

        function applyFilters(datatable) {
            $('#apply-filters').on('click', function() {
                let filters = {
                    name: $("#search_name").val(),
                    designation_id: $("#search_designation").val(),
                    location_id: $("#search_location").val(),
                    filter: 'filter',
                };
                datatable.search(filters, 'search');
            });
        }

        function resetAllFilters(datatable) {
            $('#reset-filters').on('click', function() {
                let filters = {
                    name: '',
                    designation_id: '',
                    location_id: '',
                    filter: 'filter_cancel',
                };
                $("#search_name").val('');
                $("#search_designation").val('').trigger('change');
                $("#search_location").val('').trigger('change');
                datatable.search(filters, 'search');
            });
        }

        function setFilters(filter_values, active_filters) {
            if (active_filters.name) $("#search_name").val(active_filters.name);
            if (active_filters.designation_id) $("#search_designation").val(active_filters.designation_id).trigger('change');
            if (active_filters.location_id) $("#search_location").val(active_filters.location_id).trigger('change');
        }
    </script>
    @endpush

    @push('js')
    <script>
        $(function () {
            $('.select2').select2();
        });
    </script>
    @endpush

@endsection
