// Build table URL - append patient_id if in patient card context
var table_url = route('admin.treatments.datatable');
if (typeof patientId !== 'undefined' && patientId) {
    table_url += '?patient_id=' + patientId;
}

// Use shared column definitions from treatment-columns.js
// Include patient column only if NOT in patient card context (patientId not defined)
var includePatientColumn = (typeof patientId === 'undefined' || !patientId);
var table_columns = (typeof getTreatmentColumns === 'function') 
    ? getTreatmentColumns(includePatientColumn, null)
    : [];

function editStatus(id) {

    $("#modal_change_appointment_status").modal("show");
    $("#modal_update_status_form").attr("action", route('admin.appointments.storeappointmentstatus'));


    $.ajax({
        // headers: {
        //     'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        // },
        url: route('admin.appointments.showappointmentstatus'),
        type: "GET",
        data: {id: id},
        cache: false,
        success: function(response) {
            if (response.status) {
                setStatusData(response, id);
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setStatusData(response, id) {

    try {

        let appointments = response.data.appointment;
        let appointment_status = response.data.appointment.appointment_status;
        let appointment_statuses = response.data.appointment_statuses;
        let base_appointment_statuses = response.data.base_appointment_statuses;
        let base_appointments = response.data.base_appointments;
        let appointment_status_not_show = response.data.appointment_status_not_show;
        let cancellation_reason_other_reason = response.data.cancellation_reason_other_reason;
        let appointment_type_id = response.data.appointment.appointment_type_id;
        let base_status_option = '<option value="">Select Status</option>';
        if (base_appointment_statuses) {
            Object.entries(base_appointment_statuses).forEach(function (base_status) {
                base_status_option += '<option value="'+base_status[0]+'">'+base_status[1]+'</option>';
            });
        }

        let appoint_status_option = '<option value="">Select Child Status</option>';
        if (appointment_statuses) {
            Object.entries(appointment_statuses).forEach(function (appointment_status) {
                appoint_status_option += '<option value="'+appointment_status[0]+'">'+appointment_status[1]+'</option>';
            });
        }
        $("#appointment_type_id").val(appointment_type_id);
        $("#base_appointment_status_id").html(base_status_option);
        $("#appointment_status_id").html(appoint_status_option);

        $("#appointment_id").val(id);
        $("#appointment_status_not_show").val(appointment_status_not_show);
        $("#cancellation_reason_other_reason").val(cancellation_reason_other_reason);


        if (appointments?.appointment_status?.parent_id != 0) {
            $("#base_appointment_status_id").val(appointments?.appointment_status?.parent_id);
        } else {
            $("#base_appointment_status_id").val(appointments?.appointment_status_id);
        }

        if (appointments?.appointment_status?.parent_id == 0) {
            $("#appointment_status_id_section").hide();
        } else {
            $("#appointment_status_id_section").show();
            $("#appointment_status_id").val(appointments?.appointment_status?.id);
        }

        if (appointments?.appointment_status?.parent_id == 0) {

            if (appointments.appointment_status?.is_comment == 0) {
                $("#appointment_reason").hide();
            } else {
                $("#appointment_reason").show();
                $("#reason").val(appointments?.reason);
            }
        } else {
            if(base_appointments[appointments.appointment_status?.parent_id]?.is_comment == 0
                && appointments?.appointment_status?.is_comment == 0) {
            } else {
                $("#appointment_reason").hide();
                $("#appointment_status_id_section").hide();

            }
            if(base_appointments[appointments.appointment_status.parent_id].is_comment == 0
                && appointments?.appointment_status?.is_comment == 0) {
                $("#appointment_reason").hide();
            } else {
                $("#appointment_reason").show();
                $("#appointment_status_id_section").show();
                $("#reason").val(appointments?.reason);
                $("#appointment_status_id").val(appointments?.appointment_status?.id);
            }
        }

    } catch (error) {
        showException(error);
    }
}

// editSchedule function moved to common.js

const extraValidate = {
    validators: {
        notEmpty: {
            message: 'This field is required'
        }
    },
};

let loadChildStatuses = function (appointmentStatusId) {

    statusValidate.addField('appointment_status_id', extraValidate);
    statusValidate.addField('reason', extraValidate);
    statusValidate.removeField('appointment_status_id', '');
    statusValidate.removeField('reason', '');
    if(appointmentStatusId != '') {
        resetDropdowns();
        $("input[type=submit]").attr('disabled', true);
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_child_appointment_statuses'),
            type: 'POST',
            data: {
                appointment_status_id: appointmentStatusId
            },
            cache: false,
            success: function(response) {
                if(response.status) {
                    if (response.data.dropdown) {
                        setChildStatusData(response);
                        $('.appointment_status_id').show();
                        statusValidate.addField('appointment_status_id', extraValidate);
                    } else {
                        $('.appointment_status_id').hide();
                        $('#appointment_status_id').html('');
                        statusValidate.addField('appointment_status_id', extraValidate);
                        statusValidate.removeField('appointment_status_id', '');
                    }
                } else {
                    resetDropdowns();
                }
                if(parseInt(response.count) > 1) {
                    $('.appointment_status_id').show();
                }
                if(response.status && response.data.appointment_status.is_comment == '1') {
                    $('.reason').show();
                    statusValidate.addField('reason', extraValidate);
                } else {
                    resetReason();
                    statusValidate.addField('reason', extraValidate);
                    statusValidate.removeField('reason', '');
                }
                $("input[type=submit]").removeAttr('disabled');
            },
            error: function (xhr, ajaxOptions, thrownError) {
                $("input[type=submit]").removeAttr('disabled');
                resetDropdowns();
            }
        });
    } else {
        resetDropdowns();
    }
}

function setChildStatusData(response) {

    let dropdowns = response.data.dropdown;
    let  child_options = '<option value="">Select Child Status</option>';
    if (dropdowns) {
        Object.entries(dropdowns).forEach(function (dropdown) {
            child_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
        });
    }
    $('#appointment_status_id').html(child_options);
}

var resetDropdowns = function() {
    resetReason();
    resetChildStatuses();
}

var resetReason = function () {
    $('.reason').hide();
    $('#reason').val('');
}

var resetChildStatuses = function () {
    $('.appointment_status_id').hide();
    $('#appointment_status_id').val('');
    //statusValidate.removeField('appointment_status_id', '');
}

let statusListener = function (appointmentStatusId) {

    statusValidate.addField('reason', extraValidate);
    statusValidate.removeField('reason', '');
    if(appointmentStatusId != '') {
        $("input[type=submit]").attr('disabled', true);
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_child_appointment_status_data'),
            type: 'POST',
            data: {
                appointment_status_id: appointmentStatusId,
                base_appointment_status_id: $('#base_appointment_status_id').val()
            },
            cache: false,
            success: function(response) {
                if(response.status && (response.data.appointment_status.is_comment == '1' || response.data.base_appointment_status.is_comment == '1')) {
                    $('.reason').show();
                    statusValidate.addField('reason', extraValidate);
                } else {
                    resetReason();
                    statusValidate.removeField('reason', '');
                }
                $("input[type=submit]").removeAttr('disabled');
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetReason();
                $("input[type=submit]").removeAttr('disabled');
            }
        });
    } else {
        resetReason();
    }
}

function actions(data) {

    let id = data.id;

    let edit_url = route('admin.appointments.edit', {id: id});
    let edit_service_url = route('admin.treatments.edit', {id: id});
    let detail_url = route('admin.appointments.detail', {id: id});
    let feedback_url = route('admin.appointments.feedback.index', {id: id});
    let sms_logs_url = route('admin.appointments.sms_logs', {id: id});

    let consultancy_invoice_url = route('admin.appointments.invoice-create-consultancy', {id: id, type: 'appointment'});
    let invoice_url = route('admin.appointments.invoicecreate', {id: id});
    let invoice_display_url = route('admin.appointments.InvoiceDisplay', {id: data.invoice_id});
    let image_url = route('admin.appointmentsimage.imageindex', {id: id});
    let measurements_url = route('admin.appointmentsmeasurement.measurements', {id: id});
    let medicals_url = route('admin.appointmentsmedical.medicals', {id: id});
    let plan_url = route('admin.appointmentplans.create', {id: id});
    let delete_url = route('admin.appointments.destroy', {id: id});
    let patient_url = route('admin.patients.preview', {id: data.patient_id});
    let viewlog_url = route('admin.appointments.loadPage', {id: id, type: 'web'});

    if (
        permissions.edit
        || permissions.delete
        || permissions.consultancy
        || permissions.log
        || permissions.status
        || permissions.treatment
        || permissions.invoice
        || permissions.invoice_display
        || permissions.image_manage
        || permissions.measurement_manage
        || permissions.medical_form_manage
        || permissions.plans_create
        || permissions.patient_card
    ) {
        let actions = '<div class="dropdown dropdown-inline action-dots">';

        if (permissions.invoice) {
            if(!data.invoice) {
                if (data.appointment_type == 2) {
                    actions += '<a title="Create Invoice" href="javascript:void(0);" onclick="createTreatmentInvoice(`' + invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-warning btn-sm">\
                            <span class="navi-icon"><i class="la la-file"></i></span>\
                            <!--<span class="navi-text">Create Invoice</span>-->\
                        </a>';
                }

                if(data.appointment_type == 1) {
                    actions += '<a title="Create Invoice" href="javascript:void(0);" onclick="createConsultancyInvoice(`' + consultancy_invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-warning btn-sm">\
                            <span class="navi-icon"><i class="la la-file"></i></span>\
                        </a>';
                }
            }

        }

        if (permissions.invoice_display) {

            if(data.invoice) {
                actions += '<a title="View Invoice" href="javascript:void(0);" onclick="displayInvoice(`' + invoice_display_url + '`, `' + id + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-info btn-sm">\
                            <span class="navi-icon"><i class="la la-file-invoice-dollar"></i></span>\
                        </a>';
            }
        }
        actions += '<a href="javascript:void(0);" onclick="viewSmsLogs(`'+sms_logs_url+'`);" class="d-lg-inline-flex d-none btn btn-icon btn-success btn-sm ml-2">\
                        <span class="navi-icon"><i class="la la-sms"></i></span>\
                        </a>';

        // Show WhatsApp icon only if appointment_status is NOT 2 and scheduled_date is today
        let today = new Date();
        let todayString = today.getFullYear() + '-' +
                         String(today.getMonth() + 1).padStart(2, '0') + '-' +
                         String(today.getDate()).padStart(2, '0');

        // Parse scheduled_date to check if it's today
        let isToday = false;
        if (data.scheduled_date && data.scheduled_date !== '-') {
            // Parse "Dec 15, 2025 at 12:45 PM" format
            let scheduledDatePart = data.scheduled_date.split(' at ')[0]; // Get "Dec 15, 2025"
            let scheduledDate = new Date(scheduledDatePart);
            let scheduledDateString = scheduledDate.getFullYear() + '-' +
                                     String(scheduledDate.getMonth() + 1).padStart(2, '0') + '-' +
                                     String(scheduledDate.getDate()).padStart(2, '0');
            isToday = scheduledDateString === todayString;
        }

        // Debug logging for WhatsApp icon rendering
     

        // Check user role permission for WhatsApp button (only FDM and Super-Admin)
        let canSendWhatsApp = window.canSendWhatsApp || false;

        if (data.appointment_status != 2 && isToday ) {
            // Copy WhatsApp Message Button
            actions += '<a href="javascript:void(0);" onclick="copyWhatsAppMessage(' + id + ');" class="d-lg-inline-flex d-none btn btn-icon btn-primary btn-sm ml-2" title="Copy Message">\
                            <span class="navi-icon"><i class="la la-copy" style="color: white;"></i></span>\
                        </a>';

            // Send WhatsApp Button
            // actions += '<a href="javascript:void(0);" onclick="sendWhatsApp(' + id + ');" class="d-lg-inline-flex d-none btn btn-icon btn-sm ml-2" title="Send WhatsApp" style="background-color: #25D366;">\
            //                 <span class="navi-icon"><i class="lab la-whatsapp" style="color: white;"></i></span>\
            //             </a>';
        } 

        actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                        <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
                    </a>';

        actions += '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right" style="overflow-y: scroll; height: 200px">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
            if( permissions.add_feedback){
                if(data.appointment_type == 2 && data.appointment_status==2 ) {
                    actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="addFeedback(`'+feedback_url+'`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-plus"></i></span>\
                        <span class="navi-text">Add Feedback</span>\
                    </a>\
                </li>';
                }
            }


        actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="viewDetail(`'+detail_url+'`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-eye"></i></span>\
                            <span class="navi-text">Detail</span>\
                        </a>\
                    </li>';
        if (permissions.edit) {
            if(data.appointment_type==1) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`' + edit_url + '`, `' + id + '`, `detail-actions`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>';
            } else if(data.appointment_type==2) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`' + edit_service_url + '`, `' + id + '`, `treatment-detail-actions`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>';
            }
        }
        if(data.appointment_type==2) {
            if (permissions.image_manage) {
               
            }

            if (permissions.measurement_manage) {
               
            }
        }

        if (permissions.patient_card) {
            actions += '<li class="navi-item">\
                        <a target="_blank" href="'+patient_url+'" class="navi-link">\
                            <span class="navi-icon"><i class="la la-user"></i></span>\
                            <span class="navi-text">Patient Card</span>\
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

        actions += '<li class="navi-item d-lg-none">\
                        <a href="javascript:void(0);" onclick="viewSmsLogs(`'+sms_logs_url+'`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-sms"></i></span>\
                        <span class="navi-text">SMS Log</span>\
                        </a>\
                    </li>';

        // Show WhatsApp option in mobile menu only if appointment_status is NOT 2 and scheduled_date is today and user has permission
        if (data.appointment_status != 2 && isToday && canSendWhatsApp) {
            actions += '<li class="navi-item  d-lg-none">\
                            <a href="javascript:void(0);" onclick="sendWhatsApp('+ id + ');" class="navi-link">\
                                <span class="navi-icon"><i class="lab la-whatsapp"></i></span>\
                                <span class="navi-text">Send WhatsApp</span>\
                            </a>\
                        </li>';
        }


        if (permissions.invoice_display) {
            if(data.invoice) {
                actions += '<li class="navi-item d-lg-none">\
                    <a  href="javascript:void(0);" onclick="displayInvoice(`' + invoice_display_url + '`, `' + id + '`);"  class="navi-link">\
                    <span class="navi-icon"><i class="la la-sms"></i></span>\
                    <span class="navi-text">View Invoice</span>\
                    </a>\
                </li>';
            }
        }

        if (permissions.invoice) {
            if(!data.invoice) {
                if (data.appointment_type == 2) {
                    actions += '<li class="navi-item d-lg-none">\
                        <a title="Create Invoice" href="javascript:void(0);" onclick="createTreatmentInvoice(`' + invoice_url + '`);"  class="navi-link">\
                        <span class="navi-icon"><i class="la la-file"></i></span>\
                        <span class="navi-text">Create Invoice</span>\
                        </a>\
                    </li>';
                }

                if(data.appointment_type == 1) {
                    actions += '<li class="navi-item d-lg-none">\
                        <a title="Create Invoice" href="javascript:void(0);" onclick="createConsultancyInvoice(`' + consultancy_invoice_url + '`);"  class="navi-link">\
                        <span class="navi-icon"><i class="la la-file"></i></span>\
                        <span class="navi-text">Create Invoice</span>\
                        </a>\
                    </li>';
                }
            }

        }


        actions += '</ul>\
            </div>\
        </div>';

        return actions;
    }
    return '';
}

