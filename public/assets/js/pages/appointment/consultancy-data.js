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
});

function toggleSection($this, $class) {

    setQueryStringParameter('city_id');
    setQueryStringParameter('location_id');
    setQueryStringParameter('doctor_id');

    if ($class == 'appointment') {
        $(".export-appointments").show();
        reInitTable();
    } else {
        $(".export-appointments").hide();
    }

    $(".appointment").addClass("d-none");
    $("." + $class + "-section").removeClass("d-none");

    $(".change-tab").removeClass("nav-bar-active");
    $this.addClass("nav-bar-active");

    setQueryStringParameter('tab', $class);

    $(".change-label").text($this.text())
}


let loadLocations = function (cityId, appointment = null) {

    /*if (appointment) {
        setQueryStringParameter('city_id');
        setQueryStringParameter('location_id');
        setQueryStringParameter('doctor_id');
    }*/

    if(cityId != '') {
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

                    if (appointment && appointment == 'consultancy') {
                        $('#consultancy_location_filter').html(dropdown_options);
                        setQueryStringParameter('city_id', cityId);

                        var result = get_query();
                        if (typeof result.location_id !== "undefined") {
                            $("#consultancy_location_filter").val(result.location_id).change();
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

        if (appointment) {
            setQueryStringParameter('location_id');
        }

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

                    if (appointment && appointment == 'consultancy') {
                        $('#consultancy_doctor_filter').html(dropdown_options);
                        setQueryStringParameter('location_id', locationId);

                        var result = get_query();
                        if (typeof result.doctor_id !== "undefined") {
                            $("#consultancy_doctor_filter").val(result.doctor_id).change();
                        }

                    } else {
                        $('#edit_doctor').html(dropdown_options);
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
                        resetScheduledTime();
                    }
                } else {
                    resetScheduledTime();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetScheduledTime();
            }
        });
    } else {
        resetScheduledTime();
    }
}


var resetScheduledTime = function () {
    // $('#edit_scheduled_time').html(loadScheduledTime);
}

let resetDoctors = function () {
    var doctorDropdown = '<select id="doctor_id" class="form-control select2 required" name="doctor_id"><option value="" selected="selected">Select a Doctor</option></select>';
    $('#convert_doctor_id').html(doctorDropdown);
    $('.select2').select2({ width: '100%' });
}


let ConsultancyDoctorListener = function (doctorId) {

    if (doctorId != '' && doctorId != null) {
        $("#add_consulting").attr('href',route('admin.appointments.consulting.create')+ "?" + window.location.href.split("?")[1]);
        $("#calander_block").css("display",'block');

        reInitConsultancyCalendar();

    }

    setQueryStringParameter('doctor_id', doctorId);
}

