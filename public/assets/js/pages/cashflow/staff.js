"use strict";

var CashflowStaff = (function () {
    var apiBase = '/api/cashflow/';
    var currentLedgerUserId = null;
    var poolOptionsHtml = '';
    var summaryData = [];

    function init() {
        loadDropdowns();
        loadSummary();
        bindEvents();

        if (new URLSearchParams(window.location.search).get('action') === 'add') {
            setTimeout(function () { $('#modal_advance').modal('show'); }, 500);
        }
    }

    function bindEvents() {
        $('#btn-submit-advance').on('click', submitAdvance);
        $('#btn-submit-return').on('click', submitReturn);
        $('#btn-submit-edit-advance').on('click', submitEditAdvance);

        $('#btn-close-ledger').on('click', function () {
            currentLedgerUserId = null;
            $('#staff-ledger-panel').addClass('d-none');
            $('#staff-overview').removeClass('d-none');
            $('#staff-list .staff-list-item').removeClass('active bg-light-primary');
        });

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

    // ===================== DROPDOWNS =====================

    function loadDropdowns() {
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

        $.ajax({
            url: apiBase + 'lookups', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                poolOptionsHtml = '<option value="">Select pool</option>';
                $.each(res.data.pools, function (i, p) {
                    poolOptionsHtml += '<option value="' + p.id + '">' + esc(p.name) + '</option>';
                });
                $('#advance-pool-select').html(poolOptionsHtml);
                $('#return-pool-select').html(poolOptionsHtml);
                $('#edit-advance-pool-select').html(poolOptionsHtml);
            }
        });
    }

    // ===================== LEFT PANEL – STAFF LIST =====================

    function loadSummary() {
        $('#staff-list').html('<div class="text-center text-muted py-5"><div class="spinner spinner-primary spinner-sm"></div></div>');

        $.ajax({
            url: apiBase + 'staff/summary', type: 'GET',
            success: function (res) {
                if (res.success) {
                    summaryData = res.data || [];
                    renderStaffList(summaryData);
                    renderOverviewStats(summaryData);
                }
            },
            error: function () {
                $('#staff-list').html('<div class="text-center text-danger py-3">Failed to load.</div>');
            }
        });

        loadRecentActivity();
    }

    function loadRecentActivity() {
        $.ajax({
            url: apiBase + 'staff/recent-activity', type: 'GET',
            success: function (res) {
                if (res.success) {
                    renderRecentList('#overview-recent-advances', res.data.advances, 'danger');
                    renderRecentList('#overview-recent-returns', res.data.returns, 'success');
                }
            },
            error: function () {
                $('#overview-recent-advances').html('<div class="text-center text-danger font-size-sm py-2">Failed to load.</div>');
                $('#overview-recent-returns').html('<div class="text-center text-danger font-size-sm py-2">Failed to load.</div>');
            }
        });
    }

    function renderRecentList(selector, items, amtColor) {
        var container = $(selector).empty();

        if (!items || items.length === 0) {
            container.html('<div class="text-center text-muted font-size-sm py-3">No records yet.</div>');
            return;
        }

        var html = '';
        $.each(items, function (i, item) {
            var desc = item.description || '—';
            var sub = esc(item.staff_name || '') + (item.date ? ' · ' + fdShort(item.date) : '');
            html +=
                '<div class="d-flex justify-content-between align-items-start py-2' + (i < items.length - 1 ? ' border-bottom' : '') + '">' +
                    '<div style="min-width:0;">' +
                        '<div class="font-weight-bold font-size-sm text-truncate">' + esc(desc) + '</div>' +
                        '<div class="text-muted font-size-xs mt-1">' + sub + '</div>' +
                    '</div>' +
                    '<div class="ml-3 flex-shrink-0 font-weight-bolder font-size-sm text-' + amtColor + '">PKR ' + nf(item.amount) + '</div>' +
                '</div>';
        });

        container.html(html);
    }

    function fdShort(d) {
        if (!d) return '';
        var parts = String(d).substring(0, 10).split('-');
        if (parts.length < 3) return d;
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return parseInt(parts[2]) + ' ' + (months[parseInt(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
    }

    function renderStaffList(items) {
        var container = $('#staff-list').empty();

        if (!items || items.length === 0) {
            container.html('<div class="text-center text-muted py-5">No staff advances recorded yet.</div>');
            return;
        }

        $.each(items, function (i, s) {
            var outstandingClass = s.outstanding > 0 ? 'text-danger font-weight-bold' : 'text-success';
            var eligibleBadge = s.is_advance_eligible
                ? '<span class="label label-light-success label-inline font-size-xs">Eligible</span>'
                : '<span class="label label-light-secondary label-inline font-size-xs">Ineligible</span>';

            var agingHtml = '';
            if (s.outstanding > 0 && s.days_since_last !== undefined) {
                var days = parseInt(s.days_since_last) || 0;
                var agingColor = days > 30 ? 'danger' : (days > 15 ? 'warning' : 'success');
                agingHtml = ' <span class="label label-light-' + agingColor + ' label-inline font-size-xs">' + days + 'd</span>';
            }

            container.append(
                '<div class="staff-list-item d-flex align-items-center justify-content-between px-3 py-3 rounded cursor-pointer" ' +
                'data-id="' + s.user_id + '" data-name="' + esc(s.name) + '" data-eligible="' + (s.is_advance_eligible ? 'Eligible' : 'Ineligible') + '" ' +
                'style="border-bottom:1px solid #F3F6F9;cursor:pointer;" ' +
                'onmouseover="this.style.background=\'#F3F6F9\'" onmouseout="if(!$(this).hasClass(\'active\')){this.style.background=\'\'}">' +
                    '<div>' +
                        '<div class="font-weight-bold font-size-sm">' + esc(s.name) + '</div>' +
                        '<div class="mt-1">' + eligibleBadge + '</div>' +
                    '</div>' +
                    '<div class="text-right">' +
                        '<div class="' + outstandingClass + ' font-size-sm font-weight-bold">PKR ' + nf(s.outstanding) + agingHtml + '</div>' +
                        '<div class="text-muted font-size-xs mt-1">Outstanding</div>' +
                    '</div>' +
                '</div>'
            );
        });

        $('#staff-list').on('click', '.staff-list-item', function () {
            var userId = $(this).data('id');
            var name = $(this).data('name');
            var eligible = $(this).data('eligible');

            $('#staff-list .staff-list-item').css('background', '').removeClass('active');
            $(this).css('background', '#EEF6FF').addClass('active');

            $('#ledger-staff-name').text(name);
            $('#ledger-staff-eligible').text(eligible);

            $('#staff-overview').addClass('d-none');
            $('#staff-ledger-panel').removeClass('d-none');

            loadLedger(userId);
        });
    }

    function renderOverviewStats(items) {
        var totalOutstanding = 0, totalAdvances = 0, totalReturns = 0, withBalance = 0;
        $.each(items, function (i, s) {
            totalOutstanding += parseFloat(s.outstanding || 0);
            totalAdvances += parseFloat(s.total_advances || 0);
            totalReturns += parseFloat(s.total_returns || 0);
            if (s.outstanding > 0) withBalance++;
        });
        $('#ov-outstanding').text('PKR ' + nf(totalOutstanding));
        $('#ov-staff-count').text(withBalance + ' staff with balance');
        $('#ov-advances').text('PKR ' + nf(totalAdvances));
        $('#ov-returns').text('PKR ' + nf(totalReturns));
        renderTopOutstanding(items);
    }

    function renderTopOutstanding(items) {
        var container = $('#overview-top-outstanding').empty();

        var withBalance = $.grep(items, function (s) { return parseFloat(s.outstanding || 0) > 0; });
        withBalance.sort(function (a, b) { return parseFloat(b.outstanding) - parseFloat(a.outstanding); });
        var top = withBalance.slice(0, 5);

        if (top.length === 0) {
            container.html(
                '<div class="text-center text-muted py-5">' +
                '<i class="la la-check-circle text-success" style="font-size:36px;"></i>' +
                '<p class="mt-2 mb-0 font-size-sm">No outstanding advances.</p>' +
                '</div>'
            );
            return;
        }

        var maxAmt = parseFloat(top[0].outstanding);
        var html = '';

        $.each(top, function (i, s) {
            var days = parseInt(s.days_since_last) || 0;
            var agingColor = days > 30 ? '#F64E60' : (days > 15 ? '#FFA800' : '#1BC5BD');
            var agingLabel = days > 0 ? days + 'd' : '';
            var pct = maxAmt > 0 ? Math.round((parseFloat(s.outstanding) / maxAmt) * 100) : 0;
            var barColor = days > 30 ? '#F64E60' : (days > 15 ? '#FFA800' : '#3699FF');
            var rank = i + 1;

            html +=
                '<div class="d-flex align-items-center mb-4 staff-list-item" data-id="' + s.user_id + '" data-name="' + esc(s.name) + '" data-eligible="' + (s.is_advance_eligible ? 'Eligible' : 'Ineligible') + '" style="cursor:pointer;">' +
                    '<div class="flex-shrink-0 mr-3 text-center" style="width:28px;">' +
                        '<span class="font-weight-bolder font-size-sm text-muted">#' + rank + '</span>' +
                    '</div>' +
                    '<div class="flex-grow-1">' +
                        '<div class="d-flex justify-content-between align-items-center mb-1">' +
                            '<span class="font-weight-bold font-size-sm">' + esc(s.name) + '</span>' +
                            '<div class="d-flex align-items-center">' +
                                (agingLabel ? '<span class="label label-inline font-size-xs mr-2" style="background:' + agingColor + '18;color:' + agingColor + ';font-weight:600;">' + agingLabel + '</span>' : '') +
                                '<span class="font-weight-bolder font-size-sm text-danger">PKR ' + nf(s.outstanding) + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div style="background:#F3F6F9;border-radius:4px;height:5px;overflow:hidden;">' +
                            '<div style="height:5px;border-radius:4px;background:' + barColor + ';width:' + pct + '%;transition:width 0.4s;"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        });

        if (withBalance.length > 5) {
            html += '<div class="text-center text-muted font-size-xs mt-1">+ ' + (withBalance.length - 5) + ' more — select from list</div>';
        }

        container.html(html);

        container.find('.staff-list-item').on('click', function () {
            var userId = $(this).data('id');
            var name = $(this).data('name');
            var eligible = $(this).data('eligible');

            $('#staff-list .staff-list-item').css('background', '').removeClass('active');
            $('#staff-list .staff-list-item[data-id="' + userId + '"]').css('background', '#EEF6FF').addClass('active');

            $('#ledger-staff-name').text(name);
            $('#ledger-staff-eligible').text(eligible);
            $('#staff-overview').addClass('d-none');
            $('#staff-ledger-panel').removeClass('d-none');
            loadLedger(userId);
        });
    }

    // ===================== RIGHT PANEL – LEDGER =====================

    function loadLedger(userId) {
        currentLedgerUserId = userId;
        $('#ledger-advances-tbody').html('<tr><td colspan="6" class="text-center py-3"><div class="spinner spinner-primary spinner-sm"></div></td></tr>');
        $('#ledger-returns-tbody').html('<tr><td colspan="6" class="text-center py-3"><div class="spinner spinner-primary spinner-sm"></div></td></tr>');
        $('#ledger-expenses-tbody').html('<tr><td colspan="6" class="text-center py-3"><div class="spinner spinner-primary spinner-sm"></div></td></tr>');

        $.ajax({
            url: apiBase + 'staff/' + userId + '/ledger', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                var d = res.data;

                var outstandingClass = d.outstanding > 0 ? 'text-danger' : 'text-success';
                $('#ledger-advances').text('PKR ' + nf(d.total_advances));
                $('#ledger-returns').text('PKR ' + nf(d.total_returns));
                $('#ledger-expenses').text('PKR ' + nf(d.total_expenses || 0));
                $('#ledger-outstanding').text('PKR ' + nf(d.outstanding)).removeClass('text-danger text-success').addClass(outstandingClass);

                renderAdvancesTable(d.advances);
                renderReturnsTable(d.returns);
                renderExpensesTable(d.expenses);
            },
            error: function () {
                $('#ledger-advances-tbody').html('<tr><td colspan="6" class="text-center text-danger">Failed to load.</td></tr>');
                $('#ledger-returns-tbody').html('');
            }
        });
    }

    function renderAdvancesTable(items) {
        var tbody = $('#ledger-advances-tbody').empty();
        if (!items || items.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-3">No advances recorded.</td></tr>');
            return;
        }

        $.each(items, function (i, item) {
            var isVoided = !!item.voided_at;
            var rowClass = isVoided ? 'text-muted' : '';
            var amtStyle = isVoided ? 'text-decoration:line-through;' : '';
            var voidBadge = isVoided ? ' <span class="label label-light-dark label-inline font-size-xs" title="' + esc(item.void_reason || '') + '">VOID</span>' : '';

            var actions = '';
            if (!isVoided) {
                if (cfPerms.canEdit) {
                    actions += '<button class="btn btn-sm btn-clean btn-icon btn-edit-advance" data-id="' + item.id + '" data-amount="' + parseInt(item.amount) + '" data-pool="' + item.pool_id + '" data-desc="' + esc(item.description || '') + '" title="Edit"><i class="la la-pencil text-primary"></i></button>';
                }
                if (cfPerms.canVoid) {
                    actions += '<button class="btn btn-sm btn-clean btn-icon btn-void-advance" data-id="' + item.id + '" title="Void"><i class="la la-ban text-danger"></i></button>';
                }
            }
            if (cfPerms.canAudit) {
                actions += '<button class="btn btn-sm btn-clean btn-icon btn-audit" data-id="' + item.id + '" data-type="advance" title="Audit Trail"><i class="la la-history text-muted"></i></button>';
            }

            tbody.append(
                '<tr class="' + rowClass + '">' +
                '<td class="font-size-sm">' + fd(item.created_at) + voidBadge + '</td>' +
                '<td class="font-size-sm">' + (item.pool ? esc(item.pool.name) : '-') + '</td>' +
                '<td class="text-right font-weight-bold font-size-sm" style="' + amtStyle + '">PKR ' + nf(item.amount) + '</td>' +
                '<td class="font-size-sm">' + esc(item.description || '-') + '</td>' +
                '<td class="font-size-sm">' + (item.creator ? esc(item.creator.name) : '-') + '</td>' +
                '<td class="text-right">' + actions + '</td>' +
                '</tr>'
            );
        });

        tbody.find('.btn-void-advance').on('click', function () {
            var id = $(this).data('id');
            var reason = prompt('Reason for voiding this advance (min 5 chars):');
            if (reason === null) return;
            if (!reason || reason.length < 5) { toastr.warning('Void reason must be at least 5 characters.'); return; }
            $.ajax({
                url: apiBase + 'staff/advance/' + id + '/void', type: 'POST',
                data: { void_reason: reason },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.success) { toastr.success(res.message); loadLedger(currentLedgerUserId); loadSummary(); }
                    else toastr.error(res.message);
                },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });

        tbody.find('.btn-audit').on('click', function () {
            loadAuditTrail($(this).data('id'), $(this).data('type'));
        });

        tbody.find('.btn-edit-advance').on('click', function () {
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
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-3">No returns recorded.</td></tr>');
            return;
        }

        $.each(items, function (i, item) {
            var isVoided = !!item.voided_at;
            var rowClass = isVoided ? 'text-muted' : '';
            var amtStyle = isVoided ? 'text-decoration:line-through;' : '';
            var voidBadge = isVoided ? ' <span class="label label-light-dark label-inline font-size-xs" title="' + esc(item.void_reason || '') + '">VOID</span>' : '';

            var actions = '';
            if (!isVoided && cfPerms.canReturnVoid) {
                actions += '<button class="btn btn-sm btn-clean btn-icon btn-void-return" data-id="' + item.id + '" title="Void"><i class="la la-ban text-danger"></i></button>';
            }
            if (cfPerms.canAudit) {
                actions += '<button class="btn btn-sm btn-clean btn-icon btn-audit" data-id="' + item.id + '" data-type="return" title="Audit Trail"><i class="la la-history text-muted"></i></button>';
            }

            tbody.append(
                '<tr class="' + rowClass + '">' +
                '<td class="font-size-sm">' + fd(item.created_at) + voidBadge + '</td>' +
                '<td class="font-size-sm">' + (item.pool ? esc(item.pool.name) : '-') + '</td>' +
                '<td class="text-right font-weight-bold font-size-sm" style="' + amtStyle + '">PKR ' + nf(item.amount) + '</td>' +
                '<td class="font-size-sm">' + esc(item.description || '-') + '</td>' +
                '<td class="font-size-sm">' + (item.creator ? esc(item.creator.name) : '-') + '</td>' +
                '<td class="text-right">' + actions + '</td>' +
                '</tr>'
            );
        });

        tbody.find('.btn-void-return').on('click', function () {
            var id = $(this).data('id');
            var reason = prompt('Reason for voiding this return (min 5 chars):');
            if (reason === null) return;
            if (!reason || reason.length < 5) { toastr.warning('Void reason must be at least 5 characters.'); return; }
            $.ajax({
                url: apiBase + 'staff/return/' + id + '/void', type: 'POST',
                data: { void_reason: reason },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.success) { toastr.success(res.message); loadLedger(currentLedgerUserId); loadSummary(); }
                    else toastr.error(res.message);
                },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });

        tbody.find('.btn-audit').on('click', function () {
            loadAuditTrail($(this).data('id'), $(this).data('type'));
        });
    }

    function renderExpensesTable(items) {
        var tbody = $('#ledger-expenses-tbody').empty();
        if (!items || items.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-3">No expenses recorded against this staff.</td></tr>');
            return;
        }

        $.each(items, function (i, item) {
            var statusBadge = '';
            if (item.status === 'approved') statusBadge = '<span class="label label-light-success label-inline font-size-xs">Approved</span>';
            else if (item.status === 'pending') statusBadge = '<span class="label label-light-warning label-inline font-size-xs">Pending</span>';
            else if (item.status === 'rejected') statusBadge = '<span class="label label-light-danger label-inline font-size-xs">Rejected</span>';
            else statusBadge = '<span class="label label-light-dark label-inline font-size-xs">' + esc(item.status || '-') + '</span>';

            tbody.append(
                '<tr>' +
                '<td class="font-size-sm">' + fd(item.expense_date) + '</td>' +
                '<td class="font-size-sm">' + (item.category ? esc(item.category.name) : '-') + '</td>' +
                '<td class="text-right font-weight-bold font-size-sm">PKR ' + nf(item.amount) + '</td>' +
                '<td class="font-size-sm">' + esc(item.description || '-') + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td class="font-size-sm">' + (item.creator ? esc(item.creator.name) : '-') + '</td>' +
                '</tr>'
            );
        });
    }

    // ===================== FORM SUBMISSIONS =====================

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
        }
        return missing.length === 0;
    }

    function submitAdvance() {
        var form = $('#form-advance');
        var data = {};
        form.find('input, select').each(function () { var n = $(this).attr('name'); if (n) data[n] = $(this).val(); });
        if (!highlightRequired(form, ['user_id', 'pool_id', 'amount'], data)) return;

        var existingBalance = parseFloat($('#advance-staff-select option:selected').data('balance')) || 0;
        if (existingBalance > 0) {
            if (!confirm('Warning: This staff member already has an unsettled advance of PKR ' + Math.round(existingBalance).toLocaleString() + '. Continue?')) return;
        }

        var btn = $('#btn-submit-advance').prop('disabled', true);
        $.ajax({
            url: apiBase + 'staff/advance/store', type: 'POST', data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal_advance').modal('hide');
                    loadSummary();
                    if (currentLedgerUserId) loadLedger(currentLedgerUserId);
                } else toastr.error(res.message);
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

        var btn = $('#btn-submit-return').prop('disabled', true);
        $.ajax({
            url: apiBase + 'staff/return/store', type: 'POST', data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal_return').modal('hide');
                    loadSummary();
                    if (currentLedgerUserId) loadLedger(currentLedgerUserId);
                } else toastr.error(res.message);
            },
            error: function (xhr) {
                var r = xhr.responseJSON;
                if (r && r.errors) $.each(r.errors, function (f, m) { toastr.error(m[0]); });
                else toastr.error(r ? r.message : 'Failed.');
            },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    function submitEditAdvance() {
        var form = $('#form-edit-advance');
        var data = {};
        form.find('input, select').each(function () { var n = $(this).attr('name'); if (n) data[n] = $(this).val(); });
        if (!highlightRequired(form, ['amount', 'pool_id', 'edit_reason'], data)) return;

        var id = data.advance_id;
        var btn = $('#btn-submit-edit-advance').prop('disabled', true);
        $.ajax({
            url: apiBase + 'staff/advance/' + id + '/update', type: 'POST', data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal_edit_advance').modal('hide');
                    if (currentLedgerUserId) loadLedger(currentLedgerUserId);
                    loadSummary();
                } else toastr.error(res.message);
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
                    var actionIcons = { created: 'la-plus-circle', updated: 'la-edit', voided: 'la-ban' };
                    var actionColors = { created: '#3699FF', updated: '#8950FC', voided: '#181C32' };

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
        if (!log.old_values || !log.new_values || log.action === 'created') return '';
        var oldV = log.old_values, newV = log.new_values, changes = [];
        var fieldLabels = { advance_date: 'Date', amount: 'Amount', pool_id: 'Pool', staff_user_id: 'Staff', description: 'Description', void_reason: 'Void Reason' };

        function getRelName(values, field) {
            if (field === 'pool_id' && values.pool && values.pool.name) return values.pool.name;
            if (field === 'staff_user_id' && values.staff_user && values.staff_user.name) return values.staff_user.name;
            return null;
        }
        function formatVal(field, val, values) {
            if (val === null || val === undefined || val === '') return '(empty)';
            var relName = getRelName(values, field);
            if (relName) return relName;
            if (field === 'advance_date' || field === 'return_date') return String(val).substring(0, 10) || '(empty)';
            if (field === 'amount') return 'PKR ' + parseInt(val).toLocaleString();
            return esc(String(val));
        }

        $.each(fieldLabels, function (field, label) {
            var oldVal = oldV[field], newVal = newV[field];
            var oldNorm = (oldVal === null || oldVal === undefined) ? '' : String(oldVal);
            var newNorm = (newVal === null || newVal === undefined) ? '' : String(newVal);
            if (field === 'advance_date' || field === 'return_date') { oldNorm = oldNorm.substring(0, 10); newNorm = newNorm.substring(0, 10); }
            if (oldNorm !== newNorm) {
                changes.push('<span class="font-weight-bold">' + label + ':</span> <span class="text-danger">' + formatVal(field, oldVal, oldV) + '</span> <i class="la la-arrow-right font-size-xs"></i> <span class="text-success">' + formatVal(field, newVal, newV) + '</span>');
            }
        });

        return changes.length ? '<div class="font-size-sm text-muted mt-1" style="line-height:1.8;">' + changes.join('<br>') + '</div>' : '';
    }

    function getActionBadge(action) {
        var colors = { created: 'primary', updated: 'info', voided: 'dark' };
        return '<span class="label label-light-' + (colors[action] || 'secondary') + ' label-inline">' + action.replace('_', ' ').toUpperCase() + '</span>';
    }

    function esc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
    function nf(n) { return parseFloat(n||0).toLocaleString('en-PK',{maximumFractionDigits:0}); }
    function fd(d) { if(!d) return '-'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }

    return { init: init };
})();

$(document).ready(function () { CashflowStaff.init(); });
