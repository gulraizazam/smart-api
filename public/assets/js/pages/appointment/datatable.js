
var table_url = route('admin.appointments.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 80,
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    },
    {
        field: 'Patient_ID',
        title: 'ID',
        width: 80,
        sortable: false,
        template: function (data) {
            return makePatientId(data.id);
        }
    },{
        field: 'name',
        title: 'Patient',
        width: 'auto',
    },{
        field: 'phone',
        title: 'Phone',
        width: 80,
        template: function (data) {
            return phoneClip(data);
        }
    },{
        field: 'scheduled_date',
        title: 'Scheduled',
        width: 'auto',
    },{
        field: 'service_id',
        title: 'Service',
        width: 'auto',
    },{
        field: 'appointment_type_id',
        title: 'Type',
        width: 'auto',
    },{
        field: 'doctor_id',
        title: 'Doctor',
        width: 'auto',
    },{
        field: 'region_id',
        title: 'Region',
        width: 'auto',
    },{
        field: 'city_id',
        title: 'City',
        width: 'auto',
    },{
        field: 'location_id',
        title: 'Centre',
        width: 'auto',
    },{
        field: 'appointment_status_id',
        title: 'Status',
        width: 'auto',
        template: function (data) {
            
            if (permissions.appointment_status) {
                return '<a href="javascript:void(0);" onclick="editStatus('+data.id+');">'+data.appointment_status_id+'</a>';
            } else {
                return '<span class="badge badge-dark">'+data.appointment_status_id+'</span>';
            }
        }
    },{
        field: 'consultancy_type',
        title: 'Consultancy Type',
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            return formatDate(data.created_at);
        }
    },{
        field: 'created_by',
        title: 'Created By',
        width: 'auto',
    },{
        field: 'updated_by',
        title: 'Updated By',
        width: 'auto',
    },{
        field: 'converted_by',
        title: 'Rescheduled By',
        width: 'auto',
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

function editStatus(id) {

    $("#modal_change_appointment_status").modal("show");
    $("#modal_update_status_form").attr("action", route('admin.appointments.storeappointmentstatus'));


    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
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

        $("#base_appointment_status_id").html(base_status_option);
        $("#appointment_status_id").html(appoint_status_option);

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
        if(base_appointments[appointments.appointment_status.parent_id].is_comment == 0
            && appointments?.appointment_status?.is_comment == 0) {
                $("#appointment_reason").hide();
            } else {
            $("#appointment_reason").show();
            $("#reason").val(appointments?.reason);
        }
        }

        $("#appointment_id").val(id);
        $("#appointment_status_not_show").val(appointment_status_not_show);
        $("#cancellation_reason_other_reason").val(cancellation_reason_other_reason);

    } catch (error) {
        showException(error);
    }
}

const extraValidate = {
    validators: {
        notEmpty: {
            message: 'This field is required'
        }
    },
};
let loadChildStatuses = function (appointmentStatusId) {

    statusValidate.addField('reason', extraValidate);
    statusValidate.addField('appointment_status_id', extraValidate);


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
                        statusValidate.addField('appointment_status_id', '');
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
    statusValidate.removeField('appointment_status_id', '');
}

let statusListener = function (appointmentStatusId) {
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

    let url = route('admin.appointments.edit', {id: id});
    let delete_url = route('admin.appointments.destroy', {id: id});
    let view_url = '';

    if (permissions.edit && permissions.delete) {
        let actions = '<div class="dropdown dropdown-inline action-dots">\
            <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
        if (permissions.edit) {
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`'+url+'`, `'+id+'`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
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

        if (permissions.manage) {
            actions += '<li class="navi-item">\
                            <a href="'+view_url+'" class="navi-link">\
                            <span class="navi-icon"><i class="la la-eye"></i></span>\
                            <span class="navi-text">View</span>\
                            </a>\
                        </li>';
        }

        actions += '</ul>\
            </div>\
        </div>';

        return actions;
    }
    return '';
}

function editRow(url, id) {

    $("#modal_edit_patients").modal("show");
    $("#modal_edit_patients_form").attr("action", route('admin.patients.update', {id: id}));

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(EditValidation);
        }
    });


}

function setEditData(response) {

    let genders = response.data.gender;
    let patient = response.data.patient;
    let gender_option = '<option value="">All</option>';

    Object.entries(genders).forEach(function (gender) {
        gender_option += '<option value="'+gender[0]+'">'+gender[1]+'</option>';
    });

    $("#edit_gender_id").html(gender_option);
    $("#edit_name").val(patient.name);
    $("#edit_email").val(patient.email);
    $("#edit_phone").val(patient.phone);
    $("#edit_gender_id").val(patient.gender);

}


function createPatient(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setPatientData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(AddValidation);
        }
    });


}

function setPatientData(response) {

    let genders = response.data.gender;
    let gender_option = '<option value="">All</option>';

    Object.entries(genders).forEach(function (gender) {
        gender_option += '<option value="'+gender[0]+'">'+gender[1]+'</option>';
    });
    $("#add_gender_id").html(gender_option);

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            patient_id: $("#search_patient_id").val(),
            name: $("#search_name").val(),
            email: $("#search_email").val(),
            phone: $("#search_phone").val(),
            gender: $("#search_gender").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            status: $("#search_status").val(),
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
            email: '',
            phone: '',
            gender: '',
            created_from: '',
            created_to: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    return false;
    try {

        let status = filter_values.status;
        let genders = filter_values.gender;

        let status_options = '<option value="">All</option>';
        let gender_options = '<option value="">All</option>';

        Object.entries(genders).forEach(function (gender, index) {
            gender_options += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
        });

        Object.entries(status).forEach(function (value, index) {
            status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        $("#search_status").html(status_options);
        $("#search_gender").html(gender_options);

        $("#search_name").val(active_filters.name);
        $("#search_status").val(active_filters.status);
        $("#search_gender").val(active_filters.gender);
        $("#search_phone").val(active_filters.phone);
        $("#search_email").val(active_filters.email);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);

        hideShowAdvanceFilters(active_filters);
    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
        || (typeof active_filters.status !== 'undefined' && active_filters.status != '')
        || (typeof active_filters.gender !== 'undefined' && active_filters.gender != '')
    ) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}

