
var table_url = route('admin.patients.vouchersDatatable', {id: patientCardID});

var table_columns = [
    {
        field: 'name',
        title: 'Voucher',
        width: 90,
    },{
        field: 'amount',
        title: 'Amount',
        width: 'auto',
    }
    
    ,{
        field: 'startDate',
        title: 'Voucher Start Date',
        width: 'auto',
    },{
        field: 'endDate',
        title: 'Voucher End Date',
        width: 'auto',
    },{
        field: 'service',
        title: 'Services',
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
    }];




