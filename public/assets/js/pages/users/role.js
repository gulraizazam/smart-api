
var table_url = route('admin.roles.datatable');

var table_columns = [ {
    field: 'id',
    sortable: false,
    width: 'auto',
    title: renderCheckbox(),
        template: function (data) {
            let id = data.id;
            return childCheckbox(data);
        }
    }, {
        field: 'name',
        title: 'Name',
        width: 700,
    }, {
        field: 'commission',
        title: 'Commission',
        width: 200,
        template: function (data) {
            return data.commission + ' %';
        }
    },  {
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

    let csrf = $('meta[name="csrf-token"]').attr('content');
    let url = route('admin.roles.edit', {id: id});
    let delete_url = route('admin.roles.destroy', {id: id});
    let duplicate_url = route('admin.roles.duplicate', {id: id});

    if (permissions.edit || permissions.delete || permissions.duplicate) {
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
                    <a href="'+url+'" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
        }
        if (permissions.duplicate) {
            actions += '<li class="navi-item">\
                    <a href="'+duplicate_url+'" class="navi-link">\
                        <span class="navi-icon"><i class="la la-copy"></i></span>\
                        <span class="navi-text">Duplicate</span>\
                    </a>\
                </li>';
        }
        if (permissions.delete) {
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">\
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
