// Build table URL - append patient_id if in patient card context
var table_url = route('admin.consultancy.datatable');
if (typeof patientId !== 'undefined' && patientId) {
    table_url += '?patient_id=' + patientId;
}

// Use shared column definitions from consultation-columns.js
// Include patient column only if NOT in patient card context (patientId not defined)
var includePatientColumn = (typeof patientId === 'undefined' || !patientId);
var table_columns = (typeof getConsultationColumns === 'function') 
    ? getConsultationColumns(includePatientColumn, null)
    : [];

// editStatus, setStatusData, loadChildStatuses, setChildStatusData, resetDropdowns, resetReason, resetChildStatuses, statusListener
// are now in consultation-common.js

function actions(data) {

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
            if (!data.invoice) {
                if (data.appointment_type == 2) {
                    actions += '<a title="Create Invoice" href="javascript:void(0);" onclick="createTreatmentInvoice(`' + invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-warning btn-sm">\
                            <span class="navi-icon"><i class="la la-file"></i></span>\
                            <!--<span class="navi-text">Create Invoice</span>-->\
                        </a>';
                }

                if (data.appointment_type == 1) {
                    actions += '<a title="Create Invoice" href="javascript:void(0);" onclick="createConsultancyInvoice(`' + consultancy_invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-warning btn-sm">\
                            <span class="navi-icon"><i class="la la-file"></i></span>\
                        </a>';

                }

            }

        }

        if (permissions.invoice_display) {
            if (data.invoice) {
                actions += '<a title="View Invoice" href="javascript:void(0);" onclick="displayInvoice(`' + invoice_display_url + '`, `' + id + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-info btn-sm">\
                            <span class="navi-icon"><i class="la la-file-invoice-dollar"></i></span>\
                        </a>';

            }
        }
        actions += '<a href="javascript:void(0);" onclick="viewSmsLogs(`' + sms_logs_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-success btn-sm ml-2">\
                        <span class="navi-icon"><i class="la la-sms"></i></span>\
                    </a>';

        // HIDDEN: WhatsApp icon removed from datatable as per user request
        // Show WhatsApp icon only if appointment_status is NOT 2 and scheduled_date is today
        // let today = new Date();
        // let todayString = today.getFullYear() + '-' +
        //                  String(today.getMonth() + 1).padStart(2, '0') + '-' +
        //                  String(today.getDate()).padStart(2, '0');

        // // Parse scheduled_date to check if it's today
        // let isToday = false;
        // if (data.scheduled_date && data.scheduled_date !== '-') {
        //     // Parse "Dec 15, 2025 at 12:45 PM" format
        //     let scheduledDatePart = data.scheduled_date.split(' at ')[0]; // Get "Dec 15, 2025"
        //     let scheduledDate = new Date(scheduledDatePart);
        //     let scheduledDateString = scheduledDate.getFullYear() + '-' +
        //                              String(scheduledDate.getMonth() + 1).padStart(2, '0') + '-' +
        //                              String(scheduledDate.getDate()).padStart(2, '0');
        //     isToday = scheduledDateString === todayString;
        // }

        // // Check user role permission for WhatsApp button (only FDM and Super-Admin)
        // let canSendWhatsApp = window.canSendWhatsApp || false;

        // if (data.appointment_status != 2 && data.appointment_status != 16&& isToday && canSendWhatsApp) {
        //     // Copy WhatsApp Message Button
             actions += '<a href="javascript:void(0);" onclick="copyWhatsAppMessage(' + id + ');" class="d-lg-inline-flex d-none btn btn-icon btn-primary btn-sm ml-2" title="Copy Message">\
                             <span class="navi-icon"><i class="la la-copy" style="color: white;"></i></span>\
                         </a>';

        //     // Send WhatsApp Button
        //     actions += '<a href="javascript:void(0);" onclick="sendWhatsApp(' + id + ');" class="d-lg-inline-flex d-none btn btn-icon btn-sm ml-2" title="Send WhatsApp" style="background-color: #25D366;">\
        //                     <span class="navi-icon"><i class="lab la-whatsapp" style="color: white;"></i></span>\
        //                 </a>';
        // } 

        actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                        <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
                    </a>';

        actions += '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right" style="overflow-y: scroll; height: 200px">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
        actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="viewDetail(`'+ detail_url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-eye"></i></span>\
                            <span class="navi-text">Detail</span>\
                        </a>\
                    </li>';
        if (permissions.edit) {
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
        if (permissions.patient_card) {
            actions += '<li class="navi-item">\
                        <a target="_blank" href="'+ patient_url + '" class="navi-link">\
                            <span class="navi-icon"><i class="la la-user"></i></span>\
                            <span class="navi-text">Patient Card</span>\
                        </a>\
                    </li>';
        }
        if (permissions.delete) {
            /*if (
                (data?.unscheduled_appointment_status?.id == data?.appointment_status_id) &&
                (!data?.scheduled_date && !data?.scheduled_time)
            ) {*/
            actions += '<li class="navi-item">\
                            <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-trash"></i></span>\
                            <span class="navi-text">Delete</span>\
                            </a>\
                        </li>';
        }
        //}

        if (permissions.invoice_display) {
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
                        <a href="javascript:void(0);" onclick="viewSmsLogs(`'+ sms_logs_url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-sms"></i></span>\
                            <span class="navi-text">SMS Logs</span>\
                        </a>\
                    </li>';

        // HIDDEN: WhatsApp option removed from mobile menu as per user request
        // Show WhatsApp option in mobile menu only if appointment_status is NOT 2 and scheduled_date is today and user has permission
       // if (data.appointment_status != 2 && isToday && canSendWhatsApp) {
       //      actions += '<li class="navi-item  d-lg-none">\
       //                      <a href="javascript:void(0);" onclick="sendWhatsApp('+ id + ');" class="navi-link">\
       //                          <span class="navi-icon"><i class="lab la-whatsapp"></i></span>\
       //                          <span class="navi-text">Send WhatsApp</span>\
       //                      </a>\
       //                  </li>';
       //  }


        if (permissions.invoice) {
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
    $("." + type + "-tab").addClass("nav-bar-active");

    setQueryStringParameter('tab', type);
    //setQueryStringParameter('city_id', city_id);
    setQueryStringParameter('location_id', location_id);
    setQueryStringParameter('doctor_id', doctor_id);
    setQueryStringParameter('reload', 'false');
    $(".change-label").text($("." + type + "-tab").text());

    if (type === 'treatment') {

        setQueryStringParameter('machine_id', resource_id);

        $("#treatment_city_filter").val(city_id).trigger("change");

        setTimeout(function () {
            $("#treatment_resource_filter").val(resource_id).trigger("change");
        }, 1100);

        setTimeout(function () {
            $("#treatment_doctor_filter").val(doctor_id).trigger("change");
        }, 1200);


    }

    if (type === 'consultancy') {
        $("#consultancy_city_filter").val(city_id).trigger("change");
    }
}

// viewDetail, setAppointmentDetailData, setAppointmentComments, editRow, setEditData
// are now in consultation-common.js

function setTreatmentEditData(response) {

    try {

        let appointment = response.data.appointment;
        let back_date_config = response.data.back_date_config;
        let cities = response.data.cities;
        let doctors = response.data.doctors;
        let locations = response.data.locations;
        let machines = response.data.machines;
        let resourceHadRotaDay = response.data.resourceHadRotaDay;
        let machineHadRotaDay = response.data.machineHadRotaDay;
        let services = response.data.services;
        let setting = response.data.setting;
        let genders = response.data.genders;


        let service_option = '<option value="">Select a Service</option>';
        Object.entries(services).forEach(function (service) {
            // Skip "All Services" option in edit treatment modal
            if (service[1] !== 'All Services') {
                service_option += '<option value="' + service[0] + '">' + service[1] + '</option>';
            }
        });

        let city_option = '<option value="">Select a City</option>';
        Object.entries(cities).forEach(function (city) {
            city_option += '<option value="' + city[0] + '">' + city[1] + '</option>';
        });

        let location_option = '<option value="">Select a Location</option>';
        Object.entries(locations).forEach(function (location) {
            location_option += '<option value="' + location[0] + '">' + location[1] + '</option>';
        });

        let doctor_option = '<option value="">Select a Doctor</option>';
        Object.entries(doctors).forEach(function (doctor) {
            doctor_option += '<option value="' + doctor[0] + '">' + doctor[1] + '</option>';
        });

        let gender_option = '<option value="">Select a Gender</option>';
        Object.entries(genders).forEach(function (gender) {
            gender_option += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
        });

        let machine_option = '<option value="">Select a Machine</option>';
        Object.entries(machines).forEach(function (machine) {
            machine_option += '<option value="' + machine[0] + '">' + machine[1] + '</option>';
        });

        $("#edit_treatment_service_id").html(service_option).val(appointment.service_id);
        $("#edit_treatment_machine_id").html(machine_option).val(appointment.resource_id);
        $("#edit_treatment_city_id").html(city_option).val(appointment.city_id);
        $("#edit_treatment_location_id").html(location_option).val(appointment.location_id);
        $("#edit_treatment_doctor_id").html(doctor_option).val(appointment?.doctor_id);
        $("#edit_treatment_patient_gender").html(gender_option).val(appointment?.patient?.gender);

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

        $("#edit_treatment_scheduled_time").val(appointment.scheduled_time);
        $("#scheduled_treatment_time_old").val(appointment.scheduled_time);

        $("#edit_treatment_patient_name").val(appointment?.patient?.name);

        if (permissions.contact) {
            $("#edit_treatment_patient_phone").val(appointment?.patient?.phone);
        } else {
            $("#edit_treatment_patient_phone").val("***********").attr("readonly", true);
        }

        $("#edit_old_treatment_patient_phone").val(appointment?.lead?.patient?.phone);

        $("#treatment_leadId").val(appointment?.lead_id);
        $("#treatment_appointment_id").val(appointment?.id);
        $("#treatment_resourceRotaDayID").val(resourceHadRotaDay?.id);
        $("#treatment_machineRotaDayID").val(machineHadRotaDay?.id);
        $("#treatment_start_time").val(resourceHadRotaDay?.start_time);
        $("#treatment_end_time").val(resourceHadRotaDay?.end_time);

        $("#treatment_appointment_type").val(appointment?.appointment_type_id);

    } catch (error) {
        showException(error);
    }

}

// viewSmsLogs, setSmsLogs are now in consultation-common.js

function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        // Check phone validation only if phone field exists (it was removed from filters)
        if ($("#appoint_search_phone").length && $("#appoint_search_phone").val() !== "" && ($("#appoint_search_phone").val().length < 10 || $("#appoint_search_phone").val().length > 13)) {
            toastr.error("Please enter valid phone number");
            return;
        }
        let filters = {
            delete: '',
            patient_id: $("#appointment_patient_id").val(),
            phone: $("#appoint_search_phone").val() || '',
            date_from: $("#appoint_search_start").val(),
            date_to: $("#appoint_appoint_end").val(),
            appointment_type_id: $("#appoint_search_type").val(),
            service_id: $("#appoint_search_service").val() || '',
            location_id: $("#appoint_search_centre").val(),
            doctor_id: $("#appoint_search_doctor").val(),
            appointment_status_id: $("#appoint_search_status").val(),
            created_from: $("#appoint_search_created_from").val(),
            created_to: $("#appoint_search_created_to").val(),
            created_by: $("#appoint_search_created_by").val(),
            converted_by: $("#appoint_search_rescheduled_by").val(),
            updated_by: $("#appoint_search_updated_by").val(),
            filter: 'filter',
        }
        if ($("#appoint_search_service").val() == 13) {
            resetFilters(datatable);
        }
        else {
            datatable.search(filters, 'search');
        }

    });
}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function () {
        let filters = {
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
            created_at: '',
            created_by: '',
            converted_by: '',
            updated_by: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}
function resetFilters(datatable) {


    let filters = {
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
        created_at: '',
        created_by: '',
        converted_by: '',
        updated_by: '',
        filter: 'filter_cancel',
    }
    datatable.search(filters, 'search');


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

        let service_options = '<option value="">All</option>';
        Object.values(services).forEach(function (value, index) {
            // Skip "All Services" record from database
            if (value.name !== 'All Services') {
                service_options += '<option value="' + value.id + '">' + value.name + '</option>';
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

        let created_by = $("#appoint_search_created_by").val();
        if (created_by == null || created_by == '') {
            $("#appoint_search_created_by").html(user_options);
        }

        let updated_by = $("#appoint_search_updated_by").val();

        if (updated_by == null || updated_by == '') {
            $("#appoint_search_updated_by").html(user_options);
        }

        let rescheduled_by = $("#appoint_search_rescheduled_by").val();
        if (rescheduled_by == null || rescheduled_by == '') {
            $("#appoint_search_rescheduled_by").html(user_options);
        }

        let search_type = $("#appoint_search_type").val();
        if (search_type == null || search_type == '') {
            $("#appoint_search_type").html(appoint_type_options);
        }

        let status = $("#appoint_search_status").val();
        if (status == null || status == '') {
            $("#appoint_search_status").html(appoint_status_options);
        }

        let doctor = $("#appoint_search_doctor").val();
        if (doctor == null || doctor == '') {
            $("#appoint_search_doctor").html(doctor_options);
        }

        let centre = $("#appoint_search_centre").val();
        if (centre == null || centre == '') {
            $("#appoint_search_centre").html(location_options);
        }

        let city = $("#appoint_search_city").val();
        if (city == null || city == '') {
            $("#appoint_search_city").html(city_options);
        }

        let service = $("#appoint_search_service").val();
        if (service == null) {
            $("#appoint_search_service").html(service_options);
            // Set to empty string to show "All" option by default
            $("#appoint_search_service").val('').trigger('change');
        }

        $("#appoint_search_created_by").val(active_filters.created_by);
        $("#appoint_search_updated_by").val(active_filters.updated_by);
        $("#appoint_search_rescheduled_by").val(active_filters.converted_by);
        $("#appoint_search_type").val(active_filters.appointment_type_id);
        $("#appoint_search_status").val(active_filters.appointment_status_id);
        $("#appoint_search_doctor").val(active_filters.doctor_id);
        $("#appoint_search_centre").val(active_filters.location_id);
        $("#appoint_search_service").val(active_filters.service_id);
        /*For Consultancy filter*/
        let city_value = $("#consultancy_city_filter").val();

        if (city_value == null) {
            $("#consultancy_city_filter").html(city_options);
        }

        //$("#treatment_city_filter").html(city_options);

        getUserCity();

    } catch (error) {
        showException(error);
    }
}

function resetCustomFilters() {

    // Reset patient search Select2 field
    $('#appointment_patient_id').val(null).trigger('change');
    $('.appointment_patient_id').val(null).trigger('change');
    
    // Reset all other filter fields
    $(".filter-field").val('');
    
    // Reset only filter-specific Select2 dropdowns (not modal dropdowns)
    // Target only the filter section select2 elements to avoid triggering modal handlers
    $('#appoint_search_centre').val('').trigger('change');
    $('#appoint_search_service').val('').trigger('change');
    $('#appoint_search_status').val('').trigger('change');
    $('#appoint_search_type').val('').trigger('change');
    $('#appoint_search_doctor').val('').trigger('change');
    $('#appoint_search_city').val('').trigger('change');
    $('#appoint_search_consultancy_type').val('').trigger('change');

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
        success: function (response) {

            $("#create_consultancy_invoice").html(response)

            $("#modal_create_consultancy_invoice").modal("show");
            $("#addinvoice").show();
            customDatePicker();
        },
        error: function (xhr, ajaxOptions, thrownError) {
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
        success: function (response) {

            $("#create_treatment_invoice").html(response)

            $("#modal_create_treatment_invoice").modal("show");
            customDatePicker();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            toastr.error("Unable to process the request");
        }
    });

}

// displayInvoice is now in consultation-common.js

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
                
                // Exclude disabled fields from validation
                excluded: ':disabled',

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
        validate.on('core.form.valid', function (event) {
            console.log('Form validation passed, preparing to submit');
            console.log('Service field value before serialization:', $("#edit_treatment").val());
            console.log('Service field name:', $("#edit_treatment").attr('name'));
            
            // Temporarily enable disabled fields so they're included in serialization
            var disabledFields = $(form).find(':input:disabled').not('select');
            var disabledSelects = $(form).find('select:disabled');
            
            disabledFields.prop('disabled', false);
            disabledSelects.prop('disabled', false);
            
            // Serialize form data
            var formData = $(form).serialize();
            console.log('Serialized form data:', formData);
            
            // Manually append disabled Select2 values if they weren't included
            disabledSelects.each(function() {
                var fieldName = $(this).attr('name');
                var fieldValue = $(this).val();
                if (fieldValue && formData.indexOf(fieldName + '=') === -1) {
                    formData += '&' + fieldName + '=' + encodeURIComponent(fieldValue);
                }
            });
            
            // Force capture service field value from Select2
            var serviceField = $("#edit_treatment");
            if (serviceField.length && !serviceField.prop('disabled')) {
                var serviceName = serviceField.attr('name');
                var serviceValue = serviceField.val();
                console.log('Forcing service field capture:', serviceName, '=', serviceValue);
                if (serviceValue && serviceName) {
                    // Remove old value
                    var regex = new RegExp('&?' + serviceName + '=[^&]*', 'g');
                    formData = formData.replace(regex, '');
                    // Append current value
                    formData += '&' + serviceName + '=' + encodeURIComponent(serviceValue);
                }
            }
            
            // Also ensure other enabled Select2 fields have their current value
            $(form).find('select:not(:disabled)').each(function() {
                var fieldName = $(this).attr('name');
                var fieldValue = $(this).val();
                if (fieldValue && fieldName && fieldName !== 'treatment_id') { // Skip treatment_id since we handled it above
                    // Remove old value from formData if exists
                    var regex = new RegExp('&?' + fieldName + '=[^&]*', 'g');
                    formData = formData.replace(regex, '');
                    // Append current value
                    formData += '&' + fieldName + '=' + encodeURIComponent(fieldValue);
                }
            });
            
            // Restore disabled state
            disabledFields.prop('disabled', true);
            disabledSelects.prop('disabled', true);
            
            submitForm($(form).attr('action'), $(form).attr('method'), formData, function (response) {

                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable('consultancy');
                } else {
                    toastr.error(response.message);
                }
            }, null);
        });
    }

    return {
        // public functions
        init: function () {
            Validation();
        }
    };
}();

// copyWhatsAppMessage, fallbackCopyTextToClipboard, sendWhatsApp are now in consultation-common.js

jQuery(document).ready(function () {
    AppointScheduleValidation.init();
    $("#date_range").val("");
});
