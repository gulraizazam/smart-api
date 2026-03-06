'use strict';

(function () {
    var apiBase = '/api/cashflow/';

    $(document).ready(function () {
        initDateRange();
        loadLookups();
        bindEvents();
    });

    function bindEvents() {
        $('#btn-run-report').on('click', runReport);
        $('#btn-export-csv').on('click', exportCsv);
        $('#btn-export-pdf').on('click', exportPdf);
        $('#report-type').on('change', function () {
            var type = $(this).val();
            // Show pool filter only for cashflow-statement and daily-movement
            if (type === 'cashflow-statement' || type === 'daily-movement') {
                $('#rpt-pool').removeClass('d-none');
            } else {
                $('#rpt-pool').addClass('d-none');
            }
        });
    }

    function initDateRange() {
        $('#rpt-date-range').daterangepicker({
            locale: { format: 'MM/DD/YYYY' },
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'This Year': [moment().startOf('year'), moment().endOf('year')],
                'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
            },
            startDate: moment().startOf('month'),
            endDate: moment()
        });
    }

    function loadLookups() {
        $.ajax({
            url: apiBase + 'lookups', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                var d = res.data;
                if (d.branches) {
                    $.each(d.branches, function (i, b) { $('#rpt-branch').append('<option value="' + b.id + '">' + esc(b.name) + '</option>'); });
                }
                if (d.pools) {
                    $.each(d.pools, function (i, p) { $('#rpt-pool').append('<option value="' + p.id + '">' + esc(p.name) + '</option>'); });
                }

                // Init Select2 on page-level filter selects (after options are populated)
                $('#report-type').select2();
                $('#rpt-branch').select2();
                $('#rpt-pool').select2();
            }
        });
    }

    function getFilters() {
        var picker = $('#rpt-date-range').data('daterangepicker');
        return {
            date_from: picker ? picker.startDate.format('YYYY-MM-DD') : '',
            date_to: picker ? picker.endDate.format('YYYY-MM-DD') : '',
            branch_id: $('#rpt-branch').val() || '',
            pool_id: $('#rpt-pool').val() || ''
        };
    }

    function runReport() {
        var type = $('#report-type').val();
        var filters = getFilters();
        var btn = $('#btn-run-report');
        btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> Loading...');

        var endpoint = apiBase + 'reports/' + type;

        $.ajax({
            url: endpoint, type: 'GET', data: filters,
            success: function (res) {
                if (!res.success) { toastr.error(res.message || 'Report failed.'); return; }
                renderReport(type, res.data);
            },
            error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Report failed.'); },
            complete: function () { btn.prop('disabled', false).html('<i class="la la-play"></i> Generate'); }
        });
    }

    function exportPdf() {
        var content = $('#report-output').html();
        if (!content || content.trim() === '') {
            toastr.warning('Please generate a report first.');
            return;
        }
        var type = $('#report-type option:selected').text();
        var win = window.open('', '_blank');
        win.document.write('<html><head><title>' + type + ' - PDF Export</title>');
        win.document.write('<style>body{font-family:Arial,sans-serif;padding:20px;font-size:12px}table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border:1px solid #ccc;padding:6px 8px;text-align:left}th{background:#f5f5f5;font-weight:bold}.text-right{text-align:right}.text-danger{color:#c00}.text-success{color:#060}h4{margin:0 0 10px}@media print{body{padding:0}}</style>');
        win.document.write('</head><body>');
        win.document.write('<h3>' + type + '</h3>');
        win.document.write('<p style="color:#888;font-size:11px">Generated: ' + new Date().toLocaleString() + '</p>');
        win.document.write(content);
        win.document.write('</body></html>');
        win.document.close();
        setTimeout(function () { win.print(); }, 300);
    }

    function exportCsv() {
        var type = $('#report-type').val();
        var filters = getFilters();
        var params = $.param(filters);
        window.open(apiBase + 'reports/export/' + type + '?' + params, '_blank');
    }

    // ===================== RENDER FUNCTIONS =====================

    function renderReport(type, data) {
        var $out = $('#report-output');

        switch (type) {
            case 'cashflow-statement': renderCashFlowStatement($out, data); break;
            case 'branch-comparison': renderTable($out, 'Branch Comparison', ['Branch', 'Inflows', 'Outflows', 'Count', 'Net'], data, function (r) { return [r.branch_name, 'PKR ' + nf(r.inflows), 'PKR ' + nf(r.outflows), r.expense_count, 'PKR ' + nf(r.net)]; }); break;
            case 'category-trend': renderTable($out, 'Category Trend', ['Category', 'Month', 'Amount'], data, function (r) { return [r.category, r.month, 'PKR ' + nf(r.total)]; }); break;
            case 'vendor-outstanding': renderTable($out, 'Vendor Outstanding', ['Vendor', 'Opening', 'Balance', 'Terms', 'Active'], data, function (r) { return [r.name, 'PKR ' + nf(r.opening_balance), 'PKR ' + nf(r.cached_balance), r.payment_terms || '-', r.is_active ? 'Yes' : 'No']; }); break;
            case 'staff-advance': renderTable($out, 'Staff Advance Summary', ['Staff', 'Advances', 'Expenses', 'Returns', 'Outstanding', 'Days', 'Aging'], data, function (r) { return [r.name, 'PKR ' + nf(r.total_advances), 'PKR ' + nf(r.total_expenses), 'PKR ' + nf(r.total_returns), 'PKR ' + nf(r.outstanding), r.days_since_last, agingBadge(r.aging)]; }); break;
            case 'daily-movement': renderDailyMovement($out, data); break;
            case 'transfer-log': renderTable($out, 'Transfer Log', ['Date', 'Amount', 'From', 'To', 'Method', 'Reference', 'By'], data, function (r) { return [r.transfer_date, 'PKR ' + nf(r.amount), r.from_pool ? r.from_pool.name : '', r.to_pool ? r.to_pool.name : '', r.method, r.reference_no || '-', r.creator ? r.creator.name : '']; }); break;
            case 'flagged-entries': renderTable($out, 'Flagged Entries', ['Date', 'Amount', 'Category', 'Flag Reason', 'Status', 'By'], data, function (r) { return [r.expense_date, 'PKR ' + nf(r.amount), r.category ? r.category.name : '', r.flag_reason || '-', statusBadge(r.status), r.creator ? r.creator.name : '']; }); break;
            case 'dormant-vendors': renderTable($out, 'Dormant Vendors', ['Vendor', 'Balance', 'Last Activity', 'Days Inactive'], data, function (r) { return [r.name, 'PKR ' + nf(r.cached_balance), r.last_activity || 'Never', r.days_inactive || 'N/A']; }); break;
            default: $out.html('<div class="card card-custom"><div class="card-body text-center py-5 text-muted">Unknown report type.</div></div>');
        }
    }

    function renderCashFlowStatement($out, d) {
        var html = '<div class="card card-custom">';
        html += '<div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-file-invoice mr-2"></i>Cash Flow Statement</h3></div><div class="card-toolbar"><span class="text-muted font-size-sm">' + esc(d.period.from) + ' to ' + esc(d.period.to) + '</span></div></div>';
        html += '<div class="card-body">';

        // A: Opening
        html += '<div class="mb-4"><h6 class="text-muted">A. Opening Balance</h6><div class="font-weight-bolder font-size-h4">PKR ' + nf(d.opening_balance) + '</div></div>';

        // B: Inflows
        html += '<div class="mb-4"><h6 class="text-muted">B. Inflows (Patient Payments)</h6>';
        html += '<table class="table table-sm table-bordered"><thead><tr><th>Payment Method</th><th class="text-right">Amount</th><th class="text-right">Count</th></tr></thead><tbody>';
        if (d.inflows && d.inflows.length) {
            $.each(d.inflows, function (i, r) { html += '<tr><td>' + esc(r.method) + '</td><td class="text-right">PKR ' + nf(r.total) + '</td><td class="text-right">' + r.count + '</td></tr>'; });
        } else {
            html += '<tr><td colspan="3" class="text-center text-muted">No inflows</td></tr>';
        }
        html += '</tbody><tfoot><tr class="font-weight-bolder bg-light-success"><td>Total Inflows</td><td class="text-right">PKR ' + nf(d.total_inflows) + '</td><td></td></tr></tfoot></table></div>';

        // C: Outflows
        html += '<div class="mb-4"><h6 class="text-muted">C. Outflows (by Category)</h6>';
        html += '<table class="table table-sm table-bordered"><thead><tr><th>Category</th><th class="text-right">Amount</th><th class="text-right">Count</th></tr></thead><tbody>';
        if (d.outflows && d.outflows.length) {
            $.each(d.outflows, function (i, r) { html += '<tr><td>' + esc(r.category) + '</td><td class="text-right">PKR ' + nf(r.total) + '</td><td class="text-right">' + r.count + '</td></tr>'; });
        } else {
            html += '<tr><td colspan="3" class="text-center text-muted">No outflows</td></tr>';
        }
        html += '</tbody><tfoot><tr class="font-weight-bolder bg-light-danger"><td>Total Outflows</td><td class="text-right">PKR ' + nf(d.total_outflows) + '</td><td></td></tr></tfoot></table></div>';

        // D & E
        var netCls = d.net_cash_flow >= 0 ? 'text-success' : 'text-danger';
        html += '<div class="row mb-4">';
        html += '<div class="col-md-6"><div class="bg-light-primary rounded p-3"><h6 class="text-muted mb-1">D. Net Cash Flow</h6><div class="font-weight-bolder font-size-h4 ' + netCls + '">PKR ' + nf(d.net_cash_flow) + '</div></div></div>';
        html += '<div class="col-md-6"><div class="bg-light-info rounded p-3"><h6 class="text-muted mb-1">E. Closing Balance</h6><div class="font-weight-bolder font-size-h4">PKR ' + nf(d.closing_balance) + '</div></div></div>';
        html += '</div>';

        // F: Pool breakdown
        html += '<div class="mb-2"><h6 class="text-muted">F. Pool Breakdown</h6>';
        html += '<table class="table table-sm table-bordered"><thead><tr><th>Pool</th><th>Type</th><th class="text-right">Balance</th></tr></thead><tbody>';
        if (d.pool_breakdown && d.pool_breakdown.length) {
            $.each(d.pool_breakdown, function (i, p) { html += '<tr><td>' + esc(p.name) + '</td><td>' + esc(p.type) + '</td><td class="text-right font-weight-bold">PKR ' + nf(p.cached_balance) + '</td></tr>'; });
        }
        html += '</tbody></table></div>';

        html += '</div></div>';
        $out.html(html);
    }

    function renderDailyMovement($out, d) {
        var html = '<div class="card card-custom"><div class="card-header py-3"><div class="card-title"><h3 class="card-label">Daily Cash Movement</h3></div></div><div class="card-body p-0">';
        html += '<div class="table-responsive"><table class="table table-sm table-head-custom mb-0"><thead><tr><th>Date</th><th>Pool</th><th class="text-right">Expenses</th><th class="text-right">Transfers Out</th><th class="text-right">Transfers In</th></tr></thead><tbody>';

        var hasData = false;
        if (d.expenses && d.expenses.length) {
            hasData = true;
            $.each(d.expenses, function (i, r) { html += '<tr><td>' + esc(r.date) + '</td><td>Pool #' + r.pool_id + '</td><td class="text-right text-danger">PKR ' + nf(r.total) + '</td><td></td><td></td></tr>'; });
        }
        if (d.transfers_out && d.transfers_out.length) {
            hasData = true;
            $.each(d.transfers_out, function (i, r) { html += '<tr><td>' + esc(r.date) + '</td><td>Pool #' + r.pool_id + '</td><td></td><td class="text-right text-warning">PKR ' + nf(r.total) + '</td><td></td></tr>'; });
        }
        if (d.transfers_in && d.transfers_in.length) {
            hasData = true;
            $.each(d.transfers_in, function (i, r) { html += '<tr><td>' + esc(r.date) + '</td><td>Pool #' + r.pool_id + '</td><td></td><td></td><td class="text-right text-success">PKR ' + nf(r.total) + '</td></tr>'; });
        }
        if (!hasData) {
            html += '<tr><td colspan="5" class="text-center text-muted py-4">No movements in this period.</td></tr>';
        }

        html += '</tbody></table></div></div></div>';
        $out.html(html);
    }

    function renderTable($out, title, headers, rows, rowFn) {
        var html = '<div class="card card-custom"><div class="card-header py-3"><div class="card-title"><h3 class="card-label">' + esc(title) + '</h3></div><div class="card-toolbar"><span class="badge badge-primary">' + (rows ? rows.length : 0) + ' records</span></div></div>';
        html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-head-custom mb-0"><thead><tr>';
        $.each(headers, function (i, h) { html += '<th>' + esc(h) + '</th>'; });
        html += '</tr></thead><tbody>';

        if (!rows || !rows.length) {
            html += '<tr><td colspan="' + headers.length + '" class="text-center text-muted py-4">No data available.</td></tr>';
        } else {
            $.each(rows, function (i, r) {
                var cells = rowFn(r);
                html += '<tr>';
                $.each(cells, function (j, c) { html += '<td>' + (c || '-') + '</td>'; });
                html += '</tr>';
            });
        }

        html += '</tbody></table></div></div></div>';
        $out.html(html);
    }

    // ===================== HELPERS =====================

    function agingBadge(aging) {
        if (aging === 'red') return '<span class="label label-light-danger label-inline">Overdue</span>';
        if (aging === 'amber') return '<span class="label label-light-warning label-inline">Warning</span>';
        return '<span class="label label-light-success label-inline">OK</span>';
    }

    function statusBadge(status) {
        var map = {
            'approved': '<span class="label label-light-success label-inline">Approved</span>',
            'pending': '<span class="label label-light-warning label-inline">Pending</span>',
            'rejected': '<span class="label label-light-danger label-inline">Rejected</span>'
        };
        return map[status] || '<span class="label label-light-secondary label-inline">' + esc(status) + '</span>';
    }

    function nf(n) { return Number(parseFloat(n) || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 }); }
    function esc(s) { return $('<span>').text(s || '').html(); }
    function fd(d) { return d.toISOString().split('T')[0]; }

})();
