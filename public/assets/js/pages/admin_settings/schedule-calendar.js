"use strict";

var currentWeekStart = null;
var locations = [];
var avatarColors = ['avatar-color-1', 'avatar-color-2', 'avatar-color-3', 'avatar-color-4', 'avatar-color-5', 'avatar-color-6'];

$(document).ready(function () {
    initWeekDates();
    loadLocations();
    initEventHandlers();
});

function initWeekDates() {
    // Set current week start to Monday of current week
    var today = new Date();
    var dayOfWeek = today.getDay();
    var diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1); // Adjust for Sunday
    currentWeekStart = new Date(today.setDate(diff));
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
    
    // Skip "All Centres" option - find first actual branch
    var firstBranchIndex = 0;
    for (var i = 0; i < locations.length; i++) {
        if (locations[i].name && locations[i].name.toLowerCase().indexOf('all') === -1) {
            firstBranchIndex = i;
            break;
        }
    }
    
    for (var i = 0; i < locations.length; i++) {
        // Skip "All Centres" or similar options
        if (locations[i].name && locations[i].name.toLowerCase().indexOf('all') !== -1) {
            continue;
        }
        var selected = i === firstBranchIndex ? 'selected' : '';
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
    $(document).on('click', '.shift-badge.clickable', function (e) {
        e.stopPropagation();
        var $dropdown = $(this).siblings('.shift-edit-dropdown');
        
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
        var $cell = $(this).closest('.shift-cell');
        var resourceId = $cell.data('resource-id');
        var date = $cell.data('date');
        
        // Close dropdown
        $(this).closest('.shift-dropdown').removeClass('show');
        
        // Handle action
        handleShiftAction(action, resourceId, date, shiftId);
    });
    
    // Close dropdown when clicking outside
    $(document).on('click', function () {
        $('.shift-dropdown').removeClass('show');
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
    }
}

function openEditDayModal(resourceId, date, shiftId) {
    // For now, open the add shift modal in edit mode
    // TODO: Pre-populate with existing shift data
    openAddShiftModal(resourceId, date);
    toastr.info('Edit mode - modify the shift times and save');
}

function deleteShift(resourceId, date, shiftId) {
    if (!confirm('Are you sure you want to delete this shift?')) {
        return;
    }
    
    // TODO: Implement delete shift API call
    console.log('Deleting shift:', {
        resource_id: resourceId,
        date: date,
        shift_id: shiftId
    });
    
    toastr.success('Shift deleted successfully');
    loadSchedule();
}

var currentShiftResourceId = null;
var currentShiftDate = null;
var currentShiftResourceName = '';
var shiftRowCounter = 0;

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
    var rowHtml = '<div class="shift-row mb-3" data-row="' + shiftRowCounter + '">';
    rowHtml += '<div class="row align-items-end">';
    rowHtml += '<div class="col-md-5">';
    rowHtml += '<label class="mb-2">Start time</label>';
    rowHtml += '<select class="form-control shift-start-time" name="shifts[' + shiftRowCounter + '][start_time]">';
    rowHtml += getTimeOptions('10:00 AM');
    rowHtml += '</select>';
    rowHtml += '</div>';
    rowHtml += '<div class="col-md-5">';
    rowHtml += '<label class="mb-2">End time</label>';
    rowHtml += '<select class="form-control shift-end-time" name="shifts[' + shiftRowCounter + '][end_time]">';
    rowHtml += getTimeOptions('07:00 PM');
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
    
    // Delete all shifts button
    $(document).on('click', '#btn_delete_all_shifts', function() {
        if (confirm('Are you sure you want to delete all shifts for this day?')) {
            // TODO: Implement delete all shifts API call
            toastr.info('Delete all shifts functionality coming soon');
        }
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
    
    // TODO: Implement save shift API call
    console.log('Saving shifts:', {
        resource_id: currentShiftResourceId,
        date: currentShiftDate,
        location_id: $('#shift_location_id').val(),
        shifts: shifts
    });
    
    toastr.success('Shift saved successfully');
    $('#modal_add_shift').modal('hide');
    loadSchedule();
}

// Time Off Modal Functions
var currentTimeOffResources = [];

function openTimeOffModal(resourceId, date) {
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
});

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
    
    // TODO: Implement save time off API call
    console.log('Saving time off:', {
        resource_id: resourceId,
        type: type,
        start_date: startDate,
        start_time: startTime,
        end_time: endTime,
        repeat: repeat,
        repeat_until: repeatUntil,
        description: description,
        location_id: locationId
    });
    
    toastr.success('Time off saved successfully');
    $('#modal_add_time_off').modal('hide');
    loadSchedule();
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
                renderSchedule(response.data.resources, response.data.shifts);
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

function renderSchedule(resources, shifts) {
    if (!resources || resources.length === 0) {
        $('#schedule_body').html(
            '<tr><td colspan="8" class="text-center py-10 text-muted">' +
            'No resources found for the selected filters.' +
            '</td></tr>'
        );
        return;
    }
    
    var html = '';
    
    for (var i = 0; i < resources.length; i++) {
        var resource = resources[i];
        var colorClass = avatarColors[i % avatarColors.length];
        var initials = getInitials(resource.name);
        var totalHours = calculateTotalHours(resource.id, shifts);
        
        html += '<tr>';
        
        // Team member cell
        html += '<td class="team-member-cell">';
        html += '<div class="team-member-info">';
        html += '<div class="team-member-avatar ' + colorClass + '">' + initials + '</div>';
        html += '<div class="team-member-details">';
        html += '<div class="team-member-name">' + resource.name + '</div>';
        html += '<div class="team-member-hours">' + totalHours + '</div>';
        html += '</div>';
        html += '</div>';
        html += '</td>';
        
        // Day cells
        for (var day = 0; day < 7; day++) {
            var date = new Date(currentWeekStart);
            date.setDate(date.getDate() + day);
            var dateStr = formatDateForApi(date);
            
            var shift = findShift(resource.id, dateStr, shifts);
            var isWeekend = (day === 5 || day === 6); // Saturday or Sunday
            
            html += '<td class="shift-cell" data-resource-id="' + resource.id + '" data-date="' + dateStr + '">';
            html += '<div class="shift-container">';
            if (shift && shift.start_time && shift.end_time) {
                var shiftTime = formatTime(shift.start_time) + ' - ' + formatTime(shift.end_time);
                var badgeClass = isWeekend ? 'shift-badge weekend clickable' : 'shift-badge clickable';
                var shiftId = shift.id || 0;
                html += '<span class="' + badgeClass + '" data-shift-id="' + shiftId + '">' + shiftTime + '</span>';
                // Dropdown for existing shift (shown when clicking on badge)
                html += '<div class="shift-dropdown shift-edit-dropdown">';
                html += '<button type="button" class="shift-dropdown-item" data-action="edit-day" data-shift-id="' + shiftId + '"><i class="la la-edit"></i>Edit this day</button>';
                html += '<button type="button" class="shift-dropdown-item" data-action="repeating-shifts"><i class="la la-redo-alt"></i>Set repeating shifts</button>';
                html += '<button type="button" class="shift-dropdown-item" data-action="time-off"><i class="la la-clock"></i>Add time off</button>';
                html += '<button type="button" class="shift-dropdown-item text-danger" data-action="delete-shift" data-shift-id="' + shiftId + '"><i class="la la-trash"></i>Delete this shift</button>';
                html += '</div>';
                // Dropdown for plus button (shown when clicking on plus)
                html += '<div class="shift-dropdown shift-add-dropdown">';
                html += '<button type="button" class="shift-dropdown-item" data-action="repeating-shifts"><i class="la la-redo-alt"></i>Set repeating shifts</button>';
                html += '<button type="button" class="shift-dropdown-item" data-action="time-off"><i class="la la-clock"></i>Add time off</button>';
                html += '</div>';
            } else {
                html += '<span class="shift-badge not-working">Not working</span>';
                // Dropdown for empty cell
                html += '<div class="shift-dropdown shift-add-dropdown">';
                html += '<button type="button" class="shift-dropdown-item" data-action="repeating-shifts"><i class="la la-redo-alt"></i>Set repeating shifts</button>';
                html += '<button type="button" class="shift-dropdown-item" data-action="time-off"><i class="la la-clock"></i>Add time off</button>';
                html += '</div>';
            }
            html += '<button type="button" class="shift-add-btn" title="Add shift"><i class="la la-plus"></i></button>';
            html += '</div>';
            html += '</td>';
        }
        
        html += '</tr>';
    }
    
    $('#schedule_body').html(html);
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
