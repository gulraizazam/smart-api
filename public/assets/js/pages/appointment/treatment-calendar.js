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
                defaultView: 'timeGridDay',
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
                            machine_id: appointmentObj.machine_id || appointmentObj.resource_id,
                            patient: appointmentObj.patient,
                            service: appointmentObj.service,
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
                url: route('admin.appointments.drag_drop_reschedule_treatment'),
                type: 'POST',
                data: {
                    id: event.id,
                    start: formatDate(event.start, 'YYYY-MM-DDTHH:mm:ss'),
                    end: formatDate(event.end, 'YYYY-MM-DDTHH:mm:ss'),
                    doctor_id: $("#treatment_doctor_filter").val(),
                    location_id: $("#treatment_location_filter").val(),
                    resourceId: $("#treatment_resource_filter").val() || null,
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

        // Hide warning div and reset checkboxes
        $('#treatment_doctor_warning').addClass('d-none');
        $('#use_previous_doctor').prop('checked', false);
        $('#use_selected_doctor').prop('checked', false);

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

// Treatment Resource Calendar - Shows doctors as vertical columns
var TreatmentResourceCalendar = function() {
    var currentDate = moment();
    var doctors = [];
    var appointments = [];
    var dragDropInitialized = false;
    var currentMachineId = '';

    return {
        init: function(doctorsList, date) {
            doctors = doctorsList || [];
            currentDate = date ? moment(date) : moment();

            // Store the current machine_id
            currentMachineId = $('#treatment_resource_filter').val() || '';

            // Hide fullcalendar, show custom view
            $('#treatment_calendar').hide();
            $('#custom_treatment_resource_calendar').show();

            // Initialize drag and drop only once
            if (!dragDropInitialized) {
                this.initDragAndDrop();
                dragDropInitialized = true;
            }

            this.render();
            this.loadAppointments();
        },

        render: function() {
            var html = '';

            // Check if current date is today
            var isToday = currentDate.isSame(moment(), 'day');

            // Navigation
            html += '<div class="resource-calendar-nav">';
            html += '  <div>';
            html += '    <button class="btn btn-sm btn-light" onclick="TreatmentResourceCalendar.previousDay()"><i class="fa fa-chevron-left"></i> Previous</button>';
            html += '    <button class="btn btn-sm ' + (isToday ? 'btn-primary' : 'btn-light') + '" onclick="TreatmentResourceCalendar.today()">Today</button>';
            html += '    <button class="btn btn-sm btn-light" onclick="TreatmentResourceCalendar.nextDay()">Next <i class="fa fa-chevron-right"></i></button>';
            html += '  </div>';
            html += '  <div class="current-date" style="cursor: pointer;" onclick="TreatmentResourceCalendar.openDatePicker()" title="Click to select a date">';
            html += '    <i class="fa fa-calendar" style="margin-right: 8px;"></i>';
            html += '    <span id="current-date-text">' + currentDate.format('dddd, MMMM D, YYYY');
            if (isToday) {
                html += ' <span style="color: #1BC5BD; font-size: 12px; margin-left: 8px;">(Today)</span>';
            }
            html += '</span>';
            html += '    <input type="text" id="treatment-resource-calendar-datepicker" style="position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;" />';
            html += '  </div>';
            html += '  <div></div>';
            html += '</div>';

            // Calendar container
            html += '<div class="resource-calendar-container">';

            // Header with doctor names
            html += '  <div class="resource-calendar-header">';
            html += '    <div class="resource-time-column">Time</div>';
            html += '    <div class="resource-calendar-header-doctors">';
            doctors.forEach(function(doctor) {
                html += '      <div class="resource-doctor-header" data-doctor-id="' + doctor.id + '">' + doctor.name + '</div>';
            });
            html += '    </div>';
            html += '  </div>';

            // Body with time slots
            html += '  <div class="resource-calendar-body">';
            html += '    <div class="resource-time-slots">';

            // Generate time slots from 11 AM to 8:45 PM (15 min intervals)
            var startTime = 11 * 60; // 11 AM in minutes
            var endTime = 20 * 60 + 45; // 8:45 PM in minutes
            var interval = 15; // 15 minutes

            for (var time = startTime; time <= endTime; time += interval) {
                var hours = Math.floor(time / 60);
                var mins = time % 60;
                var timeStr = (hours % 12 || 12) + ':' + (mins < 10 ? '0' : '') + mins + ' ' + (hours < 12 ? 'AM' : 'PM');
                html += '<div class="resource-time-slot" data-time="' + hours + ':' + (mins < 10 ? '0' : '') + mins + '">' + timeStr + '</div>';
            }

            html += '    </div>';

            // Calculate total height needed (number of slots * 60px per slot)
            var totalSlots = Math.floor((endTime - startTime) / interval) + 1;
            var totalHeight = totalSlots * 60; // 60px per slot

            // Doctor columns
            html += '    <div class="resource-doctors-container" style="min-height: ' + totalHeight + 'px;">';
            doctors.forEach(function(doctor) {
                html += '      <div class="resource-doctor-column" data-doctor-id="' + doctor.id + '" style="min-height: ' + totalHeight + 'px;">';

                for (var time = startTime; time <= endTime; time += interval) {
                    var hours = Math.floor(time / 60);
                    var mins = time % 60;
                    var timeStr = hours + ':' + (mins < 10 ? '0' : '') + mins;
                    html += '<div class="resource-doctor-slot" data-doctor-id="' + doctor.id + '" data-time="' + timeStr + '" onclick="TreatmentResourceCalendar.createAppointment(' + doctor.id + ', \'' + timeStr + '\', this)"></div>';
                }

                html += '      </div>';
            });
            html += '    </div>';

            html += '  </div>';
            html += '</div>';

            $('#custom_treatment_resource_calendar').html(html);
        },

        loadAppointments: function() {
            $('.appointment-loader-base').show();

            // Get and store the machine ID from the filter
            var loadingMachineId = $('#treatment_resource_filter').val();
            console.log('Loading appointments with machine_id:', loadingMachineId);

            // Update the stored machine ID if we have a value
            if (loadingMachineId) {
                currentMachineId = loadingMachineId;
                console.log('Updated currentMachineId to:', currentMachineId);
            }

            // Load appointments for each doctor
            var loadPromises = [];

            doctors.forEach(function(doctor) {
                var promise = $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.appointments.load_scheduled_service_appointments'),
                    type: 'GET',
                    data: {
                        location_id: $('#treatment_location_filter').val(),
                        doctor_id: doctor.id,
                        machine_id: loadingMachineId,
                        start: currentDate.format('YYYY-MM-DD') + 'T00:00:00',
                        end: currentDate.format('YYYY-MM-DD') + 'T23:59:59',
                    },
                    cache: false
                });
                loadPromises.push(promise);
            });

            // Wait for all requests to complete
            $.when.apply($, loadPromises).done(function() {
                var allEvents = [];
                var allRotas = [];

                console.log('=== AJAX RESPONSES RECEIVED ===');
                console.log('Number of doctors:', doctors.length);
                console.log('Arguments:', arguments);

                // Handle single doctor case
                if (doctors.length === 1) {
                    var response = arguments[0];
                    console.log('Single doctor response:', response);
                    if (response.status) {
                        // Map events and add the machine_id (since backend doesn't return it)
                        var events = Object.values(response.events || {});
                        events.forEach(function(event) {
                            // Backend doesn't return machine_id, so use the stored one
                            // All events in this response belong to the same machine since we filtered by it
                            event.machine_id = currentMachineId;
                            console.log('Event ' + event.id + ' assigned machine_id:', event.machine_id);
                            allEvents.push(event);
                        });
                        console.log('Response rotas:', response.rotas);
                        if (response.rotas && response.rotas.length > 0) {
                            allRotas = allRotas.concat(response.rotas);
                        }
                    }
                } else {
                    // Multiple doctors
                    for (var i = 0; i < arguments.length; i++) {
                        var response = arguments[i][0];
                        console.log('Doctor ' + i + ' response:', response);
                        if (response && response.status) {
                            // Map events and add the machine_id (since backend doesn't return it)
                            var events = Object.values(response.events || {});
                            events.forEach(function(event) {
                                // Backend doesn't return machine_id, so use the stored one
                                // All events in this response belong to the same machine since we filtered by it
                                event.machine_id = currentMachineId;
                                console.log('Event ' + event.id + ' assigned machine_id:', event.machine_id);
                                allEvents.push(event);
                            });
                            console.log('Doctor ' + i + ' rotas:', response.rotas);
                            if (response.rotas && response.rotas.length > 0) {
                                allRotas = allRotas.concat(response.rotas);
                            }
                        }
                    }
                }

                console.log('Total events collected:', allEvents.length);
                console.log('Total rotas collected:', allRotas.length);
                console.log('All rotas:', allRotas);
                console.log('Sample event data:', allEvents.length > 0 ? allEvents[0] : 'No events');

                TreatmentResourceCalendar.renderRotas(allRotas);
                TreatmentResourceCalendar.renderAppointments(allEvents);
                $('.appointment-loader-base').hide();
            }).fail(function() {
                console.error('AJAX request failed');
                $('.appointment-loader-base').hide();
                toastr.error('Error loading appointments');
            });
        },

        renderAppointments: function(events) {
            var slotHeight = 60;
            var slotInterval = 15;
            var pixelsPerMinute = slotHeight / slotInterval;

            console.log('=== RENDERING APPOINTMENTS ===');
            console.log('Number of events:', events.length);
            console.log('Current machine ID:', currentMachineId);
            console.log('Filter machine ID:', $('#treatment_resource_filter').val());

            events.forEach(function(event) {
                var startTime = moment(event.start);
                var endTime = moment(event.end);
                var duration = endTime.diff(startTime, 'minutes');

                var timeStr = startTime.format('H:mm');
                var doctorId = event.resourceId;
                // Use event's machine_id first, then stored machine ID, then filter
                var machineId = event.machine_id || currentMachineId || $('#treatment_resource_filter').val() || '';

                console.log('Event ' + event.id + ' machine_id:', event.machine_id, 'Final machineId:', machineId);

                var slot = $('.resource-doctor-slot[data-doctor-id="' + doctorId + '"][data-time="' + timeStr + '"]');

                if (slot.length) {
                    var appointmentHeight = duration * pixelsPerMinute;
                    var bgColor = event.color || '#3699ff';
                    var borderColor = event.color ? TreatmentResourceCalendar.darkenColor(event.color, 20) : '#187de4';

                    var startTimeFormatted = startTime.format('h:mm A');
                    var endTimeFormatted = endTime.format('h:mm A');

                    var appointmentHtml = '<div class="resource-appointment" draggable="true" data-id="' + event.id + '" data-doctor-id="' + doctorId + '" data-machine-id="' + machineId + '" data-start-time="' + timeStr + '" data-duration="' + duration + '" data-original-slot="' + doctorId + '-' + timeStr + '" style="height: ' + appointmentHeight + 'px; background: ' + bgColor + '; border-color: ' + borderColor + ';">';
                    appointmentHtml += '<div style="font-size: 10px; font-weight: 600; margin-bottom: 2px;">' + startTimeFormatted + ' - ' + endTimeFormatted + '</div>';
                    appointmentHtml += '<div style="font-size: 11px; font-weight: bold; margin-bottom: 2px;">Patient: ' + event.patient + '</div>';
                    appointmentHtml += '<div style="font-size: 10px;">Service: ' + event.service + '</div>';
                    appointmentHtml += '</div>';

                    slot.append(appointmentHtml);
                }
            });
        },

        darkenColor: function(color, percent) {
            var num = parseInt(color.replace("#",""), 16),
                amt = Math.round(2.55 * percent),
                R = (num >> 16) - amt,
                G = (num >> 8 & 0x00FF) - amt,
                B = (num & 0x0000FF) - amt;
            return "#" + (0x1000000 + (R<255?R<1?0:R:255)*0x10000 +
                (G<255?G<1?0:G:255)*0x100 +
                (B<255?B<1?0:B:255))
                .toString(16).slice(1);
        },

        renderRotas: function(rotasData) {
            if (!rotasData || rotasData.length === 0) {
                return;
            }

            rotasData.forEach(function(rotaGroup) {
                if (!rotaGroup.doctor_rotas || rotaGroup.doctor_rotas.length === 0) {
                    return;
                }

                var doctorId = rotaGroup.external_id;

                rotaGroup.doctor_rotas.forEach(function(rota) {
                    if (rota.active !== '1' && rota.active !== 1) {
                        return;
                    }

                    var rotaDate = moment(rota.date, 'YYYY-MM-DD');
                    if (!rotaDate.isSame(currentDate, 'day')) {
                        return;
                    }

                    // Has break time
                    if (rota.start_time && rota.start_off && rota.end_off && rota.end_time) {
                        TreatmentResourceCalendar.markRotaSlots(doctorId, rota.start_time, rota.start_off);
                        TreatmentResourceCalendar.markRotaSlots(doctorId, rota.end_off, rota.end_time);
                    }
                    // No break time
                    else if (rota.start_time && rota.end_time) {
                        TreatmentResourceCalendar.markRotaSlots(doctorId, rota.start_time, rota.end_time);
                    }
                });
            });
        },

        markRotaSlots: function(doctorId, startTime, endTime) {
            var startMoment = moment(startTime, ['h:mm A', 'HH:mm:ss', 'HH:mm']);
            var endMoment = moment(endTime, ['h:mm A', 'HH:mm:ss', 'HH:mm']);

            console.log('Marking slots for doctor:', doctorId);
            console.log('Start time:', startTime, '→', startMoment.format('HH:mm'));
            console.log('End time:', endTime, '→', endMoment.format('HH:mm'));

            var slots = $('.resource-doctor-slot[data-doctor-id="' + doctorId + '"]');
            console.log('Found ' + slots.length + ' slots for doctor ' + doctorId);

            var markedCount = 0;
            slots.each(function() {
                var slotTime = $(this).data('time');
                var slotMoment = moment(slotTime, 'H:mm');

                if (slotMoment.isSameOrAfter(startMoment) && slotMoment.isBefore(endMoment)) {
                    $(this).addClass('has-rota');
                    markedCount++;
                }
            });

            console.log('Marked ' + markedCount + ' slots as has-rota for doctor ' + doctorId + ' (from ' + startMoment.format('HH:mm') + ' to ' + endMoment.format('HH:mm') + ')');
        },

        createAppointment: function(doctorId, timeStr, element) {
            console.log('Clicked element:', element);
            console.log('Element classes:', $(element).attr('class'));
            console.log('Has rota class?', $(element).hasClass('has-rota'));
            
            // Only check rota if clicking on a slot without rota (grey box)
            if (!$(element).hasClass('has-rota')) {
                toastr.error("Doctor rota does not exist for this time slot");
                return;
            }

            console.log('Creating appointment for doctor:', doctorId, 'at time:', timeStr);
            
            // Store doctor ID in window.eventData for the modal
            if (!window.eventData) {
                window.eventData = {};
            }
            window.eventData.doctor_id = doctorId;
            window.eventData.location_id = $('#treatment_location_filter').val();
            
            // Create the date/time object
            var dateTime = currentDate.format('YYYY-MM-DD') + 'T' + timeStr + ':00';
            console.log('DateTime:', dateTime);
            console.log('Doctor ID:', doctorId);
            console.log('Location ID:', window.eventData.location_id);
            
            var info = { 
                date: moment(dateTime).toDate(),
                resource: { id: doctorId }
            };
            
            // Open the create treatment modal directly
            removeExtraSelect2();
            $("#create_treatment_service").html('');
            $("#create_treatment_patient_search").html('');
            $("#modal_create_treatment_form")[0].reset();

            let start = formatDate(info.date, 'YYYY-MM-DDTHH:mm:ss');
            let create_url = route('admin.appointments.treatment.create', {
                location_id: window.eventData.location_id,
                doctor_id: doctorId,
                machine_id: '',
                resource_id: '',
                start: start,
                appointment_type: 'treatment',
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
                        setCreateTreatment(response, start);
                    } else {
                        toastr.error(response.message)
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    toastr.error("Unable to process the request");
                }
            });
        },

        clickAppointment: function(appointmentId) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.appointments.detail', [appointmentId]),
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
        },

        previousDay: function() {
            currentDate = currentDate.subtract(1, 'days');
            this.render();
            this.loadAppointments();
        },

        nextDay: function() {
            currentDate = currentDate.add(1, 'days');
            this.render();
            this.loadAppointments();
        },

        today: function() {
            currentDate = moment();
            this.render();
            this.loadAppointments();
        },

        openDatePicker: function() {
            var datepickerInput = $('#treatment-resource-calendar-datepicker');
            
            if (datepickerInput.data('datepicker')) {
                datepickerInput.datepicker('destroy');
            }
            
            datepickerInput.datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true,
                orientation: 'bottom auto'
            }).on('changeDate', function(e) {
                if (e.date) {
                    currentDate = moment(e.date);
                    TreatmentResourceCalendar.render();
                    TreatmentResourceCalendar.loadAppointments();
                }
            });
            
            datepickerInput.datepicker('show');
        },

        initDragAndDrop: function() {
            var draggedElement = null;
            var originalSlot = null;
            var isDragging = false;

            // Handle appointment click
            $(document).on('click', '.resource-appointment', function(e) {
                if (!isDragging) {
                    var appointmentId = $(this).data('id');
                    TreatmentResourceCalendar.clickAppointment(appointmentId);
                }
            });

            // Handle drag start
            $(document).on('dragstart', '.resource-appointment', function(e) {
                isDragging = true;
                draggedElement = $(this);
                originalSlot = draggedElement.parent();
                draggedElement.addClass('dragging');

                // Store data for drag
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/html', this.innerHTML);
            });

            // Handle drag end
            $(document).on('dragend', '.resource-appointment', function(e) {
                draggedElement.removeClass('dragging');
                $('.resource-doctor-slot').removeClass('drag-over');

                // Reset isDragging flag after a short delay to prevent click event
                setTimeout(function() {
                    isDragging = false;
                }, 100);
            });

            // Handle drag over
            $(document).on('dragover', '.resource-doctor-slot', function(e) {
                if (e.preventDefault) {
                    e.preventDefault();
                }
                e.originalEvent.dataTransfer.dropEffect = 'move';
                $(this).addClass('drag-over');
                return false;
            });

            // Handle drag leave
            $(document).on('dragleave', '.resource-doctor-slot', function(e) {
                $(this).removeClass('drag-over');
            });

            // Handle drop
            $(document).on('drop', '.resource-doctor-slot', function(e) {
                if (e.stopPropagation) {
                    e.stopPropagation();
                }
                e.preventDefault();

                var dropSlot = $(this);
                dropSlot.removeClass('drag-over');

                if (!draggedElement) {
                    return false;
                }

                // Check if the slot has a rota
                if (!dropSlot.hasClass('has-rota')) {
                    toastr.error('Doctor rota does not exist for this time slot');
                    // Move appointment back to original slot
                    draggedElement.removeClass('dragging');
                    return false;
                }

                // Get appointment and slot details
                var appointmentId = draggedElement.data('id');
                var newDoctorId = dropSlot.data('doctor-id');
                var newTime = dropSlot.data('time');
                var duration = draggedElement.data('duration');
                var machineId = draggedElement.data('machine-id');

                // Calculate new start and end times
                var newStartDateTime = currentDate.format('YYYY-MM-DD') + ' ' + newTime + ':00';
                var newStart = moment(newStartDateTime);
                var newEnd = newStart.clone().add(duration, 'minutes');

                // Call backend to reschedule
                TreatmentResourceCalendar.rescheduleAppointment(
                    appointmentId,
                    newStart.format('YYYY-MM-DDTHH:mm:ss'),
                    newEnd.format('YYYY-MM-DDTHH:mm:ss'),
                    newDoctorId,
                    machineId,
                    draggedElement,
                    dropSlot,
                    originalSlot
                );

                return false;
            });
        },

        rescheduleAppointment: function(appointmentId, newStart, newEnd, newDoctorId, machineId, appointmentEl, dropSlot, originalSlot) {
            // Use provided machineId, fallback to stored currentMachineId, then filter, then empty string
            var finalMachineId = machineId || currentMachineId || $('#treatment_resource_filter').val() || '';

            console.log('=== RESCHEDULE APPOINTMENT ===');
            console.log('Appointment ID:', appointmentId);
            console.log('Machine ID from drag:', machineId);
            console.log('Machine ID from element data:', appointmentEl.data('machine-id'));
            console.log('Current machine ID:', currentMachineId);
            console.log('Filter value:', $('#treatment_resource_filter').val());
            console.log('Final machine ID:', finalMachineId);
            console.log('Request data:', {
                id: appointmentId,
                start: newStart,
                end: newEnd,
                doctor_id: newDoctorId,
                location_id: $('#treatment_location_filter').val(),
                resourceId: finalMachineId
            });

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.appointments.drag_drop_reschedule_treatment'),
                type: 'POST',
                data: {
                    id: appointmentId,
                    start: newStart,
                    end: newEnd,
                    doctor_id: newDoctorId,
                    location_id: $('#treatment_location_filter').val(),
                    resourceId: finalMachineId || null
                },
                cache: false,
                success: function(response) {
                    if (response.status) {
                        toastr.success(response.message || 'Treatment rescheduled successfully');

                        // Calculate new times for display
                        var newStartMoment = moment(newStart);
                        var newEndMoment = moment(newEnd);
                        var newStartFormatted = newStartMoment.format('h:mm A');
                        var newEndFormatted = newEndMoment.format('h:mm A');

                        // Update the time display in the appointment card
                        appointmentEl.find('div:first').html(newStartFormatted + ' - ' + newEndFormatted);

                        // Move the appointment to the new slot
                        appointmentEl.detach();
                        dropSlot.append(appointmentEl);

                        // Update appointment data attributes
                        appointmentEl.attr('data-doctor-id', newDoctorId);
                        appointmentEl.attr('data-start-time', dropSlot.data('time'));
                        appointmentEl.attr('data-original-slot', newDoctorId + '-' + dropSlot.data('time'));

                        appointmentEl.removeClass('dragging');
                    } else {
                        toastr.error(response.message || 'Failed to reschedule treatment');
                        // Move appointment back to original slot
                        appointmentEl.removeClass('dragging');
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    toastr.error('Unable to reschedule treatment. Please try again.');
                    // Move appointment back to original slot
                    appointmentEl.removeClass('dragging');
                }
            });
        },

        // Reload calendar appointments (called after creating a new appointment)
        reload: function() {
            console.log('TreatmentResourceCalendar.reload() called');

            // Update the stored machine_id in case it changed
            currentMachineId = $('#treatment_resource_filter').val() || '';

            // Clear existing appointments from the calendar
            $('.resource-appointment').remove();

            // Reload appointments and rotas
            this.loadAppointments();
        }
    };
}();

