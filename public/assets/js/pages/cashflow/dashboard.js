'use strict';

(function () {
    var apiBase = '/api/cashflow/';
    var trendChart = null;
    var categoryChart = null;
    var dashboardXhr = null;
    var isLoading = false;

    $(document).ready(function () {
        bindEvents();
        loadDashboard();
    });

    function bindEvents() {
        $('#btn-refresh-dash').on('click', loadDashboard);
        $('#btn-reconcile').on('click', runReconciliation);

        // Modal cleanup on close
        $('#modal_preview').on('hidden.bs.modal', function () {
            $('#preview-iframe').attr('src', '');
        });
    }

    function getDateRange() {
        return {
            date_from: moment().startOf('month').format('YYYY-MM-DD'),
            date_to: moment().format('YYYY-MM-DD')
        };
    }

    function loadDashboard() {
        if (isLoading && dashboardXhr) {
            dashboardXhr.abort();
        }
        isLoading = true;
        var dr = getDateRange();
        var params = {
            date_from: dr.date_from,
            date_to: dr.date_to
        };

        dashboardXhr = $.ajax({
            url: apiBase + 'dashboard/data', type: 'GET', data: params,
            success: function (res) {
                if (!res.success) { toastr.error(res.message || 'Failed to load dashboard'); return; }
                var d = res.data;

                renderPendingActions(d.pending_actions);
                renderSummary(d.summary);
                renderPools(d.pools);
                renderDailyTrend(d.daily_trend);
                renderCategoryPie(d.category_breakdown);
                renderVendorOutstanding(d.vendor_outstanding);
                renderVendorDueSoon(d.vendor_due_soon);
                renderStaffAdvances(d.staff_advances);
                renderStaffExpenses(d.staff_expenses);
                renderRecentEntries(d.recent_entries);
                renderVoidedAlerts(d.voided_recent);
                renderFlaggedEntries(d.flagged_entries);
                renderPendingExpenses(d.pending_expenses);

                if (d.accountant_widgets) {
                    renderAccountantWidgets(d.accountant_widgets);
                }
                if (d.vendor_trends) {
                    renderVendorTrends(d.vendor_trends);
                }
            },
            error: function (xhr) { if (xhr.statusText !== 'abort') toastr.error('Failed to load dashboard data.'); },
            complete: function () { isLoading = false; dashboardXhr = null; }
        });
    }

    // ===================== RENDER FUNCTIONS =====================

    function renderPendingActions(pa) {
        if (!pa) return;
        $('#pa-pending-count').text(pa.pending_expenses || 0);
        $('#pa-flagged-count').text(pa.flagged_entries || 0);
        $('#pa-no-receipt').text(pa.no_receipt_count || 0);
        $('#pa-today-total').text('PKR ' + nf(pa.today_total || 0));
        $('#pa-mtd-total').text('PKR ' + nf(pa.mtd_total || 0));
        $('#pa-advances-owed').text('PKR ' + nf(pa.advances_outstanding || 0));
    }

    function renderSummary(s) {
        if (!s) return;

        // Month label e.g. "April 2026"
        $('#sum-month-label').text('— ' + moment().format('MMMM YYYY'));

        // Opening balance with "As of [date]" label
        $('#sum-opening').text('PKR ' + nf(s.opening_balance));
        if (s.opening_date) {
            $('#sum-opening-date').text('As of ' + moment(s.opening_date).format('D MMM YYYY'));
        }

        $('#sum-inflows').text('PKR ' + nf(s.inflows));
        $('#sum-outflows').text('PKR ' + nf(s.outflows));
        $('#sum-net').text('PKR ' + nf(s.net));
        $('#sum-closing').text('PKR ' + nf(s.closing_balance));

        $('#sum-net').css('color', s.net < 0 ? '#F64E60' : '');
        $('#sum-closing').css('color', s.closing_balance < 0 ? '#F64E60' : '');
    }

    function renderPools(pools) {
        var cashStrip = $('#pool-balance-strip').empty();
        var bankStrip = $('#pool-balance-strip-bank').empty();
        var bankSection = $('#pool-bank-section').addClass('d-none');

        if (!pools || !pools.length) {
            cashStrip.html('<span class="text-muted py-2">No pools found.</span>');
            $('#pool-total-cash').text('PKR 0');
            $('#pool-total-bank').text('PKR 0');
            return;
        }

        var cashTotal = 0, bankTotal = 0;
        $.each(pools, function (i, p) {
            var bal = parseFloat(p.cached_balance || 0);
            if (p.type === 'bank_account') { bankTotal += bal; } else { cashTotal += bal; }
        });
        $('#pool-total-cash').text('PKR ' + nf(cashTotal));
        $('#pool-total-bank').text('PKR ' + nf(bankTotal));

        $.each(pools, function (i, p) {
            var bal = parseFloat(p.cached_balance || 0);
            var borderColor = bal < 0 ? '#F64E60' : (bal === 0 ? '#E4E6EF' : '#1BC5BD');
            var amtColor = bal < 0 ? '#F64E60' : '#181C32';
            var isBank = p.type === 'bank_account';
            var label = isBank ? esc(p.name) : esc(p.name).replace(/^ALLURA\s*/i, '');
            var borderLeft = isBank ? '3px solid #8950FC' : '3px solid ' + borderColor;

            var card = '<div style="border-left:' + borderLeft + ';background:#F8F9FB;border-radius:4px;padding:4px 10px;display:inline-flex;align-items:center;gap:6px;">' +
                '<span style="font-size:11px;color:#7E8299;white-space:nowrap;">' + label + '</span>' +
                '<span style="font-size:12px;font-weight:700;color:' + amtColor + ';white-space:nowrap;">PKR ' + nf(bal) + '</span>' +
                '</div>';

            if (isBank) {
                bankStrip.append(card);
                bankSection.removeClass('d-none');
            } else {
                cashStrip.append(card);
            }
        });
    }

    function renderDailyTrend(trend) {
        if (!trend || !trend.length) return;

        var labels = [], inData = [], outData = [];
        $.each(trend, function (i, d) {
            labels.push(d.date.substring(5)); // MM-DD
            inData.push(d.inflows);
            outData.push(d.outflows);
        });

        var ctx = document.getElementById('chart-daily-trend');
        if (!ctx) return;

        if (trendChart) trendChart.destroy();
        trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Inflows', data: inData, borderColor: '#1BC5BD', backgroundColor: 'rgba(27,197,189,0.1)', fill: true, tension: 0.3 },
                    { label: 'Outflows', data: outData, borderColor: '#F64E60', backgroundColor: 'rgba(246,78,96,0.1)', fill: true, tension: 0.3 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return 'PKR ' + nf(v); } } } }
            }
        });
    }

    function renderCategoryPie(cats) {
        if (!cats || !cats.length) return;

        var labels = [], data = [], colors = [
            '#6993FF', '#1BC5BD', '#FFA800', '#F64E60', '#8950FC',
            '#3699FF', '#E4E6EF', '#F3F6F9', '#181C32', '#B5B5C3', '#D1D3E0', '#7E8299', '#F1416C'
        ];

        $.each(cats, function (i, c) {
            labels.push(c.category);
            data.push(parseFloat(c.total));
        });

        var ctx = document.getElementById('chart-category-pie');
        if (!ctx) return;

        if (categoryChart) categoryChart.destroy();
        categoryChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: colors.slice(0, data.length) }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
            }
        });
    }

    function renderVendorOutstanding(vendors) {
        var tbody = $('#vendor-outstanding-tbody').empty();
        if (!vendors || !vendors.length) { tbody.html('<tr><td colspan="3" class="text-center text-muted">No outstanding</td></tr>'); return; }

        $.each(vendors, function (i, v) {
            tbody.append(
                '<tr>' +
                '<td>' + esc(v.name) + '</td>' +
                '<td class="text-right font-weight-bold">PKR ' + nf(v.cached_balance) + '</td>' +
                '<td>' + esc(v.payment_terms || '-') + '</td>' +
                '</tr>'
            );
        });
    }

    function renderStaffAdvances(staff) {
        var tbody = $('#staff-advances-tbody').empty();
        if (!staff || !staff.length) { tbody.html('<tr><td colspan="4" class="text-center text-muted">No outstanding advances</td></tr>'); return; }

        $.each(staff, function (i, s) {
            var agingBadge = '<span class="label label-light-success label-inline">' + s.days_since_last + 'd</span>';
            if (s.aging === 'amber') agingBadge = '<span class="label label-light-warning label-inline">' + s.days_since_last + 'd</span>';
            if (s.aging === 'red') agingBadge = '<span class="label label-light-danger label-inline">' + s.days_since_last + 'd</span>';

            tbody.append(
                '<tr>' +
                '<td>' + esc(s.name) + '</td>' +
                '<td class="text-right font-weight-bold">PKR ' + nf(s.outstanding) + '</td>' +
                '<td class="text-center">' + s.days_since_last + '</td>' +
                '<td class="text-center">' + agingBadge + '</td>' +
                '</tr>'
            );
        });
    }

    function renderStaffExpenses(expenses) {
        var tbody = $('#staff-expenses-tbody').empty();
        if (!expenses || !expenses.length) { tbody.html('<tr><td colspan="5" class="text-center text-muted">No staff expenses found</td></tr>'); return; }

        $.each(expenses, function (i, e) {
            var statusBadge = '<span class="label label-light-success label-inline">' + esc(e.status) + '</span>';
            if (e.status === 'pending') statusBadge = '<span class="label label-light-warning label-inline">Pending</span>';
            if (e.status === 'rejected') statusBadge = '<span class="label label-light-danger label-inline">Rejected</span>';

            tbody.append(
                '<tr>' +
                '<td>' + formatDate(e.expense_date) + '</td>' +
                '<td>' + (e.staff ? esc(e.staff.name) : '-') + '</td>' +
                '<td>' + esc(truncate(e.description || '', 30)) + '</td>' +
                '<td class="text-right font-weight-bold">PKR ' + nf(e.amount) + '</td>' +
                '<td class="text-center">' + statusBadge + '</td>' +
                '</tr>'
            );
        });
    }

    function renderVendorDueSoon(vendors) {
        var tbody = $('#vendor-due-tbody').empty();
        if (!vendors || !vendors.length) { tbody.html('<tr><td colspan="5" class="text-center text-muted py-3">No upcoming payments due</td></tr>'); return; }

        $.each(vendors, function (i, v) {
            var statusBadge;
            if (v.is_overdue) {
                statusBadge = '<span class="label label-danger label-inline">Overdue ' + Math.abs(v.days_until_due) + 'd</span>';
            } else if (v.days_until_due <= 2) {
                statusBadge = '<span class="label label-warning label-inline">Due in ' + v.days_until_due + 'd</span>';
            } else {
                statusBadge = '<span class="label label-light-info label-inline">Due in ' + v.days_until_due + 'd</span>';
            }

            var dateStr = '';
            if (v.due_date) {
                var d = new Date(v.due_date);
                dateStr = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
            }

            tbody.append(
                '<tr>' +
                '<td class="py-3 px-4">' + esc(v.name) + '</td>' +
                '<td class="py-3 px-4 text-right font-weight-bold">PKR ' + nf(v.balance) + '</td>' +
                '<td class="py-3 px-4">' + esc(v.payment_terms || '') + '</td>' +
                '<td class="py-3 px-4">' + dateStr + '</td>' +
                '<td class="py-3 px-4">' + statusBadge + '</td>' +
                '</tr>'
            );
        });
    }

    function renderPendingExpenses(expenses) {
        var tbody = $('#pending-list-tbody').empty();
        if (!expenses || !expenses.length) { $('#pending-list-row').addClass('d-none'); return; }

        $('#pending-list-row').removeClass('d-none');
        $.each(expenses, function (i, exp) {
            var dateStr = '';
            if (exp.expense_date) {
                var d = new Date(exp.expense_date);
                dateStr = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
            }
            var attachIcon = exp.attachment_url
                ? '<a href="javascript:;" class="btn-preview-dash" data-url="' + esc(exp.attachment_url) + '"><i class="la la-paperclip text-success" title="Has attachment"></i></a>'
                : '<i class="la la-times text-danger" title="No attachment"></i>';
            var actions = '<button class="btn btn-xs btn-light-success mr-1 btn-dash-approve" data-id="' + exp.id + '" data-attach="' + (exp.attachment_url ? '1' : '0') + '"><i class="la la-check"></i></button>' +
                '<button class="btn btn-xs btn-light-danger btn-dash-reject" data-id="' + exp.id + '"><i class="la la-times"></i></button>';

            tbody.append(
                '<tr>' +
                '<td class="py-2 px-4">' + dateStr + '</td>' +
                '<td class="py-2 px-4">' + esc(exp.description || '') + '</td>' +
                '<td class="py-2 px-4">' + (exp.category ? esc(exp.category.name) : '-') + '</td>' +
                '<td class="py-2 px-4 text-right font-weight-bold">PKR ' + nf(exp.amount) + '</td>' +
                '<td class="py-2 px-4">' + (exp.creator ? esc(exp.creator.name) : '-') + '</td>' +
                '<td class="py-2 px-4 text-center">' + attachIcon + '</td>' +
                '<td class="py-2 px-4 text-center">' + actions + '</td>' +
                '</tr>'
            );
        });

        // Bind inline approve
        $('.btn-dash-approve').off('click').on('click', function () {
            var id = $(this).data('id');
            var hasAttach = $(this).data('attach');
            if (!hasAttach) { toastr.error('Cannot approve: attachment must be present.'); return; }
            if (!confirm('Approve this expense?')) return;
            $.ajax({
                url: apiBase + 'expenses/' + id + '/approve', type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) { toastr.success(res.message || 'Approved.'); loadDashboard(); },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });

        // Bind inline reject
        $('.btn-dash-reject').off('click').on('click', function () {
            var id = $(this).data('id');
            var reason = prompt('Rejection reason (required):');
            if (!reason || reason.length < 5) { toastr.warning('Reason must be at least 5 characters.'); return; }
            $.ajax({
                url: apiBase + 'expenses/' + id + '/reject', type: 'POST',
                data: { rejection_reason: reason },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) { toastr.success(res.message || 'Rejected.'); loadDashboard(); },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });

        // Bind attachment preview
        $('.btn-preview-dash').off('click').on('click', function () {
            var url = $(this).data('url');
            if (!url) return;
            var previewUrl = getDrivePreviewUrl(url);
            if (previewUrl) {
                $('#preview-iframe').attr('src', previewUrl);
                $('#preview-open-new').attr('href', url);
                $('#modal_preview').modal('show');
            } else {
                window.open(url, '_blank');
            }
        });
    }

    function renderVoidedAlerts(voided) {
        var tbody = $('#voided-alerts-tbody').empty();
        if (!voided || !voided.length) { $('#voided-alerts-col').addClass('d-none'); return; }

        $('#voided-alerts-col').removeClass('d-none');
        $.each(voided, function (i, v) {
            var dateStr = '';
            if (v.voided_at) {
                var d = new Date(v.voided_at);
                dateStr = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
            }
            tbody.append(
                '<tr>' +
                '<td class="py-2 px-4">' + dateStr + '</td>' +
                '<td class="py-2 px-4">' + esc(v.description || '') + '</td>' +
                '<td class="py-2 px-4 text-right font-weight-bold text-danger">PKR ' + nf(v.amount) + '</td>' +
                '<td class="py-2 px-4">' + esc(v.void_reason || '') + '</td>' +
                '</tr>'
            );
        });
    }

    function renderFlaggedEntries(flagged) {
        var tbody = $('#flagged-alerts-tbody').empty();
        if (!flagged || !flagged.length) { $('#flagged-alerts-col').addClass('d-none'); return; }

        $('#flagged-alerts-col').removeClass('d-none');
        $.each(flagged, function (i, f) {
            var dateStr = '';
            if (f.expense_date) {
                var d = new Date(f.expense_date);
                dateStr = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
            }
            tbody.append(
                '<tr>' +
                '<td class="py-2 px-4">' + dateStr + '</td>' +
                '<td class="py-2 px-4">' + esc(f.description || '') + '</td>' +
                '<td class="py-2 px-4 text-right font-weight-bold text-warning">PKR ' + nf(f.amount) + '</td>' +
                '<td class="py-2 px-4">' + esc(f.flag_reason || '') + '</td>' +
                '</tr>'
            );
        });
    }

    function renderRecentEntries(entries) {
        var tbody = $('#recent-entries-tbody').empty();
        if (!entries || !entries.length) { tbody.html('<tr><td colspan="5" class="text-center text-muted">No entries today</td></tr>'); return; }

        $.each(entries, function (i, e) {
            var dateStr = '';
            if (e.expense_date) {
                var d = new Date(e.expense_date);
                dateStr = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
            }
            tbody.append(
                '<tr>' +
                '<td class="py-3 px-4">' + esc(dateStr) + '</td>' +
                '<td class="py-3 px-4">' + esc(e.category ? e.category.name : '') + '</td>' +
                '<td class="py-3 px-4 text-right font-weight-bold">PKR ' + nf(e.amount) + '</td>' +
                '<td class="py-3 px-4">' + esc(e.pool ? e.pool.name : '') + '</td>' +
                '<td class="py-3 px-4">' + esc(e.creator ? e.creator.name : '') + '</td>' +
                '</tr>'
            );
        });
    }

    function renderAccountantWidgets(aw) {
        $('#accountant-widgets-row').removeClass('d-none');
        $('#aw-entries-count').text(aw.my_entries_today ? aw.my_entries_today.count : 0);
        $('#aw-entries-total').text('PKR ' + nf(aw.my_entries_today ? aw.my_entries_today.total : 0));
        $('#aw-rejected').text(aw.rejected_needing_reentry || 0);
        $('#aw-missing').text(aw.missing_attachments || 0);
    }

    function renderVendorTrends(trends) {
        if (!trends || !trends.length) return;
        var html = '';
        $.each(trends, function (i, t) {
            html += '<div class="d-flex justify-content-between mb-1"><span class="font-size-xs">' + esc(t.vendor_name) + '</span><span class="font-weight-bold font-size-xs">PKR ' + nf(t.total) + '</span></div>';
        });
        $('#aw-vendor-trends').html(html);
    }

    // ===================== RECONCILIATION =====================

    function runReconciliation() {
        var btn = $('#btn-reconcile');
        btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> Checking...');

        $.ajax({
            url: apiBase + 'dashboard/reconciliation', type: 'GET',
            success: function (res) {
                var $r = $('#reconcile-result').removeClass('d-none');
                if (!res.success) { $r.html('<div class="alert alert-danger">' + esc(res.message) + '</div>'); return; }

                var d = res.data;
                var cls = d.is_balanced ? 'alert-success' : 'alert-danger';
                var icon = d.is_balanced ? '<i class="la la-check-circle font-size-h3"></i>' : '<i class="la la-exclamation-triangle font-size-h3"></i>';
                var status = d.is_balanced ? 'BALANCED' : 'DISCREPANCY: PKR ' + nf(Math.abs(d.discrepancy));

                $r.html(
                    '<div class="alert ' + cls + ' text-center mb-0">' + icon + '<br/>' +
                    '<strong>' + status + '</strong>' +
                    '<div class="font-size-xs mt-2">' +
                    'Cached: PKR ' + nf(d.cached_total) + '<br/>' +
                    'Calculated: PKR ' + nf(d.calculated_total) +
                    '</div></div>'
                );
            },
            error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Reconciliation failed.'); },
            complete: function () { btn.prop('disabled', false).html('<i class="la la-check-circle"></i> Run Reconciliation Check'); }
        });
    }

    // ===================== HELPERS =====================

    function nf(n) { return Number(parseFloat(n) || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 }); }
    function esc(s) { return $('<span>').text(s || '').html(); }
    function formatDate(d) { if (!d) return '-'; if (typeof d === 'string') { var p = d.split(/[-T]/); return p[2] + ' ' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][parseInt(p[1],10)-1] + ' ' + p[0]; } return d.toISOString().split('T')[0]; }
    function truncate(s, n) { return s && s.length > n ? s.substring(0, n) + '...' : (s || ''); }
    
    function getDrivePreviewUrl(url) {
        if (!url) return null;
        var match;
        // https://drive.google.com/file/d/{ID}/...
        match = url.match(/drive\.google\.com\/file\/d\/([^\/\?]+)/);
        if (match) return 'https://drive.google.com/file/d/' + match[1] + '/preview';
        // https://drive.google.com/open?id={ID}
        match = url.match(/drive\.google\.com\/open\?id=([^&]+)/);
        if (match) return 'https://drive.google.com/file/d/' + match[1] + '/preview';
        // https://docs.google.com/...
        if (/docs\.google\.com/.test(url)) return url.replace(/\/edit.*$/, '/preview');
        return null;
    }

})();
