
var table_url = route('admin.payment_modes.datatable');

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
        sortable: false,
    }, {
        field: 'payment_type',
        title: 'Featured',
        sortable: false,
    }, {
        field: 'type',
        title: 'User Type',
        width: 'auto',
        sortable: false,
    }, {
        field: 'status',
        title: 'status',
        width: 'auto',
        sortable: false,
        template: function (data) {
            let status_url = route('admin.payment_modes.status');
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
    let url = route('admin.payment_modes.destroy', {id: id});

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
        url: route('admin.payment_modes.edit', {id: id}),
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

function createUsers($route) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setCreateData(response, 'add_');

            reInitSelect2(".select2", "");
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(UserValidation);
        }
    });
}

function setCreateData(response, start_id) {


    let roles = response.data.roles;
    let locations = response.data.locations;
    let roles_options = '<option value="">Select</option>';
    let location_otions = '<option value="">Select</option>';

    for (let i = 0; i< roles.length; i++) {

        roles_options += '<option value="'+roles[i].id+'">'+roles[i].name+'</option>';
    };


    Object.values(locations).forEach(function(value, index) {

        location_otions = '<option value="">Select</option>\
            <optgroup label="'+value.name+'">';
        Object.values(value.children).forEach(function(child, index) {

            location_otions += '<option value="'+child.id+'">'+child.name+'</option>';
        });

        location_otions += '</optgroup>';
    });

    $("#add_user_roles").html(roles_options);
    $("#add_user_centers").html(location_otions);
}

function setEditData(response) {

    let payment_modes = response.data;
    $("#modal_edit_payment_modes_form").attr("action", route('admin.payment_modes.update', {id: payment_modes.id}));

    $("#edit_payment_modes_name").val(payment_modes.name);
    $('#edit_payment_mode_payment_type').val(payment_modes.payment_type).change();
    $('#edit_payment_mode_type').val(payment_modes.type).change();

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            type: $("#search_type").val(),
            payment_type: $("#search_payment_type").val(),
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
            type: '',
            payment_type: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}
