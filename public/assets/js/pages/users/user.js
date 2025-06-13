
var table_url = route('admin.users.datatable');
var Clonepager = "";
var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 80,
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    },
    {
        field: 'name',
        title: 'Name',
        width: 80,
    }, {
        field: 'email',
        title: 'Email',
        width: 80,
    }, {
        field: 'phone',
        title: 'Phone',
        width: 80,
    }, {
        field: 'gender',
        title: 'Gender',
        width: 80,
    }, {
        field: 'commission',
        title: 'Commission',
        width: 100,
    }, {
        field: 'locations',
        title: 'centre',
        width: 'auto',
        sortable: false,
        template: function (data) {

            let locations = '';

            if (data.locations.length > 0) {

                for (let i = 0; i < data.locations.length; i++) {
                    locations += '<span><span class="label label-lg font-weight-bold label-light-info label-inline mb-2">' + data.locations[i] + '</span></span>';
                }

            }

            return locations;
        }
    }, {
        field: 'roles',
        title: 'roles',
        width: 'auto',
        sortable: false,
        template: function (data) {
            let roles = '';

            if (data.roles.length > 0) {

                for (let i = 0; i < data.roles.length; i++) {
                    roles += '<span><span class="label label-lg font-weight-bold label-light-info label-inline">' + data.roles[i] + '</span></span>&nbsp;';
                }

            }

            return roles;
        }
    }, {
        field: 'status',
        title: 'status',
        width: 90,
        template: function (data) {
            let status_url = route('admin.users.status');
            return statuses(data, status_url);
        }
    }, {
        field: 'created_at',
        title: 'created at',
        width: 'auto',
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 180,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];


function actions(data) {

    let id = data.id;
    let url = route('admin.users.destroy', { id: id });

    let csrf = $('meta[name="csrf-token"]').attr('content');

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
                    <a href="javascript:void(0);" onclick="editRow('+ id + ')" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
        }
        if (permissions.change_password) {
            actions += '<li class="navi-item">\
                <a href="javascript:void(0);"  onClick="changePassword('+ id + ');" class="navi-link">\
                    <span class="navi-icon"><i class="la la-key"></i></span>\
                    <span class="navi-text">Change Password</span>\
                </a>\
            </li>';
        }
        if (permissions.delete) {
            actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="deleteRow(`'+ url + '`);" class="navi-link">\
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


function editRow(id) {

    $("#modal_edit_user").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.users.edit', { id: id }),
        type: "GET",
        cache: false,
        success: function (response) {

            setEditData(response);

            reInitSelect2(".select2", "");
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(UserValidation);
        }
    });


}

function changePassword(id) {
    $("#change_modal").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.users.change_password', { id: id }),
        type: "GET",
        cache: false,
        success: function (response) {
            $("#change_password").html(response);
            reInitSelect2(".select2", "");
            reInitValidation(PasswordValidation);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(PasswordValidation);
        }
    });
}

function createUsers($route) {

    $(".pass-msg").remove();
    $("#add_user_password").removeClass("is-invalid");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setCreateData(response);

            reInitSelect2(".select2", "");
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(UserValidation);
        }
    });
}

function setCreateData(response) {


    let roles = response.data.roles;
    let locations = response.data.locations;
    let warehouse = response.data.warehouse;
    let roles_options = '<option value="">Select</option>';
    let location_options = '<option value="">Select</option>';
    let warehouse_options = '<option value="">Select</option>';

    for (let i = 0; i < roles.length; i++) {

        roles_options += '<option value="' + roles[i].id + '">' + roles[i].name + '</option>';
    }


    Object.values(locations).forEach(function (value, index) {
        location_options += '<option value="">Select</option>\
            <optgroup label="'+ value.name + '">';
        Object.values(value.children).forEach(function (child, index) {
            location_options += '<option value="' + child.id + '">' + child.name + '</option>';
        });

        location_options += '</optgroup>';
    });
    warehouse_options += '<option value="all">All Warehouse</option>';
    Object.values(warehouse).forEach(function (value, index) {
        warehouse_options += '<option value="' + value.id + '">' + value.name + '</option>';
    });
    $("#add_user_roles").html(roles_options);
    $("#add_user_centers").html(location_options);
    $("#add_user_warehouse").html(warehouse_options);
}

