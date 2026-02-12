"use strict";

var KTDatatable = null;
var permissions = {
    create: true,
    edit: true,
    delete: true
};

$(document).ready(function () {
    initDatatable();
    initDatepickers();
    initSelect2();
    initFormHandlers();
});

function initDatatable() {
    KTDatatable = $('#kt_datatable').KTDatatable({
        data: {
            type: 'remote',
            source: {
                read: {
                    url: route('admin.business-closures.datatable'),
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    map: function (raw) {
                        var dataSet = raw;
                        if (typeof raw.data !== 'undefined') {
                            dataSet = raw.data;
                        }
                        return dataSet;
                    },
                },
            },
            pageSize: 10,
            serverPaging: true,
            serverFiltering: true,
            serverSorting: false,
            saveState: {
                cookie: false,
                webstorage: false,
            },
        },
        layout: {
            scroll: false,
            footer: false,
        },
        sortable: false,
        pagination: true,
        toolbar: {
            items: {
                pagination: {
                    pageSizeSelect: [10, 20, 30, 50, 100],
                },
            },
        },
        search: {
            input: $('#kt_datatable_search_query'),
            key: 'generalSearch'
        },
        columns: [
            {
                field: 'id',
                title: '#',
                sortable: false,
                width: 40,
                type: 'number',
                textAlign: 'center',
            },
            {
                field: 'locations',
                title: 'Locations',
                sortable: false,
                width: 200,
            },
            {
                field: 'start_date',
                title: 'Start Date',
                sortable: false,
                width: 120,
            },
            {
                field: 'end_date',
                title: 'End Date',
                sortable: false,
                width: 120,
            },
            {
                field: 'description',
                title: 'Description',
                sortable: false,
                width: 200,
            },
            {
                field: 'created_by',
                title: 'Created By',
                sortable: false,
                width: 120,
            },
            {
                field: 'created_at',
                title: 'Created At',
                sortable: false,
                width: 150,
            },
            {
                field: 'Actions',
                title: 'Actions',
                sortable: false,
                width: 100,
                overflow: 'visible',
                autoHide: false,
                template: function (row) {
                    var dropdownItems = '';
                    
                    if (permissions.edit) {
                        dropdownItems += '<a class="dropdown-item" href="javascript:void(0);" onclick="editClosure(' + row.id + ');">' +
                            '<i class="la la-edit mr-2"></i> Edit' +
                            '</a>';
                    }
                    
                    if (permissions.delete) {
                        dropdownItems += '<a class="dropdown-item text-danger" href="javascript:void(0);" onclick="deleteClosure(' + row.id + ');">' +
                            '<i class="la la-trash mr-2"></i> Delete' +
                            '</a>';
                    }
                    
                    if (!dropdownItems) {
                        return '-';
                    }
                    
                    return '<div class="dropdown dropdown-inline">' +
                        '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon" data-toggle="dropdown">' +
                        '<i class="la la-ellipsis-v"></i>' +
                        '</a>' +
                        '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">' +
                        dropdownItems +
                        '</div>' +
                        '</div>';
                },
            }
        ],
    });

    KTDatatable.on('datatable-on-ajax-done', function (event, data) {
        if (data.permissions) {
            permissions = data.permissions;
        }
        if (data.filter_values) {
            setFilters(data.filter_values, data.active_filters);
        }
    });

    // Search button
    $('#kt_search').on('click', function () {
        var filters = {
            location_id: $('#search_location_id').val(),
            start_date: $('#search_start_date').val(),
            end_date: $('#search_end_date').val(),
            filter: 'filter',
        };
        KTDatatable.search(filters, 'search');
    });

    // Reset button
    $('#kt_reset').on('click', function () {
        $('#search_location_id').val('').trigger('change');
        $('#search_start_date').val('');
        $('#search_end_date').val('');
        
        var filters = {
            location_id: '',
            start_date: '',
            end_date: '',
            filter: 'filter_cancel',
        };
        KTDatatable.search(filters, 'search');
    });

    // Bulk delete
    $('#delete-table-rows').on('click', function () {
        var ids = [];
        $('.kt-checkbox--solid input:checked').each(function () {
            ids.push($(this).val());
        });
        
        if (ids.length > 0) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete them!'
            }).then((result) => {
                if (result.isConfirmed) {
                    var filters = {
                        delete: ids.join(','),
                    };
                    KTDatatable.search(filters, 'search');
                }
            });
        }
    });

    // Checkbox selection handling
    KTDatatable.on('datatable-on-check datatable-on-uncheck', function (e) {
        var checkedNodes = KTDatatable.rows('.datatable-row-active').nodes();
        var count = $(checkedNodes).length;
        
        if (count > 0) {
            $('.delete-records').removeClass('d-none');
            $('.checkbox-count').text(count);
        } else {
            $('.delete-records').addClass('d-none');
        }
    });
}

function initDatepickers() {
    // Filter datepickers
    $('#search_start_date, #search_end_date').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
    });

    // Add modal datepickers
    $('#add_start_date, #add_end_date').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
        startDate: new Date(),
    });

    // Edit modal datepickers
    $('#edit_start_date, #edit_end_date').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
    });
}

