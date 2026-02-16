"use strict";

var currentWeekStart = null;
var locations = [];
var avatarColors = ['avatar-color-1', 'avatar-color-2', 'avatar-color-3', 'avatar-color-4', 'avatar-color-5', 'avatar-color-6'];
var businessWorkingDays = {
    monday: true,
    tuesday: true,
    wednesday: true,
    thursday: true,
    friday: true,
    saturday: true,
    sunday: false
};
var workingDayExceptions = [];
var currentCalendarResources = []; // Store resources displayed on calendar

$(document).ready(function () {
    initWeekDates();
    loadBusinessWorkingDays();
    initEventHandlers();
});

function loadBusinessWorkingDays() {
    $.ajax({
        url: route('admin.schedule.get-business-working-days'),
        type: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            if (response.status && response.data.working_days) {
                businessWorkingDays = response.data.working_days;
            }
            if (response.data.exceptions) {
                workingDayExceptions = response.data.exceptions;
            }
            // Load locations after working days are loaded
            loadLocations();
        },
        error: function () {
            // Use defaults and continue
            loadLocations();
        }
    });
}

function initWeekDates() {
    // Check for date parameter in URL
    var urlParams = new URLSearchParams(window.location.search);
    var dateParam = urlParams.get('date');
    
    var targetDate;
    if (dateParam) {
        // Parse the date from URL (format: YYYY-MM-DD)
        var parts = dateParam.split('-');
        if (parts.length === 3) {
            targetDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        } else {
            targetDate = new Date();
        }
    } else {
        targetDate = new Date();
    }
    
    // Set current week start to Monday of the week containing targetDate
    var dayOfWeek = targetDate.getDay();
    var diff = targetDate.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1); // Adjust for Sunday
    currentWeekStart = new Date(targetDate);
    currentWeekStart.setDate(diff);
    currentWeekStart.setHours(0, 0, 0, 0);
    
    updateWeekDisplay();
    updateDayHeaders();
}

function updateWeekDisplay() {
    var weekEnd = new Date(currentWeekStart);
    weekEnd.setDate(weekEnd.getDate() + 6);
    
    var startStr = formatDate(currentWeekStart, 'd MMM');
    var endStr = formatDate(weekEnd, 'd MMM, yyyy');
    
    $('#week_range_display').text(startStr + ' - ' + endStr);
}

function updateDayHeaders() {
    var dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    
    for (var i = 0; i < 7; i++) {
        var date = new Date(currentWeekStart);
        date.setDate(date.getDate() + i);
        
        var dayName = dayNames[i];
        var dayDate = date.getDate() + ' ' + getMonthName(date.getMonth());
        
        var $header = $('.day-header[data-day="' + i + '"]');
        $header.html(
            '<div class="day-name">' + dayName + ', ' + dayDate + '</div>' +
            '<div class="day-hours"></div>'
        );
    }
}

function loadLocations() {
    $.ajax({
        url: route('admin.schedule.get-locations'),
        type: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            if (response.status && response.data.locations) {
                locations = response.data.locations;
                populateLocationDropdown(locations);
                
                // Load schedule after locations are loaded
                if (locations.length > 0) {
                    loadSchedule();
                }
            }
        },
        error: function () {
            toastr.error('Failed to load locations.');
        }
    });
}

function populateLocationDropdown(locations) {
    var $select = $('#filter_location_id');
    $select.empty();
    
    // Check for location_id parameter in URL
    var urlParams = new URLSearchParams(window.location.search);
    var urlLocationId = urlParams.get('location_id');
    
    // Skip "All Centres" option - find first actual branch
    var firstBranchId = null;
    for (var i = 0; i < locations.length; i++) {
        if (locations[i].name && locations[i].name.toLowerCase().indexOf('all') === -1) {
            firstBranchId = locations[i].id;
            break;
        }
    }
    
    // Determine which location to select
    var selectedLocationId = urlLocationId ? parseInt(urlLocationId) : firstBranchId;
    
    for (var i = 0; i < locations.length; i++) {
        // Skip "All Centres" or similar options
        if (locations[i].name && locations[i].name.toLowerCase().indexOf('all') !== -1) {
            continue;
        }
        var selected = locations[i].id === selectedLocationId ? 'selected' : '';
        $select.append('<option value="' + locations[i].id + '" ' + selected + '>' + locations[i].name + '</option>');
    }
    
    $select.select2({
        placeholder: 'Select Location',
        allowClear: false
    });
}

function initEventHandlers() {
    // Location change
    $('#filter_location_id').on('change', function () {
        loadSchedule();
    });
    
    // Resource type change
    $('#filter_resource_type').on('change', function () {
        loadSchedule();
    });
    
    // Previous week
    $('#btn_prev_week').on('click', function () {
        currentWeekStart.setDate(currentWeekStart.getDate() - 7);
        updateWeekDisplay();
        updateDayHeaders();
        loadSchedule();
    });
    
    // Next week
    $('#btn_next_week').on('click', function () {
        currentWeekStart.setDate(currentWeekStart.getDate() + 7);
        updateWeekDisplay();
        updateDayHeaders();
        loadSchedule();
    });
    
    // Today button
    $('#btn_today').on('click', function () {
        initWeekDates();
        loadSchedule();
    });
    
    // Global Add dropdown - Time off
    $('#btn_add_time_off_global').on('click', function () {
        openTimeOffModalGlobal();
    });
    
    // Global Add dropdown - Business closed period
    $('#btn_add_business_closed').on('click', function () {
        window.location.href = route('admin.business-closures.index');
    });
    
    // Shift add button click - show dropdown
    $(document).on('click', '.shift-add-btn', function (e) {
        e.stopPropagation();
        var $container = $(this).closest('.shift-container');
        var $dropdown = $container.find('.shift-add-dropdown');
        
        // Close all other dropdowns
        $('.shift-dropdown').not($dropdown).removeClass('show');
        
        // Toggle this dropdown
        $dropdown.toggleClass('show');
    });
    
    // Existing shift badge click - show edit dropdown
    $(document).on('click', '.shift-badge.clickable:not(.business-closed)', function (e) {
        e.stopPropagation();
        // Find dropdown within the same wrapper or as sibling
        var $dropdown = $(this).siblings('.shift-edit-dropdown');
        if ($dropdown.length === 0) {
            $dropdown = $(this).parent().find('.shift-edit-dropdown');
        }
        
        // Close all other dropdowns
        $('.shift-dropdown').not($dropdown).removeClass('show');
        
        // Toggle this dropdown
        $dropdown.toggleClass('show');
    });
    
    // Dropdown item click
    $(document).on('click', '.shift-dropdown-item', function (e) {
        e.stopPropagation();
        var action = $(this).data('action');
        var shiftId = $(this).data('shift-id');
        var timeOffId = $(this).data('time-off-id');
        var $cell = $(this).closest('.shift-cell');
        var resourceId = $cell.data('resource-id');
        var date = $cell.data('date');
        
        // Close dropdown
        $(this).closest('.shift-dropdown').removeClass('show');
        
        // Handle action - pass timeOffId for time-off actions
        handleShiftAction(action, resourceId, date, timeOffId || shiftId);
    });
    
    // Close dropdown when clicking outside
    $(document).on('click', function () {
        $('.shift-dropdown').removeClass('show');
    });
    
    // Business closed badge click - open edit modal
    $(document).on('click', '.shift-badge.business-closed', function (e) {
        e.stopPropagation();
        var closureId = $(this).data('closure-id');
        openEditClosureModal(closureId);
    });
    
    // Spanning time off bar click - show dropdown menu
    $(document).on('click', '.spanning-time-off-bar', function (e) {
        e.stopPropagation();
        // Close all other dropdowns first
        $('.spanning-time-off-dropdown').removeClass('show');
        $('.shift-dropdown').removeClass('show');
        // Toggle this dropdown
        $(this).siblings('.spanning-time-off-dropdown').toggleClass('show');
    });
    
    // Spanning time off dropdown - Edit time off
    $(document).on('click', '.spanning-time-off-dropdown [data-action="edit-time-off"]', function (e) {
        e.stopPropagation();
        var timeOffId = $(this).data('time-off-id');
        var resourceId = $(this).data('resource-id');
        $('.spanning-time-off-dropdown').removeClass('show');
        openEditTimeOffModal(timeOffId, resourceId);
    });
    
    // Spanning time off dropdown - Delete time off
    $(document).on('click', '.spanning-time-off-dropdown [data-action="delete-time-off"]', function (e) {
        e.stopPropagation();
        var timeOffId = $(this).data('time-off-id');
        $('.spanning-time-off-dropdown').removeClass('show');
        if (confirm('Are you sure you want to delete this time off?')) {
            deleteTimeOff(timeOffId);
        }
    });
    
    // Close spanning time off dropdown when clicking outside
    $(document).on('click', function () {
        $('.spanning-time-off-dropdown').removeClass('show');
    });
}

