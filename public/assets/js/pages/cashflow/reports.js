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
            // Show/hide filters based on report type
            if (type === 'cashflow-statement' || type === 'daily-movement' || type === 'transfer-log') {
                $('#rpt-pool').removeClass('d-none');
            } else {
                $('#rpt-pool').addClass('d-none');
            }
            if (type === 'vendor-outstanding' || type === 'dormant-vendors' || type === 'staff-advance') {
                $('#rpt-date-range, #rpt-branch').closest('.d-flex').find('#rpt-date-range, #rpt-branch').addClass('d-none');
            } else {
                $('#rpt-date-range, #rpt-branch').removeClass('d-none');
            }
        });
    }

    function initDateRange() {
        $('#rpt-date-range').daterangepicker({
            locale: { format: 'YYYY-MM-DD' },
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
        win.document.write('<style>body{font-family:Arial,sans-serif;padding:20px;font-size:12px}table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border:1px solid #ddd;padding:8px 10px;text-align:left}th{background:#f8f8f8;font-weight:600}.text-right{text-align:right}.text-danger{color:#c00}.text-success{color:#060}.text-warning{color:#b57600}h4,h5{margin:0 0 10px}.badge{padding:3px 8px;border-radius:3px;font-size:11px}@media print{body{padding:0}}</style>');
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
            case 'branch-comparison': renderBranchComparison($out, data); break;
            case 'category-trend': renderCategoryTrend($out, data); break;
            case 'vendor-outstanding': renderTable($out, 'Vendor Outstanding', ['Vendor', 'Opening Balance', 'Current Balance', 'Payment Terms', 'Active'], data, function (r) {
                var balCls = r.cached_balance > 0 ? 'text-danger font-weight-bold' : (r.cached_balance < 0 ? 'text-success font-weight-bold' : '');
                return [esc(r.name), 'PKR ' + nf(r.opening_balance), '<span class="' + balCls + '">PKR ' + nf(r.cached_balance) + '</span>', esc(r.payment_terms || '—'), r.is_active ? '<span class="label label-light-success label-inline">Yes</span>' : '<span class="label label-light-danger label-inline">No</span>'];
            }); break;
            case 'staff-advance': renderStaffAdvance($out, data); break;
            case 'daily-movement': renderDailyMovement($out, data); break;
            case 'transfer-log': renderTransferLog($out, data); break;
            case 'flagged-entries': renderFlaggedEntries($out, data); break;
            case 'dormant-vendors': renderTable($out, 'Dormant Vendors', ['Vendor', 'Balance', 'Last Activity', 'Days Inactive'], data, function (r) {
                var daysCls = r.days_inactive > 180 ? 'text-danger font-weight-bold' : (r.days_inactive > 90 ? 'text-warning font-weight-bold' : '');
                return [esc(r.name), 'PKR ' + nf(r.cached_balance), r.last_activity || '<span class="text-muted">Never</span>', '<span class="' + daysCls + '">' + (r.days_inactive || 'N/A') + '</span>'];
            }); break;
            default: $out.html('<div class="card card-custom"><div class="card-body text-center py-5 text-muted">Unknown report type.</div></div>');
        }
    }

    function renderCashFlowStatement($out, d) {
        var html = '<div class="card card-custom">';
        html += '<div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-file-invoice mr-2 text-primary"></i>Cash Flow Statement</h3></div><div class="card-toolbar"><span class="label label-light-primary label-inline font-size-sm">' + esc(d.period.from) + ' to ' + esc(d.period.to) + '</span></div></div>';
        html += '<div class="card-body px-6 py-5">';

        // A: Opening
        html += '<div class="bg-light rounded p-4 mb-5"><div class="d-flex justify-content-between align-items-center"><div><span class="text-muted font-weight-bold font-size-sm">A. OPENING BALANCE</span></div><div class="font-weight-bolder font-size-h3">PKR ' + nf(d.opening_balance) + '</div></div></div>';

        // B: Inflows
        html += '<div class="mb-5"><h5 class="font-weight-bold mb-3"><i class="la la-arrow-down text-success mr-1"></i>B. Inflows</h5>';
        html += '<table class="table table-bordered table-sm mb-0"><thead class="bg-light-success"><tr><th>Source</th><th class="text-right">Amount</th><th class="text-right">Count</th></tr></thead><tbody>';
        if (d.inflows && d.inflows.length) {
            $.each(d.inflows, function (i, r) { html += '<tr><td>' + esc(r.method) + '</td><td class="text-right text-success font-weight-bold">PKR ' + nf(r.total) + '</td><td class="text-right">' + (r.count || '—') + '</td></tr>'; });
        } else {
            html += '<tr><td colspan="3" class="text-center text-muted py-3">No inflows in this period</td></tr>';
        }
        html += '</tbody><tfoot><tr class="font-weight-bolder" style="background:#e8f5e9;"><td>Total Inflows</td><td class="text-right text-success">PKR ' + nf(d.total_inflows) + '</td><td></td></tr></tfoot></table></div>';

        // C: Outflows
        html += '<div class="mb-5"><h5 class="font-weight-bold mb-3"><i class="la la-arrow-up text-danger mr-1"></i>C. Outflows</h5>';
        html += '<table class="table table-bordered table-sm mb-0"><thead class="bg-light-danger"><tr><th>Category</th><th class="text-right">Amount</th><th class="text-right">Count</th></tr></thead><tbody>';
        if (d.outflows && d.outflows.length) {
            $.each(d.outflows, function (i, r) { html += '<tr><td>' + esc(r.category) + '</td><td class="text-right text-danger font-weight-bold">PKR ' + nf(r.total) + '</td><td class="text-right">' + (r.count || '—') + '</td></tr>'; });
        } else {
            html += '<tr><td colspan="3" class="text-center text-muted py-3">No outflows in this period</td></tr>';
        }
        html += '</tbody><tfoot><tr class="font-weight-bolder" style="background:#ffebee;"><td>Total Outflows</td><td class="text-right text-danger">PKR ' + nf(d.total_outflows) + '</td><td></td></tr></tfoot></table></div>';

        // D & E: Summary cards
        var netCls = d.net_cash_flow >= 0 ? 'text-success' : 'text-danger';
        var netBg = d.net_cash_flow >= 0 ? 'bg-light-success' : 'bg-light-danger';
        html += '<div class="row mb-5">';
        html += '<div class="col-md-4"><div class="' + netBg + ' rounded p-4 text-center"><span class="text-muted font-weight-bold font-size-sm d-block mb-1">D. NET CASH FLOW</span><span class="font-weight-bolder font-size-h3 ' + netCls + '">PKR ' + nf(d.net_cash_flow) + '</span></div></div>';
        html += '<div class="col-md-4"><div class="bg-light-info rounded p-4 text-center"><span class="text-muted font-weight-bold font-size-sm d-block mb-1">E. CLOSING BALANCE</span><span class="font-weight-bolder font-size-h3">PKR ' + nf(d.closing_balance) + '</span></div></div>';
        html += '<div class="col-md-4"><div class="bg-light rounded p-4 text-center"><span class="text-muted font-weight-bold font-size-sm d-block mb-1">VARIANCE</span><span class="font-weight-bolder font-size-h3">' + (d.total_inflows > 0 ? ((d.net_cash_flow / d.total_inflows) * 100).toFixed(1) + '%' : '—') + '</span></div></div>';
        html += '</div>';

        // F: Pool breakdown
        html += '<div class="mb-2"><h5 class="font-weight-bold mb-3"><i class="la la-wallet text-info mr-1"></i>F. Pool Breakdown</h5>';
        html += '<table class="table table-bordered table-sm mb-0"><thead class="bg-light"><tr><th>Pool</th><th>Branch</th><th>Type</th><th class="text-right">Opening</th><th class="text-right">Current Balance</th></tr></thead><tbody>';
        if (d.pool_breakdown && d.pool_breakdown.length) {
            $.each(d.pool_breakdown, function (i, p) {
                var balCls = p.cached_balance < 0 ? 'text-danger' : 'text-success';
                var branchName = p.location ? p.location.name : '—';
                html += '<tr><td class="font-weight-bold">' + esc(p.name) + '</td><td>' + esc(branchName) + '</td><td><span class="label label-light-info label-inline">' + esc(p.type) + '</span></td><td class="text-right">PKR ' + nf(p.opening_balance) + '</td><td class="text-right font-weight-bold ' + balCls + '">PKR ' + nf(p.cached_balance) + '</td></tr>';
            });
        }
        html += '</tbody></table></div>';

        html += '</div></div>';
        $out.html(html);
    }

    function renderBranchComparison($out, data) {
        var html = '<div class="card card-custom"><div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-code-branch mr-2 text-info"></i>Branch Comparison</h3></div><div class="card-toolbar"><span class="badge badge-primary">' + data.length + ' branches</span></div></div>';
        html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-head-custom mb-0"><thead><tr><th>Branch</th><th class="text-right">Inflows</th><th class="text-right">Outflows</th><th class="text-right">Expenses</th><th class="text-right">Net</th></tr></thead><tbody>';

        if (!data || !data.length) {
            html += '<tr><td colspan="5" class="text-center text-muted py-4">No data available.</td></tr>';
        } else {
            $.each(data, function (i, r) {
                var netCls = r.net >= 0 ? 'text-success font-weight-bold' : 'text-danger font-weight-bold';
                html += '<tr>' +
                    '<td class="font-weight-bold">' + esc(r.branch_name) + '</td>' +
                    '<td class="text-right text-success">PKR ' + nf(r.inflows) + '</td>' +
                    '<td class="text-right text-danger">PKR ' + nf(r.outflows) + '</td>' +
                    '<td class="text-right">' + r.expense_count + '</td>' +
                    '<td class="text-right ' + netCls + '">PKR ' + nf(r.net) + '</td>' +
                    '</tr>';
            });
        }

        html += '</tbody></table></div></div></div>';
        $out.html(html);
    }

    function renderCategoryTrend($out, data) {
        // Group by month for a pivot-style display
        var months = [], categories = {}, byKey = {};
        $.each(data, function (i, r) {
            if (months.indexOf(r.month) === -1) months.push(r.month);
            categories[r.category] = true;
            byKey[r.category + '|' + r.month] = r.total;
        });
        var catList = Object.keys(categories).sort();

        var html = '<div class="card card-custom"><div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-chart-line mr-2 text-warning"></i>Category Trend</h3></div></div>';
        html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-head-custom table-bordered mb-0"><thead><tr><th>Category</th>';
        $.each(months, function (i, m) { html += '<th class="text-right">' + esc(m) + '</th>'; });
        html += '<th class="text-right font-weight-bolder">Total</th></tr></thead><tbody>';

        if (!catList.length) {
            html += '<tr><td colspan="' + (months.length + 2) + '" class="text-center text-muted py-4">No data available.</td></tr>';
        } else {
            $.each(catList, function (i, cat) {
                html += '<tr><td class="font-weight-bold">' + esc(cat) + '</td>';
                var rowTotal = 0;
                $.each(months, function (j, m) {
                    var val = parseFloat(byKey[cat + '|' + m] || 0);
                    rowTotal += val;
                    html += '<td class="text-right">' + (val ? 'PKR ' + nf(val) : '<span class="text-muted">—</span>') + '</td>';
                });
                html += '<td class="text-right font-weight-bolder text-danger">PKR ' + nf(rowTotal) + '</td></tr>';
            });
        }

        html += '</tbody></table></div></div></div>';
        $out.html(html);
    }

    function renderStaffAdvance($out, data) {
        var html = '<div class="card card-custom"><div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-user-clock mr-2 text-warning"></i>Staff Advance Summary</h3></div><div class="card-toolbar"><span class="badge badge-primary">' + (data ? data.length : 0) + ' staff</span></div></div>';
        html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-head-custom mb-0"><thead><tr><th>Staff</th><th class="text-right">Advances</th><th class="text-right">Expenses</th><th class="text-right">Returns</th><th class="text-right">Outstanding</th><th class="text-right">Days Since Last</th><th>Aging</th></tr></thead><tbody>';

        if (!data || !data.length) {
            html += '<tr><td colspan="7" class="text-center text-muted py-4">No staff advances found.</td></tr>';
        } else {
            $.each(data, function (i, r) {
                var outCls = r.outstanding > 0 ? 'text-danger font-weight-bold' : (r.outstanding < 0 ? 'text-success font-weight-bold' : '');
                html += '<tr>' +
                    '<td class="font-weight-bold">' + esc(r.name) + '</td>' +
                    '<td class="text-right">PKR ' + nf(r.total_advances) + '</td>' +
                    '<td class="text-right">PKR ' + nf(r.total_expenses) + '</td>' +
                    '<td class="text-right">PKR ' + nf(r.total_returns) + '</td>' +
                    '<td class="text-right ' + outCls + '">PKR ' + nf(r.outstanding) + '</td>' +
                    '<td class="text-right">' + r.days_since_last + '</td>' +
                    '<td>' + agingBadge(r.aging) + '</td>' +
                    '</tr>';
            });
        }

        html += '</tbody></table></div></div></div>';
        $out.html(html);
    }

    function renderDailyMovement($out, d) {
        // Merge all entries into a unified sorted list
        var rows = [];
        $.each(d.expenses || [], function (i, r) { rows.push({ date: r.date, pool: r.pool_name || ('Pool #' + r.pool_id), type: 'Expense', amount: r.total, cls: 'text-danger' }); });
        $.each(d.transfers_out || [], function (i, r) { rows.push({ date: r.date, pool: r.pool_name || ('Pool #' + r.pool_id), type: 'Transfer Out', amount: r.total, cls: 'text-warning' }); });
        $.each(d.transfers_in || [], function (i, r) { rows.push({ date: r.date, pool: r.pool_name || ('Pool #' + r.pool_id), type: 'Transfer In', amount: r.total, cls: 'text-success' }); });
        $.each(d.staff_advances || [], function (i, r) { rows.push({ date: r.date, pool: r.pool_name || ('Pool #' + r.pool_id), type: 'Staff Advance', amount: r.total, cls: 'text-info' }); });

        rows.sort(function (a, b) { return a.date < b.date ? 1 : (a.date > b.date ? -1 : 0); });

        var html = '<div class="card card-custom"><div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-calendar-alt mr-2 text-primary"></i>Daily Cash Movement</h3></div><div class="card-toolbar"><span class="badge badge-primary">' + rows.length + ' entries</span></div></div>';
        html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-head-custom mb-0"><thead><tr><th>Date</th><th>Pool</th><th>Type</th><th class="text-right">Amount</th></tr></thead><tbody>';

        if (!rows.length) {
            html += '<tr><td colspan="4" class="text-center text-muted py-4">No movements in this period.</td></tr>';
        } else {
            $.each(rows, function (i, r) {
                var typeBadge = '<span class="label label-light-' + (r.type === 'Transfer In' ? 'success' : (r.type === 'Transfer Out' ? 'warning' : (r.type === 'Staff Advance' ? 'info' : 'danger'))) + ' label-inline">' + r.type + '</span>';
                html += '<tr><td>' + esc(r.date) + '</td><td>' + esc(r.pool) + '</td><td>' + typeBadge + '</td><td class="text-right font-weight-bold ' + r.cls + '">PKR ' + nf(r.amount) + '</td></tr>';
            });
        }

        html += '</tbody></table></div></div></div>';
        $out.html(html);
    }

    function renderTransferLog($out, data) {
        var html = '<div class="card card-custom"><div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-exchange-alt mr-2 text-info"></i>Transfer Log</h3></div><div class="card-toolbar"><span class="badge badge-primary">' + (data ? data.length : 0) + ' transfers</span></div></div>';
        html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-head-custom mb-0"><thead><tr><th>Date</th><th>From</th><th>To</th><th class="text-right">Amount</th><th>Method</th><th>Reference</th><th>By</th><th>Status</th></tr></thead><tbody>';

        if (!data || !data.length) {
            html += '<tr><td colspan="8" class="text-center text-muted py-4">No transfers found.</td></tr>';
        } else {
            $.each(data, function (i, r) {
                var rowCls = r.is_voided ? 'style="opacity:0.5;text-decoration:line-through;"' : '';
                var statusBadgeHtml = r.is_voided ? '<span class="label label-light-danger label-inline">Voided</span>' : '<span class="label label-light-success label-inline">Active</span>';
                html += '<tr ' + rowCls + '>' +
                    '<td>' + esc(r.transfer_date) + '</td>' +
                    '<td>' + esc(r.from_pool ? r.from_pool.name : '') + '</td>' +
                    '<td>' + esc(r.to_pool ? r.to_pool.name : '') + '</td>' +
                    '<td class="text-right font-weight-bold">PKR ' + nf(r.amount) + '</td>' +
                    '<td>' + esc(r.method || '—') + '</td>' +
                    '<td>' + esc(r.reference_no || '—') + '</td>' +
                    '<td>' + esc(r.creator ? r.creator.name : '') + '</td>' +
                    '<td>' + statusBadgeHtml + '</td>' +
                    '</tr>';
            });
        }

        html += '</tbody></table></div></div></div>';
        $out.html(html);
    }

    function renderFlaggedEntries($out, data) {
        var html = '<div class="card card-custom"><div class="card-header py-3"><div class="card-title"><h3 class="card-label"><i class="la la-flag mr-2 text-danger"></i>Flagged Entries</h3></div><div class="card-toolbar"><span class="badge badge-danger">' + (data ? data.length : 0) + ' flagged</span></div></div>';
        html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-head-custom mb-0"><thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Branch</th><th class="text-right">Amount</th><th>Flag Reason</th><th>Status</th><th>By</th></tr></thead><tbody>';

        if (!data || !data.length) {
            html += '<tr><td colspan="8" class="text-center text-muted py-4">No flagged entries found.</td></tr>';
        } else {
            $.each(data, function (i, r) {
                var branchName = r.for_branch ? r.for_branch.name : (r.is_for_general ? 'General / Company-wide' : '—');
                var catName = r.category ? r.category.name : '—';
                var poolName = r.paid_from_pool ? r.paid_from_pool.name : '—';
                html += '<tr>' +
                    '<td>' + esc(r.expense_date) + '</td>' +
                    '<td>' + esc(r.description || '—') + '</td>' +
                    '<td>' + esc(catName) + '</td>' +
                    '<td>' + esc(branchName) + '</td>' +
                    '<td class="text-right text-danger font-weight-bold">PKR ' + nf(r.amount) + '</td>' +
                    '<td><span class="text-danger">' + esc(r.flag_reason || '—') + '</span></td>' +
                    '<td>' + statusBadge(r.status) + '</td>' +
                    '<td>' + esc(r.creator ? r.creator.name : '') + '</td>' +
                    '</tr>';
            });
        }

        html += '</tbody></table></div></div></div>';
        $out.html(html);
    }

    function renderTable($out, title, headers, rows, rowFn) {
        var html = '<div class="card card-custom"><div class="card-header py-3"><div class="card-title"><h3 class="card-label">' + esc(title) + '</h3></div><div class="card-toolbar"><span class="badge badge-primary">' + (rows ? rows.length : 0) + ' records</span></div></div>';
        html += '<div class="card-body p-0"><div class="table-responsive"><table class="table table-head-custom mb-0"><thead><tr>';
        $.each(headers, function (i, h) { html += '<th>' + esc(h) + '</th>'; });
        html += '</tr></thead><tbody>';

        if (!rows || !rows.length) {
            html += '<tr><td colspan="' + headers.length + '" class="text-center text-muted py-4">No data available.</td></tr>';
        } else {
            $.each(rows, function (i, r) {
                var cells = rowFn(r);
                html += '<tr>';
                $.each(cells, function (j, c) { html += '<td>' + (c || '—') + '</td>'; });
                html += '</tr>';
            });
        }

        html += '</tbody></table></div></div></div>';
        $out.html(html);
    }

    // ===================== HELPERS =====================

    function agingBadge(aging) {
        if (aging === 'red') return '<span class="label label-danger label-inline">Overdue</span>';
        if (aging === 'amber') return '<span class="label label-warning label-inline">Warning</span>';
        return '<span class="label label-light-success label-inline">OK</span>';
    }

    function statusBadge(status) {
        var map = {
            'approved': '<span class="label label-light-success label-inline">Approved</span>',
            'pending': '<span class="label label-warning label-inline">Pending</span>',
            'rejected': '<span class="label label-light-danger label-inline">Rejected</span>'
        };
        return map[status] || '<span class="label label-light-secondary label-inline">' + esc(status) + '</span>';
    }

    function nf(n) { return Number(parseFloat(n) || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 }); }
    function esc(s) { return $('<span>').text(s || '').html(); }

})();
