
var table_url = route('admin.user_types.datatable');

var table_columns = [
     {
        field: 'name',
        title: 'Name',
        width: 600,
    },{
        field: 'type',
        title: 'Type',
        width: 'auto',
    },  {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 70,
        overflow: 'visible',
        autoHide: false,
    }];

function editRow(id, modal) {

    $(modal).modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.user_types.edit', {id: id}),
        type: "GET",
        cache: false,
        success: function (response) {
            $("#user_type_edit").html(response);
            reInitSelect2(".select2", "");
            reInitValidation(UserTypeValidation);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(UserTypeValidation);
        }
    });


}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            type: $("#search_type").val(),
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
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}
