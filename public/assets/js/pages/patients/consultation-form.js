// Patient card consultation datatable - uses dedicated patient-specific endpoint
// This ensures patient_id is always applied via URL path parameter (same pattern as plans module)

var table_url = route('admin.patients.consultationsDatatable', { id: patientCardID });

var table_columns = [
    {
        field: 'Patient_ID',
        title: 'ID',
        width: 60,
        sortable: false,
        template: function (data) {
            let detail_url = route('admin.appointments.detail', { id: data.id });
            return '<a href="javascript:void(0);" onclick="viewConsultationDetail(`' + detail_url + '`)">' + data.Patient_ID + '</a>';
        }
    }, {
        field: 'scheduled_date',
        title: 'Scheduled',
        width: 80,
        template: function (data) {
            if (data.appointment_status_id == "Arrived" || data.appointment_status_id == "Cancelled" || data.appointment_status_id == "Converted") {
                return '<span>' + data.scheduled_date + '</span>';
            } else {
                return '<a href="javascript:void(0);" onclick="editSchedule(' + data.id + ',' + data.doctorId + ',' + data.locationId + ');"><br> ' + data.scheduled_date + ' <i style="color: #cc8600; font-size: large" class="la la-pencil"></i></a>';
            }
        }
    }, {
        field: 'service_id',
        title: 'Service',
        width: 80,
    }, {
        field: 'doctor_id',
        title: 'Doctor',
        width: 80,
    }, {
        field: 'appointment_status_id',
        title: 'Status',
        width: 80,
        template: function (data) {
            if (permissions.status) {
                if (data.scheduled_date == '-') {
                    return '<span>Un-Scheduled</span>';
                } else if (data.appointment_status_id === 'Arrived') {
                    return '<span style="color:#8950FC;">' + data.appointment_status_id + '</span>';
                } else if (data.appointment_status_id === 'Converted') {
                    return '<span style="color:#50CD89;">' + data.appointment_status_id + '</span>';
                } else {
                    return '<a href="javascript:void(0);" onclick="editStatus(' + data.id + ');">' + data.appointment_status_id + ' <i style="color: #cc8600; font-size: large" class="la la-pencil"></i></a>';
                }
            } else {
                return '<span class="badge badge-dark">' + data.appointment_status_id + '</span>';
            }
        }
    }, {
        field: 'location_id',
        title: 'Centre',
        width: 90,
    }, {
        field: 'city_id',
        title: 'City',
        width: 80,
    }, {
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            return formatDate(data.created_at);
        }
    }, {
        field: 'created_by',
        title: 'Created By',
        width: 'auto',
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 190,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return consultationActions(data);
        }
    }
];

// Actions for consultation datatable - matches main module styling
function consultationActions(data) {
    if (typeof data.id === 'undefined') {
        return '';
    }

    let id = data.id;
    let edit_url = route('admin.appointments.edit', { id: id });
    let detail_url = route('admin.appointments.detail', { id: id });
    let sms_logs_url = route('admin.appointments.sms_logs', { id: id });
    let consultancy_invoice_url = route('admin.appointments.invoice-create-consultancy', { id: id, type: 'appointment' });
    let invoice_display_url = route('admin.appointments.InvoiceDisplay', { id: data.invoice_id });
    let delete_url = route('admin.appointments.destroy', { id: id });

    let actions = '<div class="dropdown dropdown-inline action-dots">';

    // Invoice button
    if (permissions.invoice) {
        if (!data.invoice) {
            actions += '<a title="Create Invoice" href="javascript:void(0);" onclick="createConsultancyInvoice(`' + consultancy_invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-warning btn-sm">\
                    <span class="navi-icon"><i class="la la-file"></i></span>\
                </a>';
        }
    }

    // View Invoice button
    if (permissions.invoice_display) {
        if (data.invoice) {
            actions += '<a title="View Invoice" href="javascript:void(0);" onclick="displayInvoice(`' + invoice_display_url + '`, `' + id + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-info btn-sm">\
                    <span class="navi-icon"><i class="la la-file-invoice-dollar"></i></span>\
                </a>';
        }
    }

    // SMS Logs button
    actions += '<a href="javascript:void(0);" onclick="viewSmsLogs(`' + sms_logs_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-success btn-sm ml-2">\
                    <span class="navi-icon"><i class="la la-sms"></i></span>\
                </a>';

    // Copy Message button
    actions += '<a href="javascript:void(0);" onclick="copyWhatsAppMessage(' + id + ');" class="d-lg-inline-flex d-none btn btn-icon btn-primary btn-sm ml-2" title="Copy Message">\
                    <span class="navi-icon"><i class="la la-copy" style="color: white;"></i></span>\
                </a>';

    // Dropdown menu
    actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                    <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
                </a>';

    actions += '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right" style="overflow-y: scroll; height: 200px">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                </li>';

    // Detail
    actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="viewConsultationDetail(`' + detail_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye"></i></span>\
                        <span class="navi-text">Detail</span>\
                    </a>\
                </li>';

    // Edit
    if (permissions.edit) {
        actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="editRow(`' + edit_url + '`, `' + id + '`, `detail-actions`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
    }

    // Delete
    if (permissions.delete) {
        actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-trash"></i></span>\
                        <span class="navi-text">Delete</span>\
                        </a>\
                    </li>';
    }

    // View Invoice (mobile)
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

    // SMS Logs (mobile)
    actions += '<li class="navi-item d-lg-none">\
                    <a href="javascript:void(0);" onclick="viewSmsLogs(`' + sms_logs_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-sms"></i></span>\
                        <span class="navi-text">SMS Logs</span>\
                    </a>\
                </li>';

    // Create Invoice (mobile)
    if (permissions.invoice) {
        if (!data.invoice) {
            actions += '<li class="navi-item d-lg-none">\
                    <a title="Create Invoice" href="javascript:void(0);" onclick="createConsultancyInvoice(`' + consultancy_invoice_url + '`);"  class="navi-link">\
                        <span class="navi-icon"><i class="la la-file"></i></span>\
                        <span class="navi-text">Create Invoice</span>\
                    </a>\
                </li>';
        }
    }

    actions += '</ul></div></div>';

    return actions;
}

// View consultation detail
function viewConsultationDetail(url) {
    $("#modal_appointment_detail").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setConsultationDetailData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setConsultationDetailData(response) {
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
        setConsultationComments(appointment);
    } catch (e) {
        console.error('Error setting consultation detail data:', e);
    }
}

function setConsultationComments(appointment) {
    let appointment_comments = appointment.appointment_comments;
    let comment_html = '';
    if (appointment_comments && appointment_comments.length) {
        Object.values(appointment_comments).forEach(function (comment) {
            comment_html += consultationCommentData(comment?.user?.name, comment?.created_at, comment?.comment);
        });
    }
    $("#appointment_commentsection").html(comment_html);
}

// Comment data HTML generator
function consultationCommentData(user_name, created_at, comment) {
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

// All shared functions (editRow, deleteRow, editStatus, editSchedule, setEditData, setStatusData, 
// viewSmsLogs, copyWhatsAppMessage, etc.) are now loaded from consultation-common.js

// Patient card specific: Delete row with datatable reload
function deleteRow(url) {
    Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, delete it!"
    }).then(function (result) {
        if (result.value) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: url,
                type: "DELETE",
                cache: false,
                success: function (response) {
                    if (response.status) {
                        successMessage(response.message);
                        if (patientDatatable && patientDatatable['.consultation-form']) {
                            patientDatatable['.consultation-form'].reload();
                        }
                    } else {
                        errorMessage(response.message);
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    errorMessage(xhr);
                }
            });
        }
    });
}
