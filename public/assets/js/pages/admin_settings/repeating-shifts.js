"use strict";

$(document).ready(function() {
    initPage();
    initEventHandlers();
    populateTimeDropdowns();
    calculateTotalHours();
});

function initPage() {
    // Set page title with resource name
    if (resourceName) {
        $('#page_title').text("Set " + resourceName + "'s repeating shifts");
    }
    
    // Set location info
    if (locationName) {
        $('#location_name').text(locationName);
    }
    
    // Initialize datepickers
    initDatepickers();
}

function initDatepickers() {
    // Parse the selected date (format: YYYY-MM-DD)
    var startDate;
    if (selectedDate && selectedDate !== '') {
        var parts = selectedDate.split('-');
        if (parts.length === 3) {
            startDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        } else {
            startDate = new Date();
        }
    } else {
        startDate = new Date();
    }
    
    var endDate = new Date(startDate.getTime());
    endDate.setDate(endDate.getDate() + 30);
    
    // Initialize Start date datepicker
    $('#schedule_start_date').datepicker({
        format: 'MM d, yyyy',
        autoclose: true,
        todayHighlight: true
    }).datepicker('setDate', startDate);
    
    // Initialize End date datepicker
    $('#schedule_end_date').datepicker({
        format: 'MM d, yyyy',
        autoclose: true,
        todayHighlight: true
    }).datepicker('setDate', endDate);
}

function initEventHandlers() {
    // Day checkbox toggle
    $(document).on('change', '.day-enabled', function() {
        var $row = $(this).closest('.day-schedule-row');
        var isChecked = $(this).is(':checked');
        
        if (isChecked) {
            $row.find('.day-shifts-container').removeClass('d-none');
            $row.find('.not-working-label').addClass('d-none');
            $row.find('.copy-to-all-days').removeClass('d-none');
        } else {
            $row.find('.day-shifts-container').addClass('d-none');
            $row.find('.not-working-label').removeClass('d-none');
            $row.find('.copy-to-all-days').addClass('d-none');
            $row.find('.day-hours').text('');
        }
        
        calculateTotalHours();
    });
    
    // Time change
    $(document).on('change', '.shift-start, .shift-end', function() {
        var $row = $(this).closest('.day-schedule-row');
        updateDayHours($row);
        calculateTotalHours();
    });
    
    // Add shift time row
    $(document).on('click', '.add-shift-time', function() {
        var $container = $(this).closest('.day-shifts-container');
        var $currentRow = $(this).closest('.shift-time-row');
        
        var newRow = createShiftTimeRow();
        $container.append(newRow);
        
        // Show remove buttons for all rows if more than one
        if ($container.find('.shift-time-row').length > 1) {
            $container.find('.remove-shift-time').show();
        }
        
        populateTimeDropdownsForRow($container.find('.shift-time-row').last());
        calculateTotalHours();
    });
    
    // Remove shift time row
    $(document).on('click', '.remove-shift-time', function() {
        var $container = $(this).closest('.day-shifts-container');
        var $row = $(this).closest('.shift-time-row');
        
        $row.remove();
        
        // Hide remove button if only one row left
        if ($container.find('.shift-time-row').length === 1) {
            $container.find('.remove-shift-time').hide();
        }
        
        var $dayRow = $container.closest('.day-schedule-row');
        updateDayHours($dayRow);
        calculateTotalHours();
    });
    
    // Save button
    $('#btn_save_repeating_shifts').on('click', function() {
        saveRepeatingShifts();
    });
    
    // Copy to all days button
    $(document).on('click', '.copy-to-all-days', function() {
        copyToAllDays($(this).closest('.day-schedule-row'));
    });
}

function copyToAllDays($sourceRow) {
    // Get all shifts from the source day
    var shifts = [];
    $sourceRow.find('.shift-time-row').each(function() {
        shifts.push({
            start: $(this).find('.shift-start').val(),
            end: $(this).find('.shift-end').val()
        });
    });
    
    if (shifts.length === 0) {
        toastr.warning('No shifts to copy');
        return;
    }
    
    // Apply to all other days
    $('.day-schedule-row').each(function() {
        var $targetRow = $(this);
        
        // Skip the source row
        if ($targetRow.data('day') === $sourceRow.data('day')) {
            return;
        }
        
        // Enable the day
        $targetRow.find('.day-enabled').prop('checked', true);
        $targetRow.find('.day-shifts-container').removeClass('d-none');
        $targetRow.find('.not-working-label').addClass('d-none');
        
        // Clear existing shifts
        var $container = $targetRow.find('.day-shifts-container');
        $container.find('.shift-time-row').not(':first').remove();
        
        // Set the first shift
        var $firstRow = $container.find('.shift-time-row').first();
        $firstRow.find('.shift-start').val(shifts[0].start);
        $firstRow.find('.shift-end').val(shifts[0].end);
        
        // Add additional shifts if needed
        for (var i = 1; i < shifts.length; i++) {
            var newRow = createShiftTimeRow();
            $container.append(newRow);
            var $newRow = $container.find('.shift-time-row').last();
            populateTimeDropdownsForRow($newRow);
            $newRow.find('.shift-start').val(shifts[i].start);
            $newRow.find('.shift-end').val(shifts[i].end);
        }
        
        // Show/hide remove buttons
        if ($container.find('.shift-time-row').length > 1) {
            $container.find('.remove-shift-time').show();
        } else {
            $container.find('.remove-shift-time').hide();
        }
        
        // Update hours for this day
        updateDayHours($targetRow);
    });
    
    calculateTotalHours();
    toastr.success('Shifts copied to all days');
}

