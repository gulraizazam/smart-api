jQuery(document).ready(function () {
    getPatient();
    getTabCounts();
    var result = get_query();
    if (typeof result.tab !== 'undefined') {
        $("." + result.tab+ '-tab').click();
    }
    activeFirstTab(result.tab);
});

function getTabCounts() {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.patients.tabCounts', {id: patientCardID}),
        type: "GET",
        cache: false,
        success: function (response) {
            if (response.status && response.data) {
                var counts = response.data;
                $('#tab-count-appointments').text('(' + (counts.appointments || 0) + ')');
                $('#tab-count-vouchers').text('(' + (counts.vouchers || 0) + ')');
                $('#tab-count-documents').text('(' + (counts.documents || 0) + ')');
                $('#tab-count-plans').text('(' + (counts.plans || 0) + ')');
                $('#tab-count-invoices').text('(' + (counts.invoices || 0) + ')');
                $('#tab-count-refunds').text('(' + (counts.refunds || 0) + ')');
                $('#tab-count-activity').text('(' + (counts.activity_logs || 0) + ')');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.log('Error fetching tab counts:', thrownError);
        }
    });
}

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
        
        // Load patient notes after patient data is loaded
        if (typeof loadPatientNotes === 'function') {
            loadPatientNotes();
        }
        $("#patient_email").text(patient.email);
        if (permission.contact) {
            $("#patient_phone").text(patient.phone);
        } else {
            $("#patient_phone").text("***********");
        }
        $("#patient_gender").text(getGender(patient.gender));
        
        // Set membership info on profile personal info section
        if (response?.data?.membership) {
            let membership = response.data.membership;
            $("#patient_membership").text(membership.type || 'N/A');
            $("#patient_membership_expiry").text(membership.end_date ? formatDate(membership.end_date, 'MMM D, YYYY') : '-');
            $("#membership_type_row").show();
            $("#membership_expiry_row").show();
        } else {
            $("#membership_type_row").hide();
            $("#membership_expiry_row").hide();
        }
        
        // Set membership info on left card
        if (response?.data?.membership) {
            let membership = response.data.membership;
            let membershipLabel = $("#profile_membership");
            let membershipContainer = $("#profile_membership_container");
            
            // Set color based on membership type
            membershipLabel.removeClass('label-warning label-primary label-secondary');
            if (membership.type.toLowerCase().includes('gold')) {
                membershipLabel.addClass('label-warning'); // Gold color
            } else if (membership.type.toLowerCase().includes('student')) {
                membershipLabel.addClass('label-primary'); // Blue color
            } else {
                membershipLabel.addClass('label-secondary'); // Default gray
            }
            
            // Build membership text with code and status
            let membershipText = membership.type + ' (' + membership.code + ')';
            membershipLabel.text(membershipText);
            
            // Show active/expired status
            let statusBadge = membership.is_active 
                ? '<span class="label label-light-success label-inline font-weight-bold label-sm ml-2">Active</span>'
                : '<span class="label label-light-danger label-inline font-weight-bold label-sm ml-2">Expired</span>';
            membershipContainer.html('<span class="label label-inline font-weight-bold label-lg ' + 
                (membership.type.toLowerCase().includes('gold') ? 'label-warning' : 
                 membership.type.toLowerCase().includes('student') ? 'label-primary' : 'label-secondary') + 
                '" id="profile_membership">' + membershipText + '</span>' + statusBadge);
            membershipContainer.show();
        } else {
            $("#profile_membership_container").hide();
        }
        
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
    
    // Show/hide Edit Patient button based on active tab (only visible on profile tabs)
    if (page_id === 'personal_info' || page_id === 'change_profile_picture') {
        $(".profile-edit-btn").removeClass('d-none');
    } else {
        $(".profile-edit-btn").addClass('d-none');
    }
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