function goToConsultancy(type, city_id, location_id, doctor_id, resource_id) {
    if (type == 'appointment') {
        $(".export-appointments").show();
        reInitTable();
    } else {
        $(".export-appointments").hide();
    }
    $(".appointment").addClass("d-none");
    $("." + type + "-section").removeClass("d-none");
    $(".change-tab").removeClass("nav-bar-active");
    $("." +type+ "-tab").addClass("nav-bar-active");
    setQueryStringParameter('tab', type);
    setQueryStringParameter('location_id', location_id);
    setQueryStringParameter('doctor_id', doctor_id);
    setQueryStringParameter('reload', 'true');
    $(".change-label").text($("." +type+ "-tab").text());
    if (type === 'treatment') {
        setQueryStringParameter('machine_id', resource_id);
        $("#treatment_city_filter").val(city_id).trigger("change");
        setTimeout( function () {
            $("#treatment_resource_filter").val(resource_id).trigger("change");
        },1100);
        setTimeout( function () {
            $("#treatment_doctor_filter").val(doctor_id).trigger("change");
        },1200);
    }
    if (type === 'consultancy') {
        $("#consultancy_city_filter").val(city_id).trigger("change");
    }
}
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
function addFeedback(url) {

    $("#modal_appointment_feedback").modal("show");


    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
          
            $('#add_patients_name').val(response.data.appointment.name);
            $('#treatment_name').val(response.data.appointment.service.name);
            $('#add_doctor_name').val(response.data.appointment.doctor.name);
            $('#location').val(response.data.appointment.location.name);
            $('#scheduled_date').val(response.data.appointment.scheduled_date);
            $('#add_patients_id').val(response.data.appointment.patient_id);
            $('#add_treatment_id').val(response.data.appointment.id);
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
        $("#appointment_patient_scheduled_time").text(formatDate(appointment?.scheduled_date, 'MMM, D, YYYY') + " at " + test);
        $("#appointment_doctor_name").text(doctor?.name ?? 'N/A');
        $("#appointment_city_name").text(city?.name ?? 'N/A');
        $("#appointment_center_name").text(location?.name ?? 'N/A');
        $("#appointment_appointment_status").text(appointment_status?.name ?? 'N/A');
        $("#appointment_service_consultancy_name").text(service?.name ?? 'N/A');
        $("#appointment_service_consultancy_name_title").text(service?.name ?? 'N/A');
        setAppointmentComments(appointment);
    } catch (e) {
        showException(e);
    }
}

