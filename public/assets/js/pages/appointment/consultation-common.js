/**
 * Consultation Common Functions
 * Shared functions used by both main consultations module and patient card consultations tab
 */

// Edit status function
function editStatus(id) {
    $("#modal_change_appointment_status").modal("show");
    $("#modal_update_status_form").attr("action", route('admin.appointments.storeappointmentstatus'));

    $.ajax({
        url: route('admin.appointments.showappointmentstatus'),
        type: "GET",
        data: { id: id },
        cache: false,
        success: function (response) {
            if (response.status) {
                setStatusData(response, id);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

// Set status data for status change modal
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
                base_status_option += '<option value="' + base_status[0] + '">' + base_status[1] + '</option>';
            });
        }

        let appoint_status_option = '<option value="">Select Child Status</option>';
        if (appointment_statuses) {
            Object.entries(appointment_statuses).forEach(function (appointment_status) {
                appoint_status_option += '<option value="' + appointment_status[0] + '">' + appointment_status[1] + '</option>';
            });
        }

        $("#base_appointment_status_id").html(base_status_option);
        $("#appointment_status_id").html(appoint_status_option);
        $("#appointment_type_id").val(appointment_type_id);
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
            if (base_appointments[appointments.appointment_status?.parent_id]?.is_comment == 0
                && appointments?.appointment_status?.is_comment == 0) {
            } else {
                $("#appointment_reason").hide();
                $("#appointment_status_id_section").hide();
            }
            if (base_appointments[appointments.appointment_status.parent_id].is_comment == 0
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

// Edit row function for consultations
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
            errorMessage(xhr);
        }
    });
}

