"use strict";

var calendar;
var start_date;
function patient_search_func() {
    $("#patient_search_id_selector").select2({
        ajax: {
        type: "GET",
        url: route('admin.users.getpatient.id'),
        dataType: 'json',
        delay: 250,
        data: function (params) {
        return {
            search: params.term // search term
        };
        },
        processResults: function (response) {
        return {
            results: response.data.patients,
        };
        },
        cache: true
        },
        placeholder: 'Search for a repository',
        templateResult:  formatRepo,
        templateSelection: formatRepoSelection

    });

    $("#patient_search_id_selector").on("select2:select", function (e) {
        var thisID = $(this).val();
        $(this).parent().parent('div').find('.search_field').val(thisID).change();
    });

    function formatRepo (repo) {
        var $container, search_id = 'patient_search_id_selector', flag = 1;
        if (repo.loading) {
            $container = $(
                "<div class='select2-result-repository__avatar'>Searching</div>"
            );
        } else{
            $container = $(
                '<div class="select2-result-repository__avatar tst">' + repo.name + " - C " + repo.id +"</div>"
            );
        }
        return $container;
    }

    function formatRepoSelection (repo) {
        return repo.name || repo.text;
    }
}
var getURLQuery = get_query();
var ActiveURL;
var ConsultancyCalendar = function() {
    return {
        init: function(start) {
            var minxTime;
            var maxTime;
            var todayDate = moment().startOf('day');
            var TODAY = todayDate.format('YYYY-MM-DD');

            if (typeof start !== "undefined") {
                TODAY = formatDate(start, 'YYYY-MM-DD');
            }
            ActiveURL = TODAY;
            var calendarEl = document.getElementById('consultancy_calendar');
            calendar = new FullCalendar.Calendar(calendarEl, {
                plugins: [ 'bootstrap', 'interaction', 'dayGrid', 'timeGrid', 'list' ],
                themeSystem: 'bootstrap',

                isRTL: KTUtil.isRTL(),

                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },

                height: 800,
                slotDuration: '00:05:00',
                contentHeight: 780,
                aspectRatio: 3,
                minTime: "09:00:00",
                maxTime: "23:00:00",
                displayEventTime: true,

                nowIndicator: true,
                now: TODAY,
                views: {
                    dayGridMonth: { buttonText: 'month' },
                    timeGridWeek: { buttonText: 'week' },
                    timeGridDay: { buttonText: 'day' }
                },
                defaultView: 'timeGridWeek',
                defaultDate: TODAY,

                editable: true,
                droppable: true,
                eventLimit: true, // allow "more" link when too many events
                navLinks: true,
                events: function(event, callback) {

                    $('.appointment-loader-base').show();
                    start_date = event.start;

                    if ($('#consultancy_doctor_filter').val() !== null
                    ) {
                        $.ajax({
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            url: route('admin.appointments.load_scheduled_appointments'),
                            type: 'GET',
                            data: {
                                location_id: $('#consultancy_location_filter').val(),
                                doctor_id: $('#consultancy_doctor_filter').val().length !== 'undefined' ? $('#consultancy_doctor_filter').val() :
                                '',
                                start: formatDate(event.start, 'YYYY-MM-DDTHH:mm:ss'),
                                end: formatDate(event.end, 'YYYY-MM-DDTHH:mm:ss'),
                            },
                            cache: false,
                            success: async function (response) {
                                minxTime = response.start_time;
                                maxTime = response.end_time;
                                await ConsultancyCalendar.loadEvents(response, callback);
                                ConsultancyCalendar.showOnlyAvailableSlots(minxTime, maxTime);
                                $('.appointment-loader-base').hide();
                            },
                            error: function (xhr, ajaxOptions, thrownError) {
                                var events = [];
                                callback(events);
                            }
                        });
                    }
                },
                eventConstraint: { /*restrict event drop on back dates*/
                    start: moment().format('YYYY-MM-DD'),
                },
                eventDrop: function (info) { /*event drag drop*/
                  ConsultancyCalendar.checkAndUpdateAppointment(info);
                },
                eventClick:  function(info, jsEvent, view) { /*Click event to edit existing one*/
                    clickEvent(info, jsEvent, view)
                },
                dateClick: function(info, jsEvent, view, resource) { /*Create new event on for available dates*/
                    ConsultancyCalendar.createConsultancy(info);
                },
                eventMouseEnter: function(e) { /*Show info on mouse over*/
                    hoverPopup(e);
                },
                eventRender: function(info) {

                    var element = $(info.el);
                    let title = element.find('.fc-title');
                    title.html(title.text());

                    if (info.event.extendedProps && info.event.extendedProps.description) {
                        if (element.hasClass('fc-day-grid-event')) {
                            element.data('content', info.event.extendedProps.description);
                            element.data('placement', 'top');
                            KTApp.initPopover(element);
                        } else if (element.hasClass('fc-time-grid-event')) {
                            element.find('.fc-title').append('<div class="fc-description">' + info.event.extendedProps.description + '</div>');
                        } else if (element.find('.fc-list-item-title').lenght !== 0) {
                            element.find('.fc-list-item-title').append('<div class="fc-description">' + info.event.extendedProps.description + '</div>');
                        }
                    }
                }
            });

           calendar.render();
        },
        async loadEvents(response, callback) {
            patient_search_func();
            if (response.status) {
                if($("#consultancy_doctor_filter").val() != ""){
                    if (response.rotas[0].doctor_rotas.length == 0) {
                        toastr.error("Doctor rotas not defined.")
                    }
                }
                var events = [];
                //  var currentDate = null;
                $.each(response.events, function(id, appointmentObj) {
                    if (appointmentObj.id == window.eventData.id && window.eventData.firstTime == true) {
                        events.push({
                            id: appointmentObj.id,
                            title: "Name : " + appointmentObj.patient + " <br> Service: " + appointmentObj.service + " <br> Created By: " + appointmentObj.created_by, // use the element's text as the event title
                            //description: "<p>Name : " + appointmentObj.patient + " </p><p> Service: " + appointmentObj.service + " </p><p> Created By: " + appointmentObj.created_by + "</p>",
                            duration: appointmentObj.duration, // use the element's text as the event title
                            editable: appointmentObj.editable, // use the element's text as the event title,
                            backgroundColor: "#000000", // use the element's text as the event title
                            resourceId: appointmentObj.resourceId,
                            start: appointmentObj.start,
                            end: appointmentObj.end,
                            durationEditable: false,
                            eventDurationEditable: false,
                            overlap: true,
                        });
                        var date = moment(appointmentObj.start, "YYYY-MM-DD");
                        // $("#calendar").fullCalendar('gotoDate', date);
                        window.eventData.firstTime = false;
                    } else if (appointmentObj.id == window.eventData.id && window.eventData.firstTime == false) {
                        events.push({
                            id: appointmentObj.id,
                            title: "Name : " + appointmentObj.patient + " <br> Service: " + appointmentObj.service + " <br> Created By: " + appointmentObj.created_by, // use the element's text as the event title
                            // description: "<p>Name : " + appointmentObj.patient + " </p><p> Service: " + appointmentObj.service + " </p><p> Created By: " + appointmentObj.created_by + "</p>",
                            duration: appointmentObj.duration, // use the element's text as the event title
                            editable: appointmentObj.editable, // use the element's text as the event title,
                            backgroundColor: "#000000", // use the element's text as the event title
                            resourceId: appointmentObj.resourceId,
                            start: appointmentObj.start,
                            end: appointmentObj.end,
                            durationEditable: false,
                            eventDurationEditable: false,
                            overlap: true,
                        });
                    } else {

                        events.push({
                            id: appointmentObj.id,
                            title: "Name : " + appointmentObj.patient + " <br> Service: " + appointmentObj.service + " <br> Created By: " + appointmentObj.created_by, // use the element's text as the event title
                            duration: appointmentObj.duration, // use the element's text as the event title
                            editable: appointmentObj.editable, // use the element's text as the event title,
                            color: appointmentObj.color, // use the element's text as the event title
                            resourceId: appointmentObj.resourceId,
                            start: appointmentObj.start,
                            end: appointmentObj.end,
                            durationEditable: false,
                            eventDurationEditable: false,
                            overlap: true,
                        });

                        if (window.eventData.createdId == appointmentObj.id) {
                            console.log("moving to that date " + window.eventData.createdId + " dand date : " + appointmentObj.start);
                            var date = moment(appointmentObj.start, "YYYY-MM-DD");
                            //   $("#calendar").fullCalendar('gotoDate', date);
                            window.eventData.createdId = null;
                        }
                    }
                });
                if(jQuery('#consultancy_doctor_filter').val() !== ''){
                    $.each(response.rotas[0].doctor_rotas, function(id, rota) {
                        if (rota.active == '1') {
                            /**
                             * Case 1: All times are added
                             */
                            if (rota.start_time && rota.start_off) {
                                events.push({
                                    id: 'availableForMeeting',
                                    start: formatDate(rota.date + " " + rota.start_time, 'YYYY-MM-DDTHH:mm:ss'),
                                    end: formatDate(rota.date + " " + rota.start_off, 'YYYY-MM-DDTHH:mm:ss'),
                                    resourceId: $('#consultancy_doctor_filter').val(),
                                    rendering: 'background'
                                });
                                events.push({
                                    id: 'availableForMeeting',
                                    start: formatDate(rota.date + " " + rota.end_off, 'YYYY-MM-DDTHH:mm:ss'),
                                    end: formatDate(rota.date + " " + rota.end_time, 'YYYY-MM-DDTHH:mm:ss'),
                                    resourceId: $('#consultancy_doctor_filter').val(),
                                    rendering: 'background'
                                });
                            } else if (rota.start_time && !rota.start_off) {
                                events.push({
                                    id: 'availableForMeeting',
                                    start: formatDate(rota.date + " " + rota.start_time, 'YYYY-MM-DDTHH:mm:ss'),
                                    end: formatDate(rota.date + " " + rota.end_time, 'YYYY-MM-DDTHH:mm:ss'),
                                    resourceId: $('#consultancy_doctor_filter').val(),
                                    rendering: 'background'
                                });
                            }
                        }
                    });
                }
                callback(events);
            } else {
                var events = [];
                callback(events);
            }
        },
        showOnlyAvailableSlots: function(minxTime, maxTime) {

            if (typeof minxTime !== "undefined") {
                calendar.setOption('minTime', minxTime);
            }

            if (typeof maxTime !== "undefined") {
                calendar.setOption('maxTime', maxTime);
            }

            rotaTimeTitle();
        },
        checkAndUpdateAppointment: function(info) {

            let event = info.event;
            if($("#consultancy_doctor_filter").val()!=""){
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.appointments.check_and_save_appointment'),
                    type: 'POST',
                    data: {
                        id: event.id,
                        start: formatDate(event.start, 'YYYY-MM-DDTHH:mm:ss'),
                        end: formatDate(event.end, 'YYYY-MM-DDTHH:mm:ss'),
                        doctor_id: $("#consultancy_doctor_filter").val(),
                        location_id: $("#consultancy_location_filter").val()
                    },
                    cache: false,
                    success: function(response) {
                        if (response.status) {
                           toastr.success(response.message);
                        } else {
                            toastr.error(response.message);
                            reInitCalendar(start_date, calendar, ConsultancyCalendar);
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        toastr.error("unable to process the request, please try again.")
                    }
                });
            }else{
                toastr.error("Please select doctor first");
            }

        },
        setEventId: function(eventId) {
            window.eventData.createdId = eventId;
        },
        createConsultancy: function (info) {
            let result = get_query();
            let start = formatDate(info.date, 'YYYY-MM-DDTHH:mm:ss');
            let create_url = route('admin.appointments.consulting.create', {
                appointment_type: 'consulting',
                doctor_id: result.doctor_id,
                location_id: result.location_id,
                start: start
            });
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: create_url,
                type: 'GET',
                cache: false,
                success: function(response) {
                    if (response.status) {
                       setCreateConsultancy(response, start);
                    } else {
                        toastr.error(response.message)
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    toastr.error("Please select doctor first");
                }
            });

        },

    };
}();

