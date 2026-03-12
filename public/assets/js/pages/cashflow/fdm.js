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

                // Color the balance card
                colorCard('#fdm-balance-card', d.pool_balance);
                colorCard('#fdm-opening-card', d.opening_balance);

                // Period label
                var period = d.week_start + ' — Today';
                $('#fdm-transfers-period, #fdm-expenses-period, #fdm-advances-period').text('(' + period + ')');

                // Render tables
                renderTransfers(d.transfers);
                renderExpenses(d.expenses);
                renderAdvances(d.staff_advances);
            },
            error: function (xhr) {
                $('#fdm-branch-name').text('Error');
                $('#fdm-balance').text('—');
                $('#fdm-opening-balance').text('—');
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

    function renderExpenses(expenses) {
        var tbody = $('#fdm-expenses-tbody').empty();
        if (!expenses || !expenses.length) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No expenses this week.</td></tr>');
            return;
        }
        $.each(expenses, function (i, e) {
            var statusBadge = getStatusBadge(e.status);
            tbody.append(
                '<tr>' +
                '<td>' + esc(e.date) + '</td>' +
                '<td>' + esc(e.description) + '</td>' +
                '<td>' + esc(e.category) + '</td>' +
                '<td>' + esc(e.pool) + '</td>' +
                '<td class="text-right text-danger font-weight-bold">-PKR ' + nf(e.amount) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '</tr>'
            );
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