function setAppointmentComments(appointment) {

    let appointment_comments = appointment.appointment_comments;
    let comment_html = '';
    if (appointment_comments.length) {
        Object.values(appointment_comments).forEach(function (comment) {
            comment_html += commentData(comment?.user?.name, comment?.created_at, comment?.comment);
        });
    }
    $("#appointment_commentsection").html(comment_html);
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

        let type_option = '';
        Object.entries(consultancy_types).forEach(function (consultancy_type) {
            type_option += '<option value="' + consultancy_type[0] + '">' + consultancy_type[1] + '</option>';
        });

        let service_option = '<option value="">Select a Service</option>';
        Object.entries(services).forEach(function (service) {
            service_option += '<option value="' + service[0] + '">' + service[1] + '</option>';
        });

        let city_option = '<option value="">Select a City</option>';
        Object.entries(cities).forEach(function (city) {
            city_option += '<option value="' + city[0] + '">' + city[1] + '</option>';
        });

        let location_option = '<option value="">Select a Location</option>';
        Object.entries(locations).forEach(function (location) {
            location_option  += '<option value="' + location[0] + '">' + location[1] + '</option>';
        });

        let doctor_option = '<option value="">Select a Doctor</option>';
        Object.entries(doctors).forEach(function (doctor) {
            doctor_option  += '<option value="' + doctor[0] + '">' + doctor[1] + '</option>';
        });

        let gender_option = '<option value="">Select a Gender</option>';
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

        $("#edit_old_patient_phone").val(appointment?.patient?.phone);

        if (permissions.contact) {
            $("#edit_patient_phone").val(appointment?.patient?.phone);
        } else {
            $("#edit_patient_phone").val("***********").attr("readonly", true);
        }

        $("#back-date").val(back_date_config.data);
        $("#old_phone").val(appointment?.lead?.patient?.phone);
        $("#lead_id").val(appointment?.lead_id);
        $("#appointment_id").val(appointment?.id);
        $("#resourceRotaDayID").val(resourceHadRotaDay?.id);
        $("#start_time").val(resourceHadRotaDay?.start_time);
        $("#end_time").val(resourceHadRotaDay?.end_time);
        $("#consultancy_appointment_type").val(appointment?.appointment_type_id);



    } catch (error) {
        showException(error);
    }

}

