
// Use direct URL since API routes may not be in Ziggy
var table_url = '/api/patients/' + patientCardID + '/appointments-datatable';

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
        field: 'location_id',
        title: 'Centre',
        width: 'auto',
    },{
        field: 'service_id',
        title: 'Service',
        width: 90,
    },{
        field: 'appointment_type_id',
        title: 'Type',
        width: 90,
        template: function(data) {
            if (data.appointment_type_id == 1 || data.appointment_type_id == 'Consultation') {
                return '<span class="label label-lg label-light-success label-inline">Consultation</span>';
            } else {
                return '<span class="label label-lg label-light-danger label-inline">Treatment</span>';
            }
        }
    },{
        field: 'appointment_status_id',
        title: 'Status',
        width: 'auto',
    },{
        field: 'actions',
        title: 'Actions',
        width: 100,
        sortable: false,
        template: function(data) {
            return actions(data);
        }
    }];


// View appointment detail - reuses modal from consultation datatable
function viewDetail(url) {
    $("#modal_appointment_detail").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setAppointmentDetailData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setAppointmentDetailData(response) {
    try {
        let appointment = response.data.appointment;
        let permissions = response.data.permissions;
        let patient = appointment.patient;
        let doctor = appointment.doctor;
        let city = appointment.city;
        let location = appointment.location;
        let appointment_status = appointment.appointment_status;
        let service = appointment.service;
        const [hourString, minute] = appointment.scheduled_time.split(":");
        const hour = +hourString % 24;
        var test = (hour % 12 || 12) + ":" + minute + (hour < 12 ? "AM" : "PM");
        $("#appointment_comment_appointment_id").val(appointment?.id ?? 0);
        $("#appointment_patient_name").text(patient?.name ?? 'N/A');
        $("#appointment_patient_phone").text(makePhoneNumber(patient?.phone, permissions.contact, 1));
        $("#appointment_patient_c_id").text(makePatientId(patient?.id));
        $("#appointment_patient_gender").text(getGender(patient?.gender));
        $("#appointment_patient_scheduled_time").text(formatDate(appointment?.scheduled_date, 'MMM, D, YYYY ') + " at " + test);
        $("#appointment_doctor_name").text(doctor?.name ?? 'N/A');
        $("#appointment_city_name").text(city?.name ?? 'N/A');
        $("#appointment_center_name").text(location?.name ?? 'N/A');
        $("#appointment_appointment_status").text(appointment_status?.name ?? 'N/A');
        $("#appointment_service_consultancy_name").text(service?.name ?? 'N/A');
        $("#appointment_service_consultancy_name_title").text(service?.name ?? 'N/A');
        setAppointmentComments(appointment);
    } catch (e) {
        console.error('Error setting appointment detail data:', e);
    }
}

function setAppointmentComments(appointment) {
    let appointment_comments = appointment.appointment_comments;
    let comment_html = '';
    if (appointment_comments && appointment_comments.length) {
        Object.values(appointment_comments).forEach(function (comment) {
            comment_html += commentData(comment?.user?.name, comment?.created_at, comment?.comment);
        });
    }
    $("#appointment_commentsection").html(comment_html);
}

// Edit appointment - show modal
function editRow(url, id, $class = 'detail-actions') {
    if ($class === 'detail-actions') {
        $("#modal_edit_appointment").modal("show");
        $("#modal_edit_appointment_form").attr("action", route('admin.appointments.update', { id: id }));
    } else {
        $("#modal_treatment_edit").modal("show");
        $("#modal_edit_treatment_form").attr("action", route('admin.treatment.update', { id: id }));
    }

    $.ajax({
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
            toastr.error('Failed to load appointment data');
        }
    });
}