function handleShiftAction(action, resourceId, date, shiftId) {
    switch (action) {
        case 'add-shift':
            openAddShiftModal(resourceId, date);
            break;
        case 'repeating-shifts':
            openRepeatingShiftsPage(resourceId, date);
            break;
        case 'time-off':
            openTimeOffModal(resourceId, date);
            break;
        case 'edit-day':
            openEditDayModal(resourceId, date, shiftId);
            break;
        case 'delete-shift':
            deleteShift(resourceId, date, shiftId);
            break;
        case 'edit-time-off':
            openEditTimeOffModal(shiftId, resourceId); // shiftId contains time_off_id from data attribute
            break;
        case 'delete-time-off':
            deleteTimeOff(shiftId); // shiftId contains time_off_id from data attribute
            break;
    }
}

function openEditDayModal(resourceId, date, shiftId) {
    // Open the add shift modal and pre-populate with existing shifts
    currentShiftResourceId = resourceId;
    currentShiftDate = date;
    
    // Find resource name
    var resourceName = 'Resource';
    $('#schedule_body tr').each(function() {
        var $cell = $(this).find('.shift-cell[data-resource-id="' + resourceId + '"]').first();
        if ($cell.length) {
            resourceName = $(this).find('.team-member-name').text();
            return false;
        }
    });
    currentShiftResourceName = resourceName;
    
    // Format date for title
    var dateObj = new Date(date);
    var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var formattedDate = dayNames[dateObj.getDay()] + ' ' + dateObj.getDate() + ' ' + monthNames[dateObj.getMonth()];
    
    // Set modal title
    $('#add_shift_title').text(resourceName + "'s shift " + formattedDate);
    
    // Set hidden fields
    $('#shift_resource_id').val(resourceId);
    $('#shift_date').val(date);
    $('#shift_location_id').val($('#filter_location_id').val());
    
    // Get existing shifts from the calendar data
    var existingShifts = findAllShifts(resourceId, date, currentScheduleShifts);
    
    // Clear existing rows and populate with existing shifts
    shiftRowCounter = 0;
    $('#shift_rows_container').html('');
    
    if (existingShifts.length > 0) {
        for (var i = 0; i < existingShifts.length; i++) {
            addShiftRowWithData(existingShifts[i].start_time, existingShifts[i].end_time);
        }
        $('#btn_delete_all_shifts').show();
    } else {
        addShiftRow();
        $('#btn_delete_all_shifts').hide();
    }
    
    updateTotalDuration();
    
    // Show modal
    $('#modal_add_shift').modal('show');
}

function deleteShift(resourceId, date, shiftId) {
    if (!shiftId) {
        toastr.error('Shift ID not found');
        return;
    }
    
    if (!confirm('Are you sure you want to delete this shift?')) {
        return;
    }
    
    $.ajax({
        url: route('admin.schedule.delete-single-shift'),
        type: 'POST',
        data: {
            shift_id: shiftId
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.status) {
                toastr.success('Shift deleted successfully');
                loadSchedule();
            } else {
                toastr.error(response.message || 'Failed to delete shift');
            }
        },
        error: function(xhr) {
            if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('Failed to delete shift');
            }
        }
    });
}

function deleteTimeOff(timeOffId) {
    if (!timeOffId) {
        toastr.error('Time off ID not found');
        return;
    }
    
    if (!confirm('Are you sure you want to delete this time off?')) {
        return;
    }
    
    $.ajax({
        url: route('admin.schedule.delete-time-off'),
        type: 'POST',
        data: {
            time_off_id: timeOffId
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.status) {
                toastr.success('Time off deleted successfully');
                loadSchedule();
            } else {
                toastr.error(response.message || 'Failed to delete time off');
            }
        },
        error: function(xhr) {
            if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('Failed to delete time off');
            }
        }
    });
}

var currentShiftResourceId = null;
var currentShiftDate = null;
var currentShiftResourceName = '';
var shiftRowCounter = 0;
var currentScheduleShifts = []; // Store shifts data globally for edit functionality
var currentScheduleTimeOffs = []; // Store time offs data globally

function openRepeatingShiftsPage(resourceId, date) {
    // Find resource name
    var resourceName = 'Resource';
    $('#schedule_body tr').each(function() {
        var $cell = $(this).find('.shift-cell[data-resource-id="' + resourceId + '"]').first();
        if ($cell.length) {
            resourceName = $(this).find('.team-member-name').text();
            return false;
        }
    });
    
    // Get location info
    var locationId = $('#filter_location_id').val();
    var locationName = $('#filter_location_id option:selected').text();
    
    // Build URL with query parameters
    var url = route('admin.resourcerotas.repeating-shifts');
    url += '?resource_id=' + resourceId;
    url += '&resource_name=' + encodeURIComponent(resourceName);
    url += '&location_id=' + locationId;
    url += '&location_name=' + encodeURIComponent(locationName);
    url += '&date=' + date;
    
    // Navigate to the page
    window.location.href = url;
}

function openAddShiftModal(resourceId, date) {
    currentShiftResourceId = resourceId;
    currentShiftDate = date;
    
    // Find resource name
    var resourceName = 'Resource';
    $('#schedule_body tr').each(function() {
        var $cell = $(this).find('.shift-cell[data-resource-id="' + resourceId + '"]').first();
        if ($cell.length) {
            resourceName = $(this).find('.team-member-name').text();
            return false;
        }
    });
    currentShiftResourceName = resourceName;
    
    // Format date for title
    var dateObj = new Date(date);
    var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var formattedDate = dayNames[dateObj.getDay()] + ' ' + dateObj.getDate() + ' ' + monthNames[dateObj.getMonth()];
    
    // Set modal title
    $('#add_shift_title').text(resourceName + "'s shift " + formattedDate);
    
    // Set hidden fields
    $('#shift_resource_id').val(resourceId);
    $('#shift_date').val(date);
    $('#shift_location_id').val($('#filter_location_id').val());
    
    // Check if shift exists for this resource on this date
    var $cell = $('.shift-cell[data-resource-id="' + resourceId + '"][data-date="' + date + '"]');
    var hasExistingShift = $cell.find('.shift-badge:not(.not-working):not(.business-closed)').length > 0;
    
    // Show/hide delete button based on whether shift exists
    if (hasExistingShift) {
        $('#btn_delete_all_shifts').show();
    } else {
        $('#btn_delete_all_shifts').hide();
    }
    
    // Reset form
    resetShiftForm();
    
    // Show modal
    $('#modal_add_shift').modal('show');
}

