jQuery(document).ready(function() {

    var result = get_query();

    if (typeof result.tab !== 'undefined') {
        $("." + result.tab+ '-tab').click();
    } else {
        $(".appointment-tab").addClass("nav-bar-active")
    }

    if (typeof result.city_id !== "undefined"
        && typeof result.location_id !== "undefined"
        && typeof result.doctor_id !== "undefined"
        && typeof result.tab !== 'undefined' && result.tab == 'consultancy') {

        loadDoctors(result.location_id, result.tab);

        setTimeout( function () {
            $("#consultancy_city_filter").val(result.city_id).change();
            $("#consultancy_doctor_filter").val(result.doctor_id).change();
        }, 400);
    }

    if (typeof result.city_id !== "undefined"
        && typeof result.location_id !== "undefined"
        && typeof result.doctor_id !== "undefined"
        && typeof result.machine_id !== "undefined"
        && typeof result.tab !== 'undefined' && result.tab == 'treatment') {

        //loadDoctors(result.location_id, result.tab);


        setTimeout( function () {
            $("#treatment_city_filter").val(result.city_id).change();
        }, 200);
        setTimeout( function () {
            $("#treatment_location_filter").val(result.location_id).change();
        },500);

        setTimeout( function () {
            $("#treatment_doctor_filter").val(result.doctor_id).change();
        },900);

        setTimeout( function () {
            $("#treatment_resource_filter").val(result.machine_id).change();
        },1000);

    }

    $("#Add_comment").click(function () {

        if ($('input[name=comment]').val() !== '') {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'get',
                url: route('admin.appointments.storecomment'),
                data: {
                    'comment': $('input[name=comment]').val(),
                    'appointment_id': $('input[name=appointment_id]').val(),
                },
                success: function (data) {
                    console.log(data);
                    $('#commentsection').prepend(commentData(data.username, data.appointmentCommentDate, data.appointment.comment));
                },

            });
        } else {
            toastr.error("Please fill out the comment field");
        }
        $('#cment')[0].reset();
    });

});

function toggleSection($this, $class) {

    $(".menu-item").removeClass('menu-item-active');
    $(".manage-" + $class).addClass('menu-item-active');

    setQueryStringParameter('city_id');
    setQueryStringParameter('location_id');
    setQueryStringParameter('doctor_id');
    setQueryStringParameter('machine_id');

    $("#consultancy_city_filter").val('').trigger("change")
    $("#consultancy_location_filter").val('').trigger("change")
    $("#consultancy_doctor_filter").val('').trigger("change")

    if ($class == 'appointment') {
        $(".export-appointments").show();
        setQueryStringParameter('tab', $class);
        reInitTable();
       // setQueryStringParameter('tab');
    } else {
        $(".export-appointments").hide();
        setQueryStringParameter('tab', $class);
    }

    $(".appointment").addClass("d-none");
    $("." + $class + "-section").removeClass("d-none");

    $(".change-tab").removeClass("nav-bar-active");
    $this.addClass("nav-bar-active");

    $(".change-label").text($this.text())
}


let loadLocations = function (cityId, appointment = null) {

    if(cityId != '' ) {

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_locations'),
            type: 'POST',
            data: {
                city_id: cityId
            },
            cache: false,
            success: function(response) {
                if(response.status) {

                    let dropdowns =  response.data.dropdown;
                    let dropdown_options =  '<option value="">Select a Location</option>';

                    Object.entries(dropdowns).forEach(function (dropdown) {
                        dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                    });

                    let result = get_query();

                    if (appointment && appointment == 'consultancy') {
                        $('#consultancy_location_filter').html(dropdown_options);
                        setQueryStringParameter('city_id', cityId);

                        if (typeof result.location_id !== "undefined") {
                            $("#consultancy_location_filter").val(result.location_id).change();
                        }
                    } else  if (appointment && appointment == 'treatment') {
                        $('#treatment_location_filter').html(dropdown_options);
                        setQueryStringParameter('city_id', cityId);

                        if (typeof result.location_id !== "undefined") {
                            $("#treatment_location_filter").val(result.location_id).change();
                        }
                    } else {
                        $('#edit_location').html(dropdown_options);
                    }
                    $('.select2').select2({ width: '100%' });
                    resetDoctors();
                } else {
                    resetDropdowns();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetDropdowns();
            }
        });
    } else {
        resetDropdowns();
    }
}

