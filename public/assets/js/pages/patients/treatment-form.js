// Patient card treatment datatable - uses dedicated patient-specific endpoint
// This ensures patient_id is always applied via URL path parameter (same pattern as plans module)

var table_url = route('admin.patients.treatmentsDatatable', { id: patientCardID });

var table_columns = [
    {
        field: 'scheduled_date',
        title: 'Scheduled',
        width: 100,
        template: function (data) {
            return '<span>' + data.scheduled_date + '</span>';
        }
    }, {
        field: 'service_id',
        title: 'Service',
        width: 100,
    }, {
        field: 'doctor_id',
        title: 'Doctor',
        width: 100,
    }, {
        field: 'machine_id',
        title: 'Machine',
        width: 100,
    }, {
        field: 'appointment_status_id',
        title: 'Status',
        width: 100,
        template: function (data) {
            if (data.appointment_status_id === 'Arrived') {
                return '<span style="color:#8950FC;">' + data.appointment_status_id + '</span>';
            } else if (data.appointment_status_id === 'Completed') {
                return '<span style="color:#50CD89;">' + data.appointment_status_id + '</span>';
            } else if (data.appointment_status_id === 'Cancelled') {
                return '<span style="color:#F64E60;">' + data.appointment_status_id + '</span>';
            } else {
                return '<span>' + data.appointment_status_id + '</span>';
            }
        }
    }, {
        field: 'location_id',
        title: 'Centre',
        width: 100,
    }, {
        field: 'created_at',
        title: 'Created At',
        width: 100,
        template: function (data) {
            return formatDate(data.created_at);
        }
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 100,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return treatmentActions(data);
        }
    }
];

// Actions for treatment datatable
function treatmentActions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;
        let edit_url = route('admin.treatments.edit', {id: id});
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
                        <a href="javascript:void(0);" onclick="viewTreatmentDetail(\'' + detail_url + '\');" class="navi-link">\
                            <span class="navi-icon"><i class="la la-eye"></i></span>\
                            <span class="navi-text">Detail</span>\
                        </a>\
                    </li>\
                    <li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editTreatment(\'' + edit_url + '\', \'' + id + '\');" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>\
                    <li class="navi-item">\
                        <a href="javascript:void(0);" onclick="deleteTreatment(\'' + delete_url + '\');" class="navi-link">\
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

// View treatment detail
function viewTreatmentDetail(url) {
    $("#modal_appointment_detail").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setTreatmentDetailData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setTreatmentDetailData(response) {
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
        setTreatmentComments(appointment);
    } catch (e) {
        console.error('Error setting treatment detail data:', e);
    }
}

function setTreatmentComments(appointment) {
    let appointment_comments = appointment.appointment_comments;
    let comment_html = '';
    if (appointment_comments && appointment_comments.length) {
        Object.values(appointment_comments).forEach(function (comment) {
            comment_html += treatmentCommentData(comment?.user?.name, comment?.created_at, comment?.comment);
        });
    }
    $("#appointment_commentsection").html(comment_html);
}

// Edit treatment
function editTreatment(url, id) {
    $("#modal_treatment_edit").modal("show");
    $("#modal_edit_treatment_form").attr("action", route('admin.treatment.update', { id: id }));

    $.ajax({
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setTreatmentEditData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            toastr.error('Failed to load treatment data');
        }
    });
}

