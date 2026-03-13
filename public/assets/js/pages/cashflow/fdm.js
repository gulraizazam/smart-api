'use strict';

(function () {
    var apiBase = '/api/cashflow/';

    $(document).ready(function () {
        loadFdmData();
    });

    function loadFdmData() {
        $.ajax({
            url: apiBase + 'fdm/data', type: 'GET',
            success: function (res) {
                if (!res.success) {
                    $('#fdm-branch-name').text('Access Denied');
                    $('#fdm-balance').text('—');
                    $('#fdm-opening-balance').text('—');
                    showError('fdm-transfers-tbody', 6, res.message);
                    showError('fdm-expenses-tbody', 6, res.message);
                    showError('fdm-advances-tbody', 5, res.message);
                    return;
                }

                var d = res.data;

                // Balance cards
                $('#fdm-branch-name').text(d.branch_name || 'Unknown Branch');
                $('#fdm-balance').text('PKR ' + nf(d.pool_balance));
                $('#fdm-opening-balance').text('PKR ' + nf(d.opening_balance));
                
                // New cash inflow cards
                $('#fdm-services-cash').text('PKR ' + nf(d.services_cash_inflows || 0));
                $('#fdm-inventory-cash').text('PKR ' + nf(d.inventory_cash_inflows || 0));

                // Color the live balance card
                colorCard('#fdm-balance-card', d.pool_balance);

                // Week label for opening balance
                var sundayLabel = d.week_start ? moment(d.week_start).format('ddd, MMM D') : 'Sunday';
                $('#fdm-week-label').text('as of ' + sundayLabel);

                // Period label for transaction records (last 7 days)
                var period = 'Last 7 days';
                if (d.records_period_start && d.records_period_end) {
                    var startDate = moment(d.records_period_start).format('MMM D');
                    var endDate = moment(d.records_period_end).format('MMM D');
                    period = startDate + ' — ' + endDate;
                }
                $('#fdm-transfers-period, #fdm-expenses-period, #fdm-advances-period').text('(' + period + ')');

                // Render tables
                renderTransfers(d.transfers);
                renderExpenses(d.expenses);
                renderAdvances(d.staff_advances);

                // Summary totals
                var expTotal = 0;
                $.each(d.expenses || [], function (i, e) { expTotal += parseFloat(e.amount) || 0; });
                $('#fdm-total-expenses').text('PKR ' + nf(expTotal));
                $('#fdm-total-expenses-count').text((d.expenses || []).length + ' records');

                var trfTotal = 0;
                $.each(d.transfers || [], function (i, t) { trfTotal += parseFloat(t.amount) || 0; });
                $('#fdm-total-transfers').text('PKR ' + nf(trfTotal));
                $('#fdm-total-transfers-count').text((d.transfers || []).length + ' records');

                var advTotal = 0;
                $.each(d.staff_advances || [], function (i, a) { advTotal += parseFloat(a.amount) || 0; });
                $('#fdm-total-advances').text('PKR ' + nf(advTotal));
                $('#fdm-total-advances-count').text((d.staff_advances || []).length + ' records');
            },
            error: function (xhr) {
                $('#fdm-branch-name').text('Error');
                $('#fdm-balance').text('—');
                $('#fdm-opening-balance').text('PKR 0');
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to load FDM data.';
                showError('fdm-transfers-tbody', 6, msg);
                showError('fdm-expenses-tbody', 6, msg);
                showError('fdm-advances-tbody', 5, msg);
            }
        });
    }

    function colorCard(selector, value) {
        var bal = parseFloat(value || 0);
        var card = $(selector);
        card.removeClass('bg-light-success bg-light-danger bg-light-secondary');
        if (bal < 0) card.addClass('bg-light-danger');
        else if (bal === 0) card.addClass('bg-light-secondary');
        else card.addClass('bg-light-success');
    }

    function renderTransfers(transfers) {
        var tbody = $('#fdm-transfers-tbody').empty();
        if (!transfers || !transfers.length) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No transfers this week.</td></tr>');
            return;
        }
        $.each(transfers, function (i, t) {
            var amtClass = t.direction === 'in' ? 'text-success font-weight-bold' : 'text-danger font-weight-bold';
            var amtPrefix = t.direction === 'in' ? '+' : '-';
            tbody.append(
                '<tr>' +
                '<td>' + esc(t.date) + '</td>' +
                '<td>' + esc(t.from_pool) + '</td>' +
                '<td>' + esc(t.to_pool) + '</td>' +
                '<td class="text-right ' + amtClass + '">' + amtPrefix + 'PKR ' + nf(t.amount) + '</td>' +
                '<td>' + esc(t.description || '—') + '</td>' +
                '<td>' + esc(t.created_by) + '</td>' +
                '</tr>'
            );
        });
    }

    var expenseCache = [];

    function renderExpenses(expenses) {
        expenseCache = expenses || [];
        var tbody = $('#fdm-expenses-tbody').empty();
        if (!expenses || !expenses.length) {
            tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">No expenses this week.</td></tr>');
            return;
        }
        $.each(expenses, function (i, e) {
            var statusBadge = getStatusBadge(e.status);
            var shortDesc = e.description && e.description.length > 40
                ? e.description.substring(0, 40) + '...'
                : (e.description || '');
            tbody.append(
                '<tr>' +
                '<td>' + esc(e.date) + '</td>' +
                '<td title="' + esc(e.description) + '">' + esc(shortDesc) + '</td>' +
                '<td>' + esc(e.category) + '</td>' +
                '<td>' + esc(e.pool) + '</td>' +
                '<td class="text-right text-danger font-weight-bold">-PKR ' + nf(e.amount) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td class="text-center"><button class="btn btn-sm btn-clean btn-icon btn-fdm-view-exp" data-idx="' + i + '" title="View"><i class="la la-eye text-primary"></i></button></td>' +
                '</tr>'
            );
        });

        // Bind view buttons
        tbody.find('.btn-fdm-view-exp').on('click', function () {
            var idx = $(this).data('idx');
            var exp = expenseCache[idx];
            if (!exp) return;
            $('#fdm-exp-date').text(exp.date);
            $('#fdm-exp-desc').text(exp.description || '—');
            $('#fdm-exp-category').text(exp.category || '—');
            $('#fdm-exp-pool').text(exp.pool || '—');
            $('#fdm-exp-amount').text('PKR ' + nf(exp.amount));
            $('#fdm-exp-status').html(getStatusBadge(exp.status));
            $('#modal_fdm_expense').modal('show');
        });
    }

    function renderAdvances(advances) {
        var tbody = $('#fdm-advances-tbody').empty();
        if (!advances || !advances.length) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted py-4">No staff advances this week.</td></tr>');
            return;
        }
        $.each(advances, function (i, a) {
            tbody.append(
                '<tr>' +
                '<td>' + esc(a.date) + '</td>' +
                '<td>' + esc(a.staff_name) + '</td>' +
                '<td>' + esc(a.pool) + '</td>' +
                '<td class="text-right text-warning font-weight-bold">-PKR ' + nf(a.amount) + '</td>' +
                '<td>' + esc(a.description || '—') + '</td>' +
                '</tr>'
            );
        });
    }

    function getStatusBadge(status) {
        var map = {
            approved: '<span class="label label-light-success label-inline">Approved</span>',
            pending: '<span class="label label-warning label-inline">Pending</span>',
            rejected: '<span class="label label-outline-danger label-inline">Rejected</span>'
        };
        return map[status] || esc(status);
    }

    function showError(tbodyId, cols, msg) {
        $('#' + tbodyId).html('<tr><td colspan="' + cols + '" class="text-center text-danger py-4">' + esc(msg || 'Unable to load data.') + '</td></tr>');
    }

    function nf(n) { return Number(parseFloat(n) || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 }); }
    function esc(s) { return $('<span>').text(s || '').html(); }

})();
