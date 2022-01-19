
var table_url = route('admin.doctors.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 'auto',
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    },
    {
        field: 'name',
        title: 'Name',
        width: 'auto',
    }, {
        field: 'email',
        title: 'Email',
    }, {
        field: 'phone',
        title: 'Phone',
        width: 'auto',
    }, {
        field: 'gender',
        title: 'Gender',
        width: 'auto',
    }, {
        field: 'roles',
        title: 'roles',
        width: 'auto',
        sortable: false,
        template: function (data) {
            let roles = '';

            if (data.roles.length > 0) {

                for (let i = 0; i < data.roles.length; i++) {
                    roles += '<span><span class="label label-lg font-weight-bold label-light-info label-inline">'+data.roles[i]+'</span></span>&nbsp;';
                }

            }

            return roles;
        }
    }, {
        field: 'created',
        title: 'Created At',
        width: 'auto',
    }, {
        field: 'status',
        title: 'status',
        width: 'auto',
        template: function (data) {
            let status_url = route('admin.doctors.status');
            return statuses(data, status_url);
        }
    },  {
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
    let url = route('admin.doctors.destroy', {id: id});

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
                    <a href="javascript:void(0);" onclick="editRow('+id+')" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
        }
        if (permissions.change_password) {
            actions += '<li class="navi-item">\
                <a href="javascript:void(0);"  onClick="changePassword('+id+');" class="navi-link">\
                    <span class="navi-icon"><i class="la la-key"></i></span>\
                    <span class="navi-text">Change Password</span>\
                </a>\
            </li>';
        }
        if (permissions.delete) {
            actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="deleteRow(`'+url+'`);" class="navi-link">\
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
        url: route('admin.users.edit', {id: id}),
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
        url: route('admin.users.change_password', {id: id}),
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
    let roles_options = '<option value="">Select</option>';

    for (let i = 0; i< roles.length; i++) {
        roles_options += '<option value="'+roles[i].id+'">'+roles[i].name+'</option>';
    }
    $("#add_user_roles").html(roles_options);
}

function setEditData(response) {
    let user = response.data.user;
    let user_roles = response.data.user_roles;
    $("#modal_edit_user_form").attr("action", route('admin.doctors.update', {id: user.id}));

    let roles = response.data.roles;
    let roles_options = '<option value="">Select</option>';

    Object.entries(roles).forEach(function(role, index) {
        roles_options += '<option value="'+role[0]+'">'+role[1]+'</option>';
    });

    $("#edit_user_roles").html(roles_options);

    $("#edit_user_name").val(user.name);
    $("#edit_user_email").val(user.email);
    $("#edit_user_phone").val(user.phone);
    $("#edit_user_gender").val(user.gender);
    $("#edit_user_commission").val(user.commission);
    $('#edit_user_roles').val(user_roles).change();
}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            email: $("#search_email").val(),
            phone: $("#search_phone").val(),
            role_id: $("#search_role").val(),
            gender: $("#search_gender").val(),
            status: $("#search_status").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
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
            email: '',
            phone: '',
            role_id: '',
            gender: '',
            status: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    let genders = filter_values.gender_array;
    let roles = filter_values.roles;
    let status = filter_values.status;

    let location_options = '<option value="">Select</option>';
    let role_options = '<option value="">Select</option>';
    let status_options = '<option value="">All</option>';
    let gender_options = '<option value="">All</option>';

    Object.entries(status).forEach(function(value, index) {
        status_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    Object.entries(genders).forEach(function(value, index) {
        gender_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    Object.entries(roles).forEach(function(value, index) {

        role_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });
    // edit_user_gender
    $("#edit_user_gender").html(gender_options);

    $("#search_role").html(role_options);
    $("#search_center").html(location_options);
    $("#search_status").html(status_options);

    $("#search_name").val(active_filters.name);
    $("#search_phone").val(active_filters.phone);
    $("#search_gender").val(active_filters.gender);
    $("#search_commission").val(active_filters.commission);
    $("#search_email").val(active_filters.email);
    $("#search_created_from").val(active_filters.created_from);
    $("#search_created_to").val(active_filters.created_to);

    $("#search_role").val(active_filters.role_id);
    $("#search_status").val(active_filters.status);

    hideShowAdvanceFilters(active_filters);
}

function hideShowAdvanceFilters(active_filters) {
    if (active_filters.location_id != ''
        || active_filters.gender != ''
        || active_filters.commission != ''
        || active_filters.email != ''
        || active_filters.created_from != ''
        || active_filters.created_to != '') {

        $(".advance-filters").show();
        $(".advance-arrow").addClass("fa fa-caret-down");
    }
}