function resetShiftForm() {
    shiftRowCounter = 0;
    $('#shift_rows_container').html('');
    addShiftRow();
    updateTotalDuration();
}

function addShiftRow() {
    // Get the last shift's end time to use as start time for new shift
    var lastEndTime = '10:00 AM';
    var $lastRow = $('.shift-row').last();
    if ($lastRow.length > 0) {
        lastEndTime = $lastRow.find('.shift-end-time').val() || '10:00 AM';
    }
    
    // Calculate end time as 1 hour after start time
    var endTime = addOneHour(lastEndTime);
    
    addShiftRowWithData(lastEndTime, endTime);
}

function addOneHour(timeStr) {
    // Parse time string like "07:00 PM"
    var match = timeStr.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
    if (!match) return '08:00 PM';
    
    var hours = parseInt(match[1]);
    var mins = parseInt(match[2]);
    var ampm = match[3].toUpperCase();
    
    // Convert to 24-hour
    if (ampm === 'PM' && hours !== 12) {
        hours += 12;
    } else if (ampm === 'AM' && hours === 12) {
        hours = 0;
    }
    
    // Add 1 hour
    hours += 1;
    if (hours >= 24) hours = 23; // Cap at 11:30 PM
    
    // Convert back to 12-hour
    var newAmpm = hours >= 12 ? 'PM' : 'AM';
    var newHours = hours % 12;
    newHours = newHours ? newHours : 12;
    
    return (newHours < 10 ? '0' : '') + newHours + ':' + (mins === 0 ? '00' : mins) + ' ' + newAmpm;
}

function addShiftRowWithData(startTime, endTime) {
    // Convert 24-hour format to 12-hour if needed
    startTime = formatTimeTo12Hour(startTime);
    endTime = formatTimeTo12Hour(endTime);
    
    var rowHtml = '<div class="shift-row mb-3" data-row="' + shiftRowCounter + '">';
    rowHtml += '<div class="row align-items-end">';
    rowHtml += '<div class="col-md-5">';
    rowHtml += '<label class="mb-2">Start time</label>';
    rowHtml += '<select class="form-control shift-start-time" name="shifts[' + shiftRowCounter + '][start_time]">';
    rowHtml += getTimeOptions(startTime);
    rowHtml += '</select>';
    rowHtml += '</div>';
    rowHtml += '<div class="col-md-5">';
    rowHtml += '<label class="mb-2">End time</label>';
    rowHtml += '<select class="form-control shift-end-time" name="shifts[' + shiftRowCounter + '][end_time]">';
    rowHtml += getTimeOptions(endTime);
    rowHtml += '</select>';
    rowHtml += '</div>';
    rowHtml += '<div class="col-md-2 text-center">';
    var showDelete = shiftRowCounter > 0 ? '' : 'display: none;';
    rowHtml += '<button type="button" class="btn btn-icon btn-light-danger btn-sm remove-shift-row" style="' + showDelete + '">';
    rowHtml += '<i class="la la-trash"></i>';
    rowHtml += '</button>';
    rowHtml += '</div>';
    rowHtml += '</div>';
    rowHtml += '</div>';
    
    $('#shift_rows_container').append(rowHtml);
    shiftRowCounter++;
    
    // Show delete buttons if more than one row
    if ($('.shift-row').length > 1) {
        $('.remove-shift-row').show();
    }
    
    updateTotalDuration();
}

function formatTimeTo12Hour(time) {
    if (!time) return '10:00 AM';
    
    // If already in 12-hour format, return as is
    if (time.includes('AM') || time.includes('PM')) {
        return time;
    }
    
    // Convert from 24-hour format (HH:mm)
    var parts = time.split(':');
    var hour = parseInt(parts[0], 10);
    var min = parts[1] ? parts[1].substring(0, 2) : '00';
    var ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12;
    hour = hour ? hour : 12;
    return (hour < 10 ? '0' : '') + hour + ':' + min + ' ' + ampm;
}

function getTimeOptions(selectedValue) {
    var options = '';
    for (var hour = 0; hour < 24; hour++) {
        for (var min = 0; min < 60; min += 30) {
            var h = hour % 12;
            h = h ? h : 12;
            var ampm = hour >= 12 ? 'PM' : 'AM';
            var timeStr = (h < 10 ? '0' : '') + h + ':' + (min === 0 ? '00' : min) + ' ' + ampm;
            var selected = timeStr === selectedValue ? 'selected' : '';
            options += '<option value="' + timeStr + '" ' + selected + '>' + timeStr + '</option>';
        }
    }
    return options;
}

function updateTotalDuration() {
    var totalMinutes = 0;
    
    $('.shift-row').each(function() {
        var startTime = $(this).find('.shift-start-time').val();
        var endTime = $(this).find('.shift-end-time').val();
        
        if (startTime && endTime) {
            var start = parseTimeString(startTime);
            var end = parseTimeString(endTime);
            
            if (start && end) {
                var diff = (end.getTime() - start.getTime()) / (1000 * 60);
                if (diff > 0) {
                    totalMinutes += diff;
                }
            }
        }
    });
    
    var hours = Math.floor(totalMinutes / 60);
    var mins = totalMinutes % 60;
    var durationStr = hours + 'h';
    if (mins > 0) {
        durationStr += ' ' + mins + 'm';
    }
    
    $('#total_shift_duration').text('Total shift duration: ' + durationStr);
}

function parseTimeString(timeStr) {
    if (!timeStr) return null;
    
    var match = timeStr.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
    if (match) {
        var hours = parseInt(match[1]);
        var mins = parseInt(match[2]);
        var ampm = match[3].toUpperCase();
        
        if (ampm === 'PM' && hours !== 12) {
            hours += 12;
        } else if (ampm === 'AM' && hours === 12) {
            hours = 0;
        }
        
        var date = new Date();
        date.setHours(hours, mins, 0, 0);
        return date;
    }
    return null;
}

// Initialize modal event handlers
$(document).ready(function() {
    // Add shift row button
    $(document).on('click', '#btn_add_shift_row', function() {
        addShiftRow();
    });
    
    // Remove shift row
    $(document).on('click', '.remove-shift-row', function() {
        $(this).closest('.shift-row').remove();
        
        // Hide delete button if only one row left
        if ($('.shift-row').length === 1) {
            $('.remove-shift-row').hide();
        }
        
        updateTotalDuration();
    });
    
    // Update duration on time change
    $(document).on('change', '.shift-start-time, .shift-end-time', function() {
        updateTotalDuration();
    });
    
    // Save shift button
    $(document).on('click', '#btn_save_shift', function() {
        saveShift();
    });
});

function saveShift() {
    var shifts = [];
    var isValid = true;
    
    $('.shift-row').each(function() {
        var startTime = $(this).find('.shift-start-time').val();
        var endTime = $(this).find('.shift-end-time').val();
        
        if (!startTime || !endTime) {
            isValid = false;
            return false;
        }
        
        shifts.push({
            start_time: startTime,
            end_time: endTime
        });
    });
    
    if (!isValid || shifts.length === 0) {
        toastr.error('Please fill in all shift times');
        return;
    }
    
    // Disable save button to prevent double submission
    $('#btn_save_shift').prop('disabled', true).text('Saving...');
    
    $.ajax({
        url: route('admin.schedule.store-shifts'),
        type: 'POST',
        data: {
            resource_id: currentShiftResourceId,
            date: currentShiftDate,
            location_id: $('#shift_location_id').val(),
            shifts: shifts
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            $('#btn_save_shift').prop('disabled', false).text('Save');
            if (response.status) {
                toastr.success('Shift saved successfully');
                $('#modal_add_shift').modal('hide');
                loadSchedule();
            } else {
                toastr.error(response.message || 'Failed to save shift');
            }
        },
        error: function(xhr) {
            $('#btn_save_shift').prop('disabled', false).text('Save');
            if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('Failed to save shift');
            }
        }
    });
}

