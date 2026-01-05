/**
 * User Types Module - Optimized JavaScript
 * Uses API endpoints for all CRUD operations
 */

var table_url = '/api/user_types/datatable';

var table_columns = [
    {
        field: 'name',
        title: 'Name',
        width: 600,
    },
    {
        field: 'type',
        title: 'Type',
        width: 'auto',
    },
    {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 80,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return renderActions(data);
        }
    }
];

/**
 * Render action buttons for datatable row
 */
function renderActions(data) {
    if (!permissions.edit) {
        return '';
    }

    return '<a href="javascript:void(0);" onclick="editRow(' + data.id + ')" class="btn btn-sm btn-primary">' +
        '<span class="navi-icon"><i class="la la-pencil"></i></span>' +
        '<span class="navi-text">Edit</span>' +
        '</a>';
}

/**
 * Open create user type modal
 */
function createUserType() {
    $("#modal_add_user_type").modal("show");
    resetCreateForm();

    $.ajax({
        headers: getAjaxHeaders(),
        url: '/api/user_types/create',
        type: 'GET',
        cache: false,
        success: function (response) {
            if (response.status) {
                populateCreateTypeOptions(response.data.types);
            } else {
                toastr.error(response.message);
            }
        },
        error: function (xhr) {
            errorMessage(xhr);
        }
    });
}

/**
 * Reset create form fields
 */
function resetCreateForm() {
    $('#user_type_add_form')[0].reset();
    $('#user_type_add_field').val('').trigger('change');
}

/**
 * Populate type options for create form
 */
function populateCreateTypeOptions(types) {
    let options = '<option value="">Select</option>';

    if (Array.isArray(types)) {
        types.forEach(function (type) {
            options += '<option value="' + type.name + '">' + type.name + '</option>';
        });
    } else {
        Object.entries(types).forEach(function ([key, value]) {
            let typeName = typeof value === 'object' ? value.name : value;
            options += '<option value="' + typeName + '">' + typeName + '</option>';
        });
    }

    $('#user_type_add_field').html(options);
    reInitSelect2('#user_type_add_field', '');
}

/**
 * Open edit user type modal
 */
function editRow(id) {
    $("#modal_edit_user_type").modal("show");

    $.ajax({
        headers: getAjaxHeaders(),
        url: '/api/user_types/' + id + '/edit',
        type: 'GET',
        cache: false,
        success: function (response) {
            if (response.status) {
                populateEditForm(response.data);
            } else {
                toastr.error(response.message);
                $("#modal_edit_user_type").modal("hide");
            }
        },
        error: function (xhr) {
            errorMessage(xhr);
            $("#modal_edit_user_type").modal("hide");
        }
    });
}

/**
 * Populate edit form with data
 */
function populateEditForm(data) {
    let types = data.types;
    let usertype = data.usertype;

    // Set form action URL
    $('#modal_user_type_form').attr('action', '/api/user_types/' + usertype.id);

    // Populate type dropdown
    let options = '<option value="">Select</option>';
    Object.entries(types).forEach(function ([key, value]) {
        options += '<option value="' + key + '">' + value + '</option>';
    });

    $('#user_type').html(options);
    $('#user_type_name').val(usertype.name);
    $('#user_type').val(usertype.type);

    reInitSelect2('#user_type', '');
}

/**
 * Get common AJAX headers
 */
function getAjaxHeaders() {
    return {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        'Authorization': 'Bearer ' + (localStorage.getItem('token') || ''),
        'Accept': 'application/json'
    };
}

/**
 * Apply filters to datatable
 */
function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            name: $('#search_name').val().trim(),
            type: $('#search_type').val().trim(),
            filter: 'filter',
        };
        datatable.search(filters, 'search');
    });
}

/**
 * Reset all filters
 */
function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function () {
        // Clear input fields
        $('#search_name').val('');
        $('#search_type').val('');

        let filters = {
            delete: '',
            name: '',
            type: '',
            filter: 'filter_cancel',
        };
        datatable.search(filters, 'search');
    });
}
