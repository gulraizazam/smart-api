
var table_url = route('admin.packagesadvances.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 'auto',
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    }, {
        field: 'patient_id',
        title: 'Patient ID',
        sortable: false,
        width: 300,
    },{
        field: 'patient',
        title: 'Patient',
        sortable: false,
        width: 'auto',
    },{
        field: 'phone',
        title: 'Phone',
        sortable: false,
        width: 'auto',
    },{
        field: 'transaction_type',
        title: 'Transaction type',
        sortable: false,
        width: 'auto',
    },{
        field: 'cash_in',
        title: 'Cash In',
        sortable: false,
        width: 'auto',
    },{
        field: 'cash_out',
        title: 'Cash Out',
        sortable: false,
        width: 'auto',
    },{
        field: 'balance',
        title: 'Balance',
        sortable: false,
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
    }];

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            id: $("#search_patient_id").val(),
            patient_id: $("#search_patient").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
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
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        let patients = filter_values.patient;

        $("#search_patient_id").val(active_filters.id);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);

        let patient_options = "";
        Object.values(patients).forEach( function (value) {
            patient_options += '<option value="'+value.id+'">'+value.name+'-'+value.phone+'</option>';
        });
        $("#search_patient").html(active_filters.created_to);

    } catch (error) {
        showException(error);
    }
}