let loadDoctors = function (locationId, appointment = null) {

    if (locationId != '' && locationId != null) {

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_doctors'),
            type: 'POST',
            data: {
                location_id: locationId
            },
            cache: false,
            success: function(response) {
                if(response.status) {

                    let dropdowns =  response.data.dropdown;
                    let dropdown_options =  '<option value="">Select a Doctor</option>';

                    Object.entries(dropdowns).forEach(function (dropdown) {
                        dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                    });

                    let result = get_query();

                    if (appointment && appointment == 'consultancy') {
                        $('#consultancy_doctor_filter').html(dropdown_options);
                        setQueryStringParameter('location_id', locationId);

                        if (typeof result.doctor_id !== "undefined") {
                            $("#consultancy_doctor_filter").val(result.doctor_id).change();
                        }

                    } else if (appointment && appointment == 'treatment') {
                        $('#treatment_doctor_filter').html(dropdown_options);
                        setQueryStringParameter('location_id', locationId);

                        loadMachine(locationId);
                        loadCalendar();

                        if (typeof result.doctor_id !== "undefined" && typeof result.reload === "undefined") {
                          //  $("#treatment_doctor_filter").val(result.doctor_id).change();
                        }
                    } else {
                        $('#edit_doctor').html(dropdown_options);
                    }

                    $('.select2').select2({ width: '100%' });
                } else {
                    resetDoctors();
                }
                setQueryStringParameter('reload');
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetDoctors();
            }
        });
    } else {
        resetDoctors();
    }

}

var loadScheduledTime = '<input id="edit_scheduled_time" readonly="true" name="scheduled_time" class="form-control" type="text" class="required" placeholder="Schedule Time">';

let doctorListener = function (doctorId) {

    var scheduled_date = $('#edit_scheduled_date').val();

    if (
        (doctorId != '' && doctorId != null) &&
        (scheduled_date != '' && scheduled_date != null)
    ) {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_doctor_rota'),
            type: 'POST',
            data: {
                location_id: $('#edit_location').val(),
                doctor_id: doctorId,
                scheduled_date: scheduled_date,
                appointment_id: $('#appointment_id').val(),
                resourceRotaDayID: $('#resourceRotaDayID').val(),
                form: 'EditFormValidation',
                idPrefix: 'consultancty_'
            },
            cache: false,
            success: function(response) {

                if(response.status) {
                    if(
                        (response.resource_has_rota_day.start_time != '' && response.resource_has_rota_day.start_time != null) &&
                        (response.resource_has_rota_day.end_time != '' && response.resource_has_rota_day.end_time != null)
                    ) {
                        resetScheduledTime();
                        if(response.resource_has_rota_day.start_off){
                            $('#edit_scheduled_time').val(response.selected);
                            loadScheduledTime(response.resource_has_rota_day.start_time, response.resource_has_rota_day.end_time,response.resource_has_rota_day.start_off,response.resource_has_rota_day.end_off);
                        } else {
                            $('#edit_scheduled_time').val(response.selected);
                            loadScheduledTime(response.resource_has_rota_day.start_time, response.resource_has_rota_day.end_time,false,false);
                        }


                    } else {
                        $('#rotaError').show();
                       // resetScheduledTime();
                    }
                } else {
                    //resetScheduledTime();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
               // resetScheduledTime();
            }
        });
    } else {
       // resetScheduledTime();
    }
}

let resetDoctors = function () {
    var doctorDropdown = '<select id="doctor_id" class="form-control select2 required" name="doctor_id"><option value="" selected="selected">Select a Doctor</option></select>';
    $('#convert_doctor_id').html(doctorDropdown);
    $('.select2').select2({ width: '100%' });
}


let ConsultancyDoctorListener = function (doctorId) {

    if (doctorId != '' && doctorId != null) {

        if (typeof calendar !== "undefined") { /*if already initiate then destroy first*/
            calendar.destroy();
        }
        var result = get_query();

        if ($("#consultancy_city_filter").val() !== ""
            && $("#consultancy_location_filter").val() !== ""
            && $("#consultancy_doctor_filter").val() !== ""
            && typeof result.tab !== 'undefined' && result.tab == 'consultancy') {

            window.eventData = {}
            window.eventData.city_id = $("#consultancy_city_filter").val()
            window.eventData.location_id = $("#consultancy_location_filter").val()
            window.eventData.doctor_id = $("#consultancy_doctor_filter").val();
            window.eventData.id = null;
            window.eventData.firstTime = true;

            ConsultancyCalendar.init();
        }

    }

    setQueryStringParameter('doctor_id', doctorId);
}

function getPatientDetail($this) {

    $.ajax({
        type: 'get',
        url: route('admin.users.get_patient_number'),
        data: {
            'patient_id': $this.val()
        },
        success: function (resposne) {
            if (resposne.status) {
                let patient = resposne.data.patient;
                $('#create_consultancy_phone').val(patient?.phone);
                $('#create_patient_name').val(patient?.name);
                $('#create_consultancy_gender').val(patient?.gender).change();
            }

        },
    });

    $("#consultancy_patient_id").val($this.val() != '' ? $this.val() : '0');
}

function newPatient() {
    $(".new_patient_text").toggle();
    $("#create_consultancy_phone").val('').prop('readonly', false);
    $("#create_patient_name").val('').prop('readonly', false);
    $("#create_consultancy_gender").val('').change();
}
