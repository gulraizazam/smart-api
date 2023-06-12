"use strict";

var treatment_calendar;
var start_treatment_date;
var ActiveURL;
var TreatmentCalendar = function() {
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
            var calendarEl = document.getElementById('treatment_calendar');
            treatment_calendar = new FullCalendar.Calendar(calendarEl, {
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
                nowIndicator: true,
                now: TODAY,
                views: {
                    dayGridMonth: { buttonText: 'month' },
                    timeGridWeek: { buttonText: 'week' },
                    timeGridDay: { buttonText: 'day' }
                },
                defaultView: 'timeGridWeek',
                defaultDate: ActiveURL,
                editable: true,
                droppable: true,
                eventLimit: true, // allow "more" link when too many events
                navLinks: true,
                groupByResource: true,
                businessHours: true,
                refetchResourcesOnNavigate: true,
                resources: function (callback, start, end, timezone) {
                },
                events: function(event, callback) {
                    $('.appointment-loader-base').show();
                    start_treatment_date = event.start;
                    var query_result = get_query();
                    if (
                        $('#treatment_location_filter').val() !== null
                    ) {
                        $.ajax({
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            url: route('admin.appointments.load_scheduled_service_appointments'),
                            type: 'GET',
                            data: {
                                location_id: $('#treatment_location_filter').val(),
                                doctor_id: query_result.doctor_id,
                                machine_id: $('#treatment_resource_filter').val(),
                                start: formatDate(event.start, 'YYYY-MM-DD'),
                                end: formatDate(event.end, 'YYYY-MM-DD'),
                            },
                            cache: false,
                            success: async function (response) {
                                minxTime = response.start_time;
                                maxTime = response.end_time;
                                await TreatmentCalendar.loadTreatmentEvents(response, callback);
                                TreatmentCalendar.showOnlyAvailableSlotsTreatment(minxTime, maxTime);
                                $('.appointment-loader-base').hide();
                            },
                            error: function (xhr, ajaxOptions, thrownError) {
                                var events = [];
                                callback(events);
                            }
                        });

                      //  TreatmentCalendar.getResources(event, callback);

                    }

                },
                eventConstraint: { /*restrict event drop on back dates*/
                    start: moment().format('YYYY-MM-DD'),
                },
                eventDrop: async function (info) { /*event drag drop*/
                    await TreatmentCalendar.checkAndUpdateTreatment(info);
                    setTimeout( function () {
                        reInitCalendar(start_treatment_date, treatment_calendar, TreatmentCalendar);
                    },200);

                },
                eventClick:  function(info, jsEvent, view) { /*Click event to edit existing one*/
                    TreatmentCalendar.clickTreatmentEvent(info, jsEvent, view)
                },
                dateClick: function(info, jsEvent, view, resource) { /*Create new event on for available dates*/
                    TreatmentCalendar.createTreatment(info);
                },
                eventMouseEnter: function(e) { /*Show info on mouse over*/
                    TreatmentCalendar.hoverPopup(e);
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

            treatment_calendar.render();
        },

        async loadTreatmentEvents(response, callback) {

            if (response.status) {
                if($('#treatment_doctor_filter').val() !=''){
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
                            color:appointmentObj.color, // use the element's text as the event title
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
                if($('#treatment_doctor_filter').val() !=''){
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
                                    resourceIds: response.resource_ids,
                                    rendering: 'background'
                                });
                                events.push({
                                    id: 'availableForMeeting',
                                    start: formatDate(rota.date + " " + rota.end_off, 'YYYY-MM-DDTHH:mm:ss'),
                                    end: formatDate(rota.date + " " + rota.end_time, 'YYYY-MM-DDTHH:mm:ss'),
                                    resourceIds: response.resource_ids,
                                    rendering: 'background'
                                });
                            } else if (rota.start_time && !rota.start_off) {
                                events.push({
                                    id: 'availableForMeeting',
                                    start: formatDate(rota.date + " " + rota.start_time, 'YYYY-MM-DDTHH:mm:ss'),
                                    end: formatDate(rota.date + " " + rota.end_time, 'YYYY-MM-DDTHH:mm:ss'),
                                    resourceIds: response.resource_ids,
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

        showOnlyAvailableSlotsTreatment: function(minxTime, maxTime) {

            if (typeof minxTime !== "undefined") {
                treatment_calendar.setOption('minTime', minxTime);
            }

            if (typeof maxTime !== "undefined") {
                treatment_calendar.setOption('maxTime', maxTime);
            }

            rotaTimeTitle();
        },

        async checkAndUpdateTreatment(info) {

            let event = info.event;

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.appointments.check_service_schedule_and_save_appointment'),
                type: 'POST',
                data: {
                    id: event.id,
                    start: formatDate(event.start, 'YYYY-MM-DDTHH:mm:ss'),
                    end: formatDate(event.end, 'YYYY-MM-DDTHH:mm:ss'),
                    doctor_id: $("#treatment_doctor_filter").val(),
                    location_id: $("#treatment_location_filter").val(),
                    resourceId: $("#treatment_resource_filter").val(),
                },
                cache: false,
                success: function(response) {
                    if (response.status) {
                       toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    toastr.error("Unable to process the request, please try again.")
                }
            });
        },

        getResources(event, callback) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.appointments.get_room_resources_with_specific_date'),
                type: 'GET',
                data: {
                    start: formatDate(event.start, 'YYYY-MM-DD'),
                    end: formatDate(event.end, 'YYYY-MM-DD'),
                    location_id: $("#treatment_location_filter").val(),
                    machine_id: $("#treatment_resource_filter").val()
                },
                cache: false,
                success: function (response) {
                    if (response.status == '1') {
                        var resources = [];
                        $.each(response.data, function (id, resource) {

                            if (resource.resource_rota) {
                                var businessHoursArray = [];
                                if (resource.resource_rota.sunday) {
                                    var sunday = resource.resource_rota.sunday.split(",");
                                    var  sunday_start = sunday[0];
                                    var sunday_end = sunday[1];
                                    businessHoursArray.push({
                                        start: moment(sunday_start, "HH:mm a").format("HH:mm"),
                                        end: moment(sunday_end, "HH:mm a").format("HH:mm"),
                                        dow: [0]
                                    });
                                }

                                if (resource.resource_rota.monday) {
                                    var  monday = resource.resource_rota.monday.split(",");
                                    var   monday_start = monday[0];
                                    var  monday_end = monday[1];
                                    businessHoursArray.push({
                                        start: moment(monday_start, "HH:mm a").format("HH:mm"),
                                        end: moment(monday_end, "HH:mm a").format("HH:mm"),
                                        dow: [1]
                                    });
                                }

                                if (resource.resource_rota.tuesday) {
                                    var  tuesday = resource.resource_rota.tuesday.split(",");
                                    var  tuesday_start = tuesday[0];
                                    var  tuesday_end = tuesday[1];
                                    businessHoursArray.push({
                                        start: moment(tuesday_start, "HH:mm a").format("HH:mm"),
                                        end: moment(tuesday_end, "HH:mm a").format("HH:mm"),
                                        dow: [2]
                                    });
                                }

                                if (resource.resource_rota.wednesday) {
                                    var   wednesday = resource.resource_rota.wednesday.split(",");
                                    var  wednesday_start = wednesday[0];
                                    var  wednesday_end = wednesday[1];
                                    businessHoursArray.push({
                                        start: moment(wednesday_start, "HH:mm a").format("HH:mm"),
                                        end: moment(wednesday_end, "HH:mm a").format("HH:mm"),
                                        dow: [3]
                                    });
                                }


                                if (resource.resource_rota.thursday) {
                                    var  thursday = resource.resource_rota.thursday.split(",");
                                    var  thursday_start = thursday[0];
                                    var  thursday_end = thursday[1];
                                    businessHoursArray.push({
                                        start: moment(thursday_start, "HH:mm a").format("HH:mm"),
                                        end: moment(thursday_end, "HH:mm a").format("HH:mm"),
                                        dow: [4]
                                    });
                                }
                                if (resource.resource_rota.friday) {
                                    var friday = resource.resource_rota.friday.split(",");
                                    var  friday_start = friday[0];
                                    var  friday_end = friday[1];
                                    businessHoursArray.push({
                                        start: moment(friday_start, "HH:mm a").format("HH:mm"),
                                        end: moment(friday_end, "HH:mm a").format("HH:mm"),
                                        dow: [5]
                                    });
                                }

                                if (resource.resource_rota.saturday) {
                                    var  saturday = resource.resource_rota.saturday.split(",");
                                    var   saturday_start = saturday[0];
                                    var  saturday_end = saturday[1];
                                    businessHoursArray.push({
                                        start: moment(saturday_start, "HH:mm a").format("HH:mm"),
                                        end: moment(saturday_end, "HH:mm a").format("HH:mm"),
                                        dow: [6]
                                    });
                                }
                            }

                            resources.push({
                                id: resource.id,
                                title: resource.name, // use the element's text as the event title
                                businessHours: businessHoursArray
                            });

                        });
                        callback(resources);
                    } else {
                        var resources = [];
                        callback(resources);
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    var events = [];
                    callback(events);
                }
            });
        },

        setEventId: function(eventId) {
            window.eventData.createdId = eventId;
        },

        createTreatment: function (info) {

            removeExtraSelect2();

            $("#create_treatment_service").html('');
            $("#create_treatment_patient_search").html('');
            $("#modal_create_treatment_form")[0].reset();

            let start = formatDate(info.date, 'YYYY-MM-DDTHH:mm:ss');
            let create_url = route('admin.appointments.treatment.create', {
                //city_id : $("#treatment_city_filter").val(),
                location_id : $("#treatment_location_filter").val(),
                machine_id : $("#treatment_resource_filter").val(),
                doctor_id : $("#treatment_doctor_filter").val(),
                resource_id : $("#treatment_resource_filter").val(),
                start : start,
                appointment_type : 'treatment',
            });
            if($("#treatment_resource_filter").val() != ''){
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: create_url,
                    type: 'GET',
                    cache: false,
                    success: function(response) {

                        if (response.status) {
                           setCreateTreatment(response, start);
                        } else {
                            toastr.error(response.message)
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        toastr.error("Unable to process the request");
                    }
                });
            }else{
                toastr.error("Please select machine first");
            }

        },

        clickTreatmentEvent: function (info) {

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
                            setTreatmentDetailData(response);
                        } else {
                            toastr.error(response.message)
                        }
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        toastr.error("Unable to process the request.")
                    }
                });

            }

        },

        hoverPopup: function(info) {

            let id = info.event.id;
            let eventApi = info.event._def;
            let props = info.event.extendedProps;

            if (id !== 'availableForMeeting') {

                let left = event.pageX - $('#treatment_calendar').offset().left + 320;
                let top = event.pageY - $('#treatment_calendar').offset().top + 400;

                $(".modal_consultancy_popup").css({top: top,left: left}).show();

                let time = $(info.el).find(".fc-time").data('full');

                $(".full-time").html(time);
                $(".event-name").html(eventApi.title);

            } else {
                $(".modal_consultancy_popup").hide();
            }

        }
    };

}();