// Set edit data for consultation
function setEditData(response) {
    try {
        let appointment = response.data.appointment;
        let consultancy_types = response.data.consultancy_type;
        let doctors = response.data.doctors;
        let services = response.data.services;
        let permissions = response.data.permissions;

        let service_option = '<option value="">Select a Service</option>';
        Object.entries(services).forEach(function (service) {
            if (service[1] !== 'All Services') {
                service_option += '<option value="' + service[0] + '">' + service[1] + '</option>';
            }
        });

        let doctor_option = '<option value="">Select a Doctor</option>';
        Object.entries(doctors).forEach(function (doctor) {
            doctor_option += '<option value="' + doctor[0] + '">' + doctor[1] + '</option>';
        });

        // Set modal heading with patient name
        let patientName = appointment?.patient?.name || "Patient";
        $("#edit_consultation_heading").html("Edit <span class='text-primary'>" + patientName + "'s</span> Consultation");

        $("#edit_treatment").html(service_option).val(appointment.service_id);
        $("#consultancy_service_id").val(appointment.service_id);
        $("#edit_doctor").html(doctor_option).val(appointment?.doctor_id);

        // Check if status is Arrived (2) or Converted (16)
        let isArrivedOrConverted = (appointment.appointment_status_id == 2 || appointment.appointment_status_id == 16);
        
        if (isArrivedOrConverted) {
            if (permissions.update_consultation_service) {
                $("#edit_treatment").prop('disabled', false).removeClass('bg-light');
            } else {
                $("#edit_treatment").prop('disabled', true).addClass('bg-light');
            }
            
            if (permissions.update_consultation_doctor) {
                $("#edit_doctor").prop('disabled', false).removeClass('bg-light');
            } else {
                $("#edit_doctor").prop('disabled', true).addClass('bg-light');
            }
            
            if (permissions.update_consultation_schedule) {
                $("#edit_scheduled_date").prop('disabled', false).removeClass('bg-light');
                $("#edit_scheduled_time").prop('disabled', false).removeClass('bg-light');
            } else {
                $("#edit_scheduled_date").prop('disabled', true).addClass('bg-light');
                $("#edit_scheduled_time").prop('disabled', true).addClass('bg-light');
            }
        } else {
            $("#edit_treatment").prop('disabled', false).removeClass('bg-light');
            $("#edit_doctor").prop('disabled', false).removeClass('bg-light');
            $("#edit_scheduled_date").prop('disabled', false).removeClass('bg-light');
            $("#edit_scheduled_time").prop('disabled', false).removeClass('bg-light');
        }

        $("#edit_scheduled_date").val(appointment.scheduled_date);
        $("#scheduled_date_old").val(appointment.scheduled_date);
        $("#edit_scheduled_time").val(appointment.scheduled_time);
        $("#scheduled_time_old").val(appointment.scheduled_time);

        $("#old_phone").val(appointment?.lead?.patient?.phone);
        $("#lead_id").val(appointment?.lead_id);
        $("#appointment_id").val(appointment?.id);
        $("#consultancy_appointment_type").val(appointment?.appointment_type_id);
        $("#edit_location_id").val(appointment?.location_id);

        // Reinitialize select2
        if ($("#edit_treatment").hasClass("select2-hidden-accessible")) {
            $("#edit_treatment").select2('destroy');
        }
        if ($("#edit_doctor").hasClass("select2-hidden-accessible")) {
            $("#edit_doctor").select2('destroy');
        }
        $("#edit_treatment").select2();
        $("#edit_doctor").select2();

    } catch (error) {
        console.error('Error in setEditData:', error);
    }
}

