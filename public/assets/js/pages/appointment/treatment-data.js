var counter = 0;
var treatmentDoctorListener = function (doctorId) {

    setQueryStringParameter('doctor_id', doctorId);

    $("#treatment_doctor_filter").val(doctorId);

    loadCalendar();

    counter = counter+1;
}

let loadMachine = function(locationId) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.center_machines', {
            location_id: locationId,
        }),
        type: 'GET',
        data: {
            location_id: locationId
        },
        cache: false,
        success: function(response) {
            if(response.status) {

                let dropdowns =  response.data.dropdown;
                let dropdown_options =  '<option value="">Select a Machine</option>';

                Object.entries(dropdowns).forEach(function (dropdown) {
                    dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                });

                let result = get_query();

                $('#treatment_resource_filter').html(dropdown_options);

                if (typeof result.machine_id !== "undefined") {
                   // $("#treatment_resource_filter").val(result.machine_id).change();
                }

                $('.select2').select2({ width: '100%' });
            } else {
                resetDoctors();
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            resetDoctors();
        }
    });
}


let machineListener = function (machineId) {

    setQueryStringParameter('machine_id', machineId);

    if (machineId != '' && machineId != null) {

        loadCalendar();
        counter = counter +1;
    }

}

function loadCalendar() {

    if (typeof treatment_calendar !== "undefined") { /*if already initiate then destroy first*/
        treatment_calendar.destroy();

    }

    var result = get_query();

    if ($("#treatment_city_filter").val() !== ""
        && $("#treatment_location_filter").val() !== ""
        && $("#treatment_doctor_filter").val() !== ""
        && $("#treatment_resource_filter").val() !== ""
        && typeof result.tab !== 'undefined' && result.tab == 'treatment') {

        window.eventData = {}
        window.eventData.city_id = $("#treatment_city_filter").val()
        window.eventData.location_id = $("#treatment_location_filter").val()
        window.eventData.doctor_id = $("#treatment_doctor_filter").val();
        window.eventData.id = null;
        window.eventData.firstTime = true;

        setTimeout( function () {
            TreatmentCalendar.init();
        }, 500);
    }
}


var loadEndServices = function (baseServiceId) {
    if(baseServiceId != '') {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_node_service'),
            type: 'POST',
            data: {
                service_id: baseServiceId
            },
            cache: false,
            success: function(response) {
                if(response.status) {
                    let services = response.data.services;
                    let service_option = '<option value="">Select a Child Service</option>';

                    Object.entries(services).forEach( function (service) {
                        service_option += '<option value="'+service[0]+'">'+service[1]+'</option>';
                    });

                    $('#create_treatment_service').html(service_option);
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {

            }
        });
    } else {
        resetNodeServices();
        CreateFormValidation.loadLead();
    }
}

function getTreatmentPatientDetail($this) {

    if ($this.val() != '') {
        $this.parent("div").find(".select2-selection").removeClass("select2-is-invalid");
        $this.parent("div").find(".fv-help-block").text("");
    }
    $.ajax({
        type: 'get',
        url: route('admin.users.get_patient_number'),
        data: {
            'patient_id': $this.val()
        },
        success: function (resposne) {
            if (resposne.status && resposne.data.patient) {
                let patient = resposne.data.patient;

                $('#create_old_treatment_phone').val(patient?.phone);
                if (permissions.contact) {
                    $('#create_treatment_phone').val(patient?.phone);
                } else {
                    $('#create_treatment_phone').val("***********");
                }

                $('#create_treatment_patient_name').val(patient?.name);
                $('#create_treatment_gender').val(patient?.gender).trigger("change");

                if (patient?.phone != '') {
                    $("#create_treatment_phone").removeClass("is-invalid")
                    $("#create_treatment_phone").parent("div").find(".fv-help-block").remove();
                }

                if (patient?.name != '') {
                    $("#create_treatment_patient_name").removeClass("is-invalid")
                    $("#create_treatment_patient_name").parent("div").find(".fv-help-block").remove();
                }
            }

        },
    });

    $("#treatment_patient_id").val($this.val() != '' ? $this.val() : '0');
}

function setResourceValue(value) {
    $("#treatment_resource_id").val(value);
}


jQuery(document).ready(function () {

    $("#Add_treatment_comment").click(function () {
        if ($('#treatment_comment').val() !== '') {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'get',
                url: route('admin.appointments.storecomment'),
                data: {
                    'comment': $('#treatment_comment').val(),
                    'appointment_id': $('#treatment_comment_appointment_id').val(),
                },
                success: function (data) {
                    $('#treatment_commentsection').prepend(treatmentCommentData(data.username, data.appointmentCommentDate, data.appointment.comment));
                },

            });
        } else {
            toastr.error("Please fill out the comment field");
        }
        $('#treatment_cment')[0].reset();
    });



    $(document).on("click", ".croxcli", function () {
        $('.treatment_patient_search_id').val(null).trigger('change');

        $("#create_treatment_patient_search").parent("div").find(".select2-selection").addClass("select2-is-invalid");
        $("#create_treatment_patient_search").parent("div").find(".fv-help-block").text("The patient field is required");
    });
})
