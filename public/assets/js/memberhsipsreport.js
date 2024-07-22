
$('#date_range_membership').daterangepicker({
    locale: {},
    ranges: {
        'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
        'Last 7 Days': [moment().subtract(6, 'days'), moment().subtract(1, 'days')],
        'Last 30 Days': [moment().subtract(29, 'days'), moment().subtract(1, 'days')],
        'This Month': [moment().startOf('month'), moment().endOf('month')],
        'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        'This Year': [moment().startOf('year'), moment().endOf('year')],
        'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
    },
    autoUpdateInput: false
}, function (start, end, label) {
    $('#date_range_membership').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
});


$('#date_range_membership').val('');
var loadMembershipReport = function (that) {
    if (typeof that.prop("disabled") !== 'undefined' && that.prop("disabled") === true) {
        return false;
    }
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.load_membership_report'),
        type: "POST",
        data: {
            location_id: $('#location_id').val(),
            membership_type_id: $('#membership_type').val(),
            date_range: $('#date_range_membership').val(),
        },
        success: function (response) {
            $('#membership_report_content').html('');
            $('#membership_report_content').html(response);
            $("#memberships_table").DataTable({
                dom: 'Bfrtip<"bottom"l>',
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

