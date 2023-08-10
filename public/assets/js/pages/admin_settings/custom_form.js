var table_url = route('admin.custom_forms.datatable');

var table_columns = [{
    field: 'id',
    sortable: false,
    width: 25,
    title: renderCheckbox(),
    template: function(data) {
        return childCheckbox(data);
    }
}, {
    field: 'name',
    title: 'Name',
    sortable: false,
    width: 'auto',
}, {
    field: 'form_type',
    title: 'Form Type',
    sortable: false,
    width: 'auto',
    template: function(data) {
        return formType(data);
    }
}, {
    field: 'created_at',
    title: 'Created at',
    width: 'auto',
}, {
    field: 'status',
    title: 'status',
    width: 'auto',
    template: function(data) {
        let status_url = route('admin.custom_forms.status');
        return statuses(data, status_url);
    }
}, {
    field: 'actions',
    title: 'Actions',
    sortable: false,
    width: 80,
    overflow: 'visible',
    autoHide: false,
    template: function(data) {
        return actions(data);
    }
}];

function actions(data) {

    if (typeof data.id !== 'undefined') {

        let internal_id = data.id;

        let edit_url = route('admin.custom_forms.edit', { id: internal_id });
        let delete_url = route('admin.custom_forms.destroy', { id: internal_id });
        let preview_url = route('admin.custom_form_feedbacks.preview_form', { id: internal_id });
        let submit_url = route('admin.custom_form_feedbacks.fill_form', { id: internal_id });

        if (permissions.edit || permissions.preview || permissions.delete) {
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
                    <a href="' + edit_url + '" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
            }
            if (permissions.preview) {
                actions += '<li class="navi-item">\
                <a href="' + preview_url + '" class="navi-link">\
                    <span class="navi-icon"><i class="la la-eye"></i></span>\
                    <span class="navi-text">Preview</span>\
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

            if (data.custom_form_type == 0) {
                if (permissions.submit) {

                    actions += '<li class="navi-item">\
                            <a href="' + submit_url + '" class="navi-link">\
                                <span class="navi-icon"><i class="la la-send-o"></i></span>\
                                <span class="navi-text">Submit</span>\
                            </a>\
                        </li>';
                }
            }

            actions += '</ul>\
        </div>\
    </div>';

            return actions;
        }
    }
    return '';
}

function formType(data) {

    let formType = '';
    if (data.custom_form_type == '0') {
        formType = '<label>General Form</label>';
    } else if (data.custom_form_type == '1') {
        formType = '<label>Measurement Form</label>';
    } else {
        formType = '<label>Medical Form</label>';
    }

    return formType;

}


function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters = {
            delete: '',
            name: $("#search_name").val(),
            form_type_id: $("#search_form_type").val(),
            created_at: $("#date_range").val(),
            status: $("#search_status").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters = {
            delete: '',
            name: '',
            form_type_id: '',
            created_at: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        let form_types = filter_values.form_types;
        let statuses = filter_values.status;

        let status_options = '<option value="">All</option>';
        let form_type_options = '<option value="">All</option>';


        Object.entries(statuses).forEach(function(status) {
            status_options += '<option value="' + status[0] + '">' + status[1] + '</option>';
        });

        Object.entries(form_types).forEach(function(form_type) {
            form_type_options += '<option value="' + form_type[0] + '">' + form_type[1] + '</option>';
        });

        $("#search_form_type").html(form_type_options);
        $("#search_status").html(status_options);

        $("#search_name").val(active_filters.name);
        $("#search_form_type").val(active_filters.form_type_id);
        $("#date_range").val(active_filters.created_at);
        $("#search_status").val(active_filters.status);

    } catch (error) {
        showException(error);
    }
}

jQuery(document).ready( function () {
    $("#date_range").val("");
})
