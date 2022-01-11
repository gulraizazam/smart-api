
var table_url = route('admin.locations.datatable');

var table_columns = [
     {
        field: 'name',
        title: 'Name',
        width: 'auto',
    },{
        field: 'fdo_name',
        title: 'FDO Name',
        width: 'auto',
    },{
        field: 'fdo_phone',
        title: 'FDO Phone',
        width: 'auto',
    },{
        field: 'address',
        title: 'Address',
        width: 'auto',
    },{
        field: 'city',
        title: 'City',
        width: 'auto',
    },{
        field: 'region',
        title: 'Region',
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
    }, {
        field: 'status',
        title: 'status',
        width: 'auto',
        template: function (data) {
            let status_url = route('admin.locations.status');
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
        return '';

        $("#modal_add_locations").modal("show");

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


        let town = response.data.town;
        let cities = response.data.cities;

        let action = route('admin.locations.update', {id: town.id});
        $("#modal_locations_form").attr("action", action);

        let options = '<option value="">Select</option>';

        Object.entries(cities).forEach(function(value, index) {

            options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        $("#town_city_id").html(options);

        $("#town_name").val(town.name);
        $("#town_city_id").val(town.city_id);

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
                create_from: $("#created_from").val(),
                create_to: $("#created_to").val(),
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
                create_from: '',
                create_to: '',
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

        $("#search_status").val(active_filters.status);
        $("#search_city").val(active_filters.city_id);
        $("#service_region").val(active_filters.service_id);
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

    Object.values(cities).forEach(function(value, index) {

        cities_options += '<option value="'+value[0]+'">'+value+'</option>';
    });

    $("#add_location_cities").html(cities_options);

    let service_options = makeServiceOptions(response);

    $("#add_location_services").html(service_options);
}

function makeServiceOptions(response) {

    let services = response.data.services;
    let service_options = '<option value="">Select</option>';

    let tmp_id = '';
    let id = 0;
    let val = 'Select';

    Object.values(services).forEach(function(value, index) {
        if (value.id == 0) {
            return;
        }


        if(value.id < 0) {
            tmp_id = (value.id * -1);
            id = value.id * -1;
            val = '<b>'+value.name ?? ''+'</b>';
        } else {
            tmp_id = (value.id * 1);
            id = value.id ;
            val = value.name ?? '';
        }

        //in_array($tmp_id, $ServiceLocations)

        service_options += '<option value="'+id+'">'+val+'</option>';
    });

    return service_options;
}
