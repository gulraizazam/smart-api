
var table_url = route('admin.packagesadvances.datatable');

var table_columns = [
    {
        field: 'patient_id',
        title: 'Patient ID',
        sortable: false,
        width: 70,
    },{
        field: 'patient',
        title: 'Patient',
        sortable: false,
        width: 70,
    },{
        field: 'phone',
        title: 'Phone',
        sortable: false,
        width: 70,
    },{
        field: 'transtype',
        title: 'Transaction type',
        sortable: false,
        width: 'auto',
    },{
        field: 'cash_in',
        title: 'Cash In',
        sortable: false,
        width: 70,
    },{
        field: 'cash_out',
        title: 'Cash Out',
        sortable: false,
        width: 80,
    },{
        field: 'balance',
        title: 'Balance',
        sortable: false,
        width: 70,
    },{
        field: 'created_at',
        title: 'Created at',
        width: 150,
    }];

function applyFilters(datatable) {
    $('#apply-filters').on('click', function() {
        let filters =  {
            delete: '',
            id: $("#add_patient_id").val(),
            patient_id: $("#add_patient_id").val(),
            created_at: $("#date_range").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            id: '',
            patient_id: '',
            created_at: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {
    try {
        let patients = filter_values.patient;
        $("#search_patient_id").val(active_filters.id);
        $("#date_range").val(active_filters.created_at);

        let patient_options = "";
        Object.values(patients).forEach( function (value) {
            patient_options += '<option value="'+value.id+'">'+value.name+'-'+value.phone+'</option>';
        });
        $("#search_patient").html(active_filters.created_to);
    } catch (error) {
        showException(error);
    }
}

function resetCustomFilters() {
    $('.patient_search_id').val(null).trigger('change');
    $('#add_patient_id').val(null).trigger('change');
    $(".filter-field").val('');
    $('.select2').val(null).trigger('change');
}


jQuery(document).ready(function () {
    /*To get patient on search*/

    $(document).on("click", ".croxcli", function () {
        $('.search_field').val('').change();
        $('.patient_search_id').val(null).trigger('change');
    });
    patientSearch('patient_search_id');
    $("#date_range").val("");
})
