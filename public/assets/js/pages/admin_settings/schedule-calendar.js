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
    
    for (var i = 0; i < locations.length; i++) {
        var selected = i === 0 ? 'selected' : '';
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
            
            html += '<td class="shift-cell">';
            if (shift && shift.start_time && shift.end_time) {
                var shiftTime = formatTime(shift.start_time) + ' - ' + formatTime(shift.end_time);
                var badgeClass = isWeekend ? 'shift-badge weekend' : 'shift-badge';
                html += '<span class="' + badgeClass + '">' + shiftTime + '</span>';
            } else {
                html += '<span class="shift-badge not-working">Not working</span>';
            }
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
