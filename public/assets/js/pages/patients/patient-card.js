jQuery(document).ready(function () {
    getPatient();
    var result = get_query();
    if (typeof result.tab !== 'undefined') {
        $("." + result.tab+ '-tab').click();
    }
    activeFirstTab(result.tab);
});

function activeFirstTab(tab) {

    if (typeof tab === 'undefined' || tab === 'personal_info') {
        $(".personal-info").addClass("nav-bar-active");
    }
}

function getPatient() {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.patients.getPatient', {id: patientCardID}),
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
   let permission = response.data.permissions
    if (response?.data?.patient) {
        let patient = response.data.patient;
        $("#profile_patient_name").text(patient.name);
        $("#profile_patient_id").text(makePatientId(patient.id));
        $("#patient_id").text(makePatientId(patient.id));
        $("#patient_name").text(patient.name);
        $("#patient_email").text(patient.email);
        if (permission.contact) {
            $("#patient_phone").text(patient.phone);
        } else {
            $("#patient_phone").text("***********");
        }
        $("#patient_gender").text(getGender(patient.gender));
        $(".statuses").addClass("d-none");
        if (patient.active == 1) {
            $("#profile-active").removeClass("d-none");
            $("#active-icon").removeClass("d-none");
        } else {
            $("#profile-inactive").removeClass("d-none");
            $("#inactive-icon").removeClass("d-none");
        }
        if (patient.image_src) {
            let image = asset_url + 'storage/patient_image/' + patient.image_src;
            $("#profile_patient_avatar").css('background-image', "url(" + image + ")");
            $(".patient_profile_image").css('background-image', "url(" + image + ")");
        }
    }
}

function changeProfilePage($this, page_id) {

    let loadScript = true;

    $("#kt_profile_aside").removeClass("d-none");
    $(".main-patient-section").attr("style", "margin-left: 2rem !important");
    $("#page_name").text($this.text());
    $(".change-tab").removeClass("nav-bar-active");
    $this.addClass("nav-bar-active");
    $(".submit-btn").addClass("d-none");
    $(".toolbar-" + page_id).removeClass("d-none");
    $(".content-section").addClass("d-none");
    $("#" + page_id).removeClass("d-none");
    $(".profile-buttons").addClass("d-none");
    $(".toolbar-" + page_id).removeClass("d-none");
    if (page_id != 'personal_info' && page_id != 'change_profile_picture') {
        $("#kt_profile_aside").addClass("d-none");
        $(".main-patient-section").attr("style", "margin-left: 0px !important");
    }
    if (page_id == 'personal_info') {
        $(".personal-info").addClass("nav-bar-active");
        $(".change_profile_pic").removeClass("active");
        $(".persnl_info").removeClass("nav-bar-active");
        $(".personal-info").addClass("active");
        $(".persnl_info").addClass("active");
        $(".profile-buttons").removeClass("d-none");
        $(".submit-btn").addClass("d-none");
        loadScript = false;
    }
    if (page_id == 'change_profile_picture') {
        $(".personal-info").addClass("nav-bar-active");
        $(".change_profile_pic").removeClass("nav-bar-active");
        $(".change_profile_pic").addClass("active");
        $(".persnl_info").removeClass("active");
        $(".personal-info").addClass("active");
        $(".profile-buttons").removeClass("d-none");
        $(".submit-btn").addClass("d-none");
        loadScript = false;
    }
    setQueryStringParameter('tab', page_id);
    loadDataTable(page_id, loadScript);
}

function loadDataTable(page_id, loadScript = true) {
    if (loadScript) {
        /*load script on change tab and then init datatable*/
        let url = asset_url + "assets/js/pages/patients/" + page_id + ".js";
        $.getScript(url);
        setTimeout(function () {
            let className = "." + page_id;
            let datatableExist = $("#" + page_id).find(className).html();
            if (typeof table_url !== 'undefined' && typeof datatableExist !== 'undefined') {
                if (datatableExist.length === 0) {
                    KTPatientDatatable.init("." + page_id);
                } else {
                    patientDatatable[className].search({datatable_reload: 'reload'}, 'search');
                }
            }
        }, 1000);
        $(".change-tab").attr("style", "pointer-events: none !important;color: black");
        $(".horizontal-nav-bar-li").attr("style", "color: white !important");
        setTimeout(function () {
            $(".change-tab").attr("style", "pointer-events: all !important");
        }, 1500);
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
