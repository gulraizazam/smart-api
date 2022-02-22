jQuery(document).ready(function () {
    getPatient();
});


function getPatient() {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.patients.getPatient', {id: patient_id}),
        type: "GET",
        cache: false,
        success: function (response) {
            setPatientData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setPatientData(response) {

    let patient = response.data.patient;

    $("#profile_patient_name").text(patient.name);
    $("#profile_patient_id").text(makePatientId(patient.id));
    $("#patient_id").text(makePatientId(patient.id));
    $("#patient_name").text(patient.name);
    $("#patient_email").text(patient.email);
    $("#patient_phone").text(patient.phone);
    $("#patient_gender").text(getGender(patient.gender));

    $(".statuses").addClass("d-none");
    if (patient.active == 1) {
        $("#profile-active").removeClass("d-none");
        $("#active-icon").removeClass("d-none");
    } else {
        $("#profile-inactive").removeClass("d-none");
        $("#inactive-icon").removeClass("d-none");
    }

    let image = asset_url+'storage/patient_image/'+patient.image_src;

    $("#profile_patient_avatar").css('background-image', "url("+image+")");
    $(".patient_profile_image").css('background-image', "url("+image+")");

}

function changeProfilePage($this, page_id) {

    $(".change-tab").removeClass("active");
    $this.addClass("active");

    $(".content-section").addClass("d-none");

    $("#" + page_id).removeClass("d-none");
    $(".profile-buttons").addClass("d-none");

    if (page_id == 'personal_info') {
        $(".personal-info").addClass("active");
        $(".profile-buttons").removeClass("d-none");
    }
}

function savePatientImage() {

    let form_id = 'save_profile_image';
    let form = document.getElementById(form_id);

    submitFileForm($(form).attr('action'), $(form).attr('method'), form_id, function (response) {
        if (response.status) {
            $("#profile_patient_avatar").css('background-image', "url("+response.data.image+")");
            toastr.success(response.message);
        } else {
            toastr.error(response.message);
        }
    }, true);
}
