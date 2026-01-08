jQuery(document).ready(function() {
    patientSearch('appointment_patient_id');
    let patientId = $("#patient_id_url").val();
    let reportType = $("#report_type_url").val();
    $(".appointment_patient_id").val(patientId).trigger("keyup");
    $("#report_types").val(reportType).trigger("change");

    if(patientId !== ''){
$("#date_range").val('');
    }

    setTimeout(function() {
        $('.suggestion-list li:first').click();
    }, 2000);
})
$('#date_range_arrival').daterangepicker({
    locale: {
    },
    ranges: {
        'Today': [moment(), moment()],
        'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
        'Last 7 Days': [moment().subtract(6, 'days'), moment().subtract(1, 'days')],
        'Last 30 Days': [moment().subtract(29, 'days'), moment().subtract(1, 'days')],
        'This Month': [moment().startOf('month'), moment().endOf('month')],
        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        'This Year': [moment().startOf('year'), moment().endOf('year')],
        'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
    },
    startDate: moment().startOf('month'),
    endDate: moment().subtract(1, 'days')
}).val();
$('#date_range_incentive').daterangepicker({
    locale: {
    },
    ranges: {
        // 'Today': [moment(), moment()],
        // 'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
        // 'Last 7 Days': [moment().subtract(6, 'days'), moment().subtract(1, 'days')],
        // 'Last 30 Days': [moment().subtract(29, 'days'), moment().subtract(1, 'days')],
        'This Month': [moment().startOf('month'), moment().endOf('month')],
        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        // 'This Year': [moment().startOf('year'), moment().endOf('year')],
        // 'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
    },
    startDate: moment().startOf('month'),
    endDate: moment().subtract(1, 'days')
}).val();
$('#date_range_appointments').daterangepicker({
    locale: {
    },
    ranges: {
         'Today': [moment(), moment()],
         'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
         'Last 7 Days': [moment().subtract(6, 'days'), moment().subtract(1, 'days')],
         'Last 30 Days': [moment().subtract(29, 'days'), moment().subtract(1, 'days')],
        'This Month': [moment().startOf('month'), moment().endOf('month')],
        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        // 'This Year': [moment().startOf('year'), moment().endOf('year')],
        // 'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
    },
    startDate: moment().startOf('month'),
    endDate: moment().subtract(1, 'days')
}).val();
$('#date_range_inv').daterangepicker({
    locale: {
    },
    ranges: {


        'This Month': [moment().startOf('month'), moment().endOf('month')],
        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],

    },
    startDate: moment().startOf('month'),
    endDate: moment().endOf('month')
}).val();
$('#date_range_ratings').daterangepicker({
    locale: {
    },
    ranges: {

        'Today': [moment(), moment()],
        'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
        'Last 7 Days': [moment().subtract(6, 'days'), moment().subtract(1, 'days')],
        'Last 30 Days': [moment().subtract(29, 'days'), moment().subtract(1, 'days')],
       'This Month': [moment().startOf('month'), moment().endOf('month')],
       'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
         'This Year': [moment().startOf('year'), moment().endOf('year')],
    },
    startDate: moment().subtract(1, 'month').startOf('month'),
    endDate: moment().subtract(1, 'month').endOf('month')
}).val();
$('#date_range_patients').daterangepicker({
    locale: {
    },

    startDate: moment().startOf('year'),
    endDate: moment().endOf('year')
}).val();
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
            date_range: $('#date_range_arrival').val(),
            created_by:$('#created_by').val(),
            report_type:$('#report_type').val(),
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

