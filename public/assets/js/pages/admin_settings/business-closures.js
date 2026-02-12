"use strict";

// Define table_url and table_columns for the global datatable initialization (row-details.js)
var table_url = route('admin.business-closures.datatable');

var table_columns = [
    {
        field: 'id',
        title: '#',
        sortable: false,
        width: 40,
        type: 'number',
        textAlign: 'center',
    },
    {
        field: 'title',
        title: 'Title',
        sortable: false,
        width: 200,
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
            return getActions(row);
        },
    }
];

function getActions(row) {
    if (typeof row.id === 'undefined') {
        return '-';
    }
    
    var dropdownItems = '';
    
    if (permissions.edit) {
        dropdownItems += '<li class="navi-item"><a href="javascript:void(0);" onclick="editClosure(' + row.id + ');" class="navi-link"><span class="navi-icon"><i class="la la-edit"></i></span><span class="navi-text">Edit</span></a></li>';
    }
    
    if (permissions.delete) {
        dropdownItems += '<li class="navi-item"><a href="javascript:void(0);" onclick="deleteClosure(' + row.id + ');" class="navi-link"><span class="navi-icon"><i class="la la-trash"></i></span><span class="navi-text">Delete</span></a></li>';
    }
    
    if (!dropdownItems) {
        return '-';
    }
    
    return '<div class="dropdown dropdown-inline action-dots">' +
        '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">' +
        '<i class="ki ki-bold-more-hor"></i>' +
        '</a>' +
        '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">' +
        '<ul class="navi flex-column navi-hover py-2">' +
        '<li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">Choose an action:</li>' +
        dropdownItems +
        '</ul>' +
        '</div>' +
        '</div>';
}

var filtersInitialized = false;

// Called by row-details.js after datatable loads
function setFilters(filter_values, active_filters) {
    try {
        // Only populate locations once
        if (!filtersInitialized && filter_values && filter_values.locations) {
            var locations = filter_values.locations;
            var $locationFilter = $("#search_location_id");
            
            // Destroy Select2 if it exists
            if ($locationFilter.hasClass('select2-hidden-accessible')) {
                $locationFilter.select2('destroy');
            }
            
            // Clear and rebuild options - add "All" option first for "no filter"
            $locationFilter.empty();
            $locationFilter.append('<option value="">All</option>');
            
            if (locations && locations.length > 0) {
                for (var i = 0; i < locations.length; i++) {
                    // Skip if location name is "All Centres" or similar to avoid duplicate "All"
                    if (locations[i].name.toLowerCase().indexOf('all') === 0) {
                        continue;
                    }
                    $locationFilter.append('<option value="' + locations[i].id + '">' + locations[i].name + '</option>');
                }
            }
            
            filtersInitialized = true;
        }
        
        // Set active filter values (always update these)
        if (active_filters && active_filters.location_id) {
            $("#search_location_id").val(active_filters.location_id);
        }
        if (active_filters && active_filters.start_date) {
            $('#search_start_date').val(active_filters.start_date);
        }
        if (active_filters && active_filters.end_date) {
            $('#search_end_date').val(active_filters.end_date);
        }
        
    } catch (error) {
        console.error('Error setting filters:', error);
    }
}

// Called by row-details.js for filter application
function applyFilters(datatable) {
    $('#kt_search').on('click', function () {
        var filters = {
            location_id: $('#search_location_id').val(),
            start_date: $('#search_start_date').val(),
            end_date: $('#search_end_date').val(),
            filter: 'filter',
        };
        datatable.search(filters, 'search');
    });
}

// Called by row-details.js for filter reset
function resetAllFilters(datatable) {
    $('#kt_reset').on('click', function () {
        $('#search_location_id').val('');
        $('#search_start_date').val('');
        $('#search_end_date').val('');
        
        var filters = {
            location_id: '',
            start_date: '',
            end_date: '',
            filter: 'filter_cancel',
        };
        datatable.search(filters, 'search');
    });
}

$(document).ready(function () {
    initDatepickers();
    initSelect2();
    initFormHandlers();
});

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
        placeholder: 'Select Locations',
        allowClear: true,
        dropdownParent: $('#modal_add_business_closure'),
    });

    $('#edit_location_ids').select2({
        placeholder: 'Select Locations',
        allowClear: true,
        dropdownParent: $('#modal_edit_business_closure'),
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
            title: $('#add_title').val(),
            location_ids: $('#add_location_ids').val(),
            start_date: $('#add_start_date').val(),
            end_date: $('#add_end_date').val(),
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
                    datatable.reload();
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
            title: $('#edit_title').val(),
            location_ids: $('#edit_location_ids').val(),
            start_date: $('#edit_start_date').val(),
            end_date: $('#edit_end_date').val(),
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
                    datatable.reload();
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
                $('#edit_title').val(closure.title || '');
                $('#edit_start_date').val(closure.start_date.split('T')[0]);
                $('#edit_end_date').val(closure.end_date.split('T')[0]);
                
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
                        datatable.reload();
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
        for (var i = 0; i < locations.length; i++) {
            $select.append(new Option(locations[i].name, locations[i].id, false, false));
        }
    }
    
    $select.trigger('change');
}

function resetAddForm() {
    $('#add_title').val('');
    $('#add_start_date').val('');
    $('#add_end_date').val('');
    $('#add_location_ids').val(null).trigger('change');
}
