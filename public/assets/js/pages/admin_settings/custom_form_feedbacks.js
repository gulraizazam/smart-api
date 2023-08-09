
var table_url = route('admin.custom_form_feedbacks.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 80,
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    }, {
        field: 'patient_id',
        title: 'Patient ID',
        sortable: false,
        width: 'auto',
    },{
        field: 'form_name',
        title: 'Name',
        sortable: false,
        width: 'auto',
    },{
        field: 'patient_name',
        title: 'Patient Name',
        sortable: false,
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
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

    if (typeof data.id !== 'undefined') {

        let internal_id = data.id;

        let edit_url = route('admin.custom_form_feedbacks.edit', {id: internal_id});
        let delete_url = route('admin.custom_form_feedbacks.destroy', {id: internal_id});
        let preview_url = route('admin.custom_form_feedbacks.filled_preview', {id: internal_id});

        if (permissions.edit || permissions.preview) {
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
                    <a href="'+edit_url+'" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
            }
            if (permissions.preview) {
                actions += '<li class="navi-item">\
                <a href="'+preview_url+'" class="navi-link">\
                    <span class="navi-icon"><i class="la la-eye"></i></span>\
                    <span class="navi-text">Preview</span>\
                </a>\
            </li>';
            }

            if (permissions.delete) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="deleteRow(`'+delete_url+'`);" class="navi-link">\
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
    }
    return '';
}


function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            id: $("#search_id").val(),
            name: $("#search_name").val(),
            patient_name: $("#search_patient_name").val(),
            created_at: $("#date_range").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            id: '',
            name: '',
            patient_name: '',
            created_at: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        $("#search_id").val(active_filters.id);
        $("#search_name").val(active_filters.name);
        $("#search_patient_name").val(active_filters.patient_name);
        $("#date_range").val(active_filters.created_at);

    } catch (error) {
        showException(error);
    }
}

jQuery(document).ready( function () {
    $("#date_range").val("");
})
