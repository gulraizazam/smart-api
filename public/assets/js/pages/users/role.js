
var table_url = route('admin.roles.datatable');

var table_columns = [ {
    field: 'id',
    sortable: false,
    width: 25,
    title: '<th data-field="RecordID" class="datatable-cell-center datatable-cell datatable-cell-check"><span style="width: 20px;"><label class="checkbox checkbox-single checkbox-all"><input class="select-all-checkboxes" type="checkbox">&nbsp;<span></span></label></span></th>',
        template: function (data) {
            let id = data.id;
            return '<th data-field="RecordID" class="datatable-cell-center datatable-cell datatable-cell-check"><span style="width: 20px;"><label class="checkbox checkbox-single checkbox-all"><input value="'+id+'" class="table-checkboxes" type="checkbox">&nbsp;<span></span></label></span></th>';
        }
    }, {
        field: 'name',
        title: 'Name',
        width: 700,
    }, {
        field: 'commission',
        title: 'Commission',
        width: 200,
    },  {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 80,
        overflow: 'visible',
        autoHide: false,
    }];


function editRow( id, modal) {

    $(modal).modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.permissions.edit', {id: id}),
        type: "GET",
        cache: false,
        success: function (response) {
            $("#permission-create").html(response);
            reInitSelect2("#kt_select2_8", "Select an Parent Group");
            reInitValidation(KTPermissionValidation);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(KTPermissionValidation);
        }
    });


}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            commission: $("#search_commission").val(),
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
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}