function deleteAllShifts() {
    if (!currentShiftResourceId || !currentShiftDate) {
        toastr.error('Missing shift information');
        return;
    }
    
    // Show confirmation
    if (!confirm('Are you sure you want to delete all shifts for this day?')) {
        return;
    }
    
    $.ajax({
        url: route('admin.schedule.delete-shifts'),
        type: 'POST',
        data: {
            resource_id: currentShiftResourceId,
            date: currentShiftDate,
            location_id: $('#shift_location_id').val()
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.status) {
                toastr.success('Shifts deleted successfully');
                $('#modal_add_shift').modal('hide');
                loadSchedule();
            } else {
                toastr.error(response.message || 'Failed to delete shifts');
            }
        },
        error: function(xhr) {
            if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('Failed to delete shifts');
            }
        }
    });
}

// Time Off Modal Functions
var currentTimeOffResources = [];

function openTimeOffModalGlobal() {
    // Reset editing state
    editingTimeOffId = null;
    $('#modal_add_time_off .modal-title').text('Add Time Off');
    $('#btn_save_time_off').text('Save');
    
    // Open time off modal without pre-selecting a resource
    var today = new Date();
    var todayStr = formatDateForApi(today);
    
    // Set location
    $('#time_off_location_id').val($('#filter_location_id').val());
    
    // Populate team member dropdown with current calendar resources
    var $resourceSelect = $('#time_off_resource_id');
    $resourceSelect.empty();
    $resourceSelect.append('<option value="">Select team member</option>');
    
    for (var i = 0; i < currentCalendarResources.length; i++) {
        var res = currentCalendarResources[i];
        $resourceSelect.append('<option value="' + res.id + '">' + res.name + '</option>');
    }
    
    // Format and set start date to today
    var formattedDate = formatDateForDisplay(today);
    $('#time_off_start_date').val(formattedDate);
    
    // Initialize datepickers
    if ($.fn.datepicker) {
        $('#time_off_start_date').datepicker({
            format: 'D, dd M yyyy',
            autoclose: true,
            todayHighlight: true
        }).datepicker('setDate', today);
        
        $('#time_off_repeat_until').datepicker({
            format: 'D, dd M yyyy',
            autoclose: true,
            todayHighlight: true
        }).datepicker('setDate', today);
    }
    
    // Populate time dropdowns
    $('#time_off_start_time').html(getTimeOptions('10:00 AM'));
    $('#time_off_end_time').html(getTimeOptions('07:00 PM'));
    
    // Reset form fields
    $('#time_off_type').val('annual_leave');
    $('#time_off_repeat').prop('checked', false);
    $('#repeat_until_row').hide();
    $('#time_off_description').val('');
    $('#description_counter').text('0/100');
    
    // Show modal
    $('#modal_add_time_off').modal('show');
}

function openTimeOffModal(resourceId, date) {
    // Reset editing state
    editingTimeOffId = null;
    $('#modal_add_time_off .modal-title').text('Add Time Off');
    $('#btn_save_time_off').text('Save');
    
    // Set location
    $('#time_off_location_id').val($('#filter_location_id').val());
    
    // Populate team member dropdown with current resources
    var $resourceSelect = $('#time_off_resource_id');
    $resourceSelect.empty();
    
    $('#schedule_body tr').each(function() {
        var $nameCell = $(this).find('.team-member-name');
        var $shiftCell = $(this).find('.shift-cell').first();
        if ($nameCell.length && $shiftCell.length) {
            var resId = $shiftCell.data('resource-id');
            var resName = $nameCell.text();
            var selected = resId == resourceId ? 'selected' : '';
            $resourceSelect.append('<option value="' + resId + '" ' + selected + '>' + resName + '</option>');
        }
    });
    
    // Format and set start date
    var dateObj = new Date(date);
    var formattedDate = formatDateForDisplay(dateObj);
    $('#time_off_start_date').val(formattedDate);
    
    // Initialize datepickers
    if ($.fn.datepicker) {
        $('#time_off_start_date').datepicker({
            format: 'D, dd M yyyy',
            autoclose: true,
            todayHighlight: true
        }).datepicker('setDate', dateObj);
        
        $('#time_off_repeat_until').datepicker({
            format: 'D, dd M yyyy',
            autoclose: true,
            todayHighlight: true
        }).datepicker('setDate', dateObj);
    }
    
    // Populate time dropdowns
    $('#time_off_start_time').html(getTimeOptions('10:00 AM'));
    $('#time_off_end_time').html(getTimeOptions('07:00 PM'));
    
    // Reset form fields
    $('#time_off_type').val('annual_leave');
    $('#time_off_repeat').prop('checked', false);
    $('#repeat_until_row').hide();
    $('#time_off_description').val('');
    $('#description_counter').text('0/100');
    
    // Show modal
    $('#modal_add_time_off').modal('show');
}

// Edit time off modal - stores the time off ID being edited
var editingTimeOffId = null;

function openEditTimeOffModal(timeOffId, resourceId) {
    editingTimeOffId = timeOffId;
    
    // Fetch time off details
    $.ajax({
        url: route('admin.schedule.get-time-off'),
        type: 'POST',
        data: {
            time_off_id: timeOffId
        },
        success: function(response) {
            if (response.status && response.data && response.data.time_off) {
                var timeOff = response.data.time_off;
                
                // Set location
                $('#time_off_location_id').val(timeOff.location_id);
                
                // Populate team member dropdown with current resources
                var $resourceSelect = $('#time_off_resource_id');
                $resourceSelect.empty();
                
                $('#schedule_body tr').each(function() {
                    var $nameCell = $(this).find('.team-member-name');
                    var $shiftCell = $(this).find('.shift-cell').first();
                    if ($nameCell.length && $shiftCell.length) {
                        var resId = $shiftCell.data('resource-id');
                        var resName = $nameCell.text();
                        var selected = resId == timeOff.resource_id ? 'selected' : '';
                        $resourceSelect.append('<option value="' + resId + '" ' + selected + '>' + resName + '</option>');
                    }
                });
                
                // Set start date
                var startDate = new Date(timeOff.start_date);
                var formattedDate = formatDateForDisplay(startDate);
                $('#time_off_start_date').val(formattedDate);
                
                // Initialize datepickers
                if ($.fn.datepicker) {
                    $('#time_off_start_date').datepicker({
                        format: 'D, dd M yyyy',
                        autoclose: true,
                        todayHighlight: true
                    }).datepicker('setDate', startDate);
                    
                    // Always initialize repeat_until datepicker with a default date
                    var repeatUntilDate = timeOff.repeat_until ? new Date(timeOff.repeat_until) : startDate;
                    $('#time_off_repeat_until').datepicker({
                        format: 'D, dd M yyyy',
                        autoclose: true,
                        todayHighlight: true
                    }).datepicker('setDate', repeatUntilDate);
                }
                
                // Set time dropdowns
                var startTimeFormatted = formatTimeFor12Hour(timeOff.start_time);
                var endTimeFormatted = formatTimeFor12Hour(timeOff.end_time);
                $('#time_off_start_time').html(getTimeOptions(startTimeFormatted));
                $('#time_off_end_time').html(getTimeOptions(endTimeFormatted));
                
                // Ensure the value is set (in case exact time wasn't in dropdown)
                $('#time_off_start_time').val(startTimeFormatted);
                $('#time_off_end_time').val(endTimeFormatted);
                
                // If value not found in dropdown, add it as an option
                if ($('#time_off_start_time').val() !== startTimeFormatted) {
                    $('#time_off_start_time').prepend('<option value="' + startTimeFormatted + '" selected>' + startTimeFormatted + '</option>');
                }
                if ($('#time_off_end_time').val() !== endTimeFormatted) {
                    $('#time_off_end_time').prepend('<option value="' + endTimeFormatted + '" selected>' + endTimeFormatted + '</option>');
                }
                
                // Set type
                $('#time_off_type').val(timeOff.type);
                
                // Set repeat checkbox
                if (timeOff.is_repeat) {
                    $('#time_off_repeat').prop('checked', true);
                    $('#repeat_until_row').show();
                } else {
                    $('#time_off_repeat').prop('checked', false);
                    $('#repeat_until_row').hide();
                }
                
                // Set description
                $('#time_off_description').val(timeOff.description || '');
                $('#description_counter').text((timeOff.description || '').length + '/100');
                
                // Update modal title and button
                $('#modal_add_time_off .modal-title').text('Edit Time Off');
                $('#btn_save_time_off').text('Update');
                
                // Show modal
                $('#modal_add_time_off').modal('show');
            } else {
                toastr.error('Failed to load time off details');
            }
        },
        error: function() {
            toastr.error('Failed to load time off details');
        }
    });
}

