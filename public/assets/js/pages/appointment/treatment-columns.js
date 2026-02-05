"use strict";

/**
 * Shared Treatment Column Definitions
 * 
 * This file contains column definitions shared between:
 * - Main treatments module (treatmentDatatable.js)
 * - Patient card treatments section (treatments.js)
 * 
 * This ensures column changes are reflected in both places.
 */

/**
 * Get treatment columns
 * @param {boolean} includePatientColumn - Whether to include patient name column (false for patient card)
 * @param {object} perms - Permissions object (optional, will use global if not provided)
 * @returns {array} Column definitions
 */
function getTreatmentColumns(includePatientColumn = true, perms = null) {
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
                return '<a href="javascript:void(0);" onclick="viewTreatmentDetail(`' + detail_url + '`)">' + data.Patient_ID + '</a>';
            }
        }
    ];
    
    // Include patient column only for main module
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
    
    columns.push({
        field: 'scheduled_date',
        title: 'Scheduled',
        width: 80,
        template: function (data) {
            var p = perms || (typeof permissions !== 'undefined' ? permissions : {});
            if (data.appointment_status_id == "Arrived" || data.appointment_status_id == "Cancelled" || data.appointment_status_id == "Converted") {
                return '<span>' + data.scheduled_date + '</span>';
            } else {
                if (p.schedule_edit) {
                    return '<a href="javascript:void(0);" onclick="editSchedule(' + data.id + ',' + data.doctorId + ',' + data.locationId + ');"><br> ' + data.scheduled_date + ' <i style="color: #cc8600; font-size: large" class="la la-pencil"></i></a>';
                } else {
                    return '<span>' + data.scheduled_date + '</span>';
                }
            }
        }
    });
    
    columns.push({
        field: 'service_id',
        title: 'Service',
        width: 90,
    });
    
    columns.push({
        field: 'doctor_id',
        title: 'Doctor',
        width: 80,
    });
    
    columns.push({
        field: 'appointment_status_id',
        title: 'Status',
        width: 80,
        template: function (data) {
            var p = perms || (typeof permissions !== 'undefined' ? permissions : {});
            if (p.status) {
                if (data.scheduled_date == '-') {
                    return '<span>Un-Scheduled</span>';
                } else if (data.appointment_status == 2) {
                    return '<span style="color: #8950FC;">' + data.appointment_status_id + '</span>';
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
            // Use the main module's actions function if available
            if (typeof actions === 'function') {
                return actions(data);
            }
            return '';
        }
    });
    
    return columns;
}
