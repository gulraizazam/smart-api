"use strict";

var calendar;

var ConsultancyCalendar = function() {

    return {
        init: function() {
            var minxTime;
            var maxTime;
            var todayDate = moment().startOf('day');
            var YM = todayDate.format('YYYY-MM');
            var YESTERDAY = todayDate.clone().subtract(1, 'day').format('YYYY-MM-DD');
            var TODAY = todayDate.format('YYYY-MM-DD');
            var TOMORROW = todayDate.clone().add(1, 'day').format('YYYY-MM-DD');

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
                //minTime: "09:00:00",
                //maxTime: "23:00:00",

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
                eventLimit: true,
                navLinks: true,
                droppable: true,
                setEventId: function(eventId) {
                    window.eventData.createdId = eventId;
                },
                events: function(event, callback) {

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
                            start: formatDate(event.start, 'YYYY-MM-DDTHH:mm:ss'),
                            end: formatDate(event.end, 'YYYY-MM-DDTHH:mm:ss'),
                        },
                        cache: false,
                        success: function(response) {
                            if (response.status == '1') {

                                if (response.rotas[0].doctor_rotas.length == 0) {
                                    toastr.error("Doctor rotas not defined.")
                                }

                                minxTime = response.start_time;
                                maxTime = response.end_time;

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
                                       // $("#calendar").fullCalendar('gotoDate', date);
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
                                         //   $("#calendar").fullCalendar('gotoDate', date);
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

                },

                eventClick:  function(info, jsEvent, view) {
                    clickEvent(info, jsEvent, view)
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

           setTimeout( function () {

               if (typeof minxTime !== "undefined") {
                   calendar.setOption('minTime', minxTime);
               }

               if (typeof maxTime !== "undefined") {
                   calendar.setOption('maxTime', maxTime);
               }

            }, 500);

           calendar.render();
        }
    };
}();

jQuery(document).ready(function() {
    //ConsultancyCalendar.init();
});

function clickEvent(info, jsEvent, view) {

    let event = info.event.extendedProps;
    let eventApi = info.event._def;
    $('#resource_days_id').val(eventApi.publicId);
    $('#resource_days_date').val(info.event.start.toISOString().split('T')[0]);
    $('#resource_days_start_time').val(event.start_time);
    $('#resource_days_end_time').val(event.end_time);

    $('#resource_days_start_time_break').val(event.start_off);
    $('#resource_days_end_time_break').val(event.end_off);

    if($('#resource_days_start_time').val())
    {
        $('#dayOperation :input').removeAttr('disabled');
        $("#dayElement").prop( "checked", false );
    }
    else{
        $("#estado_cat").prop( "checked", true );
        $('#dayOperation :input').attr('disabled', true);
        $("#dayElement").prop( "checked", true );
    }

    isLeave($("#dayElement"));

    if(event.checked == 1){
        $('#backdateenvent').hide();
        $('#ajax_resourcerotas_calenderedit').modal('show');
    } else {
        $('#backdateenvent').show();
    }
}