function formatTimeFor12Hour(time24) {
    if (!time24) return '10:00 AM';
    var parts = time24.split(':');
    var hours24 = parseInt(parts[0]);
    var minutes = parts[1] ? parts[1].substring(0, 2) : '00'; // Take only first 2 chars (remove seconds if present)
    var ampm = hours24 >= 12 ? 'PM' : 'AM';
    var hours = hours24 % 12;
    hours = hours ? hours : 12;
    // Pad hours with leading zero if needed for single digit
    var hoursStr = hours < 10 ? '0' + hours : hours.toString();
    return hoursStr + ':' + minutes + ' ' + ampm;
}

function formatDateForDisplay(date) {
    var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return dayNames[date.getDay()] + ', ' + date.getDate() + ' ' + monthNames[date.getMonth()] + ' ' + date.getFullYear();
}

// Time Off Modal Event Handlers
$(document).ready(function() {
    // Repeat checkbox toggle
    $(document).on('change', '#time_off_repeat', function() {
        if ($(this).is(':checked')) {
            $('#repeat_until_row').show();
        } else {
            $('#repeat_until_row').hide();
        }
    });
    
    // Description character counter
    $(document).on('input', '#time_off_description', function() {
        var len = $(this).val().length;
        $('#description_counter').text(len + '/100');
    });
    
    // Save time off button
    $(document).on('click', '#btn_save_time_off', function() {
        saveTimeOff();
    });
    
    // Confirm delete closure button
    $(document).on('click', '#btn_confirm_delete_closure', function() {
        confirmDeleteClosure();
    });
    
    // Initialize edit closure datepickers
    if ($.fn.datepicker) {
        $('#edit_closure_start_date, #edit_closure_end_date').datepicker({
            format: 'D, dd M yyyy',
            autoclose: true,
            todayHighlight: true
        });
        
        // Update duration when dates change
        $('#edit_closure_start_date, #edit_closure_end_date').on('changeDate', function() {
            var startDate = $('#edit_closure_start_date').datepicker('getDate');
            var endDate = $('#edit_closure_end_date').datepicker('getDate');
            if (startDate && endDate) {
                var diffTime = Math.abs(endDate - startDate);
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                $('#closure_duration').text(diffDays + ' day' + (diffDays > 1 ? 's' : ''));
            }
        });
    }
});

// Business Closure Edit Modal Functions
function openEditClosureModal(closureId) {
    // Fetch closure data from API
    $.ajax({
        url: route('admin.business-closures.edit', { id: closureId }),
        type: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            if (response.status) {
                var closure = response.data.closure;
                var locationName = $('#filter_location_id option:selected').text();
                
                // Set modal title with location name
                $('#edit_closure_modal_title').text('Edit closed period for ' + locationName);
                
                // Populate form fields
                $('#edit_closure_id').val(closure.id);
                $('#edit_closure_title').val(closure.title || '');
                
                // Parse and set dates
                var startDate = new Date(closure.start_date);
                var endDate = new Date(closure.end_date);
                
                $('#edit_closure_start_date').datepicker('setDate', startDate);
                $('#edit_closure_end_date').datepicker('setDate', endDate);
                
                // Calculate duration
                var diffTime = Math.abs(endDate - startDate);
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                $('#closure_duration').text(diffDays + ' day' + (diffDays > 1 ? 's' : ''));
                
                // Show modal
                $('#modal_edit_closure').modal('show');
            } else {
                toastr.error(response.message || 'Failed to load closure data');
            }
        },
        error: function () {
            toastr.error('Failed to load closure data');
        }
    });
}

function saveClosureEdit() {
    var closureId = $('#edit_closure_id').val();
    var title = $('#edit_closure_title').val();
    var startDate = $('#edit_closure_start_date').val();
    var endDate = $('#edit_closure_end_date').val();
    
    if (!title || !startDate || !endDate) {
        toastr.error('Please fill in all required fields');
        return;
    }
    
    // Get location IDs - use current filter location
    var locationId = $('#filter_location_id').val();
    
    $.ajax({
        url: route('admin.business-closures.update', { id: closureId }),
        type: 'POST',
        data: {
            _method: 'PUT',
            title: title,
            location_ids: [locationId],
            start_date: startDate,
            end_date: endDate
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            if (response.status) {
                toastr.success('Business closure updated successfully');
                $('#modal_edit_closure').modal('hide');
                loadSchedule();
            } else {
                toastr.error(response.message || 'Failed to update closure');
            }
        },
        error: function (xhr) {
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;
                var errorMsg = '';
                for (var key in errors) {
                    errorMsg += errors[key][0] + '<br>';
                }
                toastr.error(errorMsg);
            } else {
                toastr.error('Failed to update closure');
            }
        }
    });
}

function deleteClosure() {
    // Get dates from the datepickers
    var startDate = $('#edit_closure_start_date').val();
    var endDate = $('#edit_closure_end_date').val();
    var duration = $('#closure_duration').text();
    
    // Format dates for display
    var dateDisplay = startDate;
    if (startDate !== endDate) {
        dateDisplay = startDate + ' - ' + endDate;
    }
    
    // Populate confirmation modal
    $('#delete_closure_dates').text(dateDisplay);
    $('#delete_closure_duration').text(duration);
    
    // Show confirmation modal
    $('#modal_delete_closure_confirm').modal('show');
}

function confirmDeleteClosure() {
    var closureId = $('#edit_closure_id').val();
    
    $.ajax({
        url: route('admin.business-closures.destroy', { id: closureId }),
        type: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            if (response.status) {
                toastr.success('Business closure deleted successfully');
                $('#modal_delete_closure_confirm').modal('hide');
                $('#modal_edit_closure').modal('hide');
                loadSchedule();
            } else {
                toastr.error(response.message || 'Failed to delete closure');
            }
        },
        error: function () {
            toastr.error('Failed to delete closure');
        }
    });
}