function setTreatmentDetailData(response) {

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

        detailActions(appointment, invoice, invoiceid, permissions, 'treatment-detail-actions');

        $("#modal_treatment_detail").modal("show");

        $("#treatment_comment_appointment_id").val(appointment?.id ?? 0);
        $("#treatment_patient_name").text(patient?.name ?? 'N/A');
        $("#treatment_patient_phone").text(makePhoneNumber(patient?.phone, permissions.contact, 1));
        if ( patient?.id) {
            $("#treatment_customer_id").text("C-" + patient?.id ?? 'N/A');
        }
        $("#treatment_patient_gender").text(getGender(patient?.gender));
        $("#treatment_patient_scheduled_time").text(formatDate(appointment?.scheduled_date, 'MMM, D, YY') + " at " + moment(appointment.scheduled_time, ["HH.mm"]).format("hh:mm a"));
        $("#treatment_doctor_name").text(doctor?.name ?? 'N/A');
        $("#treatment_city_name").text(city?.name ?? 'N/A');
        $("#treatment_center_name").text(location?.name ?? 'N/A');
        $("#treatment_appointment_status").text(appointment_status?.name ?? 'N/A');
        $("#treatment_service_consultancy_name").text(service?.name ?? 'N/A');
        $("#treatment_service_consultancy_name_title").text(service?.name ?? 'Detail');
        setTreatmentComments(appointment);

    } catch (e) {
        showException(e);
    }
}

