
var table_url = route('admin.customformfeedbackspatient.datatable', {id: patientCardID});

var table_columns = [
    {
        field: 'form_name',
        title: 'Name',
        width: 'auto',
    },{
        field: 'name',
        title: 'Patient Name',
        width: 'auto',
        sortable: false,
    },{
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            return formatDate(data.created_at)
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
    }];


function actions(data) {

    if (typeof data.internal_id !== 'undefined') {

        let id = data.internal_id;

        let edit_url = route('admin.customformfeedbackspatient.edit', {id: id});
        let preview_url = route('admin.customformfeedbackspatient.previewform', {id: id});

        if (permissions.edit && permissions.manage) {
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
            if (permissions.manage) {
                actions += '<li class="navi-item">\
                <a href="'+preview_url+'" class="navi-link">\
                    <span class="navi-icon"><i class="la la-eye"></i></span>\
                    <span class="navi-text">Preview</span>\
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

    $('#custom-form-search').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $(".page-custom-form").find('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            name: '',
            patient_name: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }

        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        $("#search_name").val(active_filters.name);
        $("#search_patient_name").val(active_filters.patient_name);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);

    } catch (error) {
        showException(error);
    }
}
