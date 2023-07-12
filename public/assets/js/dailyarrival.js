jQuery(document).ready(function() {
    patientSearch('appointment_patient_id');
})
var loadConvertedReport = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.load_dailyarrival_report'),
        type: "POST",
        data: {
            location_id: $('#location_id').val(),
            doctor_id: $('#doctors_list').val(),
            date_from: $('#appoint_search_created_from').val(),
            date_to: $('#appoint_search_created_to').val(),
            service_id:$('#service_id').val(),
            created_by:$('#created_by').val(),
            apt_type:$('#apt_type').val(),
        },
        success: function(response){
            $('#converted_content').html('');
            $('#converted_content').html(response);
            $("#arrived_patients_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                "ordering": false
            });
            hideSpinner();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            hideSpinner();
            return false;
        }
    });
};


var loadStaffWiseArrivalReport = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.staff_wise_arrival_report'),
        type: "POST",
        data: {
            location_id: $('#location_id').val(),
            doctor_id: $('#doctors_list').val(),
            date_from: $('#appoint_search_created_from').val(),
            date_to: $('#appoint_search_created_to').val(),
            created_by:$('#created_by').val(),
            apt_type:$('#apt_type').val(),
        },
        success: function(response){
            $('#converted_content').html('');
            $('#converted_content').html(response);
            $("#arrived_status_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                "ordering": false
            });
            hideSpinner();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            hideSpinner();
            return false;
        }
    });
};
var loadPatientFollowUpReport = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.patient_follow_up_report'),
        type: "POST",
        data: {
            location_id: $('#location_id').val(),
            date_from: $('#followup_search_created_from').val(),
            date_to: $('#followup_search_created_to').val(),
            patient_id: $('#patient_id').val(),
        },
        success: function(response){
            $('#followup_content').html('');
            $('#followup_content').html(response);
            $("#follow_up_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'pdfHtml5',
                ],
                "ordering": false
            });
            hideSpinner();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            hideSpinner();
            return false;
        }
    });
};
var loadPatientFollowUpMonthReport = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.patient_follow_up_report_monthly'),
        type: "POST",
        data: {
            location_id: $('#location_id').val(),
            date_from: $('#followupmonth_search_created_from').val(),
            date_to: $('#followupmonth_search_created_to').val(),
            patient_id: $('#patient_id').val(),
        },
        success: function(response){
            $('#followupmonthly_content').html('');
            $('#followupmonthly_content').html(response);
            $("#follow_up_monthly_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'pdfHtml5',
                ],
                "ordering": false
            });
            hideSpinner();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            hideSpinner();
            return false;
        }
    });
};
var loadPatientFollowUpReport = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.patient_follow_up_report'),
        type: "POST",
        data: {
            location_id: $('#location_id').val(),
            date_from: $('#followup_search_created_from').val(),
            date_to: $('#followup_search_created_to').val(),
            patient_id: $('#patient_id').val(),
        },
        success: function(response){
            $('#followup_content').html('');
            $('#followup_content').html(response);
            $("#follow_up_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'pdfHtml5',
                ],
                "ordering": false
            });
            hideSpinner();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            hideSpinner();
            return false;
        }
    });
};
var loadPatientFollowUpMonthReport = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.patient_follow_up_report_monthly'),
        type: "POST",
        data: {
            location_id: $('#location_id').val(),
            date_from: $('#followupmonth_search_created_from').val(),
            date_to: $('#followupmonth_search_created_to').val(),
            patient_id: $('#patient_id').val(),
        },
        success: function(response){
            $('#followupmonthly_content').html('');
            $('#followupmonthly_content').html(response);
            $("#follow_up_monthly_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'pdfHtml5',
                ],
                "ordering": false
            });
            hideSpinner();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            hideSpinner();
            return false;
        }
    });
};
function patientSearch(search_id = 'patient_id',flag=1) {
   
    $("." + search_id).on("keyup",function() {
        $(".suggestion-list").html('<li>Searching...</li>');
        $(".suggesstion-box").show();
        if ($(this).val().length < 2) {
            $(".suggesstion-box").hide();
            return false;
        }
        var that = $(this);
        if ($(this).val() != '') {
            setTimeout(function(){
                $.ajax({
                    type: "GET",
                    url: route('admin.users.getpatient.id'),
                    dataType: 'json',
                    data: {search: that.val()},
                    success: function (response) {
                        let html = '';
                        $(".suggestion-list").html(html);
                        let patients = response.data.patients;
                        if (patients.length) {
                            patients.forEach(function (patient) {
                                html += '<li onClick="selectUser(`' + patient.name + '`, `' + patient.id + '`, `'+ search_id+'`, `'+ flag+'`);">' + patient.name +' - '+ makePatientId(patient.id) +'</li>'
                            });
                            $(".suggestion-list").html(html);
                            $(".suggesstion-box").show();
                        } else {
                            $(".suggesstion-box").hide();
                        }
                    }
                });
            },1000);
        } else {
            $(".suggesstion-box").hide();
        }
    });
    return false;
}
function selectUser(name, user_id,  search_id) {
    $("." + search_id).parent('div').find('.search_field').val(user_id).change();
    $("#patient_id").val(user_id);
    $("." + search_id).val(name);
    $(".suggesstion-box").hide();
    $("." + search_id).focus();
}