"use strict";

var CashflowStaff = (function () {
    var apiBase = '/api/cashflow/';
    var currentLedgerUserId = null;
    var poolOptionsHtml = '';

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
        $('#btn-submit-edit-advance').on('click', submitEditAdvance);
        $('#btn-close-ledger').on('click', function () { $('#staff-ledger-card').addClass('d-none'); currentLedgerUserId = null; });
        $('#modal_advance').on('shown.bs.modal', function () {
            var mb = $(this).find('.modal-body');
            mb.find('[name="user_id"]').select2({ placeholder: 'Select staff', dropdownParent: mb });
            mb.find('[name="pool_id"]').select2({ placeholder: 'Select pool', dropdownParent: mb });
        }).on('hidden.bs.modal', function () {
            $(this).find('.kt-select2-general').select2('destroy');
            $('#form-advance')[0].reset();
        });
        $('#modal_return').on('shown.bs.modal', function () {
            var mb = $(this).find('.modal-body');
            mb.find('[name="user_id"]').select2({ placeholder: 'Select staff', dropdownParent: mb });
            mb.find('[name="pool_id"]').select2({ placeholder: 'Select pool', dropdownParent: mb });
        }).on('hidden.bs.modal', function () {
            $(this).find('.kt-select2-general').select2('destroy');
            $('#form-return')[0].reset();
        });
        $('#modal_edit_advance').on('shown.bs.modal', function () {
            var mb = $(this).find('.modal-body');
            mb.find('[name="pool_id"]').select2({ placeholder: 'Select pool', dropdownParent: mb });
        }).on('hidden.bs.modal', function () {
            $(this).find('.kt-select2-general').select2('destroy');
            $('#form-edit-advance')[0].reset();
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
                poolOptionsHtml = '<option value="">Select pool</option>';
                $.each(res.data.pools, function (i, p) {
                    var label = p.name;
                    poolOptionsHtml += '<option value="' + p.id + '">' + esc(label) + '</option>';
                });
                $('#advance-pool-select').html(poolOptionsHtml);
                $('#return-pool-select').html(poolOptionsHtml);
                $('#edit-advance-pool-select').html(poolOptionsHtml);
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
        currentLedgerUserId = userId;
        $('#ledger-advances-tbody').html('<tr><td colspan="6" class="text-center"><div class="spinner spinner-primary spinner-sm"></div></td></tr>');
        $('#ledger-returns-tbody').html('<tr><td colspan="6" class="text-center"><div class="spinner spinner-primary spinner-sm"></div></td></tr>');

        $.ajax({
            url: apiBase + 'staff/' + userId + '/ledger', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                var d = res.data;

                $('#ledger-advances').text('PKR ' + nf(d.total_advances));
                $('#ledger-returns').text('PKR ' + nf(d.total_returns));
                $('#ledger-outstanding').text('PKR ' + nf(d.outstanding));

                renderAdvancesTable(d.advances);
                renderReturnsTable(d.returns);
            }
        });
    }

    function renderAdvancesTable(items) {
        var tbody = $('#ledger-advances-tbody').empty();
        if (!items || items.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted">None.</td></tr>');
            return;
        }

        $.each(items, function (i, item) {
            var isVoided = !!item.voided_at;
            var rowClass = isVoided ? 'text-muted' : '';
            var amtStyle = isVoided ? 'text-decoration:line-through;' : '';
            var voidBadge = isVoided ? ' <span class="label label-light-dark label-inline font-size-xs" title="' + esc(item.void_reason || '') + '">VOID</span>' : '';

            var actions = '';
            if (!isVoided) {
                if (typeof cfPerms !== 'undefined' && cfPerms.canEdit) {
                    actions += '<button class="btn btn-sm btn-clean btn-icon btn-edit-advance" data-id="' + item.id + '" data-amount="' + parseInt(item.amount) + '" data-pool="' + item.pool_id + '" data-desc="' + esc(item.description || '') + '" title="Edit"><i class="la la-pencil text-primary"></i></button>';
                }
                if (typeof cfPerms !== 'undefined' && cfPerms.canVoid) {
                    actions += '<button class="btn btn-sm btn-clean btn-icon btn-void-advance" data-id="' + item.id + '" title="Void"><i class="la la-ban text-danger"></i></button>';
                }
            }
            if (typeof cfPerms !== 'undefined' && cfPerms.canAudit) {
                actions += '<button class="btn btn-sm btn-clean btn-icon btn-audit" data-id="' + item.id + '" data-type="advance" title="Audit Trail"><i class="la la-history text-muted"></i></button>';
            }

            tbody.append(
                '<tr class="' + rowClass + '">' +
                '<td>' + fd(item.created_at) + voidBadge + '</td>' +
                '<td>' + (item.pool ? esc(item.pool.name) : '-') + '</td>' +
                '<td class="text-right font-weight-bold" style="' + amtStyle + '">PKR ' + nf(item.amount) + '</td>' +
                '<td>' + esc(item.description || '-') + '</td>' +
                '<td>' + (item.creator ? esc(item.creator.name) : '-') + '</td>' +
                '<td class="text-right">' + actions + '</td>' +
                '</tr>'
            );
        });

        // Bind void handler
        $('.btn-void-advance').off('click').on('click', function () {
            var id = $(this).data('id');
            var reason = prompt('Reason for voiding this advance (min 5 chars):');
            if (reason === null) return;
            if (!reason || reason.length < 5) { toastr.warning('Void reason must be at least 5 characters.'); return; }
            $.ajax({
                url: apiBase + 'staff/advance/' + id + '/void', type: 'POST',
                data: { void_reason: reason },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.success) { toastr.success(res.message); if (currentLedgerUserId) loadLedger(currentLedgerUserId); loadSummary(); }
                    else toastr.error(res.message);
                },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });

        // Bind audit handler for advances
        $('#ledger-advances-tbody .btn-audit').off('click').on('click', function () {
            loadAuditTrail($(this).data('id'), $(this).data('type'));
        });

        // Bind edit handler
        $('.btn-edit-advance').off('click').on('click', function () {
            var btn = $(this);
            var form = $('#form-edit-advance');
            form.find('[name="advance_id"]').val(btn.data('id'));
            form.find('[name="amount"]').val(btn.data('amount'));
            form.find('[name="description"]').val(btn.data('desc'));
            $('#edit-advance-pool-select').val(btn.data('pool'));
            $('#modal_edit_advance').modal('show');
        });
    }

    function renderReturnsTable(items) {
        var tbody = $('#ledger-returns-tbody').empty();
        if (!items || items.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted">None.</td></tr>');
            return;
        }

        $.each(items, function (i, item) {
            var isVoided = !!item.voided_at;
            var rowClass = isVoided ? 'text-muted' : '';
            var amtStyle = isVoided ? 'text-decoration:line-through;' : '';
            var voidBadge = isVoided ? ' <span class="label label-light-dark label-inline font-size-xs" title="' + esc(item.void_reason || '') + '">VOID</span>' : '';

            var actions = '';
            if (!isVoided) {
                if (typeof cfPerms !== 'undefined' && cfPerms.canVoid) {
                    actions += '<button class="btn btn-sm btn-clean btn-icon btn-void-return" data-id="' + item.id + '" title="Void"><i class="la la-ban text-danger"></i></button>';
                }
            }
            if (typeof cfPerms !== 'undefined' && cfPerms.canAudit) {
                actions += '<button class="btn btn-sm btn-clean btn-icon btn-audit" data-id="' + item.id + '" data-type="return" title="Audit Trail"><i class="la la-history text-muted"></i></button>';
            }

            tbody.append(
                '<tr class="' + rowClass + '">' +
                '<td>' + fd(item.created_at) + voidBadge + '</td>' +
                '<td>' + (item.pool ? esc(item.pool.name) : '-') + '</td>' +
                '<td class="text-right font-weight-bold" style="' + amtStyle + '">PKR ' + nf(item.amount) + '</td>' +
                '<td>' + esc(item.description || '-') + '</td>' +
                '<td>' + (item.creator ? esc(item.creator.name) : '-') + '</td>' +
                '<td class="text-right">' + actions + '</td>' +
                '</tr>'
            );
        });

        // Bind audit handler for returns
        $('#ledger-returns-tbody .btn-audit').off('click').on('click', function () {
            loadAuditTrail($(this).data('id'), $(this).data('type'));
        });

        // Bind void handler for returns
        $('.btn-void-return').off('click').on('click', function () {
            var id = $(this).data('id');
            var reason = prompt('Reason for voiding this return (min 5 chars):');
            if (reason === null) return;
            if (!reason || reason.length < 5) { toastr.warning('Void reason must be at least 5 characters.'); return; }
            $.ajax({
                url: apiBase + 'staff/return/' + id + '/void', type: 'POST',
                data: { void_reason: reason },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.success) { toastr.success(res.message); if (currentLedgerUserId) loadLedger(currentLedgerUserId); loadSummary(); }
                    else toastr.error(res.message);
                },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });
    }

    function highlightRequired(form, requiredFields, data) {
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.select2-container').css('border', '').css('border-radius', '');
        var missing = [];
        $.each(requiredFields, function (i, name) {
            if (!data[name]) {
                var el = form.find('[name="' + name + '"]');
                el.addClass('is-invalid');
                el.siblings('.select2-container').css('border', '1px solid #F64E60').css('border-radius', '0.42rem');
                missing.push(name);
            }
        });
        if (missing.length) {
            toastr.warning('Please fill the highlighted fields.');
            var first = form.find('.is-invalid:visible, .select2-container[style*="border"]').first();
            if (first.length) first[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return missing.length === 0;
    }

    function submitEditAdvance() {
        var form = $('#form-edit-advance');
        var data = {};
        form.find('input, select').each(function () { var n = $(this).attr('name'); if (n) data[n] = $(this).val(); });

        if (!highlightRequired(form, ['amount', 'pool_id', 'edit_reason'], data)) return;

        var id = data.advance_id;
        var btn = $('#btn-submit-edit-advance');
        btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'staff/advance/' + id + '/update', type: 'POST', data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) { toastr.success(res.message); $('#modal_edit_advance').modal('hide'); if (currentLedgerUserId) loadLedger(currentLedgerUserId); loadSummary(); }
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

    function submitAdvance() {
        var form = $('#form-advance');
        var data = {};
        form.find('input, select').each(function () { var n = $(this).attr('name'); if (n) data[n] = $(this).val(); });

        if (!highlightRequired(form, ['user_id', 'pool_id', 'amount'], data)) return;

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
        form.find('input, select').each(function () { var n = $(this).attr('name'); if (n) data[n] = $(this).val(); });

        if (!highlightRequired(form, ['user_id', 'pool_id', 'amount'], data)) return;

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

    // ===================== AUDIT TRAIL =====================

    function loadAuditTrail(id, type) {
        $('#audit-loading').removeClass('d-none');
        $('#audit-timeline').addClass('d-none').empty();
        $('#modal_audit').modal('show');

        var url = type === 'return'
            ? apiBase + 'staff/return/' + id + '/audit'
            : apiBase + 'staff/advance/' + id + '/audit';

        $.ajax({
            url: url, type: 'GET',
            success: function (res) {
                $('#audit-loading').addClass('d-none');
                if (res.success && res.data.length > 0) {
                    var html = '';
                    var actionIcons = {
                        created: 'la-plus-circle', updated: 'la-edit', voided: 'la-ban'
                    };
                    var actionColors = {
                        created: '#3699FF', updated: '#8950FC', voided: '#181C32'
                    };

                    $.each(res.data, function (i, log) {
                        var actionBadge = getActionBadge(log.action);
                        var userName = log.user ? log.user.name : 'System';
                        var time = new Date(log.created_at).toLocaleString();
                        var icon = actionIcons[log.action] || 'la-history';
                        var color = actionColors[log.action] || '#7E8299';
                        var isLast = (i === res.data.length - 1);
                        var changeSummary = buildChangeSummary(log);

                        html += '<div class="d-flex align-items-start' + (isLast ? '' : ' mb-4') + '">' +
                            '<div class="flex-shrink-0 mr-4 text-center" style="width:40px;">' +
                            '<div style="width:36px;height:36px;border-radius:50%;background:' + color + '15;display:flex;align-items:center;justify-content:center;">' +
                            '<i class="la ' + icon + '" style="font-size:18px;color:' + color + ';"></i></div>' +
                            (isLast ? '' : '<div style="width:2px;height:20px;background:#E4E6EF;margin:4px auto 0;"></div>') +
                            '</div>' +
                            '<div class="flex-grow-1 pb-3' + (isLast ? '' : ' border-bottom') + '">' +
                            '<div class="d-flex justify-content-between align-items-center">' +
                            '<div>' + actionBadge + ' <span class="font-weight-bold ml-1">' + esc(userName) + '</span></div>' +
                            '<span class="text-muted font-size-xs">' + time + '</span>' +
                            '</div>' +
                            (log.reason ? '<div class="text-muted font-size-sm mt-1"><i class="la la-comment-alt mr-1"></i>' + esc(log.reason) + '</div>' : '') +
                            (changeSummary ? '<div class="mt-1">' + changeSummary + '</div>' : '') +
                            '</div></div>';
                    });
                    $('#audit-timeline').html(html).removeClass('d-none');
                } else {
                    $('#audit-timeline').html('<div class="text-center text-muted py-5"><i class="la la-inbox" style="font-size:40px;"></i><br>No audit records found.</div>').removeClass('d-none');
                }
            },
            error: function () {
                $('#audit-loading').addClass('d-none');
                $('#audit-timeline').html('<div class="text-center text-danger py-3">Failed to load audit trail.</div>').removeClass('d-none');
            }
        });
    }

    function buildChangeSummary(log) {
        if (!log.old_values || !log.new_values) return '';
        if (log.action === 'created') return '';

        var oldV = log.old_values;
        var newV = log.new_values;
        var changes = [];

        var fieldLabels = {
            amount: 'Amount',
            pool_id: 'Pool',
            description: 'Description',
            void_reason: 'Void Reason'
        };

        function getRelName(values, field) {
            if (field === 'pool_id' && values.pool && values.pool.name) return values.pool.name;
            return null;
        }

        function formatVal(field, val, values) {
            if (val === null || val === undefined || val === '') return '(empty)';
            var relName = getRelName(values, field);
            if (relName) return relName;
            if (field === 'amount') return 'PKR ' + parseInt(val).toLocaleString();
            return esc(String(val));
        }

        $.each(fieldLabels, function (field, label) {
            var oldVal = oldV[field];
            var newVal = newV[field];
            var oldNorm = (oldVal === null || oldVal === undefined) ? '' : String(oldVal);
            var newNorm = (newVal === null || newVal === undefined) ? '' : String(newVal);

            if (oldNorm !== newNorm) {
                changes.push(
                    '<span class="font-weight-bold">' + label + ':</span> ' +
                    '<span class="text-danger">' + formatVal(field, oldVal, oldV) + '</span>' +
                    ' <i class="la la-arrow-right font-size-xs"></i> ' +
                    '<span class="text-success">' + formatVal(field, newVal, newV) + '</span>'
                );
            }
        });

        if (changes.length === 0) return '';
        return '<div class="font-size-sm text-muted mt-1" style="line-height:1.8;">' + changes.join('<br>') + '</div>';
    }

    function getActionBadge(action) {
        var colors = {
            created: 'primary', updated: 'info', voided: 'dark'
        };
        var color = colors[action] || 'secondary';
        return '<span class="label label-light-' + color + ' label-inline">' + action.replace('_', ' ').toUpperCase() + '</span>';
    }

    function esc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
    function nf(n) { return parseFloat(n||0).toLocaleString('en-PK',{maximumFractionDigits:0}); }
    function fd(d) { if(!d) return '-'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }

    return { init: init };
})();

$(document).ready(function () { CashflowStaff.init(); });