function setTreatmentEditData(response) {

    try {

        let appointment = response.data.appointment;
        let doctors = response.data.doctors;
        let services = response.data.services;
        let resourceHadRotaDay = response.data.resourceRotaDay;
        let machineHadRotaDay = response.data.machineRotaDay;
        let editPermissions = response.data.permissions || {};

        // Build service options from all active child services
        let service_option = '<option value="">Select a Treatment</option>';
        if (services) {
            Object.entries(services).forEach(function (service) {
                service_option += '<option value="' + service[0] + '">' + service[1] + '</option>';
            });
        }

        // Build doctor options
        let doctor_option = '<option value="">Select a Doctor</option>';
        if (doctors) {
            Object.entries(doctors).forEach(function (doctor) {
                doctor_option += '<option value="' + doctor[0] + '">' + doctor[1] + '</option>';
            });
        }

        $("#treatment_service_id").html(service_option).val(appointment.service_id);
        $("#edit_treatment_service_id").html(service_option).val(appointment.service_id);
        $("#edit_treatment_doctor_id").html(doctor_option).val(appointment?.doctor_id);

        // Set hidden fields for preserved data
        $("#edit_treatment_city_id").val(appointment.city_id);
        $("#edit_treatment_location_id").val(appointment.location_id);
        $("#edit_treatment_machine_id").val(appointment.resource_id);
        $("#edit_treatment_patient_name").val(appointment?.patient?.name);
        $("#edit_treatment_patient_phone").val(appointment?.patient?.phone);
        $("#edit_old_treatment_patient_phone").val(appointment?.patient?.phone);
        $("#edit_treatment_patient_gender").val(appointment?.patient?.gender);

        // Set patient name in modal heading
        $("#edit_treatment_patient_name_display").text(appointment?.patient?.name || '');

        // Store the original doctor ID and hide warning
        $("#edit_treatment_original_doctor_id").val(appointment?.doctor_id);
        $('#edit_treatment_doctor_warning').addClass('d-none');
        $('#edit_use_previous_doctor').prop('checked', false);
        $('#edit_use_selected_doctor').prop('checked', false);
        $('#edit_use_selected_doctor_container').addClass('d-none');
        // Enable submit button by default (will be disabled if doctor change detected)
        $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);

        // Set scheduled date - API now returns it in Y-m-d format
        let scheduledDate = appointment.scheduled_date;
        $("#edit_treatment_scheduled_date_old").val(scheduledDate);
        
        // Destroy existing datepicker and reinitialize with the correct date
        var $dateField = $("#edit_treatment_scheduled_date");
        $dateField.datepicker('destroy');
        $dateField.val(scheduledDate);
        $dateField.datepicker({
            todayHighlight: true,
            orientation: 'bottom',
            format: 'yyyy-mm-dd',
            autoclose: true
        });
        const [hourString, minute] = appointment.scheduled_time.split(":");
        const hour = +hourString % 24;
        var test = (hour % 12 || 12) + ":" + minute + (hour < 12 ? " AM" : " PM");
        $("#edit_treatment_scheduled_time").val(test);
        $("#scheduled_treatment_time_old").val(test);

        $("#treatment_leadId").val(appointment?.lead_id);
        $("#treatment_patientId").val(appointment?.patient_id);
        $("#treatment_appointment_id").val(appointment?.id);
        $("#treatment_resourceRotaDayID").val(resourceHadRotaDay?.id);
        $("#treatment_machineRotaDayID").val(machineHadRotaDay?.id);
        $("#treatment_start_time").val(resourceHadRotaDay?.start_time);
        $("#treatment_end_time").val(resourceHadRotaDay?.end_time);

        $("#treatment_appointment_type").val(appointment?.appointment_type_id);

        // Apply permission-based field restrictions
        // Service field
        if (editPermissions.can_edit_service === false) {
            $("#edit_treatment_service_id").prop('disabled', true);
        } else {
            $("#edit_treatment_service_id").prop('disabled', false);
        }

        // Doctor field
        if (editPermissions.can_edit_doctor === false) {
            $("#edit_treatment_doctor_id").prop('disabled', true);
        } else {
            $("#edit_treatment_doctor_id").prop('disabled', false);
        }

        // Schedule fields (date and time)
        if (editPermissions.can_edit_schedule === false) {
            $("#edit_treatment_scheduled_date").prop('disabled', true);
            $("#edit_treatment_scheduled_time").prop('disabled', true);
        } else {
            $("#edit_treatment_scheduled_date").prop('disabled', false);
            $("#edit_treatment_scheduled_time").prop('disabled', false);
        }

        // Initialize select2 on dropdowns
        reInitSelect2("#edit_treatment_service_id", "#modal_treatment_edit");
        reInitSelect2("#edit_treatment_doctor_id", "#modal_treatment_edit");

        // Check if doctor change warning should be shown after modal data is loaded
        setTimeout(function() {
            checkEditTreatmentDoctorChange();
        }, 100);

    } catch (error) {
        showException(error);
    }

}

