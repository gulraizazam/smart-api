var table_url = route('admin.treatment.datatable');

var table_columns = [

    {
        field: 'Patient_ID',
        title: 'ID',
        width: 60,
        sortable: false,
        template: function (data) {
            let detail_url = route('admin.appointments.detail', {id: data.id});
            return '<a href="javascript:void(0);" onclick="viewDetail(`'+detail_url+'`)">'+data.Patient_ID+'</a>';
        }
    },{
        field: 'name',
        title: 'Patient',
        width: 80
    },{
        field: 'phone',
        title: 'Phone',
        width: 90,
        template: function (data) {
            return phoneClip(data);
        }
    },{
        field: 'scheduled_date',
        title: 'Scheduled',
        width: 80,
        template: function (data) {
            if (data.appointment_status_id == "Arrived" || data.appointment_status_id == "Cancelled") {
                return '<span>'+data.scheduled_date+'</span>';
            } else {
                return '<a href="javascript:void(0);" onclick="editSchedule(' + data.id + ','+ data.doctorId +','+data.locationId+');"><br> ' + data.scheduled_date + ' <i style="color: #cc8600; font-size: large" class="la la-pencil"></i></a>';
            }
        }
    },{
        field: 'service_id',
        title: 'Service',
        width: 'auto',
    },{
        field: 'appointment_type_id',
        title: 'Type',
        width: 85,
    },{
        field: 'doctor_id',
        title: 'Doctor',
        width: 90,
    },{
        field: 'appointment_status_id',
        title: 'Status',
        width: 80,
        template: function (data) {

            let unscheduled_appointment_status = data.unscheduled_appointment_status;
            let appointment_status = data.appointment_status;

            if (permissions.status) {
                if (data.scheduled_date == '-') {
                    return '<span>Un-Scheduled</span>';
                } else {
                    return '<a href="javascript:void(0);" onclick="editStatus(' + data.id + ');">' + data.appointment_status_id + ' <i style="color: #cc8600; font-size: large" class="la la-pencil"></i></a>';
                }
            } else {
                return '<span class="badge badge-dark">'+data.appointment_status_id+'</span>';
            }
        }
    },{
        field: 'location_id',
        title: 'Centre',
        width: 'auto',
    },{
        field: 'city_id',
        title: 'City',
        width: 'auto',
    },{
        field: 'region_id',
        title: 'Region',
        width: 'auto',
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
        width: 125,
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

function editSchedule(id,doc_id,loc_id) {
    $("#modal_change_appointment_schedule").modal("show");
    $("#schedule_appointment_id").val(id)
    $("#schedule_doctor_id").val(doc_id)
    $("#schedule_location_id").val(loc_id)

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.get_schedule'),
        type: "GET",
        data: {id: id},
        cache: false,
        success: function(response) {
            if (response.status) {
                let appointment = response.data.appointment;
                $("#schedule_date").val(appointment?.scheduled_date);
                $("#schedule_time").val(appointment?.scheduled_time);
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

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
    let edit_service_url = route('admin.appointments.edit_service', {id: id});
    let detail_url = route('admin.appointments.detail', {id: id});
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
        actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                        <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
                    </a>';

        actions += '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right" style="overflow-y: scroll; height: 200px">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
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
        if(data.appointment_type==1) {
            if (permissions.consultancy) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="goToConsultancy(\'consultancy\', '+data.cityId+', '+data.locationId+', '+data.doctorId+')" class="navi-link">\
                        <span class="navi-icon"><i class="la la-stethoscope"></i></span>\
                        <span class="navi-text">View On Calendar</span>\
                    </a>\
                </li>';
            }
        }
        if(data.appointment_type==2) {
            if (permissions.treatment) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="goToConsultancy(\'treatment\', '+data.cityId+', '+data.locationId+', '+data.doctorId+', '+data.resource_id+')" class="navi-link">\
                        <span class="navi-icon"><i class="la la-medkit"></i></span>\
                        <span class="navi-text">View On Calendar</span>\
                    </a>\
                </li>';
            }
        }
        if(data.cancelled_appointment_status == null && (data.cancelled_appointment_status?.id != data.appointment_status_id))
        {
            if(data.appointment_type==1) {
                if (permissions.consultancy) {
                    actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="goToConsultancy(\'consultancy\', '+data.cityId+', '+data.locationId+', '+data.doctorId+')" class="navi-link">\
                            <span class="navi-icon"><i class="la la-stethoscope"></i></span>\
                            <span class="navi-text">Consultancy</span>\
                        </a>\
                    </li>';
                }
            }
            if(data.appointment_type==2) {
                if (permissions.treatment) {
                    actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="goToConsultancy(\'treatment\', '+data.cityId+', '+data.locationId+', '+data.doctorId+', '+data.resource_id+')" class="navi-link">\
                            <span class="navi-icon"><i class="la la-medkit"></i></span>\
                            <span class="navi-text">Treatment</span>\
                        </a>\
                    </li>';
                }
            }
        }

        if(data.appointment_type==2) {
            if (permissions.image_manage) {
                actions += '<li class="navi-item">\
                        <a href="'+image_url+'" target="_blank" class="navi-link">\
                            <span class="navi-icon"><i class="la la-image"></i></span>\
                            <span class="navi-text">Images</span>\
                        </a>\
                    </li>';
            }

            if (permissions.measurement_manage) {
                actions += '<li class="navi-item">\
                        <a href="'+measurements_url+'" target="_blank" class="navi-link">\
                            <span class="navi-icon"><i class="la la-ruler-horizontal"></i></span>\
                            <span class="navi-text">Measurements</span>\
                        </a>\
                    </li>';
            }
        }

        if(data.appointment_type==1) {
            if (permissions.medical_form_manage) {
                actions += '<li class="navi-item">\
                        <a href="'+medicals_url+'" target="_blank" class="navi-link">\
                            <span class="navi-icon"><i class="la la-medkit"></i></span>\
                            <span class="navi-text">Medical</span>\
                        </a>\
                    </li>';
            }
        }

        if (permissions.plans_create) {
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="createAppointmentPlan(`'+plan_url+'`, `'+id+'`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-paper-plane"></i></span>\
                            <span class="navi-text">Create Plan</span>\
                        </a>\
                    </li>';
        }

        if (permissions.patient_card) {
            actions += '<li class="navi-item">\
                        <a target="_blank" href="'+patient_url+'" class="navi-link">\
                            <span class="navi-icon"><i class="la la-user"></i></span>\
                            <span class="navi-text">Patient Card</span>\
                        </a>\
                    </li>';
        }

        if (permissions.log) {
            actions += '<li class="navi-item">\
                        <a href="'+viewlog_url+'" target="_blank" class="navi-link">\
                            <span class="navi-icon"><i class="la la-history"></i></span>\
                            <span class="navi-text">Log</span>\
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

function setAppointmentDetailData(response) {

    try {

        let appointment = response.data.appointment;
        console.log(appointment);
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
        $("#modal_edit_treatment_form").attr("action", route('admin.appointments.update', {id: id}));
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

        let type_option = '<option value="">All</option>';
        Object.entries(consultancy_types).forEach(function (consultancy_type) {
            type_option += '<option value="' + consultancy_type[0] + '">' + consultancy_type[1] + '</option>';
        });

        let service_option = '<option value="">All</option>';
        Object.entries(services).forEach(function (service) {
            service_option += '<option value="' + service[0] + '">' + service[1] + '</option>';
        });

        let city_option = '<option value="">All</option>';
        Object.entries(cities).forEach(function (city) {
            city_option += '<option value="' + city[0] + '">' + city[1] + '</option>';
        });

        let location_option = '<option value="">All</option>';
        Object.entries(locations).forEach(function (location) {
            location_option  += '<option value="' + location[0] + '">' + location[1] + '</option>';
        });

        let doctor_option = '<option value="">All</option>';
        Object.entries(doctors).forEach(function (doctor) {
            doctor_option  += '<option value="' + doctor[0] + '">' + doctor[1] + '</option>';
        });

        let gender_option = '<option value="">All</option>';
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


        let service_option = '<option value="">All</option>';
        Object.entries(services).forEach(function (service) {
            service_option += '<option value="' + service[0] + '">' + service[1] + '</option>';
        });

        let city_option = '<option value="">All</option>';
        Object.entries(cities).forEach(function (city) {
            city_option += '<option value="' + city[0] + '">' + city[1] + '</option>';
        });

        let location_option = '<option value="">All</option>';
        Object.entries(locations).forEach(function (location) {
            location_option  += '<option value="' + location[0] + '">' + location[1] + '</option>';
        });

        let doctor_option = '<option value="">All</option>';
        Object.entries(doctors).forEach(function (doctor) {
            doctor_option  += '<option value="' + doctor[0] + '">' + doctor[1] + '</option>';
        });

        let gender_option = '<option value="">All</option>';
        Object.entries(genders).forEach(function (gender) {
            gender_option  += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
        });

        let machine_option = '<option value="">All</option>';
        Object.entries(machines).forEach(function (machine) {
            machine_option  += '<option value="' + machine[0] + '">' + machine[1] + '</option>';
        });
        $("#treatment_service_id").html(service_option).val(appointment.service_id);
        $("#edit_treatment_service_id").html(service_option).val(appointment.service_id);
        $("#edit_treatment_machine_id").html(machine_option).val(appointment.resource_id);
        $("#edit_treatment_city_id").html(city_option).val(appointment.city_id);
        $("#edit_treatment_location_id").html(location_option).val(appointment.location_id);
        $("#edit_treatment_doctor_id").html(doctor_option).val(appointment?.doctor_id);
        $("#edit_treatment_patient_gender").html(gender_option).val(appointment?.patient?.gender);

        $("#edit_treatment_scheduled_date").val(appointment.scheduled_date);
        $("#edit_treatment_scheduled_date_old").val(appointment.scheduled_date);
        const [hourString, minute] = appointment.scheduled_time.split(":");
        const hour = +hourString % 24;
        var test = (hour % 12 || 12) + ":" + minute + (hour < 12 ? " AM" : " PM");
        $("#edit_treatment_scheduled_time").val(test);
        $("#scheduled_treatment_time_old").val(test);

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


function applyFilters(datatable) {
    $('#apply-filters').on('click', function() {
        let filters =  {
            delete: '',
            patient_id: $("#treatment_patient_id").val(),
            phone: $("#appoint_search_phone").val(),
            date_from: $("#treatment_search_start").val(),
            date_to: $("#treatment_appoint_end").val(),
            region_id: $("#treatment_search_region").val(),
            city_id: $("#treatment_search_city").val(),
            service_id: $("#treatment_search_service").val(),
            location_id: $("#treatment_search_centre").val(),
            doctor_id: $("#treatment_search_doctor").val(),
            appointment_status_id: $("#treatment_search_status").val(),
            consultancy_type: $("#treatment_search_consultancy_type").val(),
            created_from: $("#treatment_search_created_from").val(),
            created_to: $("#treatment_search_created_to").val(),
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
        created_from: '',
        created_to: '',
        created_by: '',
        converted_by: '',
        updated_by: '',
        filter: 'filter_cancel',
    }
    datatable.search(filters, 'search');


}
function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
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
            created_from: '',
            created_to: '',
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
                service_options += '<option value="' + service.id + '">' + service.name + '</option>';
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

jQuery(document).ready(function() {
    AppointScheduleValidation.init();
});