function createShiftTimeRow() {
    var html = '<div class="shift-time-row d-flex align-items-center mb-2">';
    html += '<select class="form-control time-select shift-start mr-2">';
    html += '</select>';
    html += '<span class="time-separator mx-2">to</span>';
    html += '<select class="form-control time-select shift-end mr-3">';
    html += '</select>';
    html += '<button type="button" class="btn-add-time add-shift-time mr-2" title="Add shift">';
    html += '<i class="la la-plus"></i>';
    html += '</button>';
    html += '<button type="button" class="btn-remove-time remove-shift-time" title="Remove">';
    html += '<i class="la la-trash"></i>';
    html += '</button>';
    html += '</div>';
    return html;
}

function populateTimeDropdowns() {
    $('.day-schedule-row').each(function() {
        var $row = $(this);
        $row.find('.shift-time-row').each(function() {
            populateTimeDropdownsForRow($(this));
        });
    });
}

function populateTimeDropdownsForRow($row) {
    var $startSelect = $row.find('.shift-start');
    var $endSelect = $row.find('.shift-end');
    
    // Use default times from URL parameters, fallback to 10:00am - 7:00pm
    var startTime = (typeof defaultStartTime !== 'undefined' && defaultStartTime) ? defaultStartTime : '10:00am';
    var endTime = (typeof defaultEndTime !== 'undefined' && defaultEndTime) ? defaultEndTime : '7:00pm';
    
    $startSelect.html(getTimeOptions(startTime));
    $endSelect.html(getTimeOptions(endTime));
}

function getTimeOptions(selectedValue) {
    var options = '';
    for (var hour = 0; hour < 24; hour++) {
        for (var min = 0; min < 60; min += 30) {
            var h = hour % 12;
            h = h ? h : 12;
            var ampm = hour >= 12 ? 'pm' : 'am';
            var timeStr = h + ':' + (min === 0 ? '00' : min) + ampm;
            var selected = timeStr === selectedValue ? 'selected' : '';
            options += '<option value="' + timeStr + '" ' + selected + '>' + timeStr + '</option>';
        }
    }
    return options;
}

function updateDayHours($dayRow) {
    var totalMinutes = 0;
    
    $dayRow.find('.shift-time-row').each(function() {
        var startTime = $(this).find('.shift-start').val();
        var endTime = $(this).find('.shift-end').val();
        
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
    var hoursStr = hours + 'h';
    if (mins > 0) {
        hoursStr += ' ' + mins + 'm';
    }
    
    $dayRow.find('.day-hours').text(totalMinutes > 0 ? hoursStr : '');
}

function calculateTotalHours() {
    var totalMinutes = 0;
    
    $('.day-schedule-row').each(function() {
        var $row = $(this);
        var isEnabled = $row.find('.day-enabled').is(':checked');
        
        if (isEnabled) {
            $row.find('.shift-time-row').each(function() {
                var startTime = $(this).find('.shift-start').val();
                var endTime = $(this).find('.shift-end').val();
                
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
            
            updateDayHours($row);
        }
    });
    
    var hours = Math.floor(totalMinutes / 60);
    var mins = totalMinutes % 60;
    var hoursStr = hours + ' hours';
    if (mins > 0) {
        hoursStr = hours + 'h ' + mins + 'm';
    }
    
    $('#total_hours_display').text(hoursStr + ' total');
}

function parseTimeString(timeStr) {
    if (!timeStr) return null;
    
    var match = timeStr.match(/(\d{1,2}):(\d{2})(am|pm)/i);
    if (match) {
        var hours = parseInt(match[1]);
        var mins = parseInt(match[2]);
        var ampm = match[3].toLowerCase();
        
        if (ampm === 'pm' && hours !== 12) {
            hours += 12;
        } else if (ampm === 'am' && hours === 12) {
            hours = 0;
        }
        
        var date = new Date();
        date.setHours(hours, mins, 0, 0);
        return date;
    }
    return null;
}

function saveRepeatingShifts() {
    var scheduleData = {
        resource_id: resourceId,
        location_id: locationId,
        schedule_type: $('#schedule_type').val(),
        start_date: $('#schedule_start_date').val(),
        end_date: $('#schedule_end_date').val(),
        days: []
    };
    
    $('.day-schedule-row').each(function() {
        var $row = $(this);
        var dayName = $row.data('day');
        var isEnabled = $row.find('.day-enabled').is(':checked');
        
        var dayData = {
            day: dayName,
            enabled: isEnabled,
            shifts: []
        };
        
        if (isEnabled) {
            $row.find('.shift-time-row').each(function() {
                dayData.shifts.push({
                    start_time: $(this).find('.shift-start').val(),
                    end_time: $(this).find('.shift-end').val()
                });
            });
        }
        
        scheduleData.days.push(dayData);
    });
    
    // Disable save button
    $('#btn_save_repeating_shifts').prop('disabled', true).text('Saving...');
    
    $.ajax({
        url: route('admin.schedule.store-repeating-shifts'),
        type: 'POST',
        data: JSON.stringify(scheduleData),
        contentType: 'application/json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            $('#btn_save_repeating_shifts').prop('disabled', false).text('Save');
            if (response.status) {
                toastr.success('Repeating shifts saved successfully (' + response.data.shifts_created + ' shifts created)');
                
                // Redirect back to schedule calendar with location and date preserved
                setTimeout(function() {
                    var redirectUrl = route('admin.resourcerotas.schedule') + 
                        '?location_id=' + locationId + 
                        '&date=' + selectedDate;
                    window.location.href = redirectUrl;
                }, 1500);
            } else {
                toastr.error(response.message || 'Failed to save repeating shifts');
            }
        },
        error: function(xhr) {
            $('#btn_save_repeating_shifts').prop('disabled', false).text('Save');
            if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('Failed to save repeating shifts');
            }
        }
    });
}
