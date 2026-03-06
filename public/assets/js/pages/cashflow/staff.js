"use strict";

var CashflowStaff = (function () {
    var apiBase = '/api/cashflow/';

    function init() {
        loadDropdowns();
        loadSummary();
        bindEvents();

        // Auto-open modal if coming from dashboard quick-action
        if (new URLSearchParams(window.location.search).get('action') === 'add') {
            setTimeout(function () { $('#modal_advance').modal('show'); }, 500);
        }
    }

    function bindEvents() {
        $('#btn-submit-advance').on('click', submitAdvance);
        $('#btn-submit-return').on('click', submitReturn);
        $('#btn-close-ledger').on('click', function () { $('#staff-ledger-card').addClass('d-none'); });
        $('#modal_advance').on('shown.bs.modal', function () {
            var modal = $(this);
            modal.find('[name="user_id"]').select2({ placeholder: 'Select staff', dropdownParent: modal });
            modal.find('[name="pool_id"]').select2({ placeholder: 'Select pool', dropdownParent: modal });
        }).on('hidden.bs.modal', function () {
            $(this).find('.kt-select2-general').select2('destroy');
            $('#form-advance')[0].reset();
        });
        $('#modal_return').on('shown.bs.modal', function () {
            var modal = $(this);
            modal.find('[name="user_id"]').select2({ placeholder: 'Select staff', dropdownParent: modal });
            modal.find('[name="pool_id"]').select2({ placeholder: 'Select pool', dropdownParent: modal });
        }).on('hidden.bs.modal', function () {
            $(this).find('.kt-select2-general').select2('destroy');
            $('#form-return')[0].reset();
        });
    }

    function loadDropdowns() {
        // Eligible staff
        $.ajax({
            url: apiBase + 'staff/eligible', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                var opts = '<option value="">Select staff</option>';
                var retOpts = '<option value="">Select staff</option>';
                $.each(res.data, function (i, s) {
                    opts += '<option value="' + s.id + '" data-balance="' + (s.outstanding || 0) + '">' + esc(s.name) + '</option>';
                    retOpts += '<option value="' + s.id + '">' + esc(s.name) + '</option>';
                });
                $('#advance-staff-select').html(opts);
                $('#return-staff-select').html(retOpts);
            }
        });

        // Pools
        $.ajax({
            url: apiBase + 'lookups', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                var opts = '<option value="">Select pool</option>';
                $.each(res.data.pools, function (i, p) {
                    var label = p.name;
                    opts += '<option value="' + p.id + '">' + esc(label) + '</option>';
                });
                $('#advance-pool-select').html(opts);
                $('#return-pool-select').html(opts);
            }
        });
    }

    function loadSummary() {
        $('#summary-tbody').html('<tr><td colspan="6" class="text-center"><div class="spinner spinner-primary spinner-sm"></div></td></tr>');

        $.ajax({
            url: apiBase + 'staff/summary', type: 'GET',
            success: function (res) {
                if (res.success) renderSummary(res.data);
            },
            error: function () {
                $('#summary-tbody').html('<tr><td colspan="6" class="text-center text-danger">Failed to load.</td></tr>');
            }
        });
    }

    function renderSummary(items) {
        var tbody = $('#summary-tbody').empty();

        if (!items || items.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted">No staff advances recorded yet.</td></tr>');
            return;
        }

        $.each(items, function (i, s) {
            var eligibleBadge = s.is_advance_eligible
                ? '<span class="label label-light-success label-inline">Yes</span>'
                : '<span class="label label-light-danger label-inline">No</span>';

            var outstandingClass = s.outstanding > 0 ? 'text-danger font-weight-bold' : 'text-success';

            // Aging color: green < 15d, amber 15-30d, red > 30d (Sec 16 Screen 5)
            var agingBadge = '';
            if (s.outstanding > 0 && s.days_since_last !== undefined) {
                var days = parseInt(s.days_since_last) || 0;
                if (days > 30) agingBadge = ' <span class="label label-light-danger label-inline font-size-xs">' + days + 'd</span>';
                else if (days > 15) agingBadge = ' <span class="label label-light-warning label-inline font-size-xs">' + days + 'd</span>';
                else agingBadge = ' <span class="label label-light-success label-inline font-size-xs">' + days + 'd</span>';
            }

            tbody.append(
                '<tr>' +
                '<td><a href="javascript:;" class="btn-view-ledger font-weight-bold" data-id="' + s.user_id + '" data-name="' + esc(s.name) + '">' + esc(s.name) + '</a></td>' +
                '<td>' + eligibleBadge + '</td>' +
                '<td class="text-right">PKR ' + nf(s.total_advances) + '</td>' +
                '<td class="text-right">PKR ' + nf(s.total_returns) + '</td>' +
                '<td class="text-right ' + outstandingClass + '">PKR ' + nf(s.outstanding) + agingBadge + '</td>' +
                '<td class="text-center">' +
                    '<button class="btn btn-sm btn-clean btn-icon btn-view-ledger" data-id="' + s.user_id + '" data-name="' + esc(s.name) + '" title="View Ledger"><i class="la la-list-alt text-primary"></i></button>' +
                '</td>' +
                '</tr>'
            );
        });

        $('.btn-view-ledger').off('click').on('click', function () {
            var userId = $(this).data('id');
            var name = $(this).data('name');
            $('#ledger-staff-name').text(name);
            loadLedger(userId);
            $('#staff-ledger-card').removeClass('d-none');
            $('html, body').animate({ scrollTop: $('#staff-ledger-card').offset().top - 80 }, 300);
        });
    }

    function loadLedger(userId) {
        $('#ledger-advances-tbody').html('<tr><td colspan="5" class="text-center"><div class="spinner spinner-primary spinner-sm"></div></td></tr>');
        $('#ledger-returns-tbody').html('<tr><td colspan="5" class="text-center"><div class="spinner spinner-primary spinner-sm"></div></td></tr>');

        $.ajax({
            url: apiBase + 'staff/' + userId + '/ledger', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                var d = res.data;

                $('#ledger-advances').text('PKR ' + nf(d.total_advances));
                $('#ledger-returns').text('PKR ' + nf(d.total_returns));
                $('#ledger-outstanding').text('PKR ' + nf(d.outstanding));

                renderLedgerTable(d.advances, '#ledger-advances-tbody');
                renderLedgerTable(d.returns, '#ledger-returns-tbody');
            }
        });
    }

    function renderLedgerTable(items, tbodySel) {
        var tbody = $(tbodySel).empty();
        if (!items || items.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted">None.</td></tr>');
            return;
        }

        $.each(items, function (i, item) {
            tbody.append(
                '<tr>' +
                '<td>' + fd(item.created_at) + '</td>' +
                '<td>' + (item.pool ? esc(item.pool.name) : '-') + '</td>' +
                '<td class="text-right font-weight-bold">PKR ' + nf(item.amount) + '</td>' +
                '<td>' + esc(item.description || '-') + '</td>' +
                '<td>' + (item.creator ? esc(item.creator.name) : '-') + '</td>' +
                '</tr>'
            );
        });
    }

    function submitAdvance() {
        var form = $('#form-advance');
        var data = {};
        form.find('input, select, textarea').each(function () { var n = $(this).attr('name'); if (n) data[n] = $(this).val(); });

        if (!data.user_id || !data.pool_id || !data.amount) { toastr.warning('Please fill all required fields.'); return; }

        // Warn if staff already has unsettled advance (Sec 8.3)
        var existingBalance = parseFloat($('#advance-staff-select option:selected').data('balance')) || 0;
        if (existingBalance > 0) {
            if (!confirm('Warning: This staff member already has an unsettled advance of PKR ' + Math.round(existingBalance).toLocaleString() + '. Continue?')) {
                return;
            }
        }

        var btn = $(this); btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'staff/advance/store', type: 'POST', data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) { toastr.success(res.message); $('#modal_advance').modal('hide'); loadSummary(); }
                else toastr.error(res.message);
            },
            error: function (xhr) {
                var r = xhr.responseJSON;
                if (r && r.errors) $.each(r.errors, function (f, m) { toastr.error(m[0]); });
                else toastr.error(r ? r.message : 'Failed.');
            },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    function submitReturn() {
        var form = $('#form-return');
        var data = {};
        form.find('input, select, textarea').each(function () { var n = $(this).attr('name'); if (n) data[n] = $(this).val(); });

        if (!data.user_id || !data.pool_id || !data.amount) { toastr.warning('Please fill all required fields.'); return; }

        var btn = $(this); btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'staff/return/store', type: 'POST', data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) { toastr.success(res.message); $('#modal_return').modal('hide'); loadSummary(); }
                else toastr.error(res.message);
            },
            error: function (xhr) {
                var r = xhr.responseJSON;
                if (r && r.errors) $.each(r.errors, function (f, m) { toastr.error(m[0]); });
                else toastr.error(r ? r.message : 'Failed.');
            },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    function esc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
    function nf(n) { return parseFloat(n||0).toLocaleString('en-PK',{maximumFractionDigits:0}); }
    function fd(d) { if(!d) return '-'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }

    return { init: init };
})();

$(document).ready(function () { CashflowStaff.init(); });