function saveTimeOff() {
    var resourceId = $('#time_off_resource_id').val();
    var type = $('#time_off_type').val();
    var startDate = $('#time_off_start_date').val();
    var startTime = $('#time_off_start_time').val();
    var endTime = $('#time_off_end_time').val();
    var repeat = $('#time_off_repeat').is(':checked');
    var repeatUntil = repeat ? $('#time_off_repeat_until').val() : null;
    var description = $('#time_off_description').val();
    var locationId = $('#time_off_location_id').val();
    
    if (!resourceId || !startDate || !startTime || !endTime) {
        toastr.error('Please fill in all required fields');
        return;
    }
    
    // Check if editing or creating
    var isEditing = editingTimeOffId !== null;
    var url = isEditing ? route('admin.schedule.update-time-off') : route('admin.schedule.store-time-off');
    var buttonText = isEditing ? 'Updating...' : 'Saving...';
    var successMessage = isEditing ? 'Time off updated successfully' : 'Time off saved successfully';
    
    // Disable save button
    $('#btn_save_time_off').prop('disabled', true).text(buttonText);
    
    var data = {
        resource_id: resourceId,
        type: type,
        start_date: startDate,
        start_time: startTime,
        end_time: endTime,
        is_repeat: repeat ? 1 : 0,
        repeat_until: repeatUntil,
        description: description,
        location_id: locationId
    };
    
    // Add time_off_id if editing
    if (isEditing) {
        data.time_off_id = editingTimeOffId;
    }
    
    $.ajax({
        url: url,
        type: 'POST',
        data: data,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            $('#btn_save_time_off').prop('disabled', false).text('Save');
            if (response.status) {
                toastr.success(successMessage);
                $('#modal_add_time_off').modal('hide');
                // Reset editing state
                editingTimeOffId = null;
                $('#modal_add_time_off .modal-title').text('Add Time Off');
                loadSchedule();
            } else {
                toastr.error(response.message || 'Failed to save time off');
            }
        },
        error: function(xhr) {
            $('#btn_save_time_off').prop('disabled', false).text('Save');
            if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('Failed to save time off');
            }
        }
    });
}

function loadSchedule() {
    var locationId = $('#filter_location_id').val();
    var resourceType = $('#filter_resource_type').val();
    
    if (!locationId) {
        return;
    }
    
    // Show loading
    $('#schedule_body').html(
        '<tr><td colspan="8" class="text-center py-10">' +
        '<div class="spinner spinner-primary spinner-lg"></div>' +
        '<div class="mt-3">Loading schedule...</div>' +
        '</td></tr>'
    );
    
    var weekStartStr = formatDateForApi(currentWeekStart);
    var weekEnd = new Date(currentWeekStart);
    weekEnd.setDate(weekEnd.getDate() + 6);
    var weekEndStr = formatDateForApi(weekEnd);
    
    $.ajax({
        url: route('admin.schedule.get-shifts'),
        type: 'POST',
        data: {
            location_id: locationId,
            resource_type_id: resourceType,
            start_date: weekStartStr,
            end_date: weekEndStr
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            if (response.status) {
                // Store shifts globally for edit functionality
                currentScheduleShifts = response.data.shifts || [];
                currentScheduleTimeOffs = response.data.time_offs || [];
                renderSchedule(response.data.resources, response.data.shifts, response.data.closures || [], response.data.time_offs || []);
            } else {
                $('#schedule_body').html(
                    '<tr><td colspan="8" class="text-center py-10 text-muted">' +
                    'No resources found for the selected filters.' +
                    '</td></tr>'
                );
            }
        },
        error: function () {
            $('#schedule_body').html(
                '<tr><td colspan="8" class="text-center py-10 text-danger">' +
                'Failed to load schedule. Please try again.' +
                '</td></tr>'
            );
        }
    });
}

