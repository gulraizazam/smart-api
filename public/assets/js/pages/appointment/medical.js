var table_url = route('admin.appointmentsmedical.datatable', {id: appointment_id});

var table_columns = [
    {
        field: 'name',
        title: 'name',
        width: 'auto',
        sortable: false,
    },{
        field: 'name',
        title: 'Patient Name',
        width: 'auto',
    },{
        field: 'created_by',
        title: 'Created_by',
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            return formatDate(data.created_at);
        }
    },{
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 'auto',
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];

function actions(data) {

    let id = data.id;

    let edit_url = route('admin.appointmentsmedical.edit', {id: id});
    let preview_url = route('admin.appointmentsmedical.previewform', {id: id});

    if (permissions.edit) {
        let actions = '<div class="dropdown dropdown-inline action-dots">\
            <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right" >\
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


        actions += '<li class="navi-item">\
                        <a href="'+preview_url+'" class="navi-link">\
                            <span class="navi-icon"><i class="la la-eye"></i></span>\
                            <span class="navi-text">Preview</span>\
                        </a>\
                    </li>';

        actions += '</ul>\
            </div>\
        </div>';

        return actions;
    }
    return '';
}

function editRow(url, id, $class = 'detail-actions') {

    if ($class === 'detail-actions') {
        $("#modal_edit_appointment").modal("show");
        $("#modal_edit_appointment_form").attr("action", route('admin.appointments.update', {id: id}));
    } else {
        $("#modal_treatment_edit").modal("show");
        $("#modal_edit_treatment_form").attr("action", route('admin.treatment.update', {id: id}));
    }

    $.ajax({
        // headers: {
        //     'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        // },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            if ($class === 'detail-actions') {
                setEditData(response);
            } else {
                setTreatmentEditData(response);
            }

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });


}

function setEditData(response) {

    try {

        let appointment = response.data.appointment;
        let back_date_config = response.data.back_date_config;
        let cities = response.data.cities;
        let consultancy_types = response.data.consultancy_type;
        let doctors = response.data.doctors;
        let locations = response.data.locations;
        let resourceHadRotaDay = response.data.resourceHadRotaDay;
        let services = response.data.services;
        let setting = response.data.setting;
        let genders = response.data.genders;

        let type_option = '<option value="">All</option>';
        Object.entries(consultancy_types).forEach(function (consultancy_type) {
            type_option += '<option value="' + consultancy_type[0] + '">' + consultancy_type[1] + '</option>';
        });

        let service_option = '<option value="">All</option>';
        Object.entries(services).forEach(function (service) {
            service_option += '<option value="' + service[0] + '">' + service[1] + '</option>';
        });

        let city_option = '<option value="">All</option>';
        Object.entries(cities).forEach(function (city) {
            city_option += '<option value="' + city[0] + '">' + city[1] + '</option>';
        });

        let location_option = '<option value="">All</option>';
        Object.entries(locations).forEach(function (location) {
            location_option  += '<option value="' + location[0] + '">' + location[1] + '</option>';
        });

        let doctor_option = '<option value="">All</option>';
        Object.entries(doctors).forEach(function (doctor) {
            doctor_option  += '<option value="' + doctor[0] + '">' + doctor[1] + '</option>';
        });

        let gender_option = '<option value="">All</option>';
        Object.entries(genders).forEach(function (gender) {
            gender_option  += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
        });

        $("#edit_consultancy_type").html(type_option).val(appointment.consultancy_type);
        $("#edit_treatment").html(service_option).val(appointment.service_id);
        $("#edit_city").html(city_option).val(appointment.city_id);
        $("#edit_location").html(location_option).val(appointment.location_id);
        $("#edit_doctor").html(doctor_option).val(appointment?.doctor_id);
        $("#edit_gender_id").html(gender_option).val(appointment?.patient?.gender);

        $("#edit_scheduled_date").val(appointment.scheduled_date);
        $("#scheduled_date_old").val(appointment.scheduled_date);
        $("#edit_scheduled_time").val(appointment.scheduled_time);
        $("#scheduled_time_old").val(appointment.scheduled_time);
        $("#edit_patient_name").val(appointment?.patient?.name);
        $("#edit_patient_phone").val(appointment?.patient?.phone);
        $("#back-date").val(back_date_config.data);
        $("#old_phone").val(appointment?.lead?.patient?.phone);
        $("#lead_id").val(appointment?.lead_id);
        $("#appointment_id").val(appointment?.id);
        $("#resourceRotaDayID").val(resourceHadRotaDay?.id);
        $("#start_time").val(resourceHadRotaDay?.start_time);
        $("#end_time").val(resourceHadRotaDay?.end_time);


    } catch (error) {
       showException(error);
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            patient_id: $("#appoint_search_id").val(),
            name: $("#appoint_search_patient").val(),
            phone: $("#appoint_search_phone").val(),
            date_from: $("#appoint_search_start").val(),
            date_to: $("#appoint_search_end").val(),
            appointment_type_id: $("#appoint_search_type").val(),
            service_id: $("#appoint_search_service").val(),
            region_id: $("#appoint_search_region").val(),
            city_id: $("#appoint_search_city").val(),
            location_id: $("#appoint_search_centre").val(),
            doctor_id: $("#appoint_search_doctor").val(),
            appointment_status_id: $("#appoint_search_status").val(),
            consultancy_type: $("#appoint_search_consultancy_type").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            created_by: $("#appoint_search_created_by").val(),
            converted_by: $("#appoint_search_updated_by").val(),
            updated_by: $("#appoint_search_rescheduled_by").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            patient_id: '',
            name: '',
            phone: '',
            date_from: '',
            date_to: '',
            appointment_type_id: '',
            service_id: '',
            region_id: '',
            city_id: '',
            location_id: '',
            doctor_id: '',
            appointment_status_id: '',
            consultancy_type: '',
            created_from: '',
            created_to: '',
            created_by: '',
            converted_by: '',
            updated_by: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function addMedicalForm($route) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setMedicalForms(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setMedicalForms(response) {

    let CustomForms = response.data.CustomForms;

    let form_data = noRecordFoundTable(2);

    if (Object.entries(CustomForms).length) {
        form_data = '';
        Object.values(CustomForms).forEach( function (form) {
            form_data += '<tr><td>'+form.name+'</td>';
            form_data += '<td><a  href="'+route('admin.appointmentsmedical.fill_form', {id: form.id, appointment_id: appointment_id}) +'" class="btn btn-sm btn-info" >Submit</a></td></tr>';
        });

    }


    $("#medical-forms").html(form_data);
}


