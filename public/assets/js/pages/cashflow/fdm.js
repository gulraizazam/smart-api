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
                    $('#fdm-movements-tbody').html('<tr><td colspan="4" class="text-center text-danger py-5">' + esc(res.message || 'Unable to load data.') + '</td></tr>');
                    return;
                }

                var d = res.data;

                // Balance card
                $('#fdm-branch-name').text(d.branch_name || 'Unknown Branch');
                $('#fdm-balance').text('PKR ' + nf(d.pool_balance));
                // Pool name no longer shown here — replaced with "Live Cash Balance" label in blade

                // Color the balance card
                var bal = parseFloat(d.pool_balance || 0);
                var card = $('#fdm-balance-card');
                card.removeClass('bg-light-success bg-light-danger bg-light-secondary');
                if (bal < 0) card.addClass('bg-light-danger');
                else if (bal === 0) card.addClass('bg-light-secondary');
                else card.addClass('bg-light-success');

                // Movements table
                renderMovements(d.movements);
            },
            error: function (xhr) {
                $('#fdm-branch-name').text('Error');
                $('#fdm-balance').text('—');
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to load FDM data.';
                $('#fdm-movements-tbody').html('<tr><td colspan="4" class="text-center text-danger py-5">' + esc(msg) + '</td></tr>');
            }
        });
    }

    function renderMovements(movements) {
        var tbody = $('#fdm-movements-tbody').empty();

        if (!movements || !movements.length) {
            tbody.html('<tr><td colspan="4" class="text-center text-muted py-5">No movements in the last 10 days.</td></tr>');
            return;
        }

        $.each(movements, function (i, m) {
            var inflowClass = m.inflows > 0 ? 'text-success font-weight-bold' : 'text-muted';
            var outflowClass = m.outflows > 0 ? 'text-danger font-weight-bold' : 'text-muted';
            var balClass = m.balance < 0 ? 'text-danger font-weight-bold' : 'font-weight-bold';

            tbody.append(
                '<tr>' +
                '<td>' + esc(m.date) + '</td>' +
                '<td class="text-right ' + inflowClass + '">' + (m.inflows > 0 ? '+PKR ' + nf(m.inflows) : '—') + '</td>' +
                '<td class="text-right ' + outflowClass + '">' + (m.outflows > 0 ? '-PKR ' + nf(m.outflows) : '—') + '</td>' +
                '<td class="text-right ' + balClass + '">PKR ' + nf(m.balance) + '</td>' +
                '</tr>'
            );
        });
    }

    function nf(n) { return Number(parseFloat(n) || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 }); }
    function esc(s) { return $('<span>').text(s || '').html(); }

})();
