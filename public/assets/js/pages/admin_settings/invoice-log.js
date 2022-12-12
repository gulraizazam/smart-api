

var table_url = route('admin.invoices.invoiceDatatable', {id: invoice_id});

var table_columns = [
    {
        field: 'cash_flow',
        title: 'Cash Flow',
        sortable: false,
        width: 80,
    },{
        field: 'cash_amount',
        title: 'Cash Amount',
        sortable: false,
        width: 90,
    },{
        field: 'is_refund',
        title: 'Refund',
        sortable: false,
        width: 80,
        template: function (data) {
            if (data.is_refund) {
                return  data.is_refund;
            }
            return '-';
        }
    },{
        field: 'is_adjustment',
        title: 'Adjustment',
        sortable: false,
        width: 100,
        template: function (data) {
            if (data.is_adjustment) {
                return  data.is_adjustment;
            }
            return '-';
        }
    },{
        field: 'is_tax',
        title: 'Tax',
        sortable: false,
        width: 70,
        template: function (data) {
            if (data.is_tax) {
                return  data.is_tax;
            }
            return '-';
        }
    },{
        field: 'is_cancel',
        title: 'Cancel',
        sortable: false,
        width: 80,
        template: function (data) {
            if (data.is_cancel) {
                return  data.is_cancel;
            }
            return '-';
        }
    },{
        field: 'refund_note',
        title: 'Refund Note',
        sortable: false,
        width: 80,
        template: function (data) {
            if (data.refund_note) {
                return  data.refund_note;
            }
            return '-';
        }
    },{
        field: 'payment_mode_id',
        title: 'Payment Mode',
        sortable: false,
        width: 100,
    },{
        field: 'appointment_type_id',
        title: 'Appointment Type',
        sortable: false,
        width: 100,
    },{
        field: 'package_id',
        title: 'Plan',
        sortable: false,
        width: 80,
        template: function (data) {
            if (data.package_id) {
                return  data.package_id;
            }
            return '-';
        }
    },{
        field: 'location_id',
        title: 'Location',
        sortable: false,
        width: 'auto',
    }, {
        field: 'created_by',
        title: 'Created By',
        width: 'auto',
    }, {
        field: 'updated_by',
        title: 'Updated By',
        sortable: false,
        width: 'auto',
    }, {
        field: 'invoice_id',
        title: 'Invoice Id',
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
    }];
