var table_url = route('admin.user_operator_settings.datatable');

var table_columns = [
    {
        field: 'operator_name',
        title: 'Operator',
        width: 'auto',
    },
    {
        field: 'username',
        title: 'Username',
        width: 'auto',
    },
    {
        field: 'password',
        title: 'password',
        width: 'auto',
    },
    {
        field: 'mask',
        title: 'mask',
        width: 'auto',
    },
    {
        field: 'test_mode',
        title: 'Enable Test Mode',
        width: 'auto',
    },
    {
        field: 'url',
        title: 'Url',
        width: 'auto',
    },
    {
        field: 'string_1',
        title: 'custom Field 1',
        width: 'auto',
    },
    {
        field: 'string_2',
        title: 'custom Field 2',
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
            return actions(data);
        }
    }];

function actions(data) {

    let id = data.id;

    if (permissions.edit) {
        return '<a href="javascript:void(0);" onclick="editRow(' + id + ')" class="btn btn-sm btn-primary">\
        <span class="navi-icon"><i class="la la-pencil"></i></span>\
        <span class="navi-text">Edit</span>\
        </a>';
    }

    return '';
}

function editRow(id, modal) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.user_operator_settings.edit', {id: id}),
        type: "GET",
        cache: false,
        success: function (response) {
            // $("#user_type_edit").html(response);
            if (response.status) {
                $('.fv-help-block').remove();
                $('.form-control').removeClass('is-invalid');
                $("#change_modal").modal("show");
                $('#modal_user_operator_settings_form').attr('action', route('admin.user_operator_settings.update', response.data.id));
                $('#form_url').val(response.data.url);
                $('#form_username').val(response.data.username);
                $('#form_password').val('********');
                $('#form_mask').val(response.data.mask);
                $('#form_test_mode').val(response.data.test_mode);
                $('#form_string_1').val(response.data.string_1);
                $('#form_string_2').val(response.data.string_2);
            } else {
                toastr.error(response.message);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            operator_name: $("#operator_name").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function () {
        let filters = {
            delete: '',
            operator_name: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}
