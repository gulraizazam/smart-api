
var table_url = route('admin.home.datatable');
var changePages = 10;
var table_columns = [

    {
        field: 'Patient_ID',
        title: 'ID',
        width: 60,
        sortable: false,
    }, {
        field: 'name',
        title: 'Patient',
        width: 100,
    }, {
        field: 'phone',
        title: 'Phone',
        width: 100,
        template: function (data) {
            return phoneClip(data);
        }
    }, {
        field: 'scheduled_date',
        title: 'Scheduled',
        width: 'auto',
        template: function (data) {
            if (data.appointment_status_id == "Arrived" || data.appointment_status_id == "Cancelled") {
                return '<span>' + data.scheduled_date + '</span>';
            } else {
                return '<a href="javascript:void(0);" onclick="editSchedule(' + data.id + ');"><br> ' + data.scheduled_date + ' <i style="color: #cc8600; font-size: large" class="la la-pencil"></i></a>';
            }
        }
    }, {
        field: 'service_id',
        title: 'Service',
        width: 'auto',
    }, {
        field: 'appointment_type_id',
        title: 'Type',
        width: 100,
    }, {
        field: 'doctor_id',
        title: 'Doctor',
        width: 'auto',
    }, {
        field: 'appointment_status_id',
        title: 'Status',
        width: 100,
        template: function (data) {

            let unscheduled_appointment_status = data.unscheduled_appointment_status;
            let appointment_status = data.appointment_status;

            if (permissions.status) {
                /*if (unscheduled_appointment_status && (appointment_status == unscheduled_appointment_status.id)) {
                    return '<span class="badge badge-dark">'+data.appointment_status_id+'</span>';
                } else {*/
                return '<a href="javascript:void(0);" onclick="editStatus(' + data.id + ');">' + data.appointment_status_id + ' <i style="color: #cc8600; font-size: large" class="la la-pencil"></i></a>';
                //}
            } else {
                return '<span class="badge badge-dark">' + data.appointment_status_id + '</span>';
            }
        }
    }, {
        field: 'location_id',
        title: 'Centre',
        width: 'auto',
    }, {
        field: 'city_id',
        title: 'City',
        width: 'auto',
    }, {
        field: 'region_id',
        title: 'Region',
        width: 'auto',
    }, {
        field: 'consultancy_type',
        title: 'Consultancy Type',
        width: 90,
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
        field: 'updated_by',
        title: 'Updated By',
        width: 'auto',
    }, {
        field: 'converted_by',
        title: 'Rescheduled By',
        width: 'auto',
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
            if (base_appointments[appointments.appointment_status.parent_id].is_comment == 0
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

function editSchedule(id) {

    $("#modal_change_appointment_schedule").modal("show");
    $("#schedule_appointment_id").val(id)


    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.get_schedule'),
        type: "GET",
        data: { id: id },
        cache: false,
        success: function (response) {
            if (response.status) {
                let appointment = response.data.appointment;
                $("#schedule_date").val(appointment?.scheduled_date);
                $("#schedule_time").val(appointment?.scheduled_time);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}


function setTotal(meta) {

    $(".total-members").text(meta?.total ?? 0)
}

function changeDate() {
    var period = $("#recordfilter").val();
    $('.date_action_dropdown').find('select').val(period).trigger('change');
    $.ajax({
        url: '/api/dashboard/stats',
        type: "GET",
        data: { 'type': period },
        cache: false,
        success: function (response) {
            var collection = response.data.todaycollection;
            var sales = response.data.revenue.toFixed();
            let urlconsultant = route('admin.consultancy.index') + "?type=1&from=" + response.data.start_date + "&to=" + response.data.end_date;
            let urltreatment = route('admin.treatment.index') + "?type=2&from=" + response.data.start_date + "&to=" + response.data.end_date;
            $("#allrevenue").text('PKR: ' + sales);
            $("#allconsult").text(response.data.done_consultancies + '/' + response.data.all_consultancies);
            $("#allconsultantdate").attr("href", urlconsultant);
            $("#alltreat").text(response.data.done_treatments + '/' + response.data.all_treatments);
            $("#allleads").text('PKR: ' + collection);
            $("#alltreatmentdate").attr("href", urltreatment);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}


function getArrivalsByDate($this, date, time, type = 'today') {

    $(".arrival-btn").addClass("btn-default").removeClass("btn-primary");
    $this.addClass("btn-primary").removeClass("btn-default");

    datatable.search({
        'date': date,
        'time': time,
        'type': type,
    }, 'filter');
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
        validate.on('core.form.valid', function (event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {

                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable();
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

jQuery(document).ready(function () {

    AppointScheduleValidation.init();
});

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
}

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

var resetDropdowns = function () {
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
}