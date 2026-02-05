"use strict";

/**
 * Shared Consultation Column Definitions
 * 
 * Used by:
 * - Main consultations module (datatable.js)
 * - Patient card consultations (card-v2/consultations.js)
 * 
 * This ensures column changes are reflected in both places.
 */

/**
 * Get consultation columns
 * @param {boolean} includePatientColumn - Whether to include patient name column (false for patient card)
 * @param {object} perms - Permissions object
 * @returns {array} Column definitions
 */
function getConsultationColumns(includePatientColumn = true, perms = null) {
    // Permissions are read dynamically in templates, not at initialization
    // This allows the datatable to work even before permissions are loaded from API
    
    var columns = [
        {
            field: 'Patient_ID',
            title: 'ID',
            width: 60,
            sortable: false,
            template: function (data) {
                var detail_url = route('admin.appointments.detail', { id: data.id });
                return '<a href="javascript:void(0);" onclick="viewDetail(`' + detail_url + '`)">' + data.Patient_ID + '</a>';
            }
        }
    ];
    
    // Include patient column only for main module (not patient card)
    if (includePatientColumn) {
        columns.push({
            field: 'name',
            title: 'Patient',
            width: 80,
            template: function (data) {
                var view_url = route('admin.patients.card', { id: data.patient_id });
                return '<a href="' + view_url + '" style="color: #626574; font-weight: bold;">' + data.name + '</a>';
            }
        });
        columns.push({
            field: 'phone',
            title: 'Phone',
            width: 90,
            template: function (data) {
                return phoneClip(data);
            }
        });
    }
    
    // Common columns
    columns.push({
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
    });
    
    columns.push({
        field: 'service_id',
        title: 'Service',
        width: 80,
    });
    
    columns.push({
        field: 'doctor_id',
        title: 'Doctor',
        width: 80,
    });
    
    columns.push({
        field: 'appointment_status_id',
        title: 'Status',
        width: 70,
        template: function (data) {
            var statusPerms = perms || (typeof permissions !== 'undefined' ? permissions : {});
            if (statusPerms.status) {
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
    });
    
    columns.push({
        field: 'location_id',
        title: 'Centre',
        width: 90,
    });
    
    // Include city column only for main module
   
    
    columns.push({
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            return formatDate(data.created_at);
        }
    });
    
    // Include created_by, updated_by, converted_by only for main module
    if (includePatientColumn) {
        columns.push({
            field: 'created_by',
            title: 'Created By',
            width: 'auto',
        });
        columns.push({
            field: 'updated_by',
            title: 'Updated By',
            width: 'auto',
        });
        columns.push({
            field: 'converted_by',
            title: 'Rescheduled By',
            width: 'auto',
        });
    }
    
    // Actions column
    columns.push({
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 190,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            // Use appropriate actions function based on context
            if (typeof consultationActionsShared === 'function') {
                return consultationActionsShared(data, perms);
            } else if (typeof actions === 'function') {
                return actions(data);
            }
            return '';
        }
    });
    
    return columns;
}

/**
 * Shared actions function for consultations
 */
function consultationActionsShared(data, perms) {
    var p = perms || (typeof permissions !== 'undefined' ? permissions : {});
    
    var id = data.id;
    var edit_url = route('admin.appointments.edit', { id: id });
    var detail_url = route('admin.appointments.detail', { id: id });
    var sms_logs_url = route('admin.appointments.sms_logs', { id: id });
    var delete_url = route('admin.appointments.destroy', { id: id });
    var consultancy_invoice_url = route('admin.appointments.invoice-create-consultancy', { id: id, type: 'appointment' });
    var invoice_display_url = route('admin.appointments.InvoiceDisplay', { id: data.invoice_id });
    var patient_url = route('admin.patients.card', { id: data.patient_id });

    var actions = '<div class="dropdown dropdown-inline action-dots">';

    // Invoice buttons
    if (p.invoice && !data.invoice && data.appointment_type == 1) {
        actions += '<a title="Create Invoice" href="javascript:void(0);" onclick="createConsultancyInvoice(`' + consultancy_invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-warning btn-sm">' +
                '<span class="navi-icon"><i class="la la-file"></i></span>' +
            '</a>';
    }

    if (p.invoice_display && data.invoice) {
        actions += '<a title="View Invoice" href="javascript:void(0);" onclick="displayInvoice(`' + invoice_display_url + '`, `' + id + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-info btn-sm">' +
                '<span class="navi-icon"><i class="la la-file-invoice-dollar"></i></span>' +
            '</a>';
    }
    
    // SMS logs button
    actions += '<a href="javascript:void(0);" onclick="viewSmsLogs(`' + sms_logs_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-success btn-sm ml-2">' +
            '<span class="navi-icon"><i class="la la-sms"></i></span>' +
        '</a>';

    // Copy WhatsApp button
    actions += '<a href="javascript:void(0);" onclick="copyWhatsAppMessage(' + id + ');" class="d-lg-inline-flex d-none btn btn-icon btn-primary btn-sm ml-2" title="Copy Message">' +
            '<span class="navi-icon"><i class="la la-copy" style="color: white;"></i></span>' +
        '</a>';

    // Dropdown menu
    actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">' +
            '<i class="ki ki-bold-more-hor" aria-hidden="true"></i>' +
        '</a>';

    actions += '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">' +
        '<ul class="navi flex-column navi-hover py-2">' +
            '<li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">' +
                'Choose an action:' +
            '</li>';
    
    // Detail
    actions += '<li class="navi-item">' +
            '<a href="javascript:void(0);" onclick="viewDetail(`' + detail_url + '`);" class="navi-link">' +
                '<span class="navi-icon"><i class="la la-eye"></i></span>' +
                '<span class="navi-text">Detail</span>' +
            '</a>' +
        '</li>';
    
    // Edit
    if (p.edit) {
        actions += '<li class="navi-item">' +
                '<a href="javascript:void(0);" onclick="editRow(`' + edit_url + '`, `' + id + '`, `detail-actions`);" class="navi-link">' +
                    '<span class="navi-icon"><i class="la la-pencil"></i></span>' +
                    '<span class="navi-text">Edit</span>' +
                '</a>' +
            '</li>';
    }
    
    // Patient Card link (only show if not already in patient card)
    if (p.patient_card && typeof patientId === 'undefined') {
        actions += '<li class="navi-item">' +
                '<a target="_blank" href="' + patient_url + '" class="navi-link">' +
                    '<span class="navi-icon"><i class="la la-user"></i></span>' +
                    '<span class="navi-text">Patient Card</span>' +
                '</a>' +
            '</li>';
    }
    
    // Delete
    if (p.delete) {
        actions += '<li class="navi-item">' +
                '<a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">' +
                    '<span class="navi-icon"><i class="la la-trash"></i></span>' +
                    '<span class="navi-text">Delete</span>' +
                '</a>' +
            '</li>';
    }

    actions += '</ul></div></div>';

    return actions;
}