function clickEvent(info, jsEvent, view) {

    let event = info.event.extendedProps;
    let eventApi = info.event._def;
    let id = eventApi.publicId;
    if (id !== 'availableForMeeting') {

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.detail', [id]),
            type: 'Get',
            cache: false,
            success: function (response) {
                if (response.status) {
                    setDetailData(response);
                } else {
                    toastr.success(response.message)
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                toastr.error("Unable to process the request.")
            }
        });

    }

}

function setDetailData(response) {

    try {

        let appointment = response.data.appointment;
        let permissions = response.data.permissions;
        let invoice = response.data.invoice;
        let invoiceid = response.data.invoiceid;
        let patient = appointment.patient;
        let doctor = appointment.doctor;
        let city = appointment.city;
        let location = appointment.location;
        let appointment_status = appointment.appointment_status;
        let service = appointment.service;
        const [hourString, minute] = appointment.scheduled_time.split(":");
        const hour = +hourString % 24;
        var test = (hour % 12 || 12) + ":" + minute + (hour < 12 ? "AM" : "PM");
        detailActions(appointment, invoice, invoiceid, permissions);

        $("#modal_consultancy_detail").modal("show");

        $("#comment_appointment_id").val(appointment?.id ?? 0);
        $("#patient_name").text(patient?.name ?? 'N/A');
        $("#patient_phone").text(makePhoneNumber(patient?.phone, permissions.contact, 1));
        if (patient?.id) {
            $("#patient_c_id").text(makePatientId(patient?.id));
        }
        $("#patient_gender").text(getGender(patient?.gender));
        $("#patient_scheduled_time").text(formatDate(appointment?.scheduled_date, 'MMM, D, YY') + " at " + test);
        $("#doctor_name").text(doctor?.name ?? 'N/A');
        $("#city_name").text(city?.name ?? 'N/A');
        $("#center_name").text(location?.name ?? 'N/A');
        $("#appointment_status").text(appointment_status?.name ?? 'N/A');
        $("#service_consultancy_name").text(service?.name ?? 'N/A');
        $("#service_consultancy_name_title").text(service?.name ?? 'N/A');
        setComments(appointment);
    } catch (e) {
        showException(e);
    }
}


