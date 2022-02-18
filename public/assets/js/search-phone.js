$(document).ready( function () {

    $(".search-phone").keyup(function() {

        if ($(this).val().length < 3) {
            $(".suggesstion-box").hide();
            return false;
        }

        if ($(this).val() != '') {

            let form_type = $(this).parents("form").find('.form_type').val();

            $.ajax({
                type: "GET",
                url: route('admin.users.phone.search'),
                data: {search: $(this).val()},

                success: function (response) {

                    let html = '';
                    let patients = response.data.patients;

                    if (patients.length) {
                        Object.values(patients).forEach(function (patient) {
                            html += '<li onClick="selectPatient(' + patient.phone + ', '+ patient.id+', `'+form_type+'`);">' + patient.name + '</li>'
                        });

                        $(".suggestion-list").html(html);

                        $(".suggesstion-box").show();
                    } else {
                        $(".suggesstion-box").hide();
                    }

                }
            });

        } else {
            $(".suggesstion-box").hide();
        }
    });

    $(document).mouseup(function(e) {
        var container = $(".suggesstion-box");
        if (!container.is(e.target) && container.has(e.target).length === 0) {
            container.hide();
        }
    });

});

function selectPatient(phone, patient_id, form_type) {


    $(".search-phone").val(phone);
    $(".suggesstion-box").hide();

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        type: "POST",
        url: route('admin.leads.load_lead'),
        data: {
            phone: phone,
            service_id: $('#service_id').val(),
            name: $('#' +form_type+ 'full_name').val(),
            gender: $('#' +form_type+ 'gender_id').val(),
            city_id: $('#' +form_type+ '_city_id').val(),
            lead_source_id: $('#' +form_type+ 'lead_source_id').val(),
            lead_status_id: $('#' +form_type+ 'lead_status_id').val(),
            referred_by: $('#' +form_type+ 'referred_by_id').val(),
            id: $('#' +form_type+ 'lead_id').val(),
            patient_id: patient_id,
        },
        success: function (response) {
            if (response.status != '1') {
                $('#' +form_type+ 'service_id').val(response.service_id);
            }
            $('#' +form_type+ 'gender_id').val(response.gender).select2().trigger('change');
            $('#' +form_type+ 'full_name').val(response.name);
            $('#' +form_type+ 'city_id').val(response.city_id).select2().trigger('change');
            $('#' +form_type+ 'lead_source_id').val(response.lead_source_id).select2().trigger('change');
            $('#' +form_type+ 'lead_status_id').val(response.lead_status_id).select2().trigger('change');
            $('#' +form_type+ 'patient_id').val(response.patient_id);
            $('#' +form_type+ 'referred_by_id').val(response.referred_by);
        }
    });

}
