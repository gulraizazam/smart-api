
var table_url = route('admin.warehouse.datatable');

var table_columns = [
   
    {
        field: 'name',
        title: 'Name',
        width: '80',
        sortable: false,
    },
    // {
    //     field: 'manager_name',
    //     title: 'Manager Name',
    //     width: '80',
    //     sortable: false,
    // },
    // {
    //     field: 'manager_phone',
    //     title: 'Manager Phone',
    //     width: '80',
    //     sortable: false,
    // },
    // {
    //     field: 'address',
    //     title: 'Address',
    //     width: '80',
    //     sortable: false,
    // },
    {
        field: 'city',
        title: 'City',
        width: '80',
        sortable: false,
    },
    {
        field: 'created_at',
        title: 'Created At',
        width: '80',
        sortable: false,
    },
    {
        field: 'status',
        title: 'Status',
        width: '60',
        sortable: false,
        template: function (data) {
            let status_url = route('admin.warehouse.status');
            return statuses(data, status_url, true);
        }
    },
    {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: '80',
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }
];


function actions(data) {

    let id = data.id;

    let csrf = $('meta[name="csrf-token"]').attr('content');
    let url = route('admin.warehouse.edit', { id: id });
    let delete_url = route('admin.warehouse.destroy', { id: id });

    if (permissions.edit || permissions.delete) {
        let actions = '<div class="dropdown dropdown-inline action-dots">\
            <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
        if (permissions.edit) {
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`'+ url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>';
        }
        if (permissions.delete) {
            actions += '<li class="navi-item">\
                            <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-trash"></i></span>\
                            <span class="navi-text">Delete</span>\
                            </a>\
                         </li>';
        }

        actions += '</ul>\
            </div>\
        </div>';

        return actions;
    }
    return '';
}

function statuses(data, status_url, is_column_name_change = false) {

    let id = data.id;

    let active = is_column_name_change == false ? data.active : data.status;
    let status = '';

    if (active) {
        if (permissions.active) {
            status += '<span class="switch switch-icon">\
            <label>\
                <input value="1" onchange="updateStatus(`'+ status_url + '`, `' + id + '`, $(this));" type="checkbox" checked="checked" name="select">\
                <span></span>\
            </label>\
            </span>';
        } else {
            status += '<span class="switch switch-icon">\
            <label>\
                <input disabled type="checkbox" checked="checked" name="select">\
                <span></span>\
            </label>\
            </span>';
        }

    } else {

        status += '<span class="switch switch-icon">\
        <label>\
            <input value="1" onchange="updateStatus(`'+ status_url + '`, `' + id + '`, $(this));" type="checkbox" name="select">\
            <span></span>\
        </label>\
        </span>';
    }

    return status;
}

function createWarehouse($route) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {
            setCreateData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(Validation);
        }
    });
}

function setCreateData(response) {
    let cities = response.data.cities;
    let cities_options = '<option value="">Select A City</option>';

    Object.entries(cities).forEach(function(value, index) {
        cities_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    $("#add_warehouse_cities").html(cities_options);
    let image = asset_url +'assets/media/new_logo.png';

    $("#add_warehouse_image").css('background-image', "url(" + image + ")");
}

function editRow(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_warehouse").modal("show");
            setEditData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setEditData(response) {
    let warehouse = response.data.warehouse;

    $("#modal_edit_warehouse_form").attr("action", route('admin.warehouse.update', {id: warehouse.id}));
    let cities = response.data.cities;
    let cities_options = '<option value="">Select A City</option>';
    Object.entries(cities).forEach(function(value, index) {
        cities_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });
    $("#edit_warehouse_cities").html(cities_options);
    $("#edit_name").val(warehouse.name);
    $("#edit_manager_name").val(warehouse.manager_name);
    $("#edit_manager_phone").val(warehouse.manager_phone);
    $("#edit_address").val(warehouse.address);
    $("#edit_google_map").val(warehouse.google_map);
    let image = asset_url +'assets/media/new_logo.png';
    if (warehouse.image_src != null) {
        image = asset_url +'storage/warehouse_logo/'+ warehouse.image_src;
    }
    $("#edit_warehouse_image").css('background-image', "url(" + image + ")");
    $("#edit_warehouse_cities").val(warehouse.city_id).change();
    $("#modal_edit_warehouse_form").attr("action", action);
    $("#edit_name").val(warehouse.name);
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {

        let filters = {
            delete: '',
            name: $("#search_name").val(),
            manager_name: $("#search_manager_name").val(),
            manager_phone: $("#search_manager_phone").val(),
            status: $("#search_status").val(),
            city: $("#search_city").val(),
            created_at: $('#date_range').val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function () {
        let filters = {
            delete: '',
            name: '',
            manager_name: '',
            manager_phone: '',
            status: '',
            city: '',
            created_at: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    let cities = filter_values.cities;
    let status = filter_values.status;

    let city_options = '<option value="" select>Select A City</option>';
    let status_options = '<option value="" select>All</option>';

    Object.entries(status).forEach(function(value, index) {
        status_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    Object.entries(cities).forEach(function(value, index) {
        city_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    $("#search_city").html(city_options);
    $("#search_status").html(status_options);

    $("#search_name").val(active_filters.name);
    $("#search_manager_name").val(active_filters.manager_name);
    $("#search_manager_phone").val(active_filters.manager_phone);
    $("#search_address").val(active_filters.address);
    $("#date_range").val(active_filters.created_at);

    $("#search_status").val(active_filters.status);
    $("#search_city").val(active_filters.city);

    hideShowAdvanceFilters(active_filters);
}

function hideShowAdvanceFilters(active_filters) {
    if ((typeof active_filters.city_id !== 'undefined' && active_filters.city_id != '')
        || (typeof active_filters.created_at !== 'undefined' && active_filters.created_at != '')) {

        $(".advance-filters").show();
        $(".advance-arrow").addClass("fa fa-caret-down");
    }
}

jQuery(document).ready( function () {
    $("#date_range").val();
})