function initSelect2() {
    $('#add_location_ids').select2({
        placeholder: 'All Locations',
        allowClear: true,
        dropdownParent: $('#modal_add_business_closure'),
    });

    $('#edit_location_ids').select2({
        placeholder: 'All Locations',
        allowClear: true,
        dropdownParent: $('#modal_edit_business_closure'),
    });

    $('#search_location_id').select2({
        placeholder: 'All',
        allowClear: true,
    });
}

function initFormHandlers() {
    // Add form submission
    $('#form_add_business_closure').on('submit', function (e) {
        e.preventDefault();
        
        var btn = $('#btn_add_business_closure');
        btn.attr('disabled', true);
        btn.find('.indicator-label').hide();
        btn.find('.indicator-progress').show();

        var formData = {
            location_ids: $('#add_location_ids').val() || ['all'],
            start_date: $('#add_start_date').val(),
            end_date: $('#add_end_date').val(),
            description: $('#add_description').val(),
        };

        $.ajax({
            url: route('admin.business-closures.store'),
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                btn.attr('disabled', false);
                btn.find('.indicator-label').show();
                btn.find('.indicator-progress').hide();

                if (response.status) {
                    toastr.success(response.message);
                    $('#modal_add_business_closure').modal('hide');
                    resetAddForm();
                    KTDatatable.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function (xhr) {
                btn.attr('disabled', false);
                btn.find('.indicator-label').show();
                btn.find('.indicator-progress').hide();
                
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    var errorMsg = '';
                    for (var key in errors) {
                        errorMsg += errors[key][0] + '<br>';
                    }
                    toastr.error(errorMsg);
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
            }
        });
    });

    // Edit form submission
    $('#form_edit_business_closure').on('submit', function (e) {
        e.preventDefault();
        
        var btn = $('#btn_edit_business_closure');
        var closureId = $('#edit_closure_id').val();
        
        btn.attr('disabled', true);
        btn.find('.indicator-label').hide();
        btn.find('.indicator-progress').show();

        var formData = {
            _method: 'PUT',
            location_ids: $('#edit_location_ids').val() || ['all'],
            start_date: $('#edit_start_date').val(),
            end_date: $('#edit_end_date').val(),
            description: $('#edit_description').val(),
        };

        $.ajax({
            url: route('admin.business-closures.update', { id: closureId }),
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                btn.attr('disabled', false);
                btn.find('.indicator-label').show();
                btn.find('.indicator-progress').hide();

                if (response.status) {
                    toastr.success(response.message);
                    $('#modal_edit_business_closure').modal('hide');
                    KTDatatable.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function (xhr) {
                btn.attr('disabled', false);
                btn.find('.indicator-label').show();
                btn.find('.indicator-progress').hide();
                
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    var errorMsg = '';
                    for (var key in errors) {
                        errorMsg += errors[key][0] + '<br>';
                    }
                    toastr.error(errorMsg);
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
            }
        });
    });
}

function openAddModal() {
    $.ajax({
        url: route('admin.business-closures.create'),
        type: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            if (response.status) {
                populateLocations('#add_location_ids', response.data.locations);
                resetAddForm();
            } else {
                toastr.error(response.message);
            }
        },
        error: function () {
            toastr.error('Failed to load form data.');
        }
    });
}

function editClosure(id) {
    $.ajax({
        url: route('admin.business-closures.edit', { id: id }),
        type: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            if (response.status) {
                var closure = response.data.closure;
                var locationIds = response.data.location_ids;
                
                populateLocations('#edit_location_ids', response.data.locations);
                
                $('#edit_closure_id').val(closure.id);
                $('#edit_start_date').val(closure.start_date.split('T')[0]);
                $('#edit_end_date').val(closure.end_date.split('T')[0]);
                $('#edit_description').val(closure.description || '');
                
                $('#edit_location_ids').val(locationIds).trigger('change');
                
                $('#modal_edit_business_closure').modal('show');
            } else {
                toastr.error(response.message);
            }
        },
        error: function () {
            toastr.error('Failed to load closure data.');
        }
    });
}

function deleteClosure(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: route('admin.business-closures.destroy', { id: id }),
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    if (response.status) {
                        toastr.success(response.message);
                        KTDatatable.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function () {
                    toastr.error('Failed to delete closure.');
                }
            });
        }
    });
}

function populateLocations(selector, locations) {
    var $select = $(selector);
    $select.empty();
    
    if (locations && locations.length > 0) {
        locations.forEach(function (location) {
            $select.append(new Option(location.name, location.id, false, false));
        });
    }
    
    $select.trigger('change');
}

function resetAddForm() {
    $('#add_start_date').val('');
    $('#add_end_date').val('');
    $('#add_description').val('');
    $('#add_location_ids').val(null).trigger('change');
}

function setFilters(filter_values, active_filters) {
    try {
        var locations = filter_values.locations;
        
        // Populate location filter
        var $locationFilter = $('#search_location_id');
        $locationFilter.empty();
        $locationFilter.append(new Option('All', '', true, true));
        
        if (locations && locations.length > 0) {
            locations.forEach(function (location) {
                var selected = active_filters && active_filters.location_id == location.id;
                $locationFilter.append(new Option(location.name, location.id, selected, selected));
            });
        }
        
        // Set active filter values
        if (active_filters) {
            if (active_filters.start_date) {
                $('#search_start_date').val(active_filters.start_date);
            }
            if (active_filters.end_date) {
                $('#search_end_date').val(active_filters.end_date);
            }
        }
        
    } catch (error) {
        console.error('Error setting filters:', error);
    }
}
