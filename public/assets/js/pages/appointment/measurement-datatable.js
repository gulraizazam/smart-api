var table_url = route('admin.appointmentsmeasurement.datatable', {id: appointment_id});

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
    },{
        field: 'patient_id',
        title: 'Patient Name',
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
    },{
        field: 'created_by',
        title: 'Created By',
        width: 'auto',
    },{
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 180,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];

function actions(data) {

    let edit_url = route('admin.appointmentmeasurement.edit', {id: data.id});
    let preview_url = route('admin.appointmentmeasurement.previewform', {id: data.id});


        let actions = '<div class="dropdown dropdown-inline action-dots">\
        <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';

        actions += '<li class="navi-item">\
                    <a href="'+preview_url+'" target="_blank" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye"></i></span>\
                        <span class="navi-text">View</span>\
                    </a>\
                </li>';

        if (permissions.edit) {
            actions += '<li class="navi-item">\
                    <a href="'+edit_url+'" target="_blank" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
        }

        actions += '</ul>\
        </div>\
    </div>';

        return actions;
}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            id: $("#search_id").val(),
            type: $("#search_type").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
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
            type: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function addMeasurementForm($route) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setMeasurementForms(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setMeasurementForms(response) {

    let CustomForms = response.data.CustomForms;

    let form_data = noRecordFoundTable(2);

    if (Object.entries(CustomForms).length) {
        form_data = '';
        Object.values(CustomForms).forEach( function (form) {
            form_data += '<tr><td>'+form.name+'</td>';
            form_data += '<td><a  href="'+route('admin.appointmentmeasurement.fill_form', {id: form.id, appointment_id: appointment_id}) +'" class="btn btn-sm btn-info" >Submit</a></td></tr>';
        });

    }


    $("#measurement-forms").html(form_data);
}
