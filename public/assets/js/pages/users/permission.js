
var table_url = route('admin.permissions.datatable');

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
    }, {
        field: 'title',
        title: 'Title',
        width: 'auto',
    }, {
        field: 'name',
        title: 'Name',
        width: 300,
    }, {
        field: 'parent',
        title: 'Parent Permission',
        width: 300,
    },  {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 80,
        overflow: 'visible',
        autoHide: false,
    }];

function createPermission($route) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
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
            search: $("#search_search").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            search: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}