// Set edit data for treatment
function setTreatmentEditData(response) {
    console.log('setTreatmentEditData called', response);
    try {
        let appointment = response.data.appointment;
        console.log('Appointment data:', appointment);
        console.log('Patient name:', appointment?.patient?.name);
        let doctors = response.data.doctors || {};
        let machines = response.data.machines || {};
        let services = response.data.services || {};
        let cities = response.data.cities || {};
        let locations = response.data.locations || {};
        let genders = response.data.genders || {};

        let service_option = '<option value="">Select a Service</option>';
        if (services && Object.keys(services).length > 0) {
            Object.entries(services).forEach(function (service) {
                if (service[1] !== 'All Services') {
                    service_option += '<option value="' + service[0] + '">' + service[1] + '</option>';
                }
            });
        }

        let doctor_option = '<option value="">Select a Doctor</option>';
        if (doctors && Object.keys(doctors).length > 0) {
            Object.entries(doctors).forEach(function (doctor) {
                doctor_option += '<option value="' + doctor[0] + '">' + doctor[1] + '</option>';
            });
        }

        let machine_option = '<option value="">Select a Machine</option>';
        if (machines && Object.keys(machines).length > 0) {
            Object.entries(machines).forEach(function (machine) {
                machine_option += '<option value="' + machine[0] + '">' + machine[1] + '</option>';
            });
        }

        let city_option = '<option value="">Select a City</option>';
        if (cities && Object.keys(cities).length > 0) {
            Object.entries(cities).forEach(function (city) {
                city_option += '<option value="' + city[0] + '">' + city[1] + '</option>';
            });
        }

        let location_option = '<option value="">Select a Location</option>';
        if (locations && Object.keys(locations).length > 0) {
            Object.entries(locations).forEach(function (location) {
                location_option += '<option value="' + location[0] + '">' + location[1] + '</option>';
            });
        }

        let gender_option = '<option value="">Select a Gender</option>';
        if (genders && Object.keys(genders).length > 0) {
            Object.entries(genders).forEach(function (gender) {
                gender_option += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
            });
        }

        $("#edit_treatment_service_id").html(service_option).val(appointment.service_id);
        $("#edit_treatment_machine_id").html(machine_option).val(appointment.resource_id);
        $("#edit_treatment_city_id").html(city_option).val(appointment.city_id);
        $("#edit_treatment_location_id").html(location_option).val(appointment.location_id);
        $("#edit_treatment_doctor_id").html(doctor_option).val(appointment?.doctor_id);
        $("#edit_treatment_patient_gender").html(gender_option).val(appointment?.patient?.gender);

        // Set patient name and phone EARLY (before timepicker which might error)
        $("#edit_treatment_patient_name").val(appointment?.patient?.name);
        $("#edit_treatment_patient_phone").val(appointment?.patient?.phone);
        $("#edit_old_treatment_patient_phone").val(appointment?.lead?.patient?.phone);
        
        // Set patient name in modal title
        let patientName = appointment?.patient?.name || '';
        console.log('Setting patient name in title:', patientName);
        $("#edit_treatment_patient_name_display").text(patientName);
        
        // Set other appointment data
        $("#treatment_leadId").val(appointment?.lead_id);
        $("#treatment_appointment_id").val(appointment?.id);
        $("#treatment_appointment_type").val(appointment?.appointment_type_id);

        // Set scheduled date
        let scheduledDate = appointment.scheduled_date;
        $("#edit_treatment_scheduled_date_old").val(scheduledDate);
        
        var $dateField = $("#edit_treatment_scheduled_date");
        if ($dateField.data('datepicker')) {
            $dateField.datepicker('destroy');
        }
        $dateField.val(scheduledDate);
        $dateField.datepicker({
            todayHighlight: true,
            orientation: 'bottom',
            format: 'yyyy-mm-dd',
            autoclose: true
        });

        // Convert 24h time format to 12h format (e.g., "13:45:00" to "01:45 PM")
        let scheduledTime = appointment.scheduled_time;
        let formattedTime = '';
        if (scheduledTime) {
            const [hourString, minute] = scheduledTime.split(":");
            const hour = parseInt(hourString) % 24;
            formattedTime = String(hour % 12 || 12).padStart(2, '0') + ":" + minute + " " + (hour < 12 ? "AM" : "PM");
        }
        
        // Destroy existing timepicker and reinitialize with the correct time
        var $timeField = $("#edit_treatment_scheduled_time");
        if ($timeField.data('timepicker')) {
            $timeField.timepicker('destroy');
        }
        $timeField.val(formattedTime);
        $timeField.timepicker({
            showMeridian: true,
            defaultTime: formattedTime || '08:00 AM'
        });
        $("#scheduled_treatment_time_old").val(appointment.scheduled_time);

    } catch (error) {
        console.error('Error in setTreatmentEditData:', error);
    }
}

// Delete appointment row
function deleteRow(url) {
    if (confirm('Are you sure you want to delete this appointment?')) {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: url,
            type: 'DELETE',
            success: function(response) {
                if (response.status) {
                    toastr.success('Appointment deleted successfully');
                    reInitTable();
                } else {
                    toastr.error(response.message || 'Failed to delete appointment');
                }
            },
            error: function(xhr) {
                toastr.error('Failed to delete appointment');
            }
        });
    }
}

