
// Use direct URL since API routes may not be in Ziggy
var table_url = '/api/patients/' + patientCardID + '/vouchers-datatable';

var table_columns = [
    {
        field: 'name',
        title: 'Voucher',
        width: 120,
        template: function(data) {
            return '<a href="javascript:void(0);" onclick="showVoucherHistory(' + data.user_voucher_id + ', \'' + data.name + '\');" class="text-primary font-weight-bold">' + data.name + '</a>';
        }
    },
    {
        field: 'total_amount',
        title: 'Total Amount',
        width: 100,
    },
    {
        field: 'usage',
        title: 'Usage',
        width: 200,
        sortable: false,
        template: function(data) {
            let total = parseFloat(String(data.total_amount || '0').replace(/,/g, '')) || 0;
            let consumed = parseFloat(String(data.consumed_amount || '0').replace(/,/g, '')) || 0;
            let balance = parseFloat(String(data.balance || '0').replace(/,/g, '')) || 0;
            
            let percentage = total > 0 ? Math.round((consumed / total) * 100) : 0;
            let progressColor = percentage >= 100 ? 'bg-danger' : (percentage >= 75 ? 'bg-warning' : 'bg-success');
            
            return '<div class="d-flex flex-column">' +
                '<div class="d-flex justify-content-between mb-1">' +
                    '<small class="text-muted">Used: <span class="text-danger">' + (data.consumed_amount || '0.00') + '</span></small>' +
                    '<small class="text-muted">Left: <span class="text-success font-weight-bold">' + (data.balance || '0.00') + '</span></small>' +
                '</div>' +
                '<div class="progress" style="height: 8px; border-radius: 4px;">' +
                    '<div class="progress-bar ' + progressColor + '" role="progressbar" style="width: ' + percentage + '%; border-radius: 4px;" aria-valuenow="' + percentage + '" aria-valuemin="0" aria-valuemax="100"></div>' +
                '</div>' +
                '<small class="text-center text-muted mt-1">' + percentage + '% used</small>' +
            '</div>';
        }
    },
    {
        field: 'status',
        title: 'Status',
        width: 120,
        sortable: false,
        template: function(data) {
            let total = parseFloat(String(data.total_amount || '0').replace(/,/g, '')) || 0;
            let consumed = parseFloat(String(data.consumed_amount || '0').replace(/,/g, '')) || 0;
            let balance = parseFloat(String(data.balance || '0').replace(/,/g, '')) || 0;
            let percentage = total > 0 ? Math.round((consumed / total) * 100) : 0;
            
            // Determine status based on usage only
            if (percentage >= 100 || balance <= 0) {
                return '<span class="badge badge-secondary">Fully Used</span>';
            } else if (percentage > 0) {
                return '<span class="badge badge-warning">Partially Used</span>';
            } else {
                return '<span class="badge badge-success">Active</span>';
            }
        }
    },
    {
        field: 'created_at',
        title: 'Start Date',
        width: 'auto',
        template: function(data) {
            return data.created_at ? formatDate(data.created_at) : '-';
        }
    },
   
];

// Show voucher usage history modal
window.showVoucherHistory = function(userVoucherId, voucherName) {
    $("#voucher_history_modal").modal("show");
    $("#voucher_history_name").text(voucherName);
    $("#voucher_history_body").html('<tr><td colspan="5" class="text-center"><i class="la la-spinner la-spin"></i> Loading...</td></tr>');
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/patients/' + patientCardID + '/voucher-history/' + userVoucherId,
        type: "GET",
        cache: false,
        success: function(response) {
            if (response.status && response.data) {
                let data = response.data;
                
                // Update summary
                $("#voucher_history_total").text(parseFloat(data.total_amount).toFixed(2));
                $("#voucher_history_consumed").text(parseFloat(data.consumed_amount).toFixed(2));
                $("#voucher_history_balance").text(parseFloat(data.balance).toFixed(2));
                
                // Build history table
                let html = '';
                if (data.history && data.history.length > 0) {
                    data.history.forEach(function(item, index) {
                        html += '<tr>';
                        html += '<td>' + (index + 1) + '</td>';
                        html += '<td>' + (item.package_id || 'N/A') + '</td>';
                        html += '<td>' + (item.service_name || 'N/A') + '</td>';
                        html += '<td class="text-danger">-' + parseFloat(item.amount_deducted || 0).toFixed(2) + '</td>';
                        html += '<td>' + (item.applied_date || '-') + '</td>';
                        html += '</tr>';
                    });
                } else {
                    html = '<tr><td colspan="5" class="text-center text-muted">No usage history found. This voucher has not been applied to any services yet.</td></tr>';
                }
                $("#voucher_history_body").html(html);
            } else {
                $("#voucher_history_body").html('<tr><td colspan="5" class="text-center text-danger">Failed to load history</td></tr>');
            }
        },
        error: function(xhr) {
            $("#voucher_history_body").html('<tr><td colspan="5" class="text-center text-danger">Error loading history</td></tr>');
        }
    });
};

