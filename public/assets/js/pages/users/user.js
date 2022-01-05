
var table_url = route('admin.users.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 25,
        title: '<th data-field="RecordID" class="datatable-cell-center datatable-cell datatable-cell-check"><span style="width: 20px;"><label class="checkbox checkbox-single checkbox-all"><input class="select-all-checkboxes" type="checkbox">&nbsp;<span></span></label></span></th>',
        template: function (data) {
            let id = data.id;
            return '<th data-field="RecordID" class="datatable-cell-center datatable-cell datatable-cell-check"><span style="width: 20px;"><label class="checkbox checkbox-single checkbox-all"><input value="'+id+'" class="table-checkboxes" type="checkbox">&nbsp;<span></span></label></span></th>';
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
        field: 'commission',
        title: 'Commission',
        width: 'auto',
    },{
        field: 'commission',
        title: 'Commission',
        width: 'auto',
    },{
        field: 'locations',
        title: 'centre',
        width: 'auto',
        sortable: false,
        template: function (data) {

            let locations = '';

            if (data.locations.length > 0) {

                for (let i = 0; i < data.locations.length; i++) {
                    locations += '<span><span class="label label-lg font-weight-bold label-light-info label-inline mb-2">'+data.locations[i]+'</span></span>';
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
                  roles += '<span><span class="label label-lg font-weight-bold label-light-info label-inline">'+data.roles[i]+'</span></span>&nbsp;';
              }

            }

            return roles;
        }
    }, {
        field: 'created_at',
        title: 'created at',
        width: 'auto',
    }, {
        field: 'status',
        title: 'status',
        width: 'auto',
        template: function (data) {
            return statuses(data);
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
    let url = route('admin.users.destroy', {id: id});

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

function statuses(data) {
    let csrf = $('meta[name="csrf-token"]').attr('content');
    let id = data.id;
    let inactive_url = route('admin.users.inactive', {id: data.id});
    let active_url = route('admin.users.active', {id: data.id});
    let status = '';
    if (data.active) {
        if (permissions.users_active) {
            status += '<button onclick="updateStatus(`'+inactive_url+'`);" class="btn btn-sm btn-primary" type="button">Active</button>';
        } else {
            status += '<span><span class="label label-lg font-weight-bold label-light-success label-inline">Active </span></span>';
        }

    } else {
        if (permissions.users_inactive) {
            status += '<button onclick="updateStatus(`'+active_url+'`);" class="btn btn-sm btn-warning" type="button">Inactive</button>';
        } else {
            status += '<span><span class="label label-lg font-weight-bold label-light-danger label-inline">Inactive</span> </span>';
        }
    }

    return status;
}

function editRow(id) {

    $("#modal_add_user").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.users.edit', {id: id}),
        type: "GET",
        cache: false,
        success: function (response) {
            $("#user-create").html(response);
            reInitSelect2(".select2", "");
            reInitValidation(UserValidation);
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
            $("#user-create").html(response);
            reInitSelect2(".select2", "");
            reInitValidation(UserValidation);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(UserValidation);
        }
    });
}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            email: $("#search_email").val(),
            phone: $("#search_phone").val(),
            location_id: $("#search_center").val(),
            role_id: $("#search_role").val(),
            gender: $("#search_gender").val(),
            commission: $("#search_commission").val(),
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
            commission: '',
            email: '',
            phone: '',
            location_id: '',
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
