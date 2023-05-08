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