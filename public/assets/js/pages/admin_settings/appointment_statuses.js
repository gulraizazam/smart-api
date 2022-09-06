var table_url = route('admin.appointment_statuses.datatable');

var width = (window.innerWidth > 0) ? window.innerWidth : screen.width;
if (width > 1280) {
    var table_columns = [
        {
            field: 'id',
            sortable: false,
            width: 30,
            title: renderCheckbox(),
            template: function (data) {
                return childCheckbox(data);
            }
        },
        {
            field: 'name',
            title: 'Name',
            width: 90,
            sortable: false,
        },
        {
            field: 'parent_id',
            title: 'Parent Group',
            width: 80,
            sortable: false,
        },
        {
            field: 'is_comment',
            title: 'Ask for comments',
            width: 80,
            sortable: false,
        },
        {
            field: 'allow_message',
            title: 'Allow Send SMS',
            width: 80,
            sortable: false,
        },
        {
            field: 'is_default',
            title: 'Default Status for New Appointments',
            width: 'auto',
            sortable: false,
        },
        {
            field: 'is_arrived',
            title: 'Default Status for Arrived Appointments',
            width: 'auto',
            sortable: false,
        },
        {
            field: 'is_cancelled',
            title: 'Default Status for Cancelled Appointments',
            width: 'auto',
            sortable: false,
        },{
            field: 'status',
            title: 'status',
            width: 60,
            sortable: false,
            template: function (data) {
                let status_url = route('admin.appointment_statuses.status');
                return statuses(data, status_url);
            }
        },{
            field: 'actions',
            title: 'Actions',
            sortable: false,
            width: 150,
            overflow: 'visible',
            autoHide: false,
            template: function (data) {
                return actions(data);
            }
        },{
            field: 'is_unscheduled',
            title: 'Default Status for Un-Scheduled Appointments',
            width: 'auto',
            sortable: false,
        }];
} else {
    var table_columns = [
        {
            field: 'id',
            sortable: false,
            width: 30,
            title: renderCheckbox(),
            template: function (data) {
                return childCheckbox(data);
            }
        },
        {
            field: 'name',
            title: 'Name',
            width: 100,
            sortable: false,
        },
        {
            field: 'parent_id',
            title: 'Parent Group',
            width: 80,
            sortable: false,
        },
        {
            field: 'is_comment',
            title: 'Ask for comments',
            width: 80,
            sortable: false,
        },
        {
            field: 'allow_message',
            title: 'Allow Send SMS',
            width: 80,
            sortable: false,
        },
        {
            field: 'is_default',
            title: 'Default Status for New Appointments',
            width: 'auto',
            sortable: false,
        },
        {
            field: 'is_arrived',
            title: 'Default Status for Arrived Appointments',
            width: 'auto',
            sortable: false,
        },{
            field: 'status',
            title: 'status',
            width: 60,
            sortable: false,
            template: function (data) {
                let status_url = route('admin.appointment_statuses.status');
                return statuses(data, status_url);
            }
        },{
            field: 'actions',
            title: 'Actions',
            sortable: false,
            width: 100,
            overflow: 'visible',
            autoHide: false,
            template: function (data) {
                return actions(data);
            }
        }, {
            field: 'is_cancelled',
            title: 'Default Status for Cancelled Appointments',
            width: 'auto',
            sortable: false,
        },{
            field: 'is_unscheduled',
            title: 'Default Status for Un-Scheduled Appointments',
            width: 'auto',
            sortable: false,
        }];
}


function actions(data) {

    let id = data.id;

    let csrf = $('meta[name="csrf-token"]').attr('content');
    let url = route('admin.appointment_statuses.edit', {id: id});
    let delete_url = route('admin.appointment_statuses.destroy', {id: id});

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
    let lead_status = response.data;
    let parentLeadStatuses = lead_status.parent_options;
    let action = route('admin.appointment_statuses.update', {id: lead_status.id});

    $("#modal_edit_appointment_statuses_form").attr("action", action);
    $("#edit_appointment_statuses_name").val(lead_status.name);
    let parent_options = '<option value="">Parent Group</option>';

    Object.entries(parentLeadStatuses).forEach(function (value, index) {
        parent_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    $("#edit_appointment_statuses_parent_id").html(parent_options);
    if (lead_status.parent_id) {
        $("#edit_appointment_statuses_parent_id").val(lead_status.parent_id).change();
    } else {
        $("#edit_appointment_statuses_parent_id").change();
    }

    $("#modal_edit_appointment_statuses_form input[name=is_default]").prop('checked', false);
    $("#modal_edit_appointment_statuses_form input[name=is_default][value=" + lead_status.is_default + "]").prop('checked', true);

    $("#modal_edit_appointment_statuses_form input[name=is_arrived]").prop('checked', false);
    $("#modal_edit_appointment_statuses_form input[name=is_arrived][value=" + lead_status.is_arrived + "]").prop('checked', true);

    $("#modal_edit_appointment_statuses_form input[name=is_cancelled]").prop('checked', false);
    $("#modal_edit_appointment_statuses_form input[name=is_cancelled][value=" + lead_status.is_cancelled + "]").prop('checked', true);

    $("#modal_edit_appointment_statuses_form input[name=is_unscheduled]").prop('checked', false);
    $("#modal_edit_appointment_statuses_form input[name=is_unscheduled][value=" + lead_status.is_unscheduled + "]").prop('checked', true);


    $('#edit_appointment_statuses_is_comment').prop('checked', false);
    if (lead_status.is_comment == 1) {
        $('#modal_edit_appointment_statuses_form input[name=is_comment]').prop('checked', true);
    }

    $('#edit_appointment_statuses_allow_message').prop('checked', false);
    if (lead_status.allow_message == 1) {
        $('#modal_edit_appointment_statuses_form input[name=allow_message]').prop('checked', true);
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
    $("#add_appointment_statuses_parent_id").html(parent_options);

    $("#search_name").val(active_filters.appointment_status_name);
    $("#search_status").val(active_filters.status);

}

function notParent(val) {

    let form = ($(val).parents('form'))[0];
    let value = $(val).val();
    if (value == '' || value == null) {
        $('.not-have-parent').show();
    } else {
        $(form).find("input[name='is_default'][value='1']").prop('checked', false);
        $(form).find("input[name='is_default'][value='0']").prop('checked', true);

        $(form).find("input[name='is_arrived'][value='1']").prop('checked', false);
        $(form).find("input[name='is_arrived'][value='0']").prop('checked', true);

        $(form).find("input[name='is_cancelled'][value='1']").prop('checked', false);
        $(form).find("input[name='is_cancelled'][value='0']").prop('checked', true);

        $(form).find("input[name='is_unscheduled'][value='1']").prop('checked', false);
        $(form).find("input[name='is_unscheduled'][value='0']").prop('checked', true);

        $(form).find("input[name='allow_message']").prop('checked', false);

        $('.not-have-parent').hide();
    }
}

$('#add_appointment_statuses_parent_id').change(function () {
    notParent(this);
});

$('#edit_appointment_statuses_parent_id').change(function () {
    notParent(this);
});

function resetStatusForm() {
    $('.not-have-parent').show();
}
