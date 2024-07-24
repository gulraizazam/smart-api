
var table_url = route('admin.reports.load_membership_report');
if (typeof lead_type !== 'undefined' && lead_type != '') {
    table_url = route('admin.reports.load_membership_report', { type: lead_type });
}
var table_columns = [
    {
        field: 'user_id',
        title: 'Patient ID',
        sortable: false,
        width: 60,
    }, {
        field: 'user_name',
        title: 'Patient Name',
        sortable: false,
        width: 60,
    }, {
        field: 'location',
        title: 'Location',
        sortable: false,
        width: 90,

    }, {
        field: 'membership_code',
        title: 'Membership Code',
        sortable: false,
        width: 60,

    }, {
        field: 'membership_type',
        title: 'Membership Type',
        sortable: false,
        width: 70,

    }, {
        field: 'service_status',
        title: 'Service Status',
        sortable: false,
        width: 70,
    }];

function applyFilters(datatable) {
    $('#apply_filters').on('click', function () {
        let filters = {
            delete: '',
            location_id: $('#location_id').val(),
            membership_type_id: $('#membership_type').val(),
            date_range: $('#date_range_membership').val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });
}
$("#csv-leads").on("click", function () {

    let location_id = $('#location_id').val();
    let membership_type_id = $('#membership_type').val();
    let date_range = $('#date_range_membership').val();
    let url = $(this).data('href');
    let downloadUrl = `${url}?location_id=${location_id}&membership_type_id=${membership_type_id}&date_range=${date_range}`;

    // Redirect to the download URL
    window.location.href = downloadUrl;
});
$('#date_range_membership').daterangepicker({

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