function renderSchedule(resources, shifts, closures, timeOffs) {
    closures = closures || [];
    timeOffs = timeOffs || [];
    
    // Store resources for use in modals
    currentCalendarResources = resources || [];
    
    if (!resources || resources.length === 0) {
        $('#schedule_body').html(
            '<tr><td colspan="8" class="text-center py-10 text-muted">' +
            'No resources found for the selected filters.' +
            '</td></tr>'
        );
        return;
    }
    
    // Get spanning time offs (repeating time offs that span multiple days)
    var spanningTimeOffs = getSpanningTimeOffs(timeOffs);
    
    var html = '';
    
    for (var i = 0; i < resources.length; i++) {
        var resource = resources[i];
        var colorClass = avatarColors[i % avatarColors.length];
        var initials = getInitials(resource.name);
        var totalHours = calculateTotalHours(resource.id, shifts);
        
        // Get spanning time offs for this resource
        var resourceSpanningTimeOffs = spanningTimeOffs.filter(function(to) {
            return to.resource_id == resource.id;
        });
        
        var hasSpanningTimeOffs = resourceSpanningTimeOffs.length > 0;
        
        // If there are spanning time offs, render them first as a row above shifts
        if (hasSpanningTimeOffs) {
            html += '<tr class="spanning-time-off-row">';
            html += '<td class="team-member-cell" rowspan="2">';
            html += '<div class="team-member-info">';
            html += '<div class="team-member-avatar ' + colorClass + '">' + initials + '</div>';
            html += '<div class="team-member-details">';
            html += '<div class="team-member-name">' + resource.name + '</div>';
            html += '<div class="team-member-hours">' + totalHours + '</div>';
            html += '</div>';
            html += '</div>';
            html += '</td>';
            html += '<td colspan="7" class="spanning-time-off-cell">';
            html += '<div class="spanning-time-offs-container">';
            
            for (var t = 0; t < resourceSpanningTimeOffs.length; t++) {
                var spanTo = resourceSpanningTimeOffs[t];
                var leftPercent = (spanTo.startDayIndex / 7) * 100;
                var widthPercent = ((spanTo.endDayIndex - spanTo.startDayIndex + 1) / 7) * 100;
                
                html += '<div class="spanning-time-off-wrapper" style="left: ' + leftPercent + '%; width: ' + widthPercent + '%;">';
                html += '<div class="spanning-time-off-bar clickable" data-time-off-id="' + spanTo.id + '" data-resource-id="' + resource.id + '">';
                html += '<span class="spanning-time-off-label">' + spanTo.type_label + '</span>';
                html += '<span class="spanning-time-off-time">' + formatTime(spanTo.start_time) + ' - ' + formatTime(spanTo.end_time) + '</span>';
                html += '</div>';
                html += '<div class="spanning-time-off-dropdown">';
                html += '<button type="button" class="shift-dropdown-item" data-action="edit-time-off" data-time-off-id="' + spanTo.id + '" data-resource-id="' + resource.id + '"><i class="la la-edit"></i>Edit time off</button>';
                html += '<button type="button" class="shift-dropdown-item text-danger" data-action="delete-time-off" data-time-off-id="' + spanTo.id + '"><i class="la la-trash"></i>Delete time off</button>';
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>';
            html += '</td>';
            html += '</tr>';
            
            // Start shifts row (team member cell already has rowspan=2)
            html += '<tr class="resource-row has-spanning-time-off">';
        } else {
            html += '<tr class="resource-row">';
            
            // Team member cell (no rowspan needed)
            html += '<td class="team-member-cell">';
            html += '<div class="team-member-info">';
            html += '<div class="team-member-avatar ' + colorClass + '">' + initials + '</div>';
            html += '<div class="team-member-details">';
            html += '<div class="team-member-name">' + resource.name + '</div>';
            html += '<div class="team-member-hours">' + totalHours + '</div>';
            html += '</div>';
            html += '</div>';
            html += '</td>';
        }
        
        // Day cells
        for (var day = 0; day < 7; day++) {
            var date = new Date(currentWeekStart);
            date.setDate(date.getDate() + day);
            var dateStr = formatDateForApi(date);
            
            var dayShifts = findAllShifts(resource.id, dateStr, shifts);
            // Get all time offs for this day (for shift splitting)
            var allDayTimeOffs = findAllTimeOffs(resource.id, dateStr, timeOffs);
            // Filter out repeating time offs for display (they will be rendered as spanning bars)
            var nonRepeatingTimeOffs = allDayTimeOffs.filter(function(to) {
                return !to.is_repeat || !to.repeat_until;
            });
            var closure = findClosure(dateStr, closures);
            var isWeekend = (day === 5 || day === 6); // Saturday or Sunday
            var isBusinessClosed = !isBusinessWorkingDay(day, dateStr);
            
            html += '<td class="shift-cell' + (isBusinessClosed ? ' business-closed-day' : '') + '" data-resource-id="' + resource.id + '" data-date="' + dateStr + '">';
            html += '<div class="shift-container">';
            
            // Check if business is closed on this day (either by closure or non-working day)
            if (isBusinessClosed) {
                html += '<span class="shift-badge not-working">Closed</span>';
            } else if (closure) {
                html += '<span class="shift-badge business-closed clickable" data-closure-id="' + closure.id + '" title="' + (closure.title || 'Business Closed') + '">' + (closure.title || 'Closed') + '</span>';
            } else if (dayShifts.length > 0 || allDayTimeOffs.length > 0) {
                // Split shifts around ALL time offs (including repeating ones) but only display non-repeating
                var displayItems = getDisplayItemsWithTimeOffs(dayShifts, allDayTimeOffs, true);
                
                // If no display items after processing, show "Not working"
                if (displayItems.length === 0) {
                    html += '<span class="shift-badge not-working">Not working</span>';
                }
                
                for (var d = 0; d < displayItems.length; d++) {
                    var item = displayItems[d];
                    if (item.type === 'time_off') {
                        // Render time off badge
                        html += '<div class="shift-badge-wrapper">';
                        html += '<span class="shift-badge time-off clickable" data-time-off-id="' + item.id + '" data-resource-id="' + resource.id + '">';
                        html += '<strong>' + item.type_label + '</strong><br>';
                        html += formatTime(item.start_time) + ' - ' + formatTime(item.end_time);
                        html += '</span>';
                        html += '<div class="shift-dropdown shift-edit-dropdown">';
                        html += '<button type="button" class="shift-dropdown-item" data-action="edit-time-off" data-time-off-id="' + item.id + '" data-resource-id="' + resource.id + '"><i class="la la-edit"></i>Edit time off</button>';
                        html += '<button type="button" class="shift-dropdown-item text-danger" data-action="delete-time-off" data-time-off-id="' + item.id + '"><i class="la la-trash"></i>Delete time off</button>';
                        html += '</div>';
                        html += '</div>';
                    } else {
                        // Render shift badge
                        var shiftTime = formatTime(item.start_time) + ' - ' + formatTime(item.end_time);
                        var badgeClass = isWeekend ? 'shift-badge weekend clickable' : 'shift-badge clickable';
                        var shiftId = item.id || 0;
                        html += '<div class="shift-badge-wrapper">';
                        html += '<span class="' + badgeClass + '" data-shift-id="' + shiftId + '">' + shiftTime + '</span>';
                        // Individual dropdown for this shift
                        html += '<div class="shift-dropdown shift-edit-dropdown" data-shift-id="' + shiftId + '">';
                        html += '<button type="button" class="shift-dropdown-item" data-action="edit-day"><i class="la la-edit"></i>Edit this day</button>';
                        html += '<button type="button" class="shift-dropdown-item" data-action="repeating-shifts"><i class="la la-redo-alt"></i>Set repeating shifts</button>';
                        html += '<button type="button" class="shift-dropdown-item" data-action="time-off"><i class="la la-clock"></i>Add time off</button>';
                        html += '<button type="button" class="shift-dropdown-item text-danger" data-action="delete-shift" data-shift-id="' + shiftId + '"><i class="la la-trash"></i>Delete this shift</button>';
                        html += '</div>';
                        html += '</div>';
                    }
                }
                // Dropdown for plus button (shown when clicking on plus)
                html += '<div class="shift-dropdown shift-add-dropdown">';
                html += '<button type="button" class="shift-dropdown-item" data-action="add-shift"><i class="la la-plus"></i>Add shift</button>';
                html += '<button type="button" class="shift-dropdown-item" data-action="repeating-shifts"><i class="la la-redo-alt"></i>Set repeating shifts</button>';
                html += '<button type="button" class="shift-dropdown-item" data-action="time-off"><i class="la la-clock"></i>Add time off</button>';
                html += '</div>';
            } else {
                html += '<span class="shift-badge not-working">Not working</span>';
                // Dropdown for empty cell
                html += '<div class="shift-dropdown shift-add-dropdown">';
                html += '<button type="button" class="shift-dropdown-item" data-action="add-shift"><i class="la la-plus"></i>Add shift</button>';
                html += '<button type="button" class="shift-dropdown-item" data-action="repeating-shifts"><i class="la la-redo-alt"></i>Set repeating shifts</button>';
                html += '<button type="button" class="shift-dropdown-item" data-action="time-off"><i class="la la-clock"></i>Add time off</button>';
                html += '</div>';
            }
            
            // Don't show add button if business is closed (either by closure or non-working day)
            if (!closure && !isBusinessClosed) {
                html += '<button type="button" class="shift-add-btn" title="Add shift"><i class="la la-plus"></i></button>';
            }
            html += '</div>';
            html += '</td>';
        }
        
        html += '</tr>';
    }
    
    $('#schedule_body').html(html);
}

// Get spanning time offs (repeating time offs that span multiple days in current week)
function getSpanningTimeOffs(timeOffs) {
    if (!timeOffs || timeOffs.length === 0) return [];
    
    var result = [];
    var weekEnd = new Date(currentWeekStart);
    weekEnd.setDate(weekEnd.getDate() + 6);
    
    for (var i = 0; i < timeOffs.length; i++) {
        var to = timeOffs[i];
        
        // Only process repeating time offs
        if (!to.is_repeat || !to.repeat_until) continue;
        
        var startDate = new Date(to.start_date);
        var repeatUntil = new Date(to.repeat_until);
        
        // Check if this time off overlaps with current week
        if (repeatUntil < currentWeekStart || startDate > weekEnd) continue;
        
        // Calculate which days in the week this time off spans
        var effectiveStart = startDate < currentWeekStart ? currentWeekStart : startDate;
        var effectiveEnd = repeatUntil > weekEnd ? weekEnd : repeatUntil;
        
        var startDayIndex = Math.floor((effectiveStart - currentWeekStart) / (1000 * 60 * 60 * 24));
        var endDayIndex = Math.floor((effectiveEnd - currentWeekStart) / (1000 * 60 * 60 * 24));
        
        // Clamp to valid range
        startDayIndex = Math.max(0, Math.min(6, startDayIndex));
        endDayIndex = Math.max(0, Math.min(6, endDayIndex));
        
        result.push({
            id: to.id,
            resource_id: to.resource_id,
            type: to.type,
            type_label: to.type_label,
            start_time: to.start_time,
            end_time: to.end_time,
            startDayIndex: startDayIndex,
            endDayIndex: endDayIndex
        });
    }
    
    return result;
}

function isBusinessWorkingDay(dayIndex, dateStr) {
    // dayIndex: 0=Monday, 1=Tuesday, ..., 6=Sunday
    var dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    var dayName = dayNames[dayIndex];
    
    // Check for exception first
    if (dateStr && workingDayExceptions && workingDayExceptions.length > 0) {
        var exception = workingDayExceptions.find(function(e) { 
            return e.exception_date === dateStr || e.date === dateStr; 
        });
        if (exception) {
            return exception.is_working === true;
        }
    }
    
    // Fall back to default working days config
    return businessWorkingDays[dayName] === true;
}

function findShift(resourceId, dateStr, shifts) {
    if (!shifts) return null;
    
    for (var i = 0; i < shifts.length; i++) {
        if (shifts[i].resource_id == resourceId && shifts[i].date === dateStr) {
            return shifts[i];
        }
    }
    return null;
}

function findAllShifts(resourceId, dateStr, shifts) {
    if (!shifts) return [];
    
    var result = [];
    for (var i = 0; i < shifts.length; i++) {
        if (shifts[i].resource_id == resourceId && shifts[i].date === dateStr) {
            result.push(shifts[i]);
        }
    }
    return result;
}

function findAllTimeOffs(resourceId, dateStr, timeOffs) {
    if (!timeOffs) return [];
    
    var result = [];
    var checkDate = new Date(dateStr);
    
    for (var i = 0; i < timeOffs.length; i++) {
        var to = timeOffs[i];
        if (to.resource_id != resourceId) continue;
        
        var startDate = new Date(to.start_date);
        
        // Check if this time off applies to the given date
        if (to.is_repeat && to.repeat_until) {
            // Repeating time off - check if date is within range
            var repeatUntil = new Date(to.repeat_until);
            if (checkDate >= startDate && checkDate <= repeatUntil) {
                result.push(to);
            }
        } else {
            // Non-repeating - exact date match
            if (to.start_date === dateStr) {
                result.push(to);
            }
        }
    }
    return result;
}

function getDisplayItemsWithTimeOffs(shifts, timeOffs, skipRepeatingTimeOffs) {
    var items = [];
    
    // Add time offs first (they take priority in display order)
    // If skipRepeatingTimeOffs is true, don't add repeating time offs to display items
    // (they will be rendered as spanning bars instead)
    for (var i = 0; i < timeOffs.length; i++) {
        var to = timeOffs[i];
        // Skip repeating time offs for display if flag is set
        if (skipRepeatingTimeOffs && to.is_repeat && to.repeat_until) {
            continue;
        }
        items.push({
            type: 'time_off',
            id: to.id,
            start_time: to.start_time,
            end_time: to.end_time,
            type_label: to.type_label || 'Time Off',
            sort_time: timeToMinutes(to.start_time)
        });
    }
    
    // Process shifts - split them around time offs
    for (var s = 0; s < shifts.length; s++) {
        var shift = shifts[s];
        if (!shift.start_time || !shift.end_time) continue;
        
        var shiftStart = timeToMinutes(shift.start_time);
        var shiftEnd = timeToMinutes(shift.end_time);
        var segments = [{start: shiftStart, end: shiftEnd, id: shift.id}];
        
        // Cut out time off periods from this shift
        for (var t = 0; t < timeOffs.length; t++) {
            var to = timeOffs[t];
            if (!to.start_time || !to.end_time) continue;
            
            var toStart = timeToMinutes(to.start_time);
            var toEnd = timeToMinutes(to.end_time);
            
            var newSegments = [];
            for (var seg = 0; seg < segments.length; seg++) {
                var segment = segments[seg];
                
                // Check if time off overlaps with this segment
                if (toEnd <= segment.start || toStart >= segment.end) {
                    // No overlap
                    newSegments.push(segment);
                } else {
                    // There is overlap - split the segment
                    if (toStart > segment.start) {
                        // Part before time off
                        newSegments.push({start: segment.start, end: toStart, id: segment.id});
                    }
                    if (toEnd < segment.end) {
                        // Part after time off
                        newSegments.push({start: toEnd, end: segment.end, id: segment.id});
                    }
                }
            }
            segments = newSegments;
        }
        
        // Add remaining shift segments
        for (var seg = 0; seg < segments.length; seg++) {
            var segment = segments[seg];
            if (segment.end > segment.start) {
                items.push({
                    type: 'shift',
                    id: segment.id,
                    start_time: minutesToTime(segment.start),
                    end_time: minutesToTime(segment.end),
                    sort_time: segment.start
                });
            }
        }
    }
    
    // Sort by start time
    items.sort(function(a, b) {
        return a.sort_time - b.sort_time;
    });
    
    return items;
}

function timeToMinutes(timeStr) {
    if (!timeStr) return 0;
    // Handle HH:mm:ss or HH:mm format
    var parts = timeStr.split(':');
    var hours = parseInt(parts[0], 10);
    var minutes = parseInt(parts[1], 10) || 0;
    return hours * 60 + minutes;
}

function minutesToTime(minutes) {
    var hours = Math.floor(minutes / 60);
    var mins = minutes % 60;
    return (hours < 10 ? '0' : '') + hours + ':' + (mins < 10 ? '0' : '') + mins;
}

function findClosure(dateStr, closures) {
    if (!closures) return null;
    
    for (var i = 0; i < closures.length; i++) {
        if (closures[i].date === dateStr) {
            return closures[i];
        }
    }
    return null;
}

function calculateTotalHours(resourceId, shifts) {
    if (!shifts) return '0h';
    
    var totalMinutes = 0;
    
    for (var i = 0; i < shifts.length; i++) {
        if (shifts[i].resource_id == resourceId && shifts[i].start_time && shifts[i].end_time) {
            var start = parseTime(shifts[i].start_time);
            var end = parseTime(shifts[i].end_time);
            if (start && end) {
                var diff = (end.getTime() - start.getTime()) / (1000 * 60);
                if (diff > 0) {
                    totalMinutes += diff;
                }
            }
        }
    }
    
    var hours = Math.floor(totalMinutes / 60);
    var mins = totalMinutes % 60;
    
    if (mins > 0) {
        return hours + 'h ' + mins + 'm';
    }
    return hours + 'h';
}

function parseTime(timeStr) {
    if (!timeStr) return null;
    var parts = timeStr.split(':');
    if (parts.length >= 2) {
        var date = new Date();
        date.setHours(parseInt(parts[0]), parseInt(parts[1]), 0, 0);
        return date;
    }
    return null;
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    
    // If already contains AM/PM, clean it up and format properly
    if (timeStr.toLowerCase().indexOf('am') !== -1 || timeStr.toLowerCase().indexOf('pm') !== -1) {
        // Extract hours, minutes and AM/PM
        var match = timeStr.match(/(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM|am|pm)/i);
        if (match) {
            var hours = match[1].padStart(2, '0');
            var mins = match[2];
            var ampm = match[3].toUpperCase();
            return hours + ':' + mins + ' ' + ampm;
        }
        return timeStr.trim();
    }
    
    // Handle 24-hour format (HH:MM or HH:MM:SS)
    var parts = timeStr.split(':');
    if (parts.length >= 2) {
        var hours = parseInt(parts[0]);
        var mins = parts[1].substring(0, 2);
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        var hoursStr = hours.toString().padStart(2, '0');
        return hoursStr + ':' + mins + ' ' + ampm;
    }
    return timeStr;
}

function getInitials(name) {
    if (!name) return '?';
    var parts = name.split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
}

function formatDate(date, format) {
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    if (format === 'd MMM') {
        return date.getDate() + ' ' + months[date.getMonth()];
    }
    if (format === 'd MMM, yyyy') {
        return date.getDate() + ' ' + months[date.getMonth()] + ', ' + date.getFullYear();
    }
    return date.toDateString();
}

function formatDateForApi(date) {
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

function getMonthName(monthIndex) {
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return months[monthIndex];
}
