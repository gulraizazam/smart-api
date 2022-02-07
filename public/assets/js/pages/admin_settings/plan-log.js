
var table_url = route('admin.packages.planDatatable', {id: plane_id});

var table_columns = [
    {
        field: 'cash_flow',
        title: 'Cash Flow',
        sortable: false,
        width: 300,
    },{
        field: 'cash_amount',
        title: 'Cash Amount',
        sortable: false,
        width: 'auto',
    },{
        field: 'is_refund',
        title: 'Refund',
        sortable: false,
        width: 'auto',
    },{
        field: 'is_adjustment',
        title: 'Adjustment',
        sortable: false,
        width: 'auto',
    },{
        field: 'is_tax',
        title: 'Tax',
        sortable: false,
        width: 'auto',
    },{
        field: 'is_cancel',
        title: 'Cancel',
        sortable: false,
        width: 'auto',
    },{
        field: 'refund_note',
        title: 'Refund Note',
        sortable: false,
        width: 'auto',
    },{
        field: 'delete',
        title: 'DELETE',
        sortable: false,
        width: 'auto',
    },{
        field: 'payment_mode_id',
        title: 'Payment Mode',
        sortable: false,
        width: 'auto',
    },{
        field: 'appointment_type_id',
        title: 'Appointment Type',
        sortable: false,
        width: 'auto',
    },{
        field: 'location_id',
        title: 'Location',
        sortable: false,
        width: 'auto',
    },{
        field: 'created_by',
        title: 'Created By',
        width: 'auto',
    }, {
        field: 'updated_by',
        title: 'Updated By',
        sortable: false,
        width: 'auto',
    }, {
        field: 'package_id',
        title: 'Plan',
        sortable: false,
        width: 'auto',
    }, {
        field: 'invoice_id',
        title: 'Invoice Id',
        sortable: false,
        width: 'auto',
    },{
        field: 'created_at_show',
        title: 'CREATED AT SHOWN',
        sortable: false,
        width: 'auto',
    },{
        field: 'updated_at_show',
        title: 'UPDATED AT SHOWN',
        sortable: false,
        width: 'auto',
    }, {
        field: 'created_at',
        title: 'Created At',
        sortable: false,
        width: 'auto',
    }, {
        field: 'updated_at',
        title: 'Updated At',
        sortable: false,
        width: 'auto',
    }, {
        field: 'deleted_at',
        title: 'DELETED AT',
        sortable: false,
        width: 'auto',
    },{
        field: 'detail',
        title: 'DETAIL',
        sortable: false,
        width: 'auto',
    }];
