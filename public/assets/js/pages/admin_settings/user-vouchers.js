
var table_url = route('admin.user-vouchers.datatable');

var table_columns = [
    {
        field: 'patient_id',
        title: 'Patient ID',
        sortable: false,
        width: 150,
    }, {
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 200,
    }, {
        field: 'voucher_type',
        title: 'Voucher Type',
        sortable: false,
        width: 200,
    }, {
        field: 'amount',
        title: 'Amount',
        sortable: false,
        width: 150,
        template: function (data) {
            return data.amount ? parseFloat(data.amount).toFixed(2) : '0.00';
        }
    }, {
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
    }];
