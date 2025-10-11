
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
        title: 'Type',
        sortable: false,
        width: 200,
    }, {
        field: 'total_amount',
        title: 'Value',
        sortable: false,
        width: 150,
        template: function (data) {
            return data.total_amount ? parseFloat(data.total_amount).toFixed(2) : '0.00';
        }
    }, {
        field: 'consumed',
        title: 'Consumed',
        sortable: false,
        width: 150,
        template: function (data) {
            var total = data.total_amount ? parseFloat(data.total_amount) : 0;
            var remaining = data.amount ? parseFloat(data.amount) : 0;
            var consumed = total - remaining;
            return consumed.toFixed(2);
        }
    }, {
        field: 'amount',
        title: 'Remaining',
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
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 120,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];

function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;
        let edit_url = route('admin.vouchers.edit', {id: id});
        let delete_url = route('admin.vouchers.destroy', {id: id});

        if (permissions.view) {
            let actions = '<div class="dropdown dropdown-inline action-dots">\
        <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                </li>';

            // View button (empty for now)
            actions += '<li class="navi-item">\
                <a href="javascript:void(0);" class="navi-link">\
                    <span class="navi-icon"><i class="la la-eye"></i></span>\
                    <span class="navi-text">View</span>\
                </a>\
            </li>';

            // Edit button - check if editable
            if (data.can_edit && permissions.view) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="editVoucher(`' + edit_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
            } else {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" class="navi-link disabled" style="opacity: 0.5; cursor: not-allowed;" title="Cannot edit - voucher is used in services">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
            }

            // Delete button - check if deletable
            if (data.can_delete && permissions.view) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="deleteVoucher(`' + delete_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-trash"></i></span>\
                        <span class="navi-text">Delete</span>\
                    </a>\
                </li>';
            } else {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" class="navi-link disabled" style="opacity: 0.5; cursor: not-allowed;" title="Cannot delete - voucher is applied on services">\
                        <span class="navi-icon"><i class="la la-trash"></i></span>\
                        <span class="navi-text">Delete</span>\
                    </a>\
                </li>';
            }

            actions += '</ul>\
        </div>\
    </div>';

            return actions;
        }
    }
    return '';
}

function editVoucher(url) {
    $("#modal_edit_voucher").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {
            if (response.status === true || response.status === 'success') {
                let voucher = response.data.voucher;

                // Set form action to update route
                $("#modal_edit_vouchers_form").attr("action", route('admin.vouchers.update', {id: voucher.id}));

                // Populate form fields with voucher data
                $('#edit_voucher_id').val(voucher.id);
                $('#edit_patient_name').val(response.data.patient_name);
                $('#edit_voucher_type_name').val(response.data.voucher_type_name);
                $('#edit_total_amount').val(voucher.total_amount);
            } else {
                toastr.error(response.message || 'Cannot edit this voucher.');
                $("#modal_edit_voucher").modal("hide");
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'An error occurred.';
            toastr.error(message);
            $("#modal_edit_voucher").modal("hide");
        }
    });
}

function deleteVoucher(url) {
    swal.fire({
        title: 'Are you sure you want to remove?',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function(result) {
        if (result.value) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'DELETE',
                url: url,
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
    });
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function() {
        let filters = {
            delete: '',
            patient_id: $("#search_patient").val(),
            voucher_type: $("#search_voucher_type").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function() {
        $('.search_patient').val('');
        $("#search_patient").val('');
        $("#search_voucher_type").val('').trigger('change');
        $("#search_date_range").val('');
        $("#search_created_from").val('');
        $("#search_created_to").val('');

        let filters = {
            delete: '',
            patient_id: '',
            voucher_type: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    try {
        let vouchers = filter_values.vouchers;

        let voucher_options = '<option value="">All Voucher Types</option>';

        if (vouchers) {
            Object.values(vouchers).forEach(function (voucher, index) {
                voucher_options += '<option value="' + voucher.id + '">' + voucher.name + '</option>';
            });
        }

        $("#search_voucher_type").html(voucher_options);

        $("#search_patient").val(active_filters.patient_id || '');
        $("#search_voucher_type").val(active_filters.voucher_type || '');
        $("#search_created_from").val(active_filters.created_from || '');
        $("#search_created_to").val(active_filters.created_to || '');

        // Set patient name if patient_id exists
        if (active_filters.patient_id && active_filters.patient_name) {
            $('.search_patient').val(active_filters.patient_name);
        }

        // Set date range if exists
        if (active_filters.created_from && active_filters.created_to) {
            $("#search_date_range").val(active_filters.created_from + ' - ' + active_filters.created_to);
        }

    } catch (err) {
        console.log(err);
    }
}

function addUsers(){
    $('.search_patient').val('');
    $("#search_patient").val('');
}

// Initialize patient search and daterangepicker
$(document).ready(function() {
    // Patient search using existing function from custom.js
    patientSearch('search_patient');

    // Initialize daterangepicker
    $('#search_date_range').daterangepicker({
        locale: {
            format: 'YYYY-MM-DD'
        },
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'This Week': [moment().startOf('week'), moment().endOf('week')],
            'Last Week': [moment().subtract(1, 'week').startOf('week'), moment().subtract(1, 'week').endOf('week')],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        },
        autoUpdateInput: false
    });

    $('#search_date_range').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
        $('#search_created_from').val(picker.startDate.format('YYYY-MM-DD'));
        $('#search_created_to').val(picker.endDate.format('YYYY-MM-DD'));
    });

    $('#search_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
        $('#search_created_from').val('');
        $('#search_created_to').val('');
    });
});