function viewSmsLogs($route) {

    $("#modal_sms_log").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setSmsLogs(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setSmsLogs(response) {

    try {

        let SMSLogs = response.data.SMSLogs;
        let sms_statuses = response.data.sms_statuses;

        let statuses =  makeArray(sms_statuses);

        let rows = noRecordFoundTable(6);

        if (SMSLogs.length) {
            let sent_url = route('admin.appointments.resend_sms');
            rows = '';
            Object.values(SMSLogs).forEach(function (smsLog, index) {

                if(smsLog.invoice_id === null) {
                    rows += '<tr>';
                    rows += '<td>' + smsLog.to + '</td>';
                    rows += '<td><a href="javascript:void(0);" onclick="toggleText($(this))">';
                    rows += '<span class="short_text" style="display: block">' + smsLog.text.slice(0, 50).concat('...') + '</span>';

                    rows += '<span class="full_text" style="display:none; text-underline: none;"><pre>' + smsLog.text + '</pre></span>';
                    '</a></td>';

                    if(smsLog.status) {
                        rows += '<td id="smsRow{'+smsLog.id+'">Yes</td>';
                    } else {
                        rows += '<td><span class="text-center" id="spanRow'+smsLog.id+'">No</span>\
                        <br/><a id="clickRow'+smsLog.id+'" href="javascript:void(0)" onclick="resendSMS('+smsLog.id+', `'+sent_url+'`);" class="btn btn-sm btn-success spinner-button" data-toggle="tooltip" title="Resend SMS">' +
                            '<i class="la la-send-o"></i></a></td>';
                    }

                    if(smsLog.is_refund == "Yes") {
                        rows += '<td>smsLog.is_refund</td>';
                    } else {
                        rows += '<td></td>';
                    }

                    if (typeof statuses[smsLog.log_type] !== 'undefined') {
                        rows += '<td>'+statuses[smsLog.log_type]+'</td>';
                    } else {
                        rows += '<td>N/A</td>';
                    }

                    rows += '<td>' + formatDate(smsLog.created_at) + '</td>';
                    rows += '</tr>';
                }
            });
        }

        $("#appoint_sms_log_rows").html(rows);

    } catch (error) {
        showException(error);
    }

}

function resetCustomFilters() {
    $('.appointment_patient_id').val(null).trigger('change');
    $(".filter-field").val('');
    setQueryStringParameter('type');
    setQueryStringParameter('from');
    setQueryStringParameter('to');
    setQueryStringParameter('center_id');
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function() {
        let filters =  {
            delete: '',
            patient_id: $("#treatment_patient_id").val(),
            date_from: $("#treatment_search_start").val(),
            date_to: $("#treatment_appoint_end").val(),
            service_id: $("#treatment_search_service").val(),
            location_id: $("#treatment_search_centre").val(),
            doctor_id: $("#treatment_search_doctor").val(),
            appointment_status_id: $("#treatment_search_status").val(),
            created_at: $("#date_range").val(),
            created_by: $("#treatment_search_created_by").val(),
            converted_by: $("#treatment_search_rescheduled_by").val(),
            updated_by: $("#treatment_search_updated_by").val(),
            filter: 'filter',
        }

        if($("#treatment_search_service").val() == 13){
            resetFilters(datatable);
        }
        else{
            datatable.search(filters, 'search');
        }
        datatable.search(filters, 'search');
    });

}
function resetFilters(datatable) {

    let filters =  {
        delete: '',
        patient_id: '',
        name: '',
        date_from: '',
        date_to: '',
        service_id: '',
        location_id: '',
        doctor_id: '',
        appointment_status_id: '',
        created_at: '',
        created_by: '',
        converted_by: '',
        updated_by: '',
        filter: 'filter_cancel',
    }
    datatable.search(filters, 'search');


}
function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function() {
        $('#treatment_search_service').empty();
        // Clear select2 patient search
        $('#treatment_patient_id').val(null).trigger('change');
        let filters =  {
            delete: '',
            patient_id: '',
            name: '',
            date_from: '',
            date_to: '',
            service_id: '',
            location_id: '',
            doctor_id: '',
            appointment_status_id: '',
            created_at: '',
            created_by: '',
            converted_by: '',
            updated_by: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    try {
        let appointment_statuses = filter_values.appointment_statuses;
        let appointment_types = filter_values.appointment_types;
        let cities = filter_values.cities;
        let doctors = filter_values.doctors;
        let locations = filter_values.locations;
        let regions = filter_values.regions;
        let services = filter_values.services;
        let users = filter_values.users;
        let consultancy_types = filter_values.consultancy_types;

        let appoint_status_options = '<option value="">All</option>';
        Object.entries(appointment_statuses).forEach(function (status, index) {
            appoint_status_options += '<option value="' + status[0] + '">' + status[1] + '</option>';
        });

        let appoint_type_options = '<option value="">All</option>';
        Object.entries(appointment_types).forEach(function (appointment_type, index) {
            appoint_type_options += '<option value="' + appointment_type[0] + '">' + appointment_type[1] + '</option>';
        });

        let city_options = '<option value="">Select City</option>';
        Object.entries(cities).forEach(function (city, index) {
            city_options += '<option value="' + city[0] + '">' + city[1] + '</option>';
        });

        let doctor_options = '<option value="">All</option>';
        Object.entries(doctors).forEach(function (doctor, index) {
            doctor_options += '<option value="' + doctor[0] + '">' + doctor[1] + '</option>';
        });

        let location_options = '<option value="">All</option>';
        Object.entries(locations).forEach(function (location, index) {
            location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
        });

        let region_options = '<option value="">All</option>';
        Object.entries(regions).forEach(function (region, index) {
            region_options += '<option value="' + region[0] + '">' + region[1] + '</option>';
        });

        let service_options = '<option value=""></option>';
        Object.values(services).forEach(function (service, index) {
            if (service.name == 'All Services') {
                service_options += '<option value="' + service.id + '" selected>' + service.name + '</option>';
            } else {
                service_options += '<option value="bold-' + service.id + '">' + service.name + '</option>';
                Object.values(service.children).forEach(function (child, index) {
                    service_options += '<option value="' + child.id + '">' + '\t&nbsp; \t&nbsp; \t&nbsp;'+child.name + '</option>';
                });
            }
        });

        let user_options = '<option value="">All</option>';
        Object.entries(users).forEach(function (user, index) {
            user_options += '<option value="' + user[0] + '">' + user[1] + '</option>';
        });

        let consultancy_type_options = '<option value="">All</option>';
        Object.entries(consultancy_types).forEach(function (consultancy_type, index) {
            consultancy_type_options += '<option value="' + consultancy_type[0] + '">' + consultancy_type[1] + '</option>';
        });

        let created_by = $("#treatment_search_created_by").val();
        if (created_by == null || created_by == '') {
            $("#treatment_search_created_by").html(user_options);
        }

        let updated_by = $("#treatment_search_updated_by").val();
        if (updated_by == null || updated_by == '') {
            $("#treatment_search_updated_by").html(user_options);
        }

        let rescheduled_by = $("#treatment_search_rescheduled_by").val();
        if (rescheduled_by == null || rescheduled_by == '') {
            $("#treatment_search_rescheduled_by").html(user_options);
        }

        let status = $("#treatment_search_status").val();
        if (status == null || status == '') {
            $("#treatment_search_status").html(appoint_status_options);
        }

        let doctor_id = $("#treatment_search_doctor").val();
        if (doctor_id == null || doctor_id == '') {
            $("#treatment_search_doctor").html(doctor_options);
        }

        let centre_id = $("#treatment_search_centre").val();
        if (centre_id == null || centre_id == '') {
            $("#treatment_search_centre").html(location_options);
        }

        let city_id = $("#treatment_search_city").val();

        if (city_id == null || city_id == '') {
            $("#treatment_search_city").html(city_options);
        }
        let region_id = $("#treatment_search_region").val();

        if (region_id == null || region_id == '') {
            $("#treatment_search_region").html(region_options);
        }

        let consultancy_type = $("#treatment_search_consultancy_type").val();

        if (consultancy_type == null || consultancy_type == '') {
            $("#treatment_search_consultancy_type").html(consultancy_type_options);
        }


        $("#treatment_search_created_by").val(active_filters.created_by);
        $("#treatment_search_updated_by").val(active_filters.updated_by);
        $("#treatment_search_rescheduled_by").val(active_filters.converted_by);
        $("#treatment_search_type").val(active_filters.appointment_type_id);
        $("#treatment_search_status").val(active_filters.appointment_status_id);
        $("#treatment_search_doctor").val(active_filters.doctor_id);
        $("#treatment_search_centre").val(active_filters.location_id);
        $("#treatment_search_city").val(active_filters.city_id);
        $("#treatment_search_region").val(active_filters.region_id);
        $("#treatment_search_service").val(active_filters.service_id);
        $("#treatment_search_consultancy_type").val(active_filters.consultancy_type);

        /*For Consultancy filter*/
        let city_value = $("#treatment_city_filter").val();
        let service_value = $("#treatment_search_service").val();
        if(service_value==null){
            $("#treatment_search_service").html(service_options);
        }

        if (city_value == null) {
            $("#treatment_city_filter").html(city_options);
        }

        getUserCity();

    } catch (error) {
        showException(error);
    }
}

function reaCustomFilters() {

    $('.appointment_patient_id').val(null).trigger('change');
    $(".filter-field").val('');

    setQueryStringParameter('type');
    setQueryStringParameter('from');
    setQueryStringParameter('to');
    setQueryStringParameter('center_id');
    /* $(".advance-filters").slideUp();
     $(".advance-arrow").addsClass("fa-caret-right").removeClass("fa-caret-down")*/
}

jQuery(document).ready(function () {

    $("#Add_appointment_comment").click(function () {
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
                },

            });
        } else {
            toastr.error("Please fill out the comment field");
        }
        $('#appointment_cment')[0].reset();
    });

});