function setCreateTreatment(response, start) {
    try {

        patientSearch('treatment_patient_search_id');

        $("#modal_create_treatment").modal("show");

        $("#modal_create_treatment_form")[0].reset();

        //let city_id = response.data.city_id;
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
        $("#treatment_lead_id").val(lead?.id);
        $("#treatment_patient_id").val(lead?.patient_id ? lead?.patient_id : '0');
        //$("#treatment_city_id").val(city_id);
        $("#treatment_location_id").val(location_id);
        $("#treatment_doctor_id").val(doctor_id);
        $("#treatment_start").val(start);
        $("#treatment_resource_id").val($("#treatment_resource_filter").val());
        /*$("#treatment_cnic").val();
        $("#treatment_email").val();
        $("#treatment_dob").val();
        $("#treatment_address").val();
        $("#treatment_town_id").val();*/

        let type_options = '<option value="">Select Consultancy Type</option>';
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

        $("#create_treatment_types").html(type_options);

        $("#create_treatment_base_service").html(service_options);
        $("#create_treatment_gender").html(gender_options);
        $("#create_treatment_lead").html(source_options);
        $("#create_treatment_referred_by").html(employee_options);


    } catch (e) {
        showException(e);
    }
}

function setTreatmentComments(appointment) {

    let appointment_comments = appointment.appointment_comments;
    let comment_html = '';
    if (appointment_comments.length) {
        Object.values(appointment_comments).forEach(function (comment) {
            comment_html += treatmentCommentData(comment?.user?.name, comment?.created_at, comment?.comment);
        });
    }
    $("#treatment_commentsection").html(comment_html);
}

function treatmentCommentData(user_name, created_at, comment) {

    let comment_html = '';

    comment_html = '<div class="tab-content" id="itemComment">' +
        ' <div class="tab-pane active" id="portlet_comments_1"> ' +
        '<div class="mt-comments"> ' +
        '<div class="mt-comment">' +
        ' <div class="mt-comment-img" id="imgContainer"> ' +
        '<img src="'+asset_url+'assets/media/avatar.jpg" alt="Avatar"> ' +
        '</div><div class="mt-comment-body"> ' +
        '<div class="mt-comment-info"> ' +
        '<span class="mt-comment-author" id="creat_by">';
    comment_html += user_name ?? 'N/A';
    comment_html += '</span> <span class="mt-comment-date" id="datetime">';
    comment_html += formatDate(created_at, 'ddd MMM, mm yyyy HH:mm A');
    comment_html += '</span> </div>' +
        '<div class="mt-comment-text" id="message">';
    comment_html += comment ?? 'N/A';
    comment_html += '</div><div class="mt-comment-details"> </div>' +
        '</div></div></div></div></div>';

    return comment_html;
}

