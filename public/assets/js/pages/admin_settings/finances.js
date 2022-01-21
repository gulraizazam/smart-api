
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
        template: function (data) {
            let cityName = '';
            if (typeof data.location.city !== 'undefined') {
                cityName = data.location.city.name + '-';
            }
           return cityName + data.location.name;
        }
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
            name: $("#search_name").val(),
            resource_type_id: $("#search_resource_type_id").val(),
            location_id: $("#search_location_id").val(),
            machine_type_id: $("#search_machine_type_id").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            status: $("#search_status").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            name: '',
            resource_type_id: '',
            location_id: '',
            machine_type_id: '',
            created_from: '',
            created_to: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {
    try {

        let status = filter_values.status;
        let locations = filter_values.locations;
        let resource_types = filter_values.resource_types;
        let machines = filter_values.machines;

        let status_options = '<option value="">All</option>';
        let resource_options = '<option value="">All</option>';
        let location_options = '<option value="">All</option>';
        let machines_options = '<option value="">All</option>';

        Object.entries(status).forEach(function (value, index) {
            status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(locations).forEach(function (value, index) {
            location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(resource_types).forEach(function (value, index) {
            resource_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(machines).forEach(function (value, index) {
            machines_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });


        $("#search_status").html(status_options);
        $("#search_resource_type_id").html(resource_options);
        $("#search_location_id").html(location_options);
        $("#search_machine_type_id").html(machines_options);

        $("#search_name").val(active_filters.name);
        $("#search_resource_type_id").val(active_filters.resource_type_id);
        $("#search_location_id").val(active_filters.location_id);
        $("#search_machine_type_id").val(active_filters.machine_type_id);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);
        $("#search_status").val(active_filters.status);

        hideShowAdvanceFilters(active_filters);

    } catch (error) {
        showException(error);
    }
}