function setTreatmentEditData(response) {
    try {
        let appointment = response.data.appointment;
        let doctors = response.data.doctors || {};
        let machines = response.data.machines || {};
        let services = response.data.services || {};

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

        $("#edit_treatment_service_id").html(service_option).val(appointment.service_id);
        $("#edit_treatment_machine_id").html(machine_option).val(appointment.resource_id);
        $("#edit_treatment_doctor_id").html(doctor_option).val(appointment?.doctor_id);

        // Set patient name and phone
        $("#edit_treatment_patient_name").val(appointment?.patient?.name);
        $("#edit_treatment_patient_phone").val(appointment?.patient?.phone);
        $("#edit_old_treatment_patient_phone").val(appointment?.lead?.patient?.phone);
        
        // Set patient name in modal title
        let patientName = appointment?.patient?.name || '';
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

        // Convert 24h time format to 12h format
        let scheduledTime = appointment.scheduled_time;
        let formattedTime = '';
        if (scheduledTime) {
            const [hourString, minute] = scheduledTime.split(":");
            const hour = parseInt(hourString) % 24;
            formattedTime = String(hour % 12 || 12).padStart(2, '0') + ":" + minute + " " + (hour < 12 ? "AM" : "PM");
        }
        
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

// Delete treatment
function deleteTreatment(url) {
    if (confirm('Are you sure you want to delete this treatment?')) {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: url,
            type: 'DELETE',
            success: function(response) {
                if (response.status) {
                    toastr.success('Treatment deleted successfully');
                    reInitTable();
                } else {
                    toastr.error(response.message || 'Failed to delete treatment');
                }
            },
            error: function(xhr) {
                toastr.error('Failed to delete treatment');
            }
        });
    }
}

// Comment data HTML generator
function treatmentCommentData(user_name, created_at, comment) {
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

// Apply filters for treatment datatable
function applyFilters(datatable) {
    $('#treatment-form-search').on('click', function() {
        let filters = {
            date_from: $("#treatment_search_start").val(),
            date_to: $("#treatment_search_end").val(),
            service_id: $("#treatment_search_service").val(),
            location_id: $("#treatment_search_centre").val(),
            appointment_status_id: $("#treatment_search_status").val(),
            filter: 'filter',
        };
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#treatment-reset-filters').on('click', function() {
        // Clear filter inputs
        $("#treatment_search_start").val('');
        $("#treatment_search_end").val('');
        $("#treatment_search_service").val('').trigger('change');
        $("#treatment_search_centre").val('').trigger('change');
        $("#treatment_search_status").val('').trigger('change');
        
        let filters = {
            date_from: '',
            date_to: '',
            service_id: '',
            location_id: '',
            appointment_status_id: '',
            filter: 'filter_cancel',
        };
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    try {
        let locations = filter_values.locations;
        let appointment_statuses = filter_values.appointment_statuses;
        let services = filter_values.services;

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

        let service_options = '<option value="">All</option>';
        if (services) {
            Object.entries(services).forEach(function (service, index) {
                service_options += '<option value="' + service[0] + '">' + service[1] + '</option>';
            });
        }

        $("#treatment_search_centre").html(location_options);
        $("#treatment_search_status").html(status_options);
        $("#treatment_search_service").html(service_options);

        // Initialize select2 on filter dropdowns
        if (!$("#treatment_search_centre").hasClass("select2-hidden-accessible")) {
            $("#treatment_search_centre").select2({ width: '100%' });
        }
        if (!$("#treatment_search_status").hasClass("select2-hidden-accessible")) {
            $("#treatment_search_status").select2({ width: '100%' });
        }
        if (!$("#treatment_search_service").hasClass("select2-hidden-accessible")) {
            $("#treatment_search_service").select2({ width: '100%' });
        }

        // Initialize datepickers
        if (!$("#treatment_search_start").data('datepicker')) {
            $(".treatment-filters .to-from-datepicker").datepicker({
                todayHighlight: true,
                orientation: 'bottom',
                format: 'yyyy-mm-dd',
                autoclose: true
            });
        }

    } catch (error) {
        console.error('Error setting treatment filters:', error);
    }
}

// Treatment edit form submit handler
$(document).off('submit', '#modal_edit_treatment_form.treatment-edit');
$(document).on('submit', '#modal_edit_treatment_form', function(e) {
    e.preventDefault();
    
    var form = $(this);
    var url = form.attr('action');
    var formData = form.serialize();
    var submitBtn = form.find('button[type="submit"]');
    var originalText = submitBtn.html();
    
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
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
});
