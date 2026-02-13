"use strict";

var calendar;
var start_date;
var isConsultancyEventClicked = false; // Flag to track if an event was clicked
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
                defaultView: 'timeGridDay',
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
                    isConsultancyEventClicked = true; // Set flag when event is clicked
                    info.jsEvent.preventDefault(); // Prevent default action
                    info.jsEvent.stopPropagation(); // Stop event bubbling to dateClick
                    clickEvent(info, jsEvent, view);
                    // Reset flag after a short delay
                    setTimeout(function() {
                        isConsultancyEventClicked = false;
                    }, 100);
                },
                dateClick: function(info, jsEvent, view, resource) { /*Create new event on for available dates*/
                    // Don't create consultancy if an event was just clicked
                    if (isConsultancyEventClicked) {
                        return;
                    }
                    // Check if click target is an event element
                    if (info.jsEvent && info.jsEvent.target) {
                        var target = info.jsEvent.target;
                        if (target.closest('.fc-event')) {
                            return; // Don't create if clicking on an event
                        }
                    }
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

           // Function to make calendar title clickable
           var makeCalendarTitleClickable = function() {
               setTimeout(function() {
                   var titleElement = $('.fc-center h2, .fc-toolbar-title');
                   if (titleElement.length) {
                       titleElement.css({
                           'cursor': 'pointer',
                           'position': 'relative'
                       });
                       titleElement.attr('title', 'Click to select a date');

                       // Add calendar icon
                       if (!titleElement.find('.title-calendar-icon').length) {
                           titleElement.prepend('<i class="fa fa-calendar title-calendar-icon" style="margin-right: 8px;"></i>');
                       }

                       // Create hidden datepicker input if it doesn't exist
                       if (!$('#fullcalendar-datepicker').length) {
                           $('body').append('<input type="text" id="fullcalendar-datepicker" style="position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;" />');
                       }

                       titleElement.off('click').on('click', function() {
                           var datepickerInput = $('#fullcalendar-datepicker');

                           // Remove existing datepicker if any
                           if (datepickerInput.data('datepicker')) {
                               datepickerInput.datepicker('destroy');
                           }

                           // Initialize Bootstrap datepicker
                           datepickerInput.datepicker({
                               format: 'yyyy-mm-dd',
                               autoclose: true,
                               todayHighlight: true,
                               orientation: 'bottom auto'
                           }).on('changeDate', function(e) {
                               if (e.date) {
                                   calendar.gotoDate(e.date);
                               }
                           });

                           // Show the datepicker
                           datepickerInput.datepicker('show');
                       });
                   }
               }, 100);
           };

           // Make title clickable on initial render
           makeCalendarTitleClickable();

           // Re-apply clickable title when events are rendered (view changes, navigation, etc.)
           calendar.on('eventAfterAllRender', function() {
               makeCalendarTitleClickable();

               // Update today button state based on current view
               var currentViewDate = calendar.getDate();
               var isViewingToday = moment(currentViewDate).isSame(moment(), 'day');

               // Update today button styling
               var todayButton = $('.fc-today-button');
               if (isViewingToday) {
                   todayButton.addClass('fc-button-active');
               } else {
                   todayButton.removeClass('fc-button-active');
               }
           });
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
                            
                            var date = moment(appointmentObj.start, "YYYY-MM-DD");
                            //   $("#calendar").fullCalendar('gotoDate', date);
                            window.eventData.createdId = null;
                        }
                    }
                });
                if(jQuery('#consultancy_doctor_filter').val() !== ''){
                    // Get business closures and time offs for filtering
                    var closureDates = {};
                    if (response.closures && response.closures.length > 0) {
                        $.each(response.closures, function(i, closure) {
                            closureDates[closure.date] = closure.title || 'Business Closed';
                        });
                    }
                    
                    var timeOffsByDate = {};
                    if (response.time_offs && response.time_offs.length > 0) {
                        $.each(response.time_offs, function(i, timeOff) {
                            if (!timeOffsByDate[timeOff.date]) {
                                timeOffsByDate[timeOff.date] = [];
                            }
                            timeOffsByDate[timeOff.date].push(timeOff);
                        });
                    }
                    
                    // Get working days config
                    var workingDays = response.working_days || {
                        monday: true, tuesday: true, wednesday: true,
                        thursday: true, friday: true, saturday: true, sunday: false
                    };
                    var dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                    
                    $.each(response.rotas[0].doctor_rotas, function(id, rota) {
                        if (rota.active == '1') {
                            var rotaDate = rota.date;
                            var rotaDateObj = new Date(rotaDate);
                            var dayOfWeek = rotaDateObj.getDay();
                            var dayName = dayNames[dayOfWeek];
                            
                            // Skip if business is closed on this day
                            if (closureDates[rotaDate]) {
                                return; // Skip this rota day
                            }
                            
                            // Skip if not a working day
                            if (!workingDays[dayName]) {
                                return; // Skip this rota day
                            }
                            
                            // Check for time offs on this date
                            var dayTimeOffs = timeOffsByDate[rotaDate] || [];
                            
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
                                // If there are time offs, we need to split the availability
                                if (dayTimeOffs.length > 0) {
                                    var segments = splitAvailabilityAroundTimeOffs(rota, dayTimeOffs);
                                    $.each(segments, function(i, seg) {
                                        events.push({
                                            id: 'availableForMeeting',
                                            start: formatDate(rota.date + " " + seg.start, 'YYYY-MM-DDTHH:mm:ss'),
                                            end: formatDate(rota.date + " " + seg.end, 'YYYY-MM-DDTHH:mm:ss'),
                                            resourceId: $('#consultancy_doctor_filter').val(),
                                            rendering: 'background'
                                        });
                                    });
                                    
                                    // Add time off events as blocked (red background)
                                    $.each(dayTimeOffs, function(i, to) {
                                        if (to.start_time && to.end_time) {
                                            events.push({
                                                id: 'timeOff_' + to.id,
                                                title: to.type_label || 'Time Off',
                                                start: formatDate(rota.date + " " + to.start_time, 'YYYY-MM-DDTHH:mm:ss'),
                                                end: formatDate(rota.date + " " + to.end_time, 'YYYY-MM-DDTHH:mm:ss'),
                                                resourceId: $('#consultancy_doctor_filter').val(),
                                                rendering: 'background',
                                                color: '#FFE2E5'
                                            });
                                        }
                                    });
                                } else {
                                    events.push({
                                        id: 'availableForMeeting',
                                        start: formatDate(rota.date + " " + rota.start_time, 'YYYY-MM-DDTHH:mm:ss'),
                                        end: formatDate(rota.date + " " + rota.end_time, 'YYYY-MM-DDTHH:mm:ss'),
                                        resourceId: $('#consultancy_doctor_filter').val(),
                                        rendering: 'background'
                                    });
                                }
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

// Custom Resource Calendar for vertical doctor columns
var CustomResourceCalendar = function() {
    var currentDate = moment();
    var doctors = [];
    var appointments = [];
    var isLoading = false; // Flag to prevent multiple simultaneous loads
    var dragDropInitialized = false;
    var isInitializing = false; // Flag to prevent multiple simultaneous inits
    var calendarStartTime = 11 * 60; // 11 AM in minutes (hardcoded)
    var calendarEndTime = 23 * 60 + 59; // 11:59 PM in minutes (hardcoded)

    return {
        init: function(doctorsList, date) {
            // Prevent multiple simultaneous initializations
            if (isInitializing) {
                return;
            }
            
            isInitializing = true;
            doctors = doctorsList || [];
            currentDate = date ? moment(date) : moment();

            // Hide fullcalendar, show custom view
            $('#consultancy_calendar').hide();
            $('#custom_resource_calendar').show();

            // Initialize drag and drop only once
            if (!dragDropInitialized) {
                this.initDragAndDrop();
                dragDropInitialized = true;
            }

            this.render();
            this.loadAppointments();
            
            // Reset initialization flag after appointments are loaded
            setTimeout(function() {
                // Only reset if we're not still loading
                if (!isLoading) {
                    isInitializing = false;
                }
            }, 2000);
        },

        render: function() {
            var html = '';

            // Check if current date is today
            var isToday = currentDate.isSame(moment(), 'day');

            // Navigation
            html += '<div class="resource-calendar-nav">';
            html += '  <div>';
            html += '    <button class="btn btn-sm btn-light" onclick="CustomResourceCalendar.previousDay()"><i class="fa fa-chevron-left"></i> Prev</button>';
            html += '    <button class="btn btn-sm ' + (isToday ? 'btn-primary' : 'btn-light') + '" onclick="CustomResourceCalendar.today()">Today</button>';
            html += '    <button class="btn btn-sm btn-light" onclick="CustomResourceCalendar.nextDay()">Next <i class="fa fa-chevron-right"></i></button>';
            html += '  </div>';
            html += '  <div class="current-date" style="cursor: pointer;" onclick="CustomResourceCalendar.openDatePicker()" title="Click to select a date">';
            html += '    <i class="fa fa-calendar" style="margin-right: 8px;"></i>';
            html += '    <span id="current-date-text">' + currentDate.format('dddd, MMMM D, YYYY');
            if (isToday) {
                html += ' <span style="color: #1BC5BD; font-size: 12px; margin-left: 8px;">(Today)</span>';
            }
            html += '</span>';
            html += '    <input type="text" id="resource-calendar-datepicker" style="position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;" />';
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

            // Generate time slots dynamically based on API response
            var startTime = calendarStartTime; // Dynamic start time in minutes
            var endTime = calendarEndTime; // Dynamic end time in minutes
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
                    html += '<div class="resource-doctor-slot" data-doctor-id="' + doctor.id + '" data-time="' + timeStr + '"></div>';
                }

                html += '      </div>';
            });
            html += '    </div>';

            html += '  </div>';
            html += '</div>';

            $('#custom_resource_calendar').html(html);
        },

        loadAppointments: function() {
            $('#custom_resource_calendar .appointment-loader-base').show();

            // Load appointments and rotas for each doctor
            var loadPromises = [];

            doctors.forEach(function(doctor) {
                var promise = $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.appointments.load_scheduled_appointments'),
                    type: 'GET',
                    data: {
                        location_id: $('#consultancy_location_filter').val(),
                        doctor_id: doctor.id,
                        start: currentDate.format('YYYY-MM-DD') + 'T00:00:00',
                        end: currentDate.format('YYYY-MM-DD') + 'T23:59:59',
                    },
                    cache: false
                });
                loadPromises.push(promise);
            });

            // Wait for all requests to complete
            $.when.apply($, loadPromises).done(function() {
                // Arguments contain all responses
                var allEvents = [];
                var allRotas = [];

                // Handle single doctor case (arguments is the response directly)
                // Handle multiple doctors case (arguments is array of responses)
                var allTimeOffs = [];
                var allClosures = [];
                var workingDays = null;
                
                if (doctors.length === 1) {
                    var response = arguments[0];
                    if (response.status) {
                        allEvents = allEvents.concat(Object.values(response.events || {}));
                        if (response.rotas && response.rotas.length > 0) {
                            allRotas = allRotas.concat(response.rotas);
                        }
                        if (response.time_offs && response.time_offs.length > 0) {
                            allTimeOffs = allTimeOffs.concat(response.time_offs.map(function(to) {
                                to.doctor_id = doctors[0].id;
                                return to;
                            }));
                        }
                        if (response.closures) {
                            allClosures = response.closures;
                        }
                        if (response.working_days) {
                            workingDays = response.working_days;
                        }
                    }
                } else {
                    // Multiple doctors - arguments is array of [response, status, xhr]
                    for (var i = 0; i < arguments.length; i++) {
                        var response = arguments[i][0]; // First element is the actual response
                        if (response && response.status) {
                            allEvents = allEvents.concat(Object.values(response.events || {}));
                            if (response.rotas && response.rotas.length > 0) {
                                allRotas = allRotas.concat(response.rotas);
                            }
                            if (response.time_offs && response.time_offs.length > 0) {
                                allTimeOffs = allTimeOffs.concat(response.time_offs.map(function(to) {
                                    to.doctor_id = doctors[i].id;
                                    return to;
                                }));
                            }
                            if (response.closures && !allClosures.length) {
                                allClosures = response.closures;
                            }
                            if (response.working_days && !workingDays) {
                                workingDays = response.working_days;
                            }
                        }
                    }
                }

                CustomResourceCalendar.renderAppointments(allEvents);
                CustomResourceCalendar.renderRotas(allRotas, allTimeOffs, allClosures, workingDays);

                $('#custom_resource_calendar .appointment-loader-base').hide();
            }).fail(function() {
                $('#custom_resource_calendar .appointment-loader-base').hide();
                toastr.error('Error loading appointments and rotas');
            });
        },

        renderAppointments: function(events) {
            // Each slot is 60px high and represents 15 minutes
            var slotHeight = 60; // pixels
            var slotInterval = 15; // minutes
            var pixelsPerMinute = slotHeight / slotInterval; // 4 pixels per minute

            events.forEach(function(event) {
                var startTime = moment(event.start);
                var endTime = moment(event.end);
                var duration = endTime.diff(startTime, 'minutes');

                var timeStr = startTime.format('H:mm');
                var doctorId = event.resourceId;

                // Find the slot where appointment starts
                var slot = $('.resource-doctor-slot[data-doctor-id="' + doctorId + '"][data-time="' + timeStr + '"]');

                if (slot.length) {
                    // Calculate height based on duration
                    // Each minute = 4px (since 60px slot = 15 minutes)
                    var appointmentHeight = duration * pixelsPerMinute;

                    // Use appointment color if available, otherwise default to blue
                    var bgColor = event.color || '#3699ff';
                    var borderColor = event.color ? CustomResourceCalendar.darkenColor(event.color, 20) : '#187de4';

                    // Format timings
                    var startTimeFormatted = startTime.format('h:mm A');
                    var endTimeFormatted = endTime.format('h:mm A');

                    // Create gradient overlay for depth
                    var gradientOverlay = 'linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(0,0,0,0.05) 100%)';
                    
                    var appointmentHtml = '<div class="resource-appointment modern-card" draggable="true" data-id="' + event.id + '" data-doctor-id="' + doctorId + '" data-start-time="' + timeStr + '" data-duration="' + duration + '" data-original-slot="' + doctorId + '-' + timeStr + '" style="height: ' + appointmentHeight + 'px; background: ' + bgColor + '; background-image: ' + gradientOverlay + '; border-left: 4px solid ' + borderColor + '; box-shadow: 0 2px 8px rgba(0,0,0,0.12); transition: all 0.3s ease;">';
                    
                    // Time badge with icon
                    appointmentHtml += '<div style="display: flex; align-items: center; gap: 4px; margin-bottom: 6px; padding: 4px 8px; background: rgba(255,255,255,0.2); border-radius: 4px; width: fit-content;">';
                    appointmentHtml += '<i class="fa fa-clock" style="font-size: 11px; color: #fff; opacity: 0.9;"></i>';
                    appointmentHtml += '<span style="font-size: 12px; font-weight: 600; letter-spacing: 0.3px;">' + startTimeFormatted + ' - ' + endTimeFormatted + '</span>';
                    appointmentHtml += '</div>';
                    
                    // Patient name with icon
                    appointmentHtml += '<div style="display: flex; align-items: center; gap: 5px; margin-bottom: 4px;">';
                    appointmentHtml += '<i class="fa fa-user-circle" style="font-size: 12px; color: #fff; opacity: 0.85;"></i>';
                    appointmentHtml += '<span style="font-size: 12px; font-weight: 700; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">' + event.patient + '</span>';
                    appointmentHtml += '</div>';
                    
                    // Created by with icon (if exists)
                    if (event.created_by) {
                        appointmentHtml += '<div style="display: flex; align-items: center; gap: 4px; margin-top: 6px; padding-top: 6px; border-top: 1px solid rgba(255,255,255,0.2);">';
                        appointmentHtml += '<i class="fa fa-user-plus" style="font-size: 10px; color: #fff; opacity: 0.75;"></i>';
                        appointmentHtml += '<span style="font-size: 11px; opacity: 0.9; font-weight: 500;">' + event.created_by + '</span>';
                        appointmentHtml += '</div>';
                    }
                    
                    appointmentHtml += '</div>';

                    slot.append(appointmentHtml);
                } else {
                    console.log('Slot not found for appointment:', event.patient, 'at', timeStr, 'for doctor', doctorId);
                }
            });
        },

        // Helper function to darken a color for borders
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

        renderRotas: function(rotasData, timeOffs, closures, workingDays) {
            if (!rotasData || rotasData.length === 0) {
                return;
            }

            // Build time offs lookup by doctor_id
            var timeOffsByDoctor = {};
            if (timeOffs && timeOffs.length > 0) {
                timeOffs.forEach(function(to) {
                    if (!timeOffsByDoctor[to.doctor_id]) {
                        timeOffsByDoctor[to.doctor_id] = [];
                    }
                    timeOffsByDoctor[to.doctor_id].push(to);
                });
            }

            // Build closures lookup by date
            var closureDates = {};
            if (closures && closures.length > 0) {
                closures.forEach(function(c) {
                    closureDates[c.date] = c.title || 'Business Closed';
                });
            }

            // Working days config
            var workingDaysConfig = workingDays || {
                monday: true, tuesday: true, wednesday: true,
                thursday: true, friday: true, saturday: true, sunday: false
            };
            var dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

            rotasData.forEach(function(rotaGroup) {
                if (!rotaGroup.doctor_rotas || rotaGroup.doctor_rotas.length === 0) {
                    return;
                }

                // The doctor_id is the external_id field in the rotaGroup
                var doctorId = rotaGroup.external_id;
                var doctorTimeOffs = timeOffsByDoctor[doctorId] || [];

                rotaGroup.doctor_rotas.forEach(function(rota) {
                    if (rota.active !== '1' && rota.active !== 1) {
                        return;
                    }

                    // Check if rota date matches current date
                    var rotaDate = moment(rota.date, 'YYYY-MM-DD');
                    if (!rotaDate.isSame(currentDate, 'day')) {
                        return;
                    }

                    // Skip if business is closed
                    if (closureDates[rota.date]) {
                        return;
                    }

                    // Skip if not a working day
                    var dayOfWeek = rotaDate.day();
                    var dayName = dayNames[dayOfWeek];
                    if (!workingDaysConfig[dayName]) {
                        return;
                    }

                    // Get time offs for this date
                    var dayTimeOffs = doctorTimeOffs.filter(function(to) {
                        return to.date === rota.date;
                    });

                    // Case 1: Has break time (start_off and end_off)
                    if (rota.start_time && rota.start_off && rota.end_off && rota.end_time) {
                        // Morning session: start_time to start_off
                        CustomResourceCalendar.markRotaSlotsWithTimeOffs(doctorId, rota.start_time, rota.start_off, dayTimeOffs);
                        // Afternoon session: end_off to end_time
                        CustomResourceCalendar.markRotaSlotsWithTimeOffs(doctorId, rota.end_off, rota.end_time, dayTimeOffs);
                    }
                    // Case 2: No break time - continuous availability
                    else if (rota.start_time && rota.end_time) {
                        CustomResourceCalendar.markRotaSlotsWithTimeOffs(doctorId, rota.start_time, rota.end_time, dayTimeOffs);
                    }

                    // Render time off blocks
                    dayTimeOffs.forEach(function(to) {
                        if (to.start_time && to.end_time) {
                            CustomResourceCalendar.markTimeOffSlots(doctorId, to.start_time, to.end_time, to.type_label || 'Time Off');
                        }
                    });
                });
            });
        },

        markRotaSlotsWithTimeOffs: function(doctorId, startTime, endTime, timeOffs) {
            // If no time offs, just mark normally
            if (!timeOffs || timeOffs.length === 0) {
                CustomResourceCalendar.markRotaSlots(doctorId, startTime, endTime);
                return;
            }

            // Split availability around time offs
            var segments = splitAvailabilityAroundTimeOffs({start_time: startTime, end_time: endTime}, timeOffs);
            segments.forEach(function(seg) {
                CustomResourceCalendar.markRotaSlots(doctorId, seg.start, seg.end);
            });
        },

        markTimeOffSlots: function(doctorId, startTime, endTime, label) {
            var startMoment = moment(startTime, ['HH:mm:ss', 'HH:mm', 'h:mm A', 'hh:mm A']);
            var endMoment = moment(endTime, ['HH:mm:ss', 'HH:mm', 'h:mm A', 'hh:mm A']);

            if (!startMoment.isValid() || !endMoment.isValid()) {
                return;
            }

            // Find the first slot to place the time off block
            var firstTimeStr = startMoment.format('H:mm');
            var firstSlot = $('.resource-doctor-slot[data-doctor-id="' + doctorId + '"][data-time="' + firstTimeStr + '"]');
            
            if (!firstSlot.length) {
                return;
            }

            // Calculate duration in minutes and height
            var durationMinutes = endMoment.diff(startMoment, 'minutes');
            var slotHeight = 60; // Each slot is 60px for 15 minutes
            var pixelsPerMinute = slotHeight / 15;
            var blockHeight = durationMinutes * pixelsPerMinute;

            // Create a single time off block
            var timeOffBlock = $('<div class="time-off-block"></div>');
            timeOffBlock.css({
                'position': 'absolute',
                'top': '0',
                'left': '2px',
                'right': '2px',
                'height': blockHeight + 'px',
                'background': '#FFE2E5',
                'border-left': '4px solid #F64E60',
                'border-radius': '6px',
                'display': 'flex',
                'align-items': 'center',
                'justify-content': 'center',
                'z-index': '3',
                'cursor': 'not-allowed'
            });
            timeOffBlock.html('<span style="color: #F64E60; font-weight: 500; font-size: 11px;">' + label + '</span>');
            timeOffBlock.attr('title', label + ' (' + startMoment.format('h:mm A') + ' - ' + endMoment.format('h:mm A') + ')');

            // Append to first slot
            firstSlot.css('position', 'relative');
            firstSlot.append(timeOffBlock);

            // Mark all slots as time-off (for styling/blocking clicks) but without the label
            var currentSlot = startMoment.clone();
            while (currentSlot.isBefore(endMoment)) {
                var timeStr = currentSlot.format('H:mm');
                var slot = $('.resource-doctor-slot[data-doctor-id="' + doctorId + '"][data-time="' + timeStr + '"]');
                if (slot.length) {
                    slot.addClass('time-off-slot-no-label');
                }
                currentSlot.add(15, 'minutes');
            }
        },

        markRotaSlots: function(doctorId, startTime, endTime) {
            // Try parsing in multiple formats (12-hour with AM/PM or 24-hour)
            var startMoment = moment(startTime, ['h:mm A', 'HH:mm:ss', 'HH:mm']);
            var endMoment = moment(endTime, ['h:mm A', 'HH:mm:ss', 'HH:mm']);

          

            var slots = $('.resource-doctor-slot[data-doctor-id="' + doctorId + '"]');
          

            var markedCount = 0;
            slots.each(function() {
                var slotTime = $(this).data('time');
                var slotMoment = moment(slotTime, 'H:mm');

                // Check if slot time falls within rota time range
                if (slotMoment.isSameOrAfter(startMoment) && slotMoment.isBefore(endMoment)) {
                    $(this).addClass('has-rota');
                    markedCount++;
                }
            });

         
        },

        createAppointment: function(doctorId, time, element) {
            // Check if the slot has a rota (has-rota class)
            if (!$(element).hasClass('has-rota')) {
                toastr.error("Doctor rota does not exist for this time slot");
                return;
            }

            var dateTime = currentDate.format('YYYY-MM-DD') + ' ' + time + ':00';
            var start = formatDate(dateTime, 'YYYY-MM-DDTHH:mm:ss');

            var create_url = route('admin.appointments.consulting.create', {
                appointment_type: 'consulting',
                doctor_id: doctorId,
                location_id: $('#consultancy_location_filter').val(),
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
                    toastr.error("Unable to create appointment");
                }
            });
        },

        viewAppointment: function(appointmentId) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.appointments.detail', [appointmentId]),
                type: 'Get',
                cache: false,
                success: function (response) {
                    if (response.status) {
                        setDetailData(response);
                    } else {
                        toastr.error(response.message)
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    toastr.error("Unable to load appointment details.")
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
            // Initialize and trigger datepicker
            var datepickerInput = $('#resource-calendar-datepicker');

            // Remove existing datepicker if any
            if (datepickerInput.data('datepicker')) {
                datepickerInput.datepicker('destroy');
            }

            // Initialize Bootstrap datepicker
            datepickerInput.datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true,
                orientation: 'bottom auto'
            }).on('changeDate', function(e) {
                if (e.date) {
                    currentDate = moment(e.date);
                    CustomResourceCalendar.render();
                    CustomResourceCalendar.loadAppointments();
                }
            });

            // Show the datepicker
            datepickerInput.datepicker('show');
        },

        goToDate: function(date) {
            currentDate = moment(date);
            this.render();
            this.loadAppointments();
        },

        highlightNewAppointment: function(appointmentId) {
            // Wait for appointments to be rendered
            setTimeout(function() {
                var appointmentEl = $('.resource-appointment[data-id="' + appointmentId + '"]');
                if (appointmentEl.length) {
                    // Scroll to the appointment
                    appointmentEl[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    // Add a highlight animation
                    appointmentEl.css({
                        'animation': 'pulse-highlight 1.5s ease-in-out 2',
                        'box-shadow': '0 0 10px 2px rgba(54, 153, 255, 0.5)'
                    });

                    // Remove highlight after animation
                    setTimeout(function() {
                        appointmentEl.css({
                            'animation': '',
                            'box-shadow': ''
                        });
                    }, 3000);
                }
            }, 500);
        },

        initDragAndDrop: function() {
            var draggedElement = null;
            var originalSlot = null;
            var isDragging = false;
            var dragStartX = 0;
            var dragStartY = 0;
            var hasMoved = false;

            // Handle slot click (for creating appointments)
            $(document).on('click', '.resource-doctor-slot', function(e) {
                // Don't create if clicking on an appointment inside the slot
                if ($(e.target).hasClass('resource-appointment') || $(e.target).closest('.resource-appointment').length) {
                    return;
                }
                
                var doctorId = $(this).data('doctor-id');
                var timeStr = $(this).data('time');
                CustomResourceCalendar.createAppointment(doctorId, timeStr, this);
            });

            // Handle appointment click
            $(document).on('click', '.resource-appointment', function(e) {
                e.stopPropagation(); // Prevent click from bubbling to slot click
                e.preventDefault(); // Prevent default action
                if (!isDragging && !hasMoved) {
                    var appointmentId = $(this).data('id');
                    CustomResourceCalendar.viewAppointment(appointmentId);
                }
            });

            // Handle drag start
            $(document).on('dragstart', '.resource-appointment', function(e) {
                isDragging = true;
                hasMoved = false;
                draggedElement = $(this);
                originalSlot = draggedElement.parent();
                
                // Store initial position
                dragStartX = e.originalEvent.clientX;
                dragStartY = e.originalEvent.clientY;

                // Store data for drag
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                e.originalEvent.dataTransfer.setData('text/html', this.innerHTML);
                
                // Hide the original element after a tiny delay to allow drag image to be created
                setTimeout(function() {
                    if (draggedElement) {
                        draggedElement.css('visibility', 'hidden');
                    }
                }, 0);
            });
            
            // Track drag movement
            $(document).on('drag', '.resource-appointment', function(e) {
                if (isDragging && e.originalEvent.clientX !== 0 && e.originalEvent.clientY !== 0) {
                    var deltaX = Math.abs(e.originalEvent.clientX - dragStartX);
                    var deltaY = Math.abs(e.originalEvent.clientY - dragStartY);
                    // Consider it a real drag if moved more than 5 pixels
                    if (deltaX > 5 || deltaY > 5) {
                        hasMoved = true;
                    }
                }
            });

            // Handle drag end
            $(document).on('dragend', '.resource-appointment', function(e) {
                $('.resource-doctor-slot').removeClass('drag-over');
                
                // Show the element again
                if (draggedElement) {
                    draggedElement.css('visibility', 'visible');
                }

                // Reset isDragging flag after a short delay to prevent click event
                setTimeout(function() {
                    isDragging = false;
                    hasMoved = false;
                    if (draggedElement) {
                        draggedElement.removeClass('dragging');
                    }
                }, 150);
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
                var originalDoctorId = draggedElement.data('doctor-id');
                var newDoctorId = dropSlot.data('doctor-id');

                // Get original and new time
                var originalTime = draggedElement.attr('data-start-time');
                var newTime = dropSlot.attr('data-time');
                var duration = parseInt(draggedElement.attr('data-duration'));

                // Check if dropping in the exact same slot (same doctor AND same time)
                if (originalTime === newTime && originalDoctorId === newDoctorId) {
                    toastr.warning('Appointment is already in this time slot');
                    draggedElement.removeClass('dragging');
                    return false;
                }

                // Calculate new start and end times
                var newStartDateTime = currentDate.format('YYYY-MM-DD') + ' ' + newTime + ':00';
                var newStart = moment(newStartDateTime);
                var newEnd = newStart.clone().add(duration, 'minutes');

                // Call backend to reschedule
                CustomResourceCalendar.rescheduleAppointment(
                    appointmentId,
                    newStart.format('YYYY-MM-DDTHH:mm:ss'),
                    newEnd.format('YYYY-MM-DDTHH:mm:ss'),
                    newDoctorId,
                    draggedElement,
                    dropSlot,
                    originalSlot
                );

                return false;
            });
        },

        rescheduleAppointment: function(appointmentId, newStart, newEnd, newDoctorId, appointmentEl, dropSlot, originalSlot) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.appointments.check_and_save_appointment'),
                type: 'POST',
                data: {
                    id: appointmentId,
                    start: newStart,
                    end: newEnd,
                    doctor_id: newDoctorId,
                    location_id: $('#consultancy_location_filter').val()
                },
                cache: false,
                success: function(response) {
                    if (response.status) {
                        toastr.success(response.message || 'Appointment rescheduled successfully');

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
                        toastr.error(response.message || 'Failed to reschedule appointment');
                        // Move appointment back to original slot
                        appointmentEl.removeClass('dragging');
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    // Try to get error message from response
                    var errorMessage = 'Unable to reschedule appointment. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    toastr.error(errorMessage);
                    // Move appointment back to original slot
                    appointmentEl.removeClass('dragging');
                }
            });
        }
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

        //leadSearch('lead_search_id');

        $("#modal_create_consultancy").modal("show");
        $("#modal_create_consultancy_form")[0].reset();
        $('.patient_search_id').val(null).trigger('change');
        $('.lead_search_id').val(null).trigger('change');
        $('#create_consultancy_referred_by').val(null).trigger('change');
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
        
        // Set scheduled time value for timepicker
        if (start) {
            let scheduledDateTime = moment(start);
            let formattedTime = scheduledDateTime.format('h:mm A');
            $("#create_scheduled_time").val(formattedTime);
            
            // Update modal heading with day and date
            let dayName = scheduledDateTime.format('dddd');
            let dateFormatted = scheduledDateTime.format('MMM D');
            $("#create_consultation_heading").text('New consultation - ' + dayName + ', ' + dateFormatted);
        }

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
        // Referred by is now a patient search Select2 field, not populated with employees
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
            $("#create_consultancy_gender").removeClass("is-valid");
            // Set focus on phone number field
            $('.lead_search_id').focus();
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

