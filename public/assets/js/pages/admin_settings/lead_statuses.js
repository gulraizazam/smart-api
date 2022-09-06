var table_url = route('admin.lead_statuses.datatable');

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
    },
    {
        field: 'parent_id',
        title: 'Parent Group',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'is_junk',
        title: 'Default for Junk Leads',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'is_default',
        title: 'Default for Open Leads',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'is_arrived',
        title: 'Default for Arrived Leads',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'is_converted',
        title: 'Default for Converted Leads',
        width: 'auto',
        sortable: false,
    },{
        field: 'is_comment',
        title: 'Ask for comments',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'status',
        title: 'status',
        width: 'auto',
        sortable: false,
        template: function (data) {
            let status_url = route('admin.lead_statuses.status');
            return statuses(data, status_url);
        }
    }, {
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
    let url = route('admin.lead_statuses.edit', {id: id});
    let delete_url = route('admin.lead_statuses.destroy', {id: id});

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
                        <a href="javascript:void(0);" onclick="editRow(`' + url + '`);" class="navi-link">\
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

function editRow(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_lead_statuses").modal("show");
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(TownValidation);
        }
    });
}

function setEditData(response) {
    let lead_status = response.data.lead_statuse;
    let parentLeadStatuses = response.data.parentLeadStatuses;
    let action = route('admin.lead_statuses.update', {id: lead_status.id});

    $("#modal_edit_lead_statuses_form").attr("action", action);
    $("#edit_lead_statuses_name").val(lead_status.name);
    let parent_options = '<option value="">Parent Group</option>';

    Object.entries(parentLeadStatuses).forEach(function (value, index) {
        parent_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    $("#modal_edit_lead_statuses_form input[name=is_default]").prop('checked', false);
    $("#modal_edit_lead_statuses_form input[name=is_default][value=" + lead_status.is_default + "]").prop('checked', true);

    $("#modal_edit_lead_statuses_form input[name=is_arrived]").prop('checked', false);
    $("#modal_edit_lead_statuses_form input[name=is_arrived][value=" + lead_status.is_arrived + "]").prop('checked', true);

    $("#modal_edit_lead_statuses_form input[name=is_converted]").prop('checked', false);
    $("#modal_edit_lead_statuses_form input[name=is_converted][value=" + lead_status.is_converted + "]").prop('checked', true);

    $("#modal_edit_lead_statuses_form input[name=is_junk]").prop('checked', false);
    $("#modal_edit_lead_statuses_form input[name=is_junk][value=" + lead_status.is_junk + "]").prop('checked', true);

    $("#edit_lead_statuses_parent_id").html(parent_options);
    if (lead_status.parent_id) {
        $("#edit_lead_statuses_parent_id").val(lead_status.parent_id).change();
    }
    $('#add_lead_statuses_is_comment').prop('checked', false);
    if (lead_status.is_comment == 1) {
        $('#modal_edit_lead_statuses_form input[name=is_comment]').prop('checked', true);
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            name: $("#search_name").val(),
            status: $("#search_status").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function () {
        let filters = {
            delete: '',
            name: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    let status = filter_values.status;
    let status_options = '<option value="">All</option>';
    let parents = filter_values.parents;
    let parent_options = '<option value="">Parent Group</option>';
    Object.entries(status).forEach(function (value, index) {
        status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });
    Object.entries(parents).forEach(function (value, index) {
        parent_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    $("#search_status").html(status_options);
    $("#add_lead_statuses_parent_id").html(parent_options);

    $("#search_name").val(active_filters.lead_status_name);
    $("#search_status").val(active_filters.status);

}
