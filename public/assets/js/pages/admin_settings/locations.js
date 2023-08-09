var table_url = route('admin.locations.datatable');
var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 30,
        title: renderCheckbox(),
        template: function (data) {
            let id = data.id;
            return childCheckbox(data);
        }
    },
     {
        field: 'name',
        title: 'Name',
         sortable: false,
        width: 'auto',
    },{
        field: 'fdo_name',
        title: 'FDO Name',
        sortable: false,
        width: 80,
    },{
        field: 'fdo_phone',
        title: 'FDO Phone',
        sortable: false,
        width: 100,
    },{
        field: 'address',
        title: 'Address',
        sortable: false,
        width: 'auto',
    },{
        field: 'city',
        title: 'City',
        sortable: false,
        width: 60,
    }, {
        field: 'created_at',
        title: 'Created At',
        width: 120,
    },{
        field: 'status',
        title: 'status',
        width: 60,
        template: function (data) {
            let status_url = route('admin.locations.status');
            return statuses(data, status_url);
        }
    }, {
        field: 'region',
        title: 'Region',
        sortable: false,
        width: 120,
    },{
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
    let image = asset_url +'assets/media/new_logo.png';
    if (location.image_src != '') {
        image = asset_url +'storage/centre_logo/'+ location.image_src;
    }
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
            created_at: $("#date_range").val(),
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
            created_at: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}
function setFilters(filter_values, active_filters) {

    let cities = filter_values.cities;
    let regions = filter_values.regions;
    let services = filter_values.services;
    let status = filter_values.status;

    let city_options = '<option value="">Select A City</option>';
    let region_options = '<option value="">Select A Region</option>';
    let services_options = '<option value="">Select A Service</option>';
    let status_options = '<option value="">All</option>';

    Object.entries(status).forEach(function(value, index) {
        status_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    Object.entries(cities).forEach(function(value, index) {

        city_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    Object.entries(regions).forEach(function(value, index) {

        region_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    Object.entries(services).forEach(function(value, index) {

        services_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    $("#search_city").html(city_options);
    $("#search_region").html(region_options);
    $("#service_region").html(region_options);
    $("#search_status").html(status_options);

    $("#search_name").val(active_filters.name);
    $("#search_fdo_name").val(active_filters.fdo_name);
    $("#search_fdo_phone").val(active_filters.fdo_phone);
    $("#search_address").val(active_filters.address);
    $("#date_range").val(active_filters.created_at);

    $("#search_status").val(active_filters.status);
    $("#search_city").val(active_filters.city_id);
    $("#search_region").val(active_filters.region_id);

    hideShowAdvanceFilters(active_filters);

    getUserCity();
}
function createCentre($route) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setCreateData(response);

            //reInitSelect2(".select2", "Select");
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

    $("#add_location_cities").html(cities_options);

    let service_options = makeServiceOptions(response);
    $("#add_location_services").html(service_options);

    let image = asset_url +'assets/media/new_logo.png';

    $("#add_center_image").css('background-image', "url(" + image + ")");
}
function makeServiceOptions(response) {

    let services = response.data.services;
    let service_value = '';
    let service_child_value = '';
    let service_options = '';

    Object.values(services).forEach(function (value, index) {
        service_value=value.name;
        if (service_value == 'All Services') {
            service_options += '<option value="' + value.id + '">' + service_value + '</option>';
        } else {
            service_options += '<option value="' + value.id + '">' + service_value + '</option>';
            Object.values(value.children).forEach(function (child, index) {
                service_child_value='\t&nbsp; \t&nbsp; \t&nbsp;'+child.name;
                service_options += '<option value="' + child.id + '">' + service_child_value + '</option>';
            });
        }
    });
    return service_options;
}
function hideShowAdvanceFilters(active_filters) {
    if ((typeof active_filters.city_id !== 'undefined' && active_filters.city_id != '')
        || (typeof active_filters.region_id !== 'undefined' && active_filters.region_id != '')
        || (typeof active_filters.address !== 'undefined' && active_filters.address != '')
        || (typeof active_filters.email !== 'undefined' && active_filters.email != '')
        || (typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')) {

        $(".advance-filters").show();
        $(".advance-arrow").addClass("fa fa-caret-down");
    }
}

jQuery(document).ready( function () {
    $("#date_range").val("");
})
