jQuery(document).ready(function() {
    var result = get_query();

    if (typeof result.tab !== 'undefined') {
        $("." + result.tab+ '-tab').click();
    }

    if (typeof result.city_id !== "undefined"
        && typeof result.location_id !== "undefined"
        && typeof result.doctor_id !== "undefined"
        && typeof result.tab !== 'undefined' && result.tab == 'consultancy') {

        loadDoctors(result.location_id, result.tab);
        //ConsultancyDoctorListener(result.doctor_id);

        setTimeout( function () {
            $("#consultancy_city_filter").val(result.city_id).change();
            $("#consultancy_centre_filter").val(result.location_id).change();
            $("#consultancy_doctor_filter").val(result.doctor_id).change();
        }, 400);
    }
});

function toggleSection($this, $class) {

    setQueryStringParameter('city_id');
    setQueryStringParameter('location_id');
    setQueryStringParameter('doctor_id');

    if ($class == 'appointment') {
        $(".export-appointments").show();
        reInitTable();
    } else {
        $(".export-appointments").hide();
    }

    $(".appointment").addClass("d-none");
    $("." + $class + "-section").removeClass("d-none");

    $(".change-tab").removeClass("nav-bar-active");
    $this.addClass("nav-bar-active");

    setQueryStringParameter('tab', $class);

    $(".change-label").text($this.text())
}

let ConsultancyDoctorListener = function (doctorId) {

    if (doctorId != '' && doctorId != null) {
        $("#add_consulting").attr('href',route('admin.appointments.consulting.create')+ "?" + window.location.href.split("?")[1]);
        $("#calander_block").css("display",'block');
        //calendar.fullCalendar('destroy');
        ConsultancyCalendar.init();
    } else {
        calenderHide();
        $('#calendar').val('');
    }

    setQueryStringParameter('doctor_id', doctorId);
}

function calenderHide() {

}
