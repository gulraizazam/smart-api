
var table_url = route('admin.services.datatable');
let changePages = 1000;
var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 'auto',
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    }, {
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 300,
        template: function (data) {
            if (data.parent_id == 0) {
                return '<b class="text text-dark" style="font-size: 12px;">'+data.name+'</b>';
            }
            return '<span class="ml-3">'+data.name+'</span>';
        }
    },{
        field: 'duration',
        title: 'Duration',
        sortable: false,
        width: 'auto',
        template: function (data) {
            if (typeof data.price !== 'undefined') {
                return '<span>'+data.duration+' mins</span>';
            }
            return '00.00';
        }
    },{
        field: 'color',
        title: 'Color',
        sortable: false,
        width: 'auto',
        template: function (data) {
            return '<span class="badge" style="background-color: '+data.color+' !important; color: #fff; font-size: 12px;">'+data.color+'</span>';
        }
    },{
        field: 'price',
        title: 'Price',
        sortable: false,
        width: 'auto',
        template: function (data) {
            if (data.slug == 'all') {
                return '-';
            }
            if (typeof data.price !== 'undefined') {
                return '<span>'+data.price.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")+'</span>';
            } else {
                return '00.00';
            }
        }
    },{
        field: 'complimentory',
        title: 'Complimentory',
        sortable: false,
        width: 'auto',
        template: function (data) {
            if (data.parent_id == 0) {
                return '-';
            }
            if (typeof data.complimentory !== 'undefined') {
                return '<span>'+data.complimentory == 0 ? 'Yes' : 'No'+'</span>';
            }
            return 'No';
        }
    }, {
        field: 'status',
        title: 'status',
        width: 'auto',
        sortable: false,
        template: function (data) {
            let status_url = route('admin.services.status');
            return statuses(data, status_url);
        }
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 80,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];


function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let csrf = $('meta[name="csrf-token"]').attr('content');
        let url = route('admin.locations.edit', {id: id});
        let delete_url = route('admin.locations.destroy', {id: id});

        if (permissions.edit && permissions.delete) {
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
                    <a href="javascript:void(0);" onclick="editRow(`' + url + '`);" class="navi-link">\
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
    }
    return '';
}

function editRow(url) {

    $("#modal_edit_locations").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {

            setEditData(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(TownValidation);
        }
    });


}

function setEditData(response) {

    let location = response.data.location;

    $("#modal_edit_location_form").attr("action", route('admin.locations.update', {id: location.id}));

    let service_location = response.data.service_location;

    let cities = response.data.cities;
    let cities_options = '<option value="">Select A City</option>';

    Object.entries(cities).forEach(function(value, index) {
        cities_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    $("#edit_location_cities").html(cities_options);

    let service_options = makeServiceOptions(response);

    $("#edit_location_services").html(service_options);

    $("#edit_name").val(location.name);
    $("#edit_fdo_name").val(location.fdo_name);
    $("#edit_fdo_phone").val(location.fdo_phone);
    $("#edit_address").val(location.address);
    $("#edit_google_map").val(location.google_map);
    $("#edit_tax_percentage").val(location.tax_percentage);
    $("#edit_ntn").val(location.ntn);
    $("#edit_stn").val(location.stn);
    let image = asset_url +'storage/centre_logo/'+ location.image_src;
    $("#edit-image").css('background-image', "url(" + image + ")");
    $("#edit_location_cities").val(location.city_id).change();
    $("#edit_location_services").val(service_location).change();




}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            fdo_name: $("#search_fdo_name").val(),
            fdo_phone: $("#search_fdo_phone").val(),
            address: $("#search_address").val(),
            city_id: $("#search_city").val(),
            region_id: $("#search_region").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            status: $("#search_status").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            name: '',
            fdo_name: '',
            fdo_phone: '',
            address: '',
            city_id: '',
            region_id: '',
            created_from: '',
            created_to: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    let status = filter_values.status;

    let status_options = '<option value="">All</option>';

    Object.entries(status).forEach(function(value, index) {
        status_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });


    $("#search_status").html(status_options);

    $("#search_name").val(active_filters.name);
    $("#search_fdo_name").val(active_filters.fdo_name);
    $("#search_fdo_phone").val(active_filters.fdo_phone);
    $("#search_address").val(active_filters.address);
    $("#search_created_from").val(active_filters.created_from);
    $("#search_created_to").val(active_filters.created_to);

    $("#search_status").val(active_filters.status);
    $("#search_city").val(active_filters.city_id);
    $("#service_region").val(active_filters.service_id);

    hideShowAdvanceFilters(active_filters);
}

function createService($route) {

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


    let services = response.data.parent_services;
    let durations = response.data.durations;
    let services_options = '<option value="">Parent Service</option>';
    let duration_options = '<option value="">Select a Duration</option>';

    Object.entries(services).forEach(function(value, index) {
        services_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    Object.entries(durations).forEach(function(value, index) {
        duration_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    $("#add_service_duration").html(duration_options);

    $("#add_services").html(service_options);
}

function hideShowAdvanceFilters(active_filters) {
    if (active_filters.city_id != ''
        || active_filters.region_id != ''
        || active_filters.address != ''
        || active_filters.email != ''
        || active_filters.created_from != ''
        || active_filters.created_to != '') {

        $(".advance-filters").show();
        $(".advance-arrow").addClass("fa fa-caret-down");
    }
}
