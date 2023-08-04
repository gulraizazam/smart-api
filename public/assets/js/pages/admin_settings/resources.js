
var table_url = route('admin.resources.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 70,
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    }, {
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 'auto',
    },{
        field: 'resource_types.name',
        title: 'Resource Type',
        sortable: false,
        width: 'auto',
    },{
        field: 'location.name',
        title: 'Centre',
        sortable: false,
        width: 'auto',
        template: function (data) {
            let cityName = '';
            if (typeof data.location.city !== 'undefined') {
                cityName = data.location.city.name + '-';
            }
           return cityName + data.location.name;
        }
    },{
        field: 'machine_type.name',
        title: 'Machine Type',
        sortable: false,
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
    }, {
        field: 'status',
        title: 'status',
        width: 70,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.resources.status');
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

        let url = route('admin.resources.edit', {id: id});
        let delete_url = route('admin.resources.destroy', {id: id});

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

    $("#modal_edit_resources").modal("show");

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

            reInitValidation(EditValidation);
        }
    });


}

function setEditData(response) {

    try {

        let resource = response.data.resource;
        let machine_types = response.data.machine_types;
        let resource_types = response.data.resource_types;
        let locations = response.data.locations;

        $("#modal_edit_resources_form").attr("action", route('admin.resources.update', {id: resource.id}));


        let machine_options = '<option value="">Select</option>';
        let location_options = '<option value="">Select</option>';
        let resource_options = '<option value="">Select</option>';

        Object.entries(machine_types).forEach(function (value, index) {
            machine_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(locations).forEach(function (value, index) {
            location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(resource_types).forEach(function (value, index) {
            resource_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        $("#edit_machine_type_id").html(machine_options);
        $("#edit_location_id").html(location_options);
        $("#edit_resource_type_id").html(resource_options);

        $("#edit_name").val(resource.name);
        $("#edit_location_id").val(resource.location_id);
        $("#edit_machine_type_id").val(resource.machine_type_id);
        $("#edit_resource_type_id").val(resource.resource_type_id);

    } catch (error) {
        showException(error);
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            resource_type_id: $("#search_resource_type_id").val(),
            location_id: $("#search_location_id").val(),
            machine_type_id: $("#search_machine_type_id").val(),
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
            resource_type_id: '',
            location_id: '',
            machine_type_id: '',
            created_at: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {
    try {

        let status = filter_values.status;
        let locations = filter_values.locations;
        let resource_types = filter_values.resource_types;
        let machines = filter_values.machines;

        let status_options = '<option value="">All</option>';
        let resource_options = '<option value="">All</option>';
        let location_options = '<option value="">All</option>';
        let machines_options = '<option value="">All</option>';

        Object.entries(status).forEach(function (value, index) {
            status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(locations).forEach(function (value, index) {
            location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(resource_types).forEach(function (value, index) {
            resource_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(machines).forEach(function (value, index) {
            machines_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });


        $("#search_status").html(status_options);
        $("#search_resource_type_id").html(resource_options);
        $("#search_location_id").html(location_options);
        $("#search_machine_type_id").html(machines_options);

        $("#search_name").val(active_filters.name);
        $("#search_resource_type_id").val(active_filters.resource_type_id);
        $("#search_location_id").val(active_filters.location_id);
        $("#search_machine_type_id").val(active_filters.machine_type_id);
        $("#date_range").val(active_filters.created_at);
        $("#search_status").val(active_filters.status);

        hideShowAdvanceFilters(active_filters);

        getUserCentre();

    } catch (err) {
        showException(error);
    }
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
            reInitValidation(AddValidation);
        }
    });
}

function setCreateData(response) {

    try {

        let machine_types = response.data.machine_types;
        let resource_types = response.data.resource_types;
        let locations = response.data.locations;

        let machine_options = '<option value="">Select</option>';
        let location_options = '<option value="">Select</option>';
        let resource_options = '<option value="">Select</option>';

        Object.entries(machine_types).forEach(function (value, index) {
            machine_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(locations).forEach(function (value, index) {
            location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(resource_types).forEach(function (value, index) {
            resource_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        $("#add_machine_type_id").html(machine_options);
        $("#add_location_id").html(location_options);
        $("#add_resource_type_id").html(resource_options);

    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
        || (typeof active_filters.status !== 'undefined' && active_filters.status != '')) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}

jQuery(document).ready( function () {
    $("#date_range").val("");
})