function reInitConsultancyCalendar() {

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

function getAppointments() {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.load_scheduled_appointments'),
        type: 'GET',
        data: {
            city_id: $('#consultancy_city_filter').val(),
            location_id: $('#consultancy_location_filter').val(),
            doctor_id: $('#consultancy_doctor_filter').val(),
            start: start.format(),
            end: end.format(),
        },
        cache: false,
        success: function(response) {
            console.log(response);
            if (response.status == '1') {
                if (response.rotas[0].doctor_rotas.length == 0) {
                    console.log("hello");
                    type = "info";
                    message = "Doctor rotas not defined.";
                    Utils.notification('info', "Doctor rotas not defined.");
                }
                minTime = response.min_time;
                $("#calendar").fullCalendar('option', 'minTime', minTime);
                var events = [];
                //  var currentDate = null;
                $.each(response.events, function(id, appointmentObj) {
                    if (appointmentObj.id == window.eventData.id && window.eventData.firstTime == true) {
                        events.push({
                            id: appointmentObj.id,
                            title: "Name : " + appointmentObj.patient + " <br> Service: " + appointmentObj.service + " <br> Created By: " + appointmentObj.created_by, // use the element's text as the event title
                            description: "<p>Name : " + appointmentObj.patient + " </p><p> Service: " + appointmentObj.service + " </p><p> Created By: " + appointmentObj.created_by + "</p>",
                            duration: appointmentObj.duration, // use the element's text as the event title
                            editable: appointmentObj.editable, // use the element's text as the event title,
                            color: "#000000", // use the element's text as the event title
                            resourceId: appointmentObj.resourceId,
                            start: appointmentObj.start,
                            end: appointmentObj.end,
                            durationEditable: false,
                            eventDurationEditable: false,
                            overlap: true,
                            constraint: 'availableForMeeting', // defined below
                        });
                        var date = moment(appointmentObj.start, "YYYY-MM-DD");
                        $("#calendar").fullCalendar('gotoDate', date);
                        window.eventData.firstTime = false;
                    } else if (appointmentObj.id == window.eventData.id && window.eventData.firstTime == false) {
                        events.push({
                            id: appointmentObj.id,
                            title: "Name : " + appointmentObj.patient + " <br> Service: " + appointmentObj.service + " <br> Created By: " + appointmentObj.created_by, // use the element's text as the event title
                            description: "<p>Name : " + appointmentObj.patient + " </p><p> Service: " + appointmentObj.service + " </p><p> Created By: " + appointmentObj.created_by + "</p>",
                            duration: appointmentObj.duration, // use the element's text as the event title
                            editable: appointmentObj.editable, // use the element's text as the event title,
                            color: "#000000", // use the element's text as the event title
                            resourceId: appointmentObj.resourceId,
                            start: appointmentObj.start,
                            end: appointmentObj.end,
                            durationEditable: false,
                            eventDurationEditable: false,
                            overlap: true,
                            constraint: 'availableForMeeting', // defined below
                        });
                    } else {

                        events.push({
                            id: appointmentObj.id,
                            title: "Name : " + appointmentObj.patient + " <br> Service: " + appointmentObj.service + " <br> Created By: " + appointmentObj.created_by, // use the element's text as the event title
                            description: "<p>Name : " + appointmentObj.patient + " </p><p> Service: " + appointmentObj.service + " </p><p> Created By: " + appointmentObj.created_by + "</p>",
                            duration: appointmentObj.duration, // use the element's text as the event title
                            editable: appointmentObj.editable, // use the element's text as the event title,
                            color: appointmentObj.color, // use the element's text as the event title
                            resourceId: appointmentObj.resourceId,
                            start: appointmentObj.start,
                            end: appointmentObj.end,
                            durationEditable: false,
                            eventDurationEditable: false,
                            overlap: true,
                            constraint: 'availableForMeeting', // defined below
                        });

                        if (window.eventData.createdId == appointmentObj.id) {
                            console.log("moving to that date " + window.eventData.createdId + " dand date : " + appointmentObj.start);
                            var date = moment(appointmentObj.start, "YYYY-MM-DD");
                            $("#calendar").fullCalendar('gotoDate', date);
                            window.eventData.createdId = null;
                        }
                    }
                });

                $.each(response.rotas[0].doctor_rotas, function(id, rota) {
                    if (rota.active == '1') {
                        /**
                         * Case 1: All times are added
                         */
                        if (rota.start_time && rota.start_off) {
                            events.push({
                                id: 'availableForMeeting',
                                start: $.fullCalendar.moment(rota.date + " " + rota.start_time, 'YYYY-MM-DD HH:mm a').stripZone().format(),
                                end: $.fullCalendar.moment(rota.date + " " + rota.start_off, 'YYYY-MM-DD HH:mm a').stripZone().format(),
                                resourceId: $('#doctor_id').val(),
                                rendering: 'background'
                            });
                            events.push({
                                id: 'availableForMeeting',
                                start: $.fullCalendar.moment(rota.date + " " + rota.end_off, 'YYYY-MM-DD HH:mm a').stripZone().format(),
                                end: $.fullCalendar.moment(rota.date + " " + rota.end_time, 'YYYY-MM-DD HH:mm a').stripZone().format(),
                                resourceId: $('#doctor_id').val(),
                                rendering: 'background'
                            });
                        } else if (rota.start_time && !rota.start_off) {
                            events.push({
                                id: 'availableForMeeting',
                                start: $.fullCalendar.moment(rota.date + " " + rota.start_time, 'YYYY-MM-DD HH:mm a').stripZone().format(),
                                end: $.fullCalendar.moment(rota.date + " " + rota.end_time, 'YYYY-MM-DD HH:mm a').stripZone().format(),
                                resourceId: $('#doctor_id').val(),
                                rendering: 'background'
                            });
                        }
                    }
                });

                callback(events);
            } else {
                var events = [];
                callback(events);
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            var events = [];
            callback(events);
        }
    });
}
