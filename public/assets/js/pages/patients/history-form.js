// Patient Activity History

var historyLoaded = false;

function loadPatientHistory() {
    if (historyLoaded) return;
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/patients/' + patientCardID + '/activity-history',
        type: 'GET',
        cache: false,
        success: function(response) {
            historyLoaded = true;
            renderActivityTimeline(response.data);
        },
        error: function(xhr) {
            $('#activity-timeline-container').hide();
            $('#no-activities').show();
            console.error('Failed to load activity history:', xhr);
        }
    });
}

function renderActivityTimeline(activities) {
    $('#activity-timeline-container').hide();
    
    if (!activities || activities.length === 0) {
        $('#no-activities').show();
        return;
    }
    
    let html = '';
    
    activities.forEach(function(activity) {
        let typeClass = getActivityTypeClass(activity.type);
        let timeFormatted = formatActivityTime(activity.created_at);
        
        html += `
            <div class="timeline-item ${typeClass}">
                <div class="timeline-time">${timeFormatted}</div>
                <div class="timeline-content">${activity.description}</div>
            </div>
        `;
    });
    
    $('#activity-timeline').html(html).show();
}

function getActivityTypeClass(type) {
    const typeMap = {
        'lead_created': 'lead-created',
        'consultation_booked': 'consultation-booked',
        'lead_booked': 'lead-booked',
        'package_created': 'package-created',
        'service_added': 'service-added',
        'payment_made': 'payment-made',
        'consultation_converted': 'consultation-converted',
        'lead_converted': 'lead-converted',
        'treatment_booked': 'treatment-booked',
        'treatment_arrived': 'treatment-arrived',
        'invoice_created': 'payment-made',
        'refund_made': 'service-added'
    };
    return typeMap[type] || 'lead-created';
}

function formatActivityTime(dateString) {
    if (!dateString) return '';
    let date = new Date(dateString);
    let hours = date.getHours();
    let minutes = date.getMinutes();
    let ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    minutes = minutes < 10 ? '0' + minutes : minutes;
    
    let months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    let month = months[date.getMonth()];
    let day = date.getDate();
    let year = date.getFullYear();
    
    return `${month} ${day}, ${year} ${hours}:${minutes} ${ampm}`;
}

// Load history when tab is clicked
$(document).on('click', '.history-form-tab', function() {
    setTimeout(function() {
        loadPatientHistory();
    }, 100);
});