/**
 * Split availability around time offs
 * Returns array of available time segments after removing time off periods
 */
function splitAvailabilityAroundTimeOffs(rota, timeOffs) {
    var segments = [{
        start: rota.start_time,
        end: rota.end_time
    }];
    
    // Convert time to minutes for easier comparison
    function timeToMinutes(timeStr) {
        if (!timeStr) return 0;
        var parts = timeStr.split(':');
        return parseInt(parts[0]) * 60 + parseInt(parts[1] || 0);
    }
    
    function minutesToTime(minutes) {
        var hours = Math.floor(minutes / 60);
        var mins = minutes % 60;
        return (hours < 10 ? '0' : '') + hours + ':' + (mins < 10 ? '0' : '') + mins + ':00';
    }
    
    // Process each time off
    $.each(timeOffs, function(i, to) {
        if (!to.start_time || !to.end_time) return;
        
        var toStart = timeToMinutes(to.start_time);
        var toEnd = timeToMinutes(to.end_time);
        
        var newSegments = [];
        $.each(segments, function(j, seg) {
            var segStart = timeToMinutes(seg.start);
            var segEnd = timeToMinutes(seg.end);
            
            // Check if time off overlaps with this segment
            if (toEnd <= segStart || toStart >= segEnd) {
                // No overlap
                newSegments.push(seg);
            } else {
                // There is overlap - split the segment
                if (toStart > segStart) {
                    // Part before time off
                    newSegments.push({
                        start: seg.start,
                        end: minutesToTime(toStart)
                    });
                }
                if (toEnd < segEnd) {
                    // Part after time off
                    newSegments.push({
                        start: minutesToTime(toEnd),
                        end: seg.end
                    });
                }
            }
        });
        segments = newSegments;
    });
    
    return segments;
}
