
var table_url = route('admin.vouchers.datatable');

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
    }, {
        field: 'Actions',
        title: 'Actions',
        sortable: false,
        width: 150,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            var actions = '';

            // View button (empty for now)
            actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" title="View">' +
                '<i class="fa fa-eye"></i>' +
                '</a>';

            // Edit button - check if editable
            if (data.can_edit) {
                actions += '<a href="javascript:void(0);" onclick="editVoucher(' + data.id + ');" class="btn btn-sm btn-clean btn-icon mr-2" title="Edit">' +
                    '<i class="fa fa-edit"></i>' +
                    '</a>';
            } else {
                actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2 disabled" title="Cannot edit - voucher is used in services" style="opacity: 0.5; cursor: not-allowed;">' +
                    '<i class="fa fa-edit"></i>' +
                    '</a>';
            }

            // Delete button - check if deletable
            if (data.can_delete) {
                actions += '<a href="javascript:void(0);" onclick="deleteVoucher(' + data.id + ');" class="btn btn-sm btn-clean btn-icon" title="Delete">' +
                    '<i class="fa fa-trash"></i>' +
                    '</a>';
            } else {
                actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon disabled" title="Cannot delete - voucher is applied on services" style="opacity: 0.5; cursor: not-allowed;">' +
                    '<i class="fa fa-trash"></i>' +
                    '</a>';
            }

            return actions;
        }
    }];

function editVoucher(id) {
    $.ajax({
        url: route('admin.vouchers.edit', id),
        type: 'GET',
        success: function(response) {
            if (response.status === 'success') {
                // Open edit modal with voucher data
                $('#modal_edit_voucher').modal('show');
                // Populate form fields with voucher data
                $('#edit_voucher_id').val(response.data.id);
                $('#edit_user_id').val(response.data.user_id).trigger('change');
                $('#edit_voucher_id_field').val(response.data.voucher_id).trigger('change');
                $('#edit_amount').val(response.data.amount);
            } else {
                toastr.error(response.message || 'Cannot edit this voucher.');
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'An error occurred.';
            toastr.error(message);
        }
    });
}

function deleteVoucher(id) {
    if (!confirm('Are you sure you want to delete this voucher?')) {
        return;
    }

    $.ajax({
        url: route('admin.vouchers.destroy', id),
        type: 'DELETE',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.status === 'success') {
                toastr.success(response.message || 'Voucher deleted successfully.');
                // Reload datatable
                $('#kt_datatable').KTDatatable().reload();
            } else {
                toastr.error(response.message || 'Cannot delete this voucher.');
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'An error occurred.';
            toastr.error(message);
        }
    });
}