// Set edit data for consultation edit modal
function setEditData(response) {
    try {
        console.log('setEditData called', response);
        let appointment = response.data.appointment;
        let back_date_config = response.data.back_date_config;
        let consultancy_types = response.data.consultancy_type;
        let doctors = response.data.doctors;
        let resourceHadRotaDay = response.data.resourceHadRotaDay;
        let services = response.data.services;
        let setting = response.data.setting;
        let genders = response.data.genders;
        let permissions = response.data.permissions;
        console.log('All data extracted successfully');

        let type_option = '<option value="">Select a Consultancy Type</option>';
        Object.entries(consultancy_types).forEach(function (type) {
            type_option += '<option value="' + type[0] + '">' + type[1] + '</option>';
        });

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

        let gender_option = '<option value="">Select a Gender</option>';
        if (genders) {
            Object.entries(genders).forEach(function (gender) {
                gender_option += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
            });
        }

        // Set modal heading with patient name in blue color
        let patientName = appointment?.patient?.name || "Patient";
        $("#edit_consultation_heading").html("Edit <span class='text-primary'>" + patientName + "'s</span> Consultation");

        $("#edit_consultancy_type").html(type_option).val(appointment.consultancy_type);
        $("#edit_treatment").html(service_option).val(appointment.service_id);
        $("#consultancy_service_id").html(service_option).val(appointment.service_id);
        $("#edit_doctor").html(doctor_option).val(appointment?.doctor_id);

        // Check if status is Arrived (2) or Converted (16)
        let isArrivedOrConverted = (appointment.appointment_status_id == 2 || appointment.appointment_status_id == 16);
        
        // Debug logs
        console.log('=== Edit Consultation Field Permissions Debug ===');
        console.log('Appointment Status ID:', appointment.appointment_status_id);
        console.log('Is Arrived or Converted:', isArrivedOrConverted);
        console.log('Permissions Object:', permissions);
        console.log('update_consultation_service:', permissions.update_consultation_service);
        console.log('update_consultation_doctor:', permissions.update_consultation_doctor);
        console.log('update_consultation_schedule:', permissions.update_consultation_schedule);
        
        // For arrived/converted status: use permissions to control field editing
        // For other statuses: always enable fields regardless of permissions
        if (isArrivedOrConverted) {
            console.log('Status is Arrived/Converted - applying permission checks');
            
            if (permissions.update_consultation_service) {
                console.log('Service field: ENABLED (has permission)');
                $("#edit_treatment").prop('disabled', false).removeClass('bg-light');
            } else {
                console.log('Service field: DISABLED (no permission)');
                $("#edit_treatment").prop('disabled', true).addClass('bg-light');
            }
            
            if (permissions.update_consultation_doctor) {
                console.log('Doctor field: ENABLED (has permission)');
                $("#edit_doctor").prop('disabled', false).removeClass('bg-light');
            } else {
                console.log('Doctor field: DISABLED (no permission)');
                $("#edit_doctor").prop('disabled', true).addClass('bg-light');
            }
            
            if (permissions.update_consultation_schedule) {
                console.log('Schedule fields: ENABLED (has permission)');
                $("#edit_scheduled_date").prop('disabled', false).removeClass('bg-light');
                $("#edit_scheduled_time").prop('disabled', false).removeClass('bg-light');
            } else {
                console.log('Schedule fields: DISABLED (no permission)');
                $("#edit_scheduled_date").prop('disabled', true).addClass('bg-light');
                $("#edit_scheduled_time").prop('disabled', true).addClass('bg-light');
            }
        } else {
            console.log('Status is NOT Arrived/Converted - enabling all fields');
            $("#edit_treatment").prop('disabled', false).removeClass('bg-light');
            $("#edit_doctor").prop('disabled', false).removeClass('bg-light');
            $("#edit_scheduled_date").prop('disabled', false).removeClass('bg-light');
            $("#edit_scheduled_time").prop('disabled', false).removeClass('bg-light');
        }
        console.log('=== End Debug ===');

        $("#edit_scheduled_date").val(appointment.scheduled_date);
        $("#scheduled_date_old").val(appointment.scheduled_date);
        $("#edit_scheduled_time").val(appointment.scheduled_time);
        $("#scheduled_time_old").val(appointment.scheduled_time);

        $("#back-date").val(back_date_config.data);
        $("#old_phone").val(appointment?.lead?.patient?.phone);
        $("#lead_id").val(appointment?.lead_id);
        $("#appointment_id").val(appointment?.id);
        $("#resourceRotaDayID").val(resourceHadRotaDay?.id);
        $("#start_time").val(resourceHadRotaDay?.start_time);
        $("#end_time").val(resourceHadRotaDay?.end_time);
        $("#consultancy_appointment_type").val(appointment?.appointment_type_id);
        $("#edit_location_id").val(appointment?.location_id);

        // Destroy existing Select2 instances first to ensure clean state
        if ($("#edit_treatment").hasClass("select2-hidden-accessible")) {
            $("#edit_treatment").select2('destroy');
        }
        if ($("#edit_doctor").hasClass("select2-hidden-accessible")) {
            $("#edit_doctor").select2('destroy');
        }
        
        // Reinitialize select2 for the dropdowns AFTER all values are set and permissions applied
        $("#edit_treatment").select2();
        $("#edit_doctor").select2();
        
        console.log('setEditData completed successfully');
        console.log('Service field initial value:', $("#edit_treatment").val());

    } catch (error) {
        console.error('Error in setEditData:', error);
        showException(error);
    }
}

// View appointment detail
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

// Set appointment detail data
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
        showException(e);
    }
}

// Set appointment comments
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

// View SMS logs
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

