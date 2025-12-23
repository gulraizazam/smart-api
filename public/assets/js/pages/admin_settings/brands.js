
var table_url = route('admin.brands.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: '40',
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
        field: 'status',
        title: 'status',
        width: 80,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.brands.status');
            return statusesBrand(data, status_url, true);
        }
    },
    {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 'auto',
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }
];


function actions(data) {
    let id = data.id;

    let csrf = $('meta[name="csrf-token"]').attr('content');
    let url = route('admin.brands.edit', {id: id});
    let delete_url = route('admin.brands.destroy', {id: id});

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
                        <a href="javascript:void(0);" onclick="editRow(`'+url+'`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
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
function statusesBrand(data, status_url, is_column_name_change = false) {

    let id = data.id;

    let active = is_column_name_change == false ? data.active : data.status;
    let status = '';

    if (active) {
        if (permissions.b_active) {
            status += '<span class="switch switch-icon">\
            <label>\
                <input value="1" onchange="updateStatus(`'+ status_url + '`, `' + id + '`, $(this));" type="checkbox" checked="checked" name="select">\
                <span></span>\
            </label>\
            </span>';
        }
        else{
            status += '<span class="switch switch-icon">\
            <label>\
                <input disabled type="checkbox" checked="checked" name="select">\
                <span></span>\
            </label>\
            </span>';
        }

    } else {
        if (permissions.b_active) {
            status += '<span class="switch switch-icon">\
            <label>\
                <input value="1" onchange="updateStatus(`'+ status_url + '`, `' + id + '`, $(this));" type="checkbox" name="select">\
                <span></span>\
            </label>\
            </span>';
        }else{
            status += '<span class="switch switch-icon">\
            <label>\
                <input disabled type="checkbox"  name="select">\
                <span></span>\
            </label>\
            </span>';
        }
    }

    return status;
}
function editRow(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_brands").modal("show");
            setEditData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });


}

function setEditData(response) {
    let brand = response.data;
    let action = route('admin.brands.update', {id: brand.id});
    $("#modal_edit_brands_form").attr("action", action);
    $("#edit_brands_name").val(brand.name);
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
        }
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            name: '',
            status: '',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    $("#search_name").val(active_filters.name);
}
