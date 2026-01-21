jQuery(document).ready(function() {

    

    $("#Add_comment").click(function () {

        if ($('#consultancy_comment').val() !== '') {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'get',
                url: route('admin.appointments.storecomment'),
                data: {
                    'comment': $('#consultancy_comment').val(),
                    'appointment_id': $('#comment_appointment_id').val(),
                },
                success: function (data) {
                    $('#commentsection').prepend(commentData(data.username, data.appointmentCommentDate, data.appointment.comment));
                },

            });
        } else {
            toastr.error("Please fill out the comment field");
        }
        $('#cment')[0].reset();
    });

    $("#create_consultancy_service").change( function () {
        loadLead(patient);
    });

    // Patient search now uses Select2 with optimized API (same as referred by field)
    // Initialized in referred-by-patient-search.js

});

var loadScheduledTime = '<input id="edit_scheduled_time" readonly="true" name="scheduled_time" class="form-control" type="text" class="required" placeholder="Schedule Time">';

let doctorListener = function (doctorId) {

    var scheduled_date = $('#edit_scheduled_date').val();

    if (
        (doctorId != '' && doctorId != null) &&
        (scheduled_date != '' && scheduled_date != null)
    ) {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_doctor_rota'),
            type: 'POST',
            data: {
                location_id: $('#edit_location_id').val(),
                doctor_id: doctorId,
                scheduled_date: scheduled_date,
                appointment_id: $('#appointment_id').val(),
                resourceRotaDayID: $('#resourceRotaDayID').val(),
                form: 'EditFormValidation',
                idPrefix: 'consultancty_'
            },
            cache: false,
            success: function(response) {

                if(response.status) {
                    if(
                        (response.resource_has_rota_day.start_time != '' && response.resource_has_rota_day.start_time != null) &&
                        (response.resource_has_rota_day.end_time != '' && response.resource_has_rota_day.end_time != null)
                    ) {
                        // Set the scheduled time value
                        $('#edit_scheduled_time').val(response.selected);
                        $('#resourceRotaDayID').val(response.resource_has_rota_day.id);
                        $('#start_time').val(response.resource_has_rota_day.start_time);
                        $('#end_time').val(response.resource_has_rota_day.end_time);
                    } else {
                        toastr.error('Doctor does not have rota for selected date');
                    }
                } else {
                    toastr.error('Unable to load doctor rota');
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
               // resetScheduledTime();
            }
        });
    } else {
       // resetScheduledTime();
    }
}
