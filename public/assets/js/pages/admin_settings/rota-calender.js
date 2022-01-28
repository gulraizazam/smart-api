let calender_url = route('admin.resourcerotas.events', {id: calender_id});

function clickEvent(info, jsEvent, view) {

    let event = info.event.extendedProps;
    let eventApi = info.event._def;
    $('#resource_days_id').val(eventApi.publicId);
    $('#resource_days_date').val(info.event.start.toISOString().split('T')[0]);
    $('#resource_days_start_time').val(event.start_time);
    $('#resource_days_end_time').val(event.end_time);

    $('#resource_days_start_time_break').val(event.start_off);
    $('#resource_days_end_time_break').val(event.end_off);

    if($('#resource_days_start_time').val())
    {
        $('#dayOperation :input').removeAttr('disabled');
        $("#dayElement").prop( "checked", false );
    }
    else{
        $("#estado_cat").prop( "checked", true );
        $('#dayOperation :input').attr('disabled', true);
        $("#dayElement").prop( "checked", true );
    }

    isLeave($("#dayElement"));

    if(event.checked == 1){
        $('#backdateenvent').hide();
        $('#ajax_resourcerotas_calenderedit').modal('show');
    } else {
        $('#backdateenvent').show();
    }
}

function getCalenderData() {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.resourcerotas.calender', {id: calender_id}),
        type: "GET",
        cache: false,
        success: function (response) {

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(AddValidation);
        }
    });
}

function isLeave($this) {

    let elem = $("#modal_update_calender").find(".timepicker");
    if ($this.is(":checked")) {
        elem.prop("disabled", true).addClass('disable-filed');
    } else {
        elem.prop("disabled", false).removeClass('disable-filed');
    }
}

$(document).ready(function () {
    //getCalenderData();
})