function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;
        let edit_url = route('admin.appointments.edit', {appointment: id});
        let edit_service_url = route('admin.treatments.edit', {id: id});
        let detail_url = route('admin.appointments.detail', {id: id});
        let delete_url = route('admin.appointments.destroy', {id: id});

        let actionsHtml = '<div class="dropdown dropdown-inline action-dots">\
            <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action:\
                    </li>\
                    <li class="navi-item">\
                        <a href="javascript:void(0);" onclick="viewDetail(\'' + detail_url + '\');" class="navi-link">\
                            <span class="navi-icon"><i class="la la-eye"></i></span>\
                            <span class="navi-text">Detail</span>\
                        </a>\
                    </li>';
        
        // Use different edit URL based on appointment type (1=Consultation, 2=Treatment)
        if (data.appointment_type_id == 1 || data.appointment_type_id == 'Consultation') {
            actionsHtml += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(\'' + edit_url + '\', \'' + id + '\', \'detail-actions\');" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>';
        } else {
            actionsHtml += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(\'' + edit_service_url + '\', \'' + id + '\', \'treatment-detail-actions\');" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>';
        }
        
        actionsHtml += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="deleteRow(\'' + delete_url + '\');" class="navi-link">\
                            <span class="navi-icon"><i class="la la-trash"></i></span>\
                            <span class="navi-text">Delete</span>\
                        </a>\
                    </li>\
                </ul>\
            </div>\
        </div>';

        return actionsHtml;
    }
    return '';
}


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

// Comment data HTML generator
function commentData(user_name, created_at, comment) {
    let comment_html = '';
    comment_html = '<div class="tab-content" id="itemComment">' +
        ' <div class="tab-pane active" id="portlet_comments_1"> ' +
        '<div class="mt-comments"> ' +
        '<div class="mt-comment">' +
        ' <div class="mt-comment-img" id="imgContainer"> ' +
        '<img src="'+asset_url+'assets/media/avatar.jpg" alt="Avatar"> ' +
        '</div><div class="mt-comment-body"> ' +
        '<div class="mt-comment-info"> ' +
        '<span class="mt-comment-author" id="creat_by">';
    comment_html += user_name ?? 'N/A';
    comment_html += '</span> <span class="mt-comment-date" id="datetime">';
    comment_html += formatDate(created_at, 'ddd MMM DD, yyyy hh:mm A');
    comment_html += '</span> </div>' +
        '<div class="mt-comment-text" id="message">';
    comment_html += comment ?? 'N/A';
    comment_html += '</div><div class="mt-comment-details"> </div>' +
        '</div></div></div></div></div>';
    return comment_html;
}

// Treatment edit form submit handler - use AJAX instead of page redirect
$(document).on('submit', '#modal_edit_treatment_form', function(e) {
    e.preventDefault();
    
    var form = $(this);
    var url = form.attr('action');
    var formData = form.serialize();
    var submitBtn = form.find('button[type="submit"]');
    var originalText = submitBtn.html();
    
    // Show spinner
    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'PUT',
        data: formData,
        success: function(response) {
            if (response.status) {
                toastr.success(response.message || 'Treatment updated successfully');
                $("#modal_treatment_edit").modal("hide");
                reInitTable();
            } else {
                toastr.error(response.message || 'Failed to update treatment');
            }
        },
        error: function(xhr) {
            toastr.error('Failed to update treatment');
        },
        complete: function() {
            // Restore button
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
});

// Consultation edit form submit handler - use AJAX instead of page redirect
$(document).on('submit', '#modal_edit_appointment_form', function(e) {
    e.preventDefault();
    
    var form = $(this);
    var url = form.attr('action');
    var formData = form.serialize();
    var submitBtn = form.find('button[type="submit"]');
    var originalText = submitBtn.html();
    
    // Show spinner
    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'PUT',
        data: formData,
        success: function(response) {
            if (response.status) {
                toastr.success(response.message || 'Consultation updated successfully');
                $("#modal_edit_appointment").modal("hide");
                reInitTable();
            } else {
                toastr.error(response.message || 'Failed to update consultation');
            }
        },
        error: function(xhr) {
            toastr.error('Failed to update consultation');
        },
        complete: function() {
            // Restore button
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
});

// Comment button click handler for appointment detail modal
$(document).on('click', '#Add_appointment_comment', function () {
    if ($('#appointment_comment').val() !== '') {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: route('admin.appointments.storecomment'),
            data: {
                'comment': $('#appointment_comment').val(),
                'appointment_id': $('#appointment_comment_appointment_id').val(),
            },
            success: function (data) {
                $('#appointment_commentsection').prepend(commentData(data.username, data.appointmentCommentDate, data.appointment.comment));
                $('#appointment_comment').val('');
                toastr.success('Comment added successfully');
            },
            error: function(xhr) {
                toastr.error('Failed to add comment');
            }
        });
    } else {
        toastr.error("Please fill out the comment field");
    }
});