function setComments(appointment) {

    let appointment_comments = appointment.appointment_comments;
    let comment_html = '';
    if (appointment_comments.length) {
        Object.values(appointment_comments).forEach(function (comment) {
            comment_html += commentData(comment?.user?.name, comment?.created_at, comment?.comment);
        });
    }
    $("#commentsection").html(comment_html);
}



function setCreateConsultancy(response, start) {
    try {
        $("#create_patient_search").parent("div").find(".selection").remove();

        leadSearch('lead_search_id')
        //patientSearch('patient_search_id')

        $("#modal_create_consultancy").modal("show");
        $("#modal_create_consultancy_form")[0].reset();
        $('.patient_search_id').val(null).trigger('change');
        $('.lead_search_id').val(null).trigger('change');
        $('.new_patient_text').hide();

        let city_id = response.data.city_id;
        let doctor_id = response.data.doctor_id;
        let location_id = response.data.location_id;
        let employees = response.data.employees;
        let lead = response.data.lead;
        let lead_sources = response.data.lead_sources;
        let services = response.data.services;
        let setting = response.data.setting;
        let genders = response.data.genders;

        let consultancy_types = response.data.consultancy_types;

        /*Hidden fields*/
        $("#consultancy_lead_id").val(lead?.id);
        $("#consultancy_patient_id").val(lead?.patient_id ? lead?.patient_id : '0');
        $("#consultancy_city_id").val(city_id);
        $("#consultancy_location_id").val(location_id);
        $("#consultancy_doctor_id").val(doctor_id);
        $("#consultancy_resource_id").val(doctor_id);
        $("#consultancy_start").val(start);
        $("#consultancy_resource_id").val();
        $("#consultancy_appointment_type").val();
        $("#consultancy_cnic").val();
        $("#consultancy_email").val();
        $("#consultancy_dob").val();
        $("#consultancy_address").val();
        $("#consultancy_town_id").val();

        let type_options = '';
        if (consultancy_types) {
            Object.entries(consultancy_types).forEach(function (consultancy_type) {
                type_options += '<option value="'+consultancy_type[0]+'">'+consultancy_type[1]+'</option>';
            });
        }

        let service_options = '<option value="">Select a Service</option>';
        if (services) {
            Object.entries(services).forEach(function (service) {
                service_options += '<option value="'+service[0]+'">'+service[1]+'</option>';
            });
        }

        let gender_options = '<option value="">Select a Gender</option>';
        if (genders) {
            Object.entries(genders).forEach(function (gender) {
                gender_options += '<option value="'+gender[0]+'">'+gender[1]+'</option>';
            });
        }

        let source_options = '<option value="">Select a Source</option>';
        if (lead_sources) {
            Object.entries(lead_sources).forEach(function (source) {
                source_options += '<option value="'+source[0]+'">'+source[1]+'</option>';
            });
        }

        let employee_options = '<option value="">Select a Referrer</option>';
        if (employees) {
            Object.entries(employees).forEach(function (employee) {
                employee_options += '<option value="'+employee[0]+'">'+employee[1]+'</option>';
            });
        }
        var myDropDown=$("#create_consultancy_gender");
        myDropDown.attr('size',0);

        $("#create_consultancy_types").html(type_options);
        $("#create_consultancy_service").html(service_options);
        $("#create_consultancy_lead").html(source_options);
        $("#create_consultancy_referred_by").html(employee_options);
        $("#create_consultancy_gender").html(gender_options);

        if(setting?.data == '1') {
            $(".consult-type").show();
            $(".consultancy-service").removeClass("col-md-12").addClass("col-md-6");
        } else {
            $(".consultancy-service").removeClass("col-md-6").addClass("col-md-12");
            $(".consult-type").hide();
        }

        setTimeout( function () {
            $(".select2-selection").removeClass("select2-is-invalid");
        }, 200);

    } catch (e) {
        showException(e);
    }
}

function hoverPopup(info) {

    let id = info.event.id;
    let eventApi = info.event._def;
    let props = info.event.extendedProps;

    if (id !== 'availableForMeeting') {

       /*let left = event.pageX - $(info.el).position().left;
       let top = event.pageY - $(info.el).position().top;*/

        let left = event.pageX - $('#consultancy_calendar').offset().left + 320;
        let top = event.pageY - $('#consultancy_calendar').offset().top + 400;

        $(".modal_consultancy_popup").css({top: top,left: left}).show();

        let time = $(info.el).find(".fc-time").data('full');

        $(".full-time").html(time);
        $(".event-name").html(eventApi.title);

    } else {
        $(".modal_consultancy_popup").hide();
    }

}