function createConsultancyInvoice(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'GET',
        cache: false,
        success: function(response) {

            $("#create_consultancy_invoice").html(response)

            $("#modal_create_consultancy_invoice").modal("show");
            $("#addinvoice").show();
            customDatePicker();
        },
        error: function(xhr, ajaxOptions, thrownError) {
            toastr.error("Unable to process the request");
        }
    });

}

function createTreatmentInvoice(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'GET',
        cache: false,
        success: function(response) {

            $("#create_treatment_invoice").html(response)
            $('#package_id_create').change();
            $("#modal_create_treatment_invoice").modal("show");
            customDatePicker();

        },
        error: function(xhr, ajaxOptions, thrownError) {
            toastr.error("Unable to process the request");
        }
    });

}

function displayInvoice(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'GET',
        cache: false,
        success: function(response) {

            $("#display_invoice").html(response)

            $("#modal_display_invoice").modal("show");
        },
        error: function(xhr, ajaxOptions, thrownError) {
            toastr.error("Unable to process the request");
        }
    });

}

/*Schedule validation*/
var AppointScheduleValidation = function () {
    // Private functions
    var Validation = function () {
        let modal_id = 'modal_update_scheduled_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    scheduled_date: {
                        validators: {
                            notEmpty: {
                                message: 'The schedule date field is required'
                            }
                        }
                    },
                    scheduled_time: {
                        validators: {
                            notEmpty: {
                                message: 'The schedule time field is required'
                            }
                        }
                    }
                },

                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    // Bootstrap Framework Integration
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                    // Validate fields when clicking the Submit button
                    submitButton: new FormValidation.plugins.SubmitButton(),
                }
            }
        );
        validate.on('core.form.invalid', function (e) {
            select2Validation();
        });
        validate.on('core.form.valid', function(event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {

                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable('treatment');
                } else {
                    toastr.error(response.message);
                }
            }, null);
        });
    }

    return {
        // public functions
        init: function() {
            Validation();
        }
    };
}();

// Function to handle doctor change detection in edit treatment
var isResettingDoctor = false; // Flag to prevent infinite loop

