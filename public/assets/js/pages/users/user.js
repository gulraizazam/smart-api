
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
        field: 'location',
        title: 'centre',
        width: 'auto',
        sortable: false
    }, {
        field: 'roles',
        title: 'roles',
        width: 'auto',
        sortable: false
    }, {
        field: 'created_at',
        title: 'created at',
        width: 'auto',
    }, {
        field: 'status',
        title: 'status',
        width: 'auto',
    },  {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 180,
        overflow: 'visible',
        autoHide: false,
    }];

function editRow(id, modal) {

    $(modal).modal("show");

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

function changePassword(id, modal) {
    $(modal).modal("show");

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