var loadIncentiveReport  = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.incentive_report'),
        type: "POST",
        data: {

            doctor_id: $('#doctor_id').val(),
            date_range: $('#date_range_incentive').val(),
            centre_id: $('#centre_id').val(),

        },
        success: function(response){
            $('#incentive_content').html('');
            $('#incentive_content').html(response);
            // $("#incentive_table").DataTable({
            //     dom: 'Bfrtip',
            //     buttons: [
            //         'excelHtml5',
            //         'csvHtml5',
            //         'pdfHtml5',
            //     ],
            //     "ordering": false
            // });
            hideSpinner();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            hideSpinner();
            return false;
        }
    });
};
var loadAppointmentsReport  = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.appointments_report'),
        type: "POST",
        data: {

            time: $('#time_id').val(),
            date_range: $('#date_range_appointments').val(),
            centre_id: $('#centre_id').val(),
            created_by:$("#created_by_id").val(),

        },
        success: function(response){
            $('#apt_content').html('');
            $('#apt_content').html(response);
            $("#appointments_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                "ordering": false,
                "pageLength": 50
            });
            // $("#incentive_table").DataTable({
            //     dom: 'Bfrtip',
            //     buttons: [
            //         'excelHtml5',
            //         'csvHtml5',
            //         'pdfHtml5',
            //     ],
            //     "ordering": false
            // });
            hideSpinner();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            hideSpinner();
            return false;
        }
    });
};
var loadInventoryReport  = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    showSpinner();

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.load_inventory_report'),
        type: "POST",
        data: {

           report_type:$("#report_type").val(),
            date_range: $('#date_range_inv').val(),
            centre_id: $('#centre_id').val(),
            doctor_id:$("#doctor_id_filter").val(),
            brand_id:$("#brand_id").val(),


        },
        success: function(response){
            $('#inv_content').html('');
            $('#inv_content').html(response);
            $("#inv_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                "ordering": false,
                "pageLength": 50
            });
            $("#doc_sales_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                "ordering": false,
                "pageLength": 50
            });
            // $("#incentive_table").DataTable({
            //     dom: 'Bfrtip',
            //     buttons: [
            //         'excelHtml5',
            //         'csvHtml5',
            //         'pdfHtml5',
            //     ],
            //     "ordering": false
            // });
            hideSpinner();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            hideSpinner();
            return false;
        }
    });
};
var loadFeedbackReport  = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    // if($("#centre_id").val() == ""){
    //     alert("Please select a centre");
    //     return false;
    // }
    showSpinner();

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.load_feedback_report'),
        type: "POST",
        data: {


            date_range: $('#date_range_ratings').val(),
            centre_id: $('#centre_id').val(),
            doctor_id:$("#feedback_doctor_id_filter").val(),
            service_id:$("#service_id_filter").val(),


        },
        success: function(response){
            $('#feedback_content').html('');
            $('#feedback_content').html(response);
            $("#feedback_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
               searching: false,     // Disable search box
                paging: false,        // Disable pagination
                info: false
            });
            $("#doc_sales_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                "ordering": false,
                "pageLength": 50
            });
            // $("#incentive_table").DataTable({
            //     dom: 'Bfrtip',
            //     buttons: [
            //         'excelHtml5',
            //         'csvHtml5',
            //         'pdfHtml5',
            //     ],
            //     "ordering": false
            // });
            hideSpinner();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            hideSpinner();
            return false;
        }
    });
};
var loadFutureTreatmentsReport  = function (that) {


    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }

    showSpinner();

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.load_future_treatments_report'),
        type: "POST",
        data: {


            date_range: $('#date_range_patients').val(),
            centre_id: $('#centre_id').val(),
            patient_id:$("#order_patient_search").val(),
            membership_id:$("#membership_type").val(),


        },
        success: function(response){
            $('#patients_content').html('');
            $('#patients_content').html(response);
            $("#patients_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                 searching: false,     // Disable search box
                paging: false,        // Disable pagination
                info: false,
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
var loadUpsellingReport  = function (that) {


    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }

    showSpinner();

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.load_upselling_report'),
        type: "POST",
        data: {


            date_range: $('#date_range_ratings').val(),
            centre_id: $('#centre_id').val(),



        },
        success: function(response){
            $('#upselling_content').html('');
            $('#upselling_content').html(response);
            $("#upselling_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                 searching: false,     // Disable search box
                paging: false,        // Disable pagination
                info: false,
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
var loadConsultantRevenueReport  = function (that) {


    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }

    showSpinner();

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.load_consultant_revenue_report'),
        type: "POST",
        data: {


            date_range: $('#date_range_ratings').val(),
            centre_id: $('#centre_id').val(),



        },
        success: function(response){
            $('#consultant_revenue_content').html('');
            $('#consultant_revenue_content').html(response);
            $("#consultant_revenue_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                 searching: false,     // Disable search box
                paging: false,        // Disable pagination
                info: false,
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
            report_type: $('#report_types').val(),
            location_id: $('#location_id').val(),
            date_range: $('#date_range').val(),
            patient_id: $('#patient_id').val(),
        },
        success: function(response){
            $('#followup_content').html('');
            $('#followup_content').html(response);
            $(".follow_up_table").DataTable({
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
function getEmployees(locationId){

    let url = route('admin.get-doctors');

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "POST",
        cache: false,
        data: {location_id: locationId},
        success: function (response) {

            if(response.status==false){
                toastr.error(response.message);
            }else{

                 let employees = response.users;
                let emp_options = '<option value="">Select Doctor</option>';
                Object.entries(employees).forEach(function (value, index) {
                    emp_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
                });
                $("#doctor_id_filter").html(emp_options);
            }

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}
function getEmployeesForSales(locationId){

    let url = route('admin.get-doctors-for-sales');

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "POST",
        cache: false,
        data: {location_id: locationId},
        success: function (response) {

            if(response.status==false){
                toastr.error(response.message);
            }else{

                 let employees = response.users;
                let emp_options = '<option value="">Select Doctor</option>';
                Object.entries(employees).forEach(function (value, index) {
                    emp_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
                });
                $("#doctor_id_filter").html(emp_options);
            }

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}
function getCentreDoctors(locationId){

    let url = route('admin.get-centre-doctors');

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "POST",
        cache: false,
        data: {location_id: locationId},
        success: function (response) {

            if(response.status==false){
                toastr.error(response.message);
            }else{

                 let employees = response.users;
                let emp_options = '<option value="">Select Doctor</option>';
                Object.entries(employees).forEach(function (value, index) {
                    emp_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
                });
                $("#feedback_doctor_id_filter").html(emp_options);
            }

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}
function patientSearch(search_id = 'patient_id',flag=1) {

    // Unbind previous event handlers to prevent multiple bindings
    $("." + search_id).off("keyup");
    
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
function hideDoctor()
{
    var rType = $("#report_type").val();
    if(rType == "doctor_sales_report"){
        $("#doc_dropdown").show();
    }else{
        $("#doc_dropdown").hide();
    }
}