function checkEditTreatmentDoctorChange() {
    // Skip if we're in the middle of resetting the doctor
    if (isResettingDoctor) {
        return;
    }

    var selectedDoctorId = $("#edit_treatment_doctor_id").val();
    var originalDoctorId = $("#edit_treatment_original_doctor_id").val();
    var patientId = $("#treatment_patientId").val();
    var serviceId = $("#edit_treatment_service_id").val();
    var locationId = $("#edit_treatment_location_id").val();
    var currentAppointmentId = $("#treatment_appointment_id").val();

    // If no doctor selected or no patient ID, just hide warning and enable submit
    if (!selectedDoctorId || !patientId || !serviceId) {
        $('#edit_treatment_doctor_warning').addClass('d-none');
        $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);
        return;
    }

    // Get the scheduled date and time for rota check
    var scheduledDate = $("#edit_treatment_scheduled_date").val();
    var scheduledTime = $("#edit_treatment_scheduled_time").val();
    
    // Convert time to 24-hour format for API (e.g., "1:00 PM" -> "13:00:00")
    var startDateTime = null;
    if (scheduledDate && scheduledTime) {
        var timeParts = scheduledTime.match(/(\d+):(\d+)\s*(AM|PM)/i);
        if (timeParts) {
            var hours = parseInt(timeParts[1]);
            var minutes = timeParts[2];
            var period = timeParts[3].toUpperCase();
            
            if (period === 'PM' && hours !== 12) {
                hours += 12;
            } else if (period === 'AM' && hours === 12) {
                hours = 0;
            }
            
            var formattedTime = String(hours).padStart(2, '0') + ':' + minutes + ':00';
            startDateTime = scheduledDate + 'T' + formattedTime;
        }
    }

    // Call backend to check if patient has past arrived treatments of the same service
    $.ajax({
        type: 'GET',
        url: route('admin.treatments.check_patient_last_treatment'),
        data: {
            patient_id: patientId,
            service_id: serviceId,
            location_id: locationId,
            exclude_appointment_id: currentAppointmentId,
            start: startDateTime
        },
        success: function(response) {
            if (response.status && response.data.last_treatment) {
                var lastTreatment = response.data.last_treatment;
                var lastDoctorId = lastTreatment.doctor_id;
                var lastDoctorName = lastTreatment.doctor_name;
                var lastServiceId = lastTreatment.service_id;
                var hasDoctorRota = lastTreatment.has_doctor_rota;
                var canEditDoctor = response.data.can_edit_doctor || false;

                // Check if the last treatment's service matches current service
                if (lastServiceId == serviceId) {
                    // Service matches, now check if doctor is different
                    if (lastDoctorId != selectedDoctorId) {
                        // Show warning message
                        $('#edit_warning_message').html('The last session for this treatment was performed by ' + lastDoctorName + '.');
                        
                        // Get selected doctor name for second option
                        var selectedDoctorName = $('#edit_treatment_doctor_id option:selected').text();
                        
                        // Store the selected doctor ID before any changes
                        $('#edit_treatment_doctor_warning').data('selected-doctor-id', selectedDoctorId);
                        $('#edit_treatment_doctor_warning').data('selected-doctor-name', selectedDoctorName);
                        
                        if (hasDoctorRota) {
                            // Doctor has rota, enable option 1 and auto-select it
                            $('#edit_previous_doctor_option').html('<strong>Schedule the treatment with ' + lastDoctorName + '</strong>');
                            $('#edit_use_previous_doctor').prop('disabled', false);
                            $('#edit_use_previous_doctor').prop('checked', true);
                            
                            // Check if user has permission to edit doctor
                            if (canEditDoctor) {
                                // Show second radio option for users with permission
                                $('#edit_selected_doctor_option').html('<strong>Proceed with ' + selectedDoctorName + '</strong>');
                                $('#edit_use_selected_doctor_container').removeClass('d-none');
                                $('#edit_use_selected_doctor').prop('disabled', false);
                                
                                // Don't auto-change the doctor, let user choose
                                // Enable submit button
                                $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);
                            } else {
                                // Hide second option for users without permission
                                $('#edit_use_selected_doctor_container').addClass('d-none');
                                
                                // Update doctor to previous doctor
                                isResettingDoctor = true;
                                $('#edit_treatment_doctor_id').val(lastDoctorId).trigger('change.select2');
                                setTimeout(function() {
                                    isResettingDoctor = false;
                                }, 100);
                                
                                // Enable submit button
                                $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);
                            }
                        } else {
                            // Doctor doesn't have rota
                            $('#edit_previous_doctor_option').html('<strong>Schedule the treatment with ' + lastDoctorName + '</strong> <span class="text-danger">(Doctor is not available in this time slot)</span>');
                            $('#edit_use_previous_doctor').prop('disabled', true);
                            $('#edit_use_previous_doctor').prop('checked', false);
                            
                            // Check if user has permission to edit doctor
                            if (canEditDoctor) {
                                // Show second option and auto-select it
                                $('#edit_selected_doctor_option').html('<strong>Proceed with ' + selectedDoctorName + '</strong>');
                                $('#edit_use_selected_doctor_container').removeClass('d-none');
                                $('#edit_use_selected_doctor').prop('disabled', false);
                                $('#edit_use_selected_doctor').prop('checked', true);
                                
                                // Enable submit button - user can proceed with selected doctor
                                $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);
                            } else {
                                // Hide second option and keep submit disabled
                                $('#edit_use_selected_doctor_container').addClass('d-none');
                                $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', true);
                            }
                        }

                        // Show the warning div
                        $('#edit_treatment_doctor_warning').removeClass('d-none');

                        // Store the last doctor ID for the radio button handler
                        $('#edit_treatment_doctor_warning').data('last-doctor-id', lastDoctorId);
                        $('#edit_treatment_doctor_warning').data('has-doctor-rota', hasDoctorRota);
                        $('#edit_treatment_doctor_warning').data('can-edit-doctor', canEditDoctor);
                    } else {
                        // Last treatment was with the same doctor, no warning needed
                        $('#edit_treatment_doctor_warning').addClass('d-none');
                        $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);
                    }
                } else {
                    // Service changed - check if there are other treatments with this new service
                    // If no other treatments found, allow the change
                    $('#edit_treatment_doctor_warning').addClass('d-none');
                    $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);
                }
            } else {
                // No past arrived treatment found for this service
                // This is the first/only treatment - allow any doctor selection
                $('#edit_treatment_doctor_warning').addClass('d-none');
                $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);
            }
        },
        error: function() {
            // On error, hide warning and enable submit
            $('#edit_treatment_doctor_warning').addClass('d-none');
            $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);
        }
    });
}

