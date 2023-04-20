
var table_url = route('admin.patients.appointmentsDatatable', {id: patientCardID});

var table_columns = [
    {
        field: 'name',
        title: 'Patient',
        width: 'auto',
    },{
        field: 'phone',
        title: 'Phone',
        width: 90,
    },{
        field: 'scheduled_date',
        title: 'Scheduled',
        width: 'auto',
    },{
        field: 'doctor_id',
        title: 'Doctor',
        width: 100,
    },{
        field: 'city_id',
        title: 'City',
        width: 80,
    },{
        field: 'location_id',
        title: 'Centre',
        width: 'auto',
    },{
        field: 'service_id',
        title: 'Service',
        width: 'auto',
    },{
        field: 'appointment_status_id',
        title: 'Status',
        width: 'auto',
    },{
        field: 'appointment_type_id',
        title: 'Type',
        width: 'auto',
    },{
        field: 'consultancy_type',
        title: 'Consultancy Type',
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
    },{
        field: 'created_by',
        title: 'Created By',
        width: 'auto',
    }];


function applyFilters(datatable) {

    $('#appointment-form-search').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#appoint_search_patient").val(),
            phone: $("#appoint_search_phone").val(),
            date_from: $("#appoint_search_start").val(),
            date_to: $("#appoint_search_end").val(),
            doctor_id: $("#appoint_search_doctor").val(),
            city_id: $("#appoint_search_city").val(),
            location_id: $("#appoint_search_centre").val(),
            service_id: $("#appoint_search_service").val(),
            appointment_status_id: $("#appoint_search_status").val(),
            appointment_type_id: $("#appoint_search_type").val(),
            consultancy_type: $("#appoint_search_consultancy_type").val(),
            created_from: $("#appoint_search_created_from").val(),
            created_to: $("#appoint_search_created_to").val(),
            created_by: $("#appoint_search_created_to").val(),
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
            phone: '',
            date_from: '',
            date_to: '',
            doctor_id: '',
            city_id: '',
            location_id: '',
            service_id: '',
            appointment_status_id: '',
            appointment_type_id: '',
            consultancy_type: '',
            created_from: '',
            created_to: '',
            created_by: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {
    try {
        let patient = filter_values.patient;
        let cities = filter_values.cities;
        let locations = filter_values.locations;
        let appointment_statuses = filter_values.appointment_statuses;
        let appointment_types = filter_values.appointment_types;
        let doctors = filter_values.doctors;
        let services = filter_values.services;
        let users = filter_values.users;
        let consultancy_types = filter_values.consultancy_types;

        let city_options = '<option value="">All</option>';

        if (cities) {
            Object.entries(cities).forEach(function (city, index) {
                city_options += '<option value="' + city[0] + '">' + city[1] + '</option>';
            });
        }

        let location_options = '<option value="">All</option>';
        if (locations) {
            Object.entries(locations).forEach(function (location, index) {
                location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
            });
        }

        let status_options = '<option value="">All</option>';
        if (appointment_statuses) {
            Object.entries(appointment_statuses).forEach(function (status, index) {
                status_options += '<option value="' + status[0] + '">' + status[1] + '</option>';
            });
        }

        let type_options = '<option value="">All</option>';
        if (appointment_types) {
            Object.entries(appointment_types).forEach(function (type, index) {
                type_options += '<option value="' + type[0] + '">' + type[1] + '</option>';
            });
        }

        let doctor_options = '<option value="">All</option>';
        if (doctors) {
            Object.entries(doctors).forEach(function (doctor, index) {
                doctor_options += '<option value="' + doctor[0] + '">' + doctor[1] + '</option>';
            });
        }

        let service_options = '<option value="">All</option>';
        if (services) {
            Object.entries(services).forEach(function (service, index) {
                service_options += '<option value="' + service[0] + '">' + service[1] + '</option>';
            });
        }

        let user_options = '<option value="">All</option>';
        if (users) {
            Object.entries(users).forEach(function (user, index) {
                user_options += '<option value="' + user[0] + '">' + user[1] + '</option>';
            });
        }

        let consultancy_type_options = '<option value="">All</option>';
        if (consultancy_types) {
            Object.entries(consultancy_types).forEach(function (consultancy_type, index) {
                consultancy_type_options += '<option value="' + consultancy_type[0] + '">' + consultancy_type[1] + '</option>';
            });
        }

        $("#appoint_search_city").html(city_options);
        $("#appoint_search_centre").html(location_options);
        $("#appoint_search_status").html(status_options);
        $("#appoint_search_type").html(type_options);
        $("#appoint_search_doctor").html(doctor_options);
        $("#appoint_search_service").html(service_options);
        $("#appoint_search_created_by").html(user_options);
        $("#appoint_search_consultancy_type").html(consultancy_type_options);

        $("#appoint_search_patient").val(active_filters?.name);
       // $("#appoint_search_phone").val(active_filters?.phone);
        $("#appoint_search_start").val(active_filters?.date_from);
        $("#appoint_search_end").val(active_filters?.date_to);
        $("#appoint_search_created_from").val(active_filters?.created_from);
        $("#appoint_search_created_to").val(active_filters?.created_to);

        $("#appoint_search_city").val(active_filters.city_id);
        $("#appoint_search_centre").val(active_filters.location_id);
        $("#appoint_search_status").val(active_filters.appointment_status_id);
        $("#appoint_search_type").val(active_filters.appointment_type_id);
        $("#appoint_search_doctor").val(active_filters.doctor_id);
        $("#appoint_search_service").val(active_filters.service_id);
        $("#appoint_search_created_by").val(active_filters.created_by);
        $("#appoint_search_consultancy_type").val(active_filters.consultancy_type);

        hideShowAdvanceFilters();

    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters?.created_from !== 'undefined' && active_filters?.created_from != '')
        || (typeof active_filters?.created_to !== 'undefined' && active_filters?.created_to != '')
        || (typeof active_filters?.city_id !== 'undefined' && active_filters?.city_id != '')
        || (typeof active_filters?.location_id !== 'undefined' && active_filters?.location_id != '')
        || (typeof active_filters?.service_id !== 'undefined' && active_filters?.service_id != '')
        || (typeof active_filters?.appointment_status_id !== 'undefined' && active_filters?.appointment_status_id != '')
        || (typeof active_filters?.appointment_type_id !== 'undefined' && active_filters?.appointment_type_id != '')
        || (typeof active_filters?.created_by !== 'undefined' && active_filters?.created_by != '')
    ) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}

