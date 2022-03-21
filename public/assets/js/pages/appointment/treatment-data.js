
var treatmentDoctorListener = function (doctorId) {
    setQueryStringParameter('doctor_id', doctorId);
}

let loadMachine = function(locationId) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.center_machines', {
            location_id: locationId,
        }),
        type: 'GET',
        data: {
            location_id: locationId
        },
        cache: false,
        success: function(response) {
            if(response.status) {

                console.log(response.data.dropdown)
                let dropdowns =  response.data.dropdown;
                let dropdown_options =  '<option value="">Select a Doctor</option>';

                Object.entries(dropdowns).forEach(function (dropdown) {
                    dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                });

                let result = get_query();

                $('#treatment_resource_filter').html(dropdown_options);

                if (typeof result.machine_id !== "undefined") {
                    $("#treatment_resource_filter").val(result.machine_id).change();
                }

                $('.select2').select2({ width: '100%' });
            } else {
                resetDoctors();
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            resetDoctors();
        }
    });
}

let machineListener = function (machineId) {

    if (machineId != '' && machineId != null) {

        if (typeof calendar !== "undefined") { /*if already initiate then destroy first*/
            calendar.destroy();
        }
        var result = get_query();

        if ($("#treatment_city_filter").val() !== ""
            && $("#treatment_location_filter").val() !== ""
            && $("#treatment_doctor_filter").val() !== ""
            && $("#treatment_resource_filter").val() !== ""
            && typeof result.tab !== 'undefined' && result.tab == 'treatment') {

            window.eventData = {}
            window.eventData.city_id = $("#treatment_city_filter").val()
            window.eventData.location_id = $("#treatment_location_filter").val()
            window.eventData.doctor_id = $("#treatment_doctor_filter").val();
            window.eventData.id = null;
            window.eventData.firstTime = true;

            TreatmentCalendar.init();
        }

    }

    setQueryStringParameter('machine_id', machineId);

}