// Handle radio button changes
$(document).on('change', '#edit_use_previous_doctor', function() {
    if ($(this).is(':checked')) {
        isResettingDoctor = true;
        // Use the last doctor ID from the past arrived treatment
        var lastDoctorId = $('#edit_treatment_doctor_warning').data('last-doctor-id');
        $('#edit_treatment_doctor_id').val(lastDoctorId).trigger('change.select2');

        // Enable submit button
        $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);

        setTimeout(function() {
            isResettingDoctor = false;
        }, 100);
    }
});

$(document).on('change', '#edit_use_selected_doctor', function() {
    if ($(this).is(':checked')) {
        isResettingDoctor = true;
        // Restore the originally selected doctor
        var selectedDoctorId = $('#edit_treatment_doctor_warning').data('selected-doctor-id');
        $('#edit_treatment_doctor_id').val(selectedDoctorId).trigger('change.select2');
        
        // Enable submit button
        $('#modal_edit_treatment_form button[type="submit"]').prop('disabled', false);
        
        setTimeout(function() {
            isResettingDoctor = false;
        }, 100);
    }
});

// Attach the check function to doctor dropdown change event
$(document).on('change', '#edit_treatment_doctor_id', function() {
    checkEditTreatmentDoctorChange();
});

// Attach the check function to service dropdown change event
$(document).on('change', '#edit_treatment_service_id', function() {
    checkEditTreatmentDoctorChange();
});

function copyWhatsAppMessage(appointmentId) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.get_whatsapp_data'),
        type: 'GET',
        data: { id: appointmentId },
        cache: false,
        success: function (response) {
            if (response.status) {
                // Replace \n with actual line breaks
                let message = response.data.message.replace(/\\n/g, '\n');

                // Copy to clipboard
                if (navigator.clipboard && window.isSecureContext) {
                    // Use modern clipboard API
                    navigator.clipboard.writeText(message).then(function() {
                        toastr.success('Message copied to clipboard!');
                    }).catch(function(err) {
                        console.error('Failed to copy message:', err);
                        fallbackCopyTextToClipboard(message);
                    });
                } else {
                    // Fallback for older browsers or non-HTTPS
                    fallbackCopyTextToClipboard(message);
                }
            } else {
                toastr.error(response.message || 'Unable to fetch WhatsApp message');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Copy Message Error:', xhr);
            errorMessage(xhr);
        }
    });
}

// Fallback function for copying text to clipboard
function fallbackCopyTextToClipboard(text) {
    let textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.top = "-9999px";
    textArea.style.left = "-9999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        let successful = document.execCommand('copy');
        if (successful) {
            toastr.success('Message copied to clipboard!');
        } else {
            toastr.error('Failed to copy message');
        }
    } catch (err) {
        console.error('Fallback: Unable to copy', err);
        toastr.error('Failed to copy message');
    }

    document.body.removeChild(textArea);
}

function sendWhatsApp(appointmentId) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.get_whatsapp_data'),
        type: 'GET',
        data: { id: appointmentId },
        cache: false,
        success: function (response) {
         
            if (response.status) {
            
                if (!response.data.whatsapp) {
                    console.error('ERROR: WhatsApp number not found');
                    toastr.error('Customer WhatsApp number not found');
                    return;
                }

                // Replace \n with actual line breaks
                let message = response.data.message.replace(/\\n/g, '\n');
                let phoneNumber = response.data.whatsapp;
                let encodedMessage = encodeURIComponent(message);

             
                // Try to open WhatsApp desktop app first
                let whatsappAppUrl = 'whatsapp://send?phone=' + phoneNumber + '&text=' + encodedMessage;
                let whatsappWebUrl = 'https://web.whatsapp.com/send?phone=' + phoneNumber + '&text=' + encodedMessage;

              
                // Track if app opened successfully
                let appOpened = false;
                let startTime = Date.now();

                // Try to open WhatsApp app directly
                window.location.href = whatsappAppUrl;

                // Detect if user switched to WhatsApp app using blur event
                let blurHandler = function() {
                    let blurTime = Date.now();
                    // If blur happens quickly (within 2 seconds), likely the app opened
                    if (blurTime - startTime < 2000) {
           
                        appOpened = true;
                        window.removeEventListener('blur', blurHandler);
                    }
                };
                window.addEventListener('blur', blurHandler);

                // Also check visibility change
                let visibilityHandler = function() {
                    if (document.hidden) {
                    
                        appOpened = true;
                        document.removeEventListener('visibilitychange', visibilityHandler);
                    }
                };
                document.addEventListener('visibilitychange', visibilityHandler);

                // Wait and check if app opened, if not fall back to web
                setTimeout(function() {
                    // Clean up listeners
                    window.removeEventListener('blur', blurHandler);
                    document.removeEventListener('visibilitychange', visibilityHandler);

                    // If app didn't open, fall back to web
                    if (!appOpened) {
                    
                        let whatsappWindow = window.open(whatsappWebUrl, 'whatsapp_window');
                        if (whatsappWindow) {
                            whatsappWindow.focus();
          
                        } else {
                            console.error('ERROR: Failed to open WhatsApp Web (popup blocker?)');
                        }
                    } else {
                        console.log('WhatsApp app opened successfully - not opening web version');
                    }
                }, 1500);

            } else {
                console.error('ERROR: Response status false');
                console.error('Error Message:', response.message);
                toastr.error(response.message || 'Unable to fetch WhatsApp data');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('=== WhatsApp AJAX Error ===');
            console.error('XHR:', xhr);
            console.error('Status:', xhr.status);
            console.error('Response Text:', xhr.responseText);
            console.error('Thrown Error:', thrownError);
            errorMessage(xhr);
        }
    });
}

jQuery(document).ready(function() {
    AppointScheduleValidation.init();
    $("#date_range").val("");
});
