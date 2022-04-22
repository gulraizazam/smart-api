"use strict";

var calendar;
var start_date;

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

                    if ($('#consultancy_city_filter').val() !== null
                        && $('#consultancy_location_filter').val() !== null
                        && $('#consultancy_doctor_filter').val() !== null
                    ) {

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

            if (response.status) {

                if (response.rotas[0].doctor_rotas.length == 0) {
                    toastr.error("Doctor rotas not defined.")
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
        showOnlyAvailableSlots: function(minxTime, maxTime) {

            if (typeof minxTime !== "undefined") {
                calendar.setOption('minTime', minxTime);
            }

            if (typeof maxTime !== "undefined") {
                calendar.setOption('maxTime', maxTime);
            }
        },
        checkAndUpdateAppointment: function(info) {

            let event = info.event;

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
                    toastr.success("unabled to process the request, please try again.")
                }
            });
        },
        setEventId: function(eventId) {
            window.eventData.createdId = eventId;
        },
        createConsultancy: function (info) {

            let result = get_query();

            let start = formatDate(info.date, 'YYYY-MM-DDTHH:mm:ss');
            let create_url = route('admin.appointments.consulting.create', {
                appointment_type: 'consulting',
                city_id: result.city_id,
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
                    toastr.error("Unabled to process the request");
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
                toastr.error("Unabled to process the request.")
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

        detailActions(appointment, invoice, invoiceid, permissions);

        $("#modal_consultancy_detail").modal("show");

        $("#comment_appointment_id").val(appointment?.id ?? 0);
        $("#patient_name").text(patient?.name ?? 'N/A');
        $("#patient_phone").text(makePhoneNumber(patient?.phone, permissions.contact, 1));
        $("#patient_email").text(patient?.email ?? 'N/A');
        $("#patient_gender").text(getGender(patient?.gender));
        $("#patient_scheduled_time").text(formatDate(appointment?.scheduled_date, 'MMM, D, YY') + " at " + appointment.scheduled_time);
        $("#doctor_name").text(doctor?.name ?? 'N/A');
        $("#city_name").text(city?.name ?? 'N/A');
        $("#center_name").text(location?.name ?? 'N/A');
        $("#appointment_status").text(appointment_status?.name ?? 'N/A');
        $("#service_consultancy_name").text(service?.name ?? 'N/A');

        setComments(appointment);
    } catch (e) {
        showException(e);
    }
}

function detailActions(appointment, invoice, invoiceid, permissions, $class = 'detail-actions') {

    let id = appointment.id;

    let edit_url = route('admin.appointments.edit', {id: appointment.id});
    let edit_service_url = route('admin.appointments.edit_service', {id: appointment.id});
    let detail_url = route('admin.appointments.detail', {id: appointment.id});
    let sms_logs_url = route('admin.appointments.sms_logs', {id: appointment.id});
    let patient_url = route('admin.patients.preview', {id: appointment.patient_id});
    let service_invoice_url = route('admin.appointments.invoicecreate', {id: appointment.id});
    let consultancy_invoice_url = route('admin.appointments.invoice-create-consultancy', {id: appointment.id, type: 'appointment'});
    let image_url = route('admin.appointmentsimage.imageindex', {id: appointment.id});
    let measurement_url = route('admin.appointmentsmeasurement.measurements', {id: appointment.id});
    let medical_url = route('admin.appointmentsmedical.medicals', {id: appointment.id});
    let plan_create_url = route('admin.appointmentplans.create', {id: appointment.id});
    let log_url = route('admin.appointments.loadPage', {id: appointment.id, type: 'web'});

    let buttons = '<td colspan="4" style="text-align: right;">';

    if (permissions.edit) {
        if (appointment.appointment_type_id == 1) {
            buttons += '<a class="btn btn-sm btn-info mr-2" href="javascript:void(0);" onclick="editRow(`' + edit_url + '`, `' + id + '`);" >\
            <i class="la la-pencil"></i>Edit\
            </a>';
        } else {
            buttons += '<a class="btn btn-sm btn-info mr-2" href="javascript:void(0);" onclick="editRow(`' + edit_service_url + '`, `' + id + '`, `' + $class + '`);" >\
            <i class="la la-pencil"></i>Edit\
            </a>';
        }
    }

    buttons += '<a href="javascript:void(0);" onclick="viewSmsLogs(`' + sms_logs_url + '`);" class="btn btn-sm btn-info mr-2" >\
        <i class="la la-sms" data-toggle="tooltip" title="SMS Logs"></i>SMS Logs\
        </a>';
    if (permissions.invoice) {
        if (!invoice) {
            if (appointment.appointment_type_id == 2) {
                buttons += '<a class="btn btn-sm btn-info mr-2" href="javascript:void(0);" onclick="createTreatmentInvoice(`' + service_invoice_url + '`);">\
                <i class="la la-file" title="Generate Invoice"></i>Generate Invoice\
                </a>';
            }

            if (appointment.appointment_type_id == 1) {
                buttons += '<a class="btn btn-sm btn-info mr-2" href="javascript:void(0);" onclick="createConsultancyInvoice(`' + consultancy_invoice_url + '`);" >\
                <i class="la la-file" title="Generate Invoice"></i>Generate Invoice\
                </a>';
            }
        }
        if (permissions.invoice_display) {
            if (invoice) {
                let invoice_url = route('admin.appointments.InvoiceDisplay', {id: invoiceid});
                buttons += '<a class="btn btn-sm btn-info mr-2" href="javascript:void(0);" onclick="displayInvoice(`' + invoice_url + '`);" >\
                <i class="la la-file-invoice-dollar" title="Invoice Display"></i>Invoice Display\
                </a>';
            }
        }
    }

    if (appointment.appointment_type_id == 2) {
        if (permissions.image_manage) {
            buttons += '<a class="btn btn-sm btn-info mr-2" href="'+image_url+'" target="_blank">\
        <i class="la la-image" title="Images"></i>Images\
        </a>';
        }

        if (permissions.measurement_manage) {
            buttons += '<a class="btn btn-sm btn-info mr-2" href="'+measurement_url+'"  target="_blank">\
        <i class="la la-ruler-horizontal" title="Measurement"></i>Measurement\
        </a>';
        }
    }

    if (appointment.appointment_type_id == 1) {

        if(permissions.medical_form_manage) {
            buttons += '<a class="btn btn-sm btn-info mr-2" href="'+medical_url+'" target="_blank">\
            <i class="la la-medkit" title="Medical History Form"></i>Medical Form\
            </a>';
        }
    }

    if (permissions.plans_create) {
        buttons += '<a class="btn btn-sm btn-info mr-2" href="javascript:void(0);" onclick="createAppointmentPlan(`'+plan_create_url+'`);">\
            <i class="la la-paper-plane" title="Create Plan"></i>Create Plan\
            </a>';
    }

    if(permissions.patient_card) {
        buttons += '<a class="btn btn-sm btn-info mr-2" target="_blank" href="'+patient_url+'">\
        <i class="la la-user" title="Patient Card"></i>Patient Card\
        </a>';
    }

    if (permissions.log) {
        buttons += '<a class="btn btn-sm btn-info mr-2" target="_blank" href="'+log_url+'">\
        <i class="la la-history" title="Log"></i>Log\
        </a>';
    }

    buttons += '</td>';

    $("." + $class).html(buttons);

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

function commentData(user_name, created_at, comment) {

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

function setCreateConsultancy(response, start) {

    try {

        $("#create_patient_search").parent("div").find(".selection").remove();

        patientSearch('patient_search_id')

        $("#modal_create_consultancy").modal("show");
        $("#modal_create_consultancy_form")[0].reset();
        $('.patient_search_id').val(null).trigger('change');
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

        $("#create_consultancy_types").html(type_options);

        $("#create_consultancy_service").html(service_options);
        $("#create_consultancy_gender").html(gender_options);
        $("#create_consultancy_lead").html(source_options);
        $("#create_consultancy_referred_by").html(employee_options);


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

       let left = event.pageX - $('#consultancy_calendar').offset().left + 320;
       let top = event.pageY - $('#consultancy_calendar').offset().top + 500;

        $(".modal_consultancy_popup").css({top: top,left: left}).show();

        let time = $(info.el).find(".fc-time").data('full');

        $(".full-time").html(time);
        $(".event-name").html(eventApi.title);

    } else {
        $(".modal_consultancy_popup").hide();
    }

}