// Set SMS logs data
function setSmsLogs(response) {
    try {
        let SMSLogs = response.data.SMSLogs;
        let sms_statuses = response.data.sms_statuses;
        let statuses = makeArray(sms_statuses);
        let rows = noRecordFoundTable(6);

        if (SMSLogs.length) {
            let sent_url = route('admin.appointments.resend_sms');
            rows = '';
            Object.values(SMSLogs).forEach(function (smsLog, index) {
                if (smsLog.invoice_id === null) {
                    rows += '<tr>';
                    rows += '<td>' + smsLog.to + '</td>';
                    rows += '<td><a href="javascript:void(0);" onclick="toggleText($(this))">';
                    rows += '<span class="short_text" style="display: block">' + smsLog.text.slice(0, 50).concat('...') + '</span>';
                    rows += '<span class="full_text" style="display:none; text-underline: none;"><pre>' + smsLog.text + '</pre></span>';
                    '</a></td>';

                    if (smsLog.status) {
                        rows += '<td id="smsRow{' + smsLog.id + '">Yes</td>';
                    } else {
                        rows += '<td><span class="text-center" id="spanRow' + smsLog.id + '">No</span>\
                        <br/><a id="clickRow' + smsLog.id + '" href="javascript:void(0)" onclick="resendSMS(' + smsLog.id + ', `' + sent_url + '`);" class="btn btn-sm btn-success spinner-button" data-toggle="tooltip" title="Resend SMS">' +
                            '<i class="la la-send-o"></i></a></td>';
                    }

                    if (smsLog.is_refund == "Yes") {
                        rows += '<td>smsLog.is_refund</td>';
                    } else {
                        rows += '<td></td>';
                    }

                    if (typeof statuses[smsLog.log_type] !== 'undefined') {
                        rows += '<td>' + statuses[smsLog.log_type] + '</td>';
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

// Copy WhatsApp message
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
                let message = response.data.message.replace(/\\n/g, '\n');

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(message).then(function() {
                        toastr.success('Message copied to clipboard!');
                    }).catch(function(err) {
                        console.error('Failed to copy message:', err);
                        fallbackCopyTextToClipboard(message);
                    });
                } else {
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

// Send WhatsApp message
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

                let message = response.data.message.replace(/\\n/g, '\n');
                let phoneNumber = response.data.whatsapp;
                let encodedMessage = encodeURIComponent(message);

                let whatsappAppUrl = 'whatsapp://send?phone=' + phoneNumber + '&text=' + encodedMessage;
                let whatsappWebUrl = 'https://web.whatsapp.com/send?phone=' + phoneNumber + '&text=' + encodedMessage;

                let appOpened = false;
                let startTime = Date.now();

                window.location.href = whatsappAppUrl;

                let blurHandler = function() {
                    let blurTime = Date.now();
                    if (blurTime - startTime < 2000) {
                        appOpened = true;
                        window.removeEventListener('blur', blurHandler);
                    }
                };
                window.addEventListener('blur', blurHandler);

                let visibilityHandler = function() {
                    if (document.hidden) {
                        appOpened = true;
                        document.removeEventListener('visibilitychange', visibilityHandler);
                    }
                };
                document.addEventListener('visibilitychange', visibilityHandler);

                setTimeout(function() {
                    window.removeEventListener('blur', blurHandler);
                    document.removeEventListener('visibilitychange', visibilityHandler);

                    if (!appOpened) {
                        console.log('WhatsApp app not detected, falling back to WhatsApp Web');
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

// Create consultancy invoice
function createConsultancyInvoice(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'GET',
        cache: false,
        success: function (response) {
            $("#create_consultancy_invoice").html(response);
            $("#modal_create_consultancy_invoice").modal("show");
            $("#addinvoice").show();
            customDatePicker();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            toastr.error("Unable to process the request");
        }
    });
}

// Create treatment invoice
function createTreatmentInvoice(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'GET',
        cache: false,
        success: function (response) {
            $("#create_treatment_invoice").html(response);
            $("#modal_create_treatment_invoice").modal("show");
            customDatePicker();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            toastr.error("Unable to process the request");
        }
    });
}

// Display invoice
function displayInvoice(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'GET',
        cache: false,
        success: function (response) {
            $("#display_invoice").html(response);
            $("#modal_display_invoice").modal("show");
        },
        error: function (xhr, ajaxOptions, thrownError) {
            toastr.error("Unable to process the request");
        }
    });
}

// Status validation helpers
const extraValidate = {
    validators: {
        notEmpty: {
            message: 'This field is required'
        }
    },
};

// Load child statuses
let loadChildStatuses = function (appointmentStatusId) {
    if (typeof statusValidate === 'undefined') return;
    
    statusValidate.addField('appointment_status_id', extraValidate);
    statusValidate.addField('reason', extraValidate);
    statusValidate.removeField('appointment_status_id', '');
    statusValidate.removeField('reason', '');
    
    if (appointmentStatusId != '') {
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
            success: function (response) {
                if (response.status) {
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
                if (parseInt(response.count) > 1) {
                    $('.appointment_status_id').show();
                }
                if (response.status && response.data.appointment_status.is_comment == '1') {
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
};

// Set child status data
function setChildStatusData(response) {
    let dropdowns = response.data.dropdown;
    let child_options = '<option value="">Select Child Status</option>';
    if (dropdowns) {
        Object.entries(dropdowns).forEach(function (dropdown) {
            child_options += '<option value="' + dropdown[0] + '">' + dropdown[1] + '</option>';
        });
    }
    $('#appointment_status_id').html(child_options);
}

// Reset dropdowns
var resetDropdowns = function () {
    resetReason();
    resetChildStatuses();
};

// Reset reason field
var resetReason = function () {
    $('.reason').hide();
    $('#reason').val('');
};

// Reset child statuses
var resetChildStatuses = function () {
    $('.appointment_status_id').hide();
    $('#appointment_status_id').val('');
};

// Status listener
let statusListener = function (appointmentStatusId) {
    if (typeof statusValidate === 'undefined') return;
    
    statusValidate.addField('reason', extraValidate);
    statusValidate.removeField('reason', '');
    
    if (appointmentStatusId != '') {
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
            success: function (response) {
                if (response.status && (response.data.appointment_status.is_comment == '1' || response.data.base_appointment_status.is_comment == '1')) {
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
};

// Actions template function for datatable
function consultationActions(data) {
    let id = data.id;

    let edit_url = route('admin.appointments.edit', { id: id });
    let edit_service_url = route('admin.treatments.edit', { id: id });
    let detail_url = route('admin.appointments.detail', { id: id });
    let sms_logs_url = route('admin.appointments.sms_logs', { id: id });

    let consultancy_invoice_url = route('admin.appointments.invoice-create-consultancy', { id: id, type: 'appointment' });
    let invoice_url = route('admin.appointments.invoicecreate', { id: id });
    let invoice_display_url = route('admin.appointments.InvoiceDisplay', { id: data.invoice_id });
    let image_url = route('admin.appointmentsimage.imageindex', { id: id });
    let measurements_url = route('admin.appointmentsmeasurement.measurements', { id: id });
    let medicals_url = route('admin.appointmentsmedical.medicals', { id: id });
    let plan_url = route('admin.appointmentplans.create', { id: id });
    let delete_url = route('admin.appointments.destroy', { id: id });
    let patient_url = route('admin.patients.card', { id: data.patient_id });
    let viewlog_url = route('admin.appointments.loadPage', { id: id, type: 'web' });

    // Use window.userPermissions if available (patient card context), otherwise use global permissions
    let perms = (typeof window.userPermissions !== 'undefined') ? window.userPermissions : permissions;

    if (
        perms.edit
        || perms.delete
        || perms.consultancy
        || perms.log
        || perms.status
        || perms.treatment
        || perms.invoice
        || perms.invoice_display
        || perms.image_manage
        || perms.measurement_manage
        || perms.medical_form_manage
        || perms.plans_create
        || perms.patient_card
    ) {
        let actions = '<div class="dropdown dropdown-inline action-dots">';

        if (perms.invoice) {
            if (!data.invoice) {
                if (data.appointment_type == 2) {
                    actions += '<a title="Create Invoice" href="javascript:void(0);" onclick="createTreatmentInvoice(`' + invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-warning btn-sm">\
                            <span class="navi-icon"><i class="la la-file"></i></span>\
                        </a>';
                }

                if (data.appointment_type == 1) {
                    actions += '<a title="Create Invoice" href="javascript:void(0);" onclick="createConsultancyInvoice(`' + consultancy_invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-warning btn-sm">\
                            <span class="navi-icon"><i class="la la-file"></i></span>\
                        </a>';
                }
            }
        }

        if (perms.invoice_display) {
            if (data.invoice) {
                actions += '<a title="View Invoice" href="javascript:void(0);" onclick="displayInvoice(`' + invoice_display_url + '`, `' + id + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-info btn-sm">\
                            <span class="navi-icon"><i class="la la-file-invoice-dollar"></i></span>\
                        </a>';
            }
        }
        
        actions += '<a href="javascript:void(0);" onclick="viewSmsLogs(`' + sms_logs_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-success btn-sm ml-2">\
                        <span class="navi-icon"><i class="la la-sms"></i></span>\
                    </a>';

        actions += '<a href="javascript:void(0);" onclick="copyWhatsAppMessage(' + id + ');" class="d-lg-inline-flex d-none btn btn-icon btn-primary btn-sm ml-2" title="Copy Message">\
                        <span class="navi-icon"><i class="la la-copy" style="color: white;"></i></span>\
                    </a>';

        actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                        <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
                    </a>';

        actions += '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right" style="overflow-y: scroll; height: 200px">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
        actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="viewDetail(`' + detail_url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-eye"></i></span>\
                            <span class="navi-text">Detail</span>\
                        </a>\
                    </li>';
        if (perms.edit) {
            if (data.appointment_type == 1) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`' + edit_url + '`, `' + id + '`, `detail-actions`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>';
            } else if (data.appointment_type == 2) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`' + edit_service_url + '`, `' + id + '`, `treatment-detail-actions`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>';
            }
        }
        if (perms.patient_card) {
            actions += '<li class="navi-item">\
                        <a target="_blank" href="' + patient_url + '" class="navi-link">\
                            <span class="navi-icon"><i class="la la-user"></i></span>\
                            <span class="navi-text">Patient Card</span>\
                        </a>\
                    </li>';
        }
        if (perms.delete) {
            actions += '<li class="navi-item">\
                            <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-trash"></i></span>\
                            <span class="navi-text">Delete</span>\
                            </a>\
                        </li>';
        }

        if (perms.invoice_display) {
            if (data.invoice) {
                actions += '<li class="navi-item d-lg-none">\
                        <a title="View Invoice" href="javascript:void(0);" onclick="displayInvoice(`' + invoice_display_url + '`, `' + id + '`);"  class="navi-link">\
                            <span class="navi-icon"><i class="la la-file-invoice-dollar"></i></span>\
                            <span class="navi-text">View Invoice</span>\
                        </a>\
                    </li>';
            }
        }

        actions += '<li class="navi-item  d-lg-none">\
                        <a href="javascript:void(0);" onclick="viewSmsLogs(`' + sms_logs_url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-sms"></i></span>\
                            <span class="navi-text">SMS Logs</span>\
                        </a>\
                    </li>';

        if (perms.invoice) {
            if (!data.invoice) {
                if (data.appointment_type == 2) {
                    actions += '<li class="navi-item d-lg-none">\
                        <a title="Create Invoice" href="javascript:void(0);" onclick="createTreatmentInvoice(`' + invoice_url + '`);"  class="navi-link">\
                            <span class="navi-icon"><i class="la la-file"></i></span>\
                            <span class="navi-text">Create Invoice</span>\
                        </a>\
                    </li>';
                }

                if (data.appointment_type == 1) {
                    actions += '<li class="navi-item d-lg-none">\
                        <a title="Create Invoice" href="javascript:void(0);" onclick="createConsultancyInvoice(`' + consultancy_invoice_url + '`);"   class="navi-link">\
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