function setEditData(response) {

    let user = response.data.user;

    let user_roles = response.data.user_roles;
    let user_has_locations = response.data.user_has_locations;
    let warehouse = response.data.warehouse;
    let user_has_warehouse = response.data.user_has_warehouse;


    $("#modal_edit_user_form").attr("action", route('admin.users.update', { id: user.id }));


    let roles = response.data.roles;
    let locations = response.data.locations;
    let roles_options = '<option value="">Select</option>';
    let location_options = '<option value="">Select</option>';
    let warehouse_options = '<option value="">Select</option>';

    Object.entries(roles).forEach(function (role, index) {

        roles_options += '<option value="' + role[0] + '">' + role[1] + '</option>';
    });


    Object.values(locations).forEach(function (value, index) {
        location_options += '<optgroup label="' + value.name + '">';
        Object.values(value.children).forEach(function (child, index) {

            location_options += '<option value="' + child.id + '">' + child.name + '</option>';
        });
        location_options += '</optgroup>';
    });

    warehouse_options += '<option value="all">All Warehouse</option>';
    Object.values(warehouse).forEach(function (value, index) {
        warehouse_options += '<option value="' + value.id + '">' + value.name + '</option>';
    });

    $("#edit_user_roles").html(roles_options);
    $("#edit_user_centers").html(location_options);
    $("#edit_user_warehouse").html(warehouse_options);

    $("#edit_user_name").val(user.name);
    $("#edit_user_email").val(user.email);
    $("#edit_user_gender").val(user.gender);
    $("#edit_user_commission").val(user.commission);

    $("#edit_old_user_phone").val(user.phone);

    if (permissions.contact) {
        $("#edit_user_phone").val(user.phone);
    } else {
        $("#edit_user_phone").val("***********").attr("readonly", true);
    }

    $('#edit_user_roles').val(user_roles).change();

    $("#edit_user_centers").val(user_has_locations).change();
    if(user_has_warehouse.length == warehouse.length){
        $("#edit_user_warehouse").val(['all']).change();
    } else {
        $("#edit_user_warehouse").val(user_has_warehouse).change();
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function () {

        let filters = {
            delete: '',
            name: $("#search_name").val(),
            email: $("#search_email").val(),
            phone: $("#search_phone").val(),
            location_id: $("#search_center").val(),
            role_id: $("#search_role").val(),
            gender: $("#search_gender").val(),
            commission: $("#search_commission").val(),
            status: $("#search_status").val(),
            created_at: $("#date_range").val(),
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
            commission: '',
            email: '',
            phone: '',
            location_id: '',
            role_id: '',
            gender: '',
            status: '',
            date_range: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    let locations = filter_values.locations;
    let roles = filter_values.roles;
    let status = filter_values.status;

    let location_options = '<option value="">Select</option>';
    let role_options = '<option value="">All</option>';
    let status_options = '<option value="">All</option>';

    Object.entries(status).forEach(function (value, index) {
        status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    Object.entries(locations).forEach(function (value, index) {

        location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    Object.entries(roles).forEach(function (value, index) {

        role_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    $("#search_role").html(role_options);
    $("#search_center").html(location_options);
    $("#search_status").html(status_options);

    $("#search_name").val(active_filters.name);
    $("#search_phone").val(active_filters.phone);
    $("#search_gender").val(active_filters.gender);
    $("#search_commission").val(active_filters.commission);
    $("#search_email").val(active_filters.email);
    $("#date_range").val(active_filters.created_at);

    $("#search_role").val(active_filters.role_id);
    $("#search_center").val(active_filters.location_id);
    $("#search_status").val(active_filters.status);

    hideShowAdvanceFilters(active_filters);

    getUserCentre();
}

function hideShowAdvanceFilters(active_filters) {
    if ((typeof active_filters.location_id !== 'undefined' && active_filters.location_id != '')
        || (typeof active_filters.gender !== 'undefined' && active_filters.gender != '')
        || (typeof active_filters.commission !== 'undefined' && active_filters.commission != '')
        || (typeof active_filters.email !== 'undefined' && active_filters.email != '')
        || (typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
    ) {

        $(".advance-filters").show();
        $(".advance-arrow").addClass("fa fa-caret-down");
    }
}

jQuery(document).ready(function () {

    $("#add_user_password").keyup(function () {
        $(".pass-msg").remove();
        $("#add_user_password").removeClass("is-invalid");
    });
    $("#date_range").val("");
})
