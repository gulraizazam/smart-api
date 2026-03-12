"use strict";

var CashflowSettings = (function () {
    var apiBase = '/api/cashflow/';

    function init() {
        loadSettingsData();
        bindEvents();

        // Init Select2 on page-level filter selects
        $('#audit-entity-filter').select2();
        $('#audit-action-filter').select2();
    }

    function bindEvents() {
        $('#btn-save-settings').on('click', saveSettings);
        $('#btn-init-pools').on('click', initializePools);
        $('#btn-recalculate-pools').on('click', recalculatePools);
        $('#btn-submit-pool').on('click', submitPool);
        $('#btn-submit-edit-pool').on('click', submitEditPool);
        $('#btn-submit-category').on('click', submitCategory);
        $('#btn-save-pm-mapping').on('click', savePmMapping);
        $('#btn-load-audit').on('click', function () { loadAuditTrail(1); });
        $('#btn-reset-module').on('click', resetModule);

        $('#modal_add_pool, #modal_add_category').on('shown.bs.modal', function () {
            $(this).find('.kt-select2-general').select2({ placeholder: 'Select type', dropdownParent: $(this) });
        }).on('hidden.bs.modal', function () {
            var s2 = $(this).find('.kt-select2-general');
            if (s2.length && s2.data('select2')) s2.select2('destroy');
        });
    }

    // ===================== LOAD DATA =====================

    function loadSettingsData() {
        $.ajax({
            url: apiBase + 'settings/data',
            type: 'GET',
            success: function (res) {
                if (res.success) {
                    populateSettings(res.data.settings);
                    renderPools(res.data.pools);
                    renderCategories(res.data.categories);
                    if (res.data.payment_modes && res.data.pools) {
                        renderPmMapping(res.data.payment_modes, res.data.pools, res.data.settings);
                    }

                    loadEligibleStaff();
                    loadPendingRequests();

                    // Sec 27.9: Reset button hidden after first period lock
                    if (res.data.has_period_locks) {
                        $('#btn-reset-module').addClass('d-none');
                        // Sec 3.1: Go-live date frozen after first lock
                        $('#settings-form [name="go_live_date"]').prop('disabled', true).closest('.form-group').find('.form-text').text('Frozen after first period lock.');
                    } else {
                        $('#btn-reset-module').removeClass('d-none');
                    }
                } else {
                    toastr.error(res.message || 'Failed to load settings.');
                }
            },
            error: function () {
                toastr.error('Failed to load settings data.');
            }
        });
    }

    function populateSettings(settings) {
        var form = $('#settings-form');
        $.each(settings, function (key, value) {
            var input = form.find('[name="' + key + '"]');
            if (input.length) {
                input.val(value || '');
            }
        });
        $('#settings-loading').addClass('d-none');
        form.removeClass('d-none');
    }

    // ===================== SETTINGS =====================

    function saveSettings() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="spinner spinner-white spinner-sm mr-2"></i> Saving...');

        var formData = {};
        $('#settings-form').find('input, select, textarea').each(function () {
            var name = $(this).attr('name');
            if (name) {
                formData[name] = $(this).val();
            }
        });

        $.ajax({
            url: apiBase + 'settings/update',
            type: 'POST',
            data: { settings: formData },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                } else {
                    toastr.error(res.message || 'Failed to save settings.');
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to save settings.';
                toastr.error(msg);
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="la la-save"></i> Save Settings');
            }
        });
    }

    // ===================== POOLS =====================

    function renderPools(pools) {
        var tbody = $('#pools-tbody');
        tbody.empty();

        if (!pools || pools.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center text-muted">No pools found. Click "Initialize Branch Pools" to create them.</td></tr>');
            return;
        }

        var typeLabels = {
            'branch_cash': '<span class="label label-light-primary label-inline">Branch</span>',
            'head_office_cash': '<span class="label label-light-info label-inline">Head Office</span>',
            'bank_account': '<span class="label label-light-success label-inline">Bank</span>'
        };

        $.each(pools, function (i, pool) {
            var branchName = pool.location ? pool.location.name : '-';
            var statusBadge = pool.is_active
                ? '<span class="label label-light-success label-inline">Active</span>'
                : '<span class="label label-light-danger label-inline">Inactive</span>';

            var balanceClass = parseFloat(pool.cached_balance) < 0 ? 'text-danger' : '';

            var actions = '<button class="btn btn-sm btn-clean btn-icon btn-edit-pool" ' +
                'data-id="' + pool.id + '" ' +
                'data-name="' + escapeHtml(pool.name) + '" ' +
                'data-opening="' + pool.opening_balance + '" ' +
                'data-frozen="' + (pool.opening_balance_frozen ? 1 : 0) + '" ' +
                'data-type="' + pool.type + '" ' +
                'title="Edit"><i class="la la-edit text-primary"></i></button>' +
                '<button class="btn btn-sm btn-clean btn-icon btn-delete-pool" ' +
                'data-id="' + pool.id + '" ' +
                'data-name="' + escapeHtml(pool.name) + '" ' +
                'title="Delete"><i class="la la-trash text-danger"></i></button>';

            tbody.append(
                '<tr>' +
                '<td>' + escapeHtml(pool.name) + '</td>' +
                '<td>' + (typeLabels[pool.type] || pool.type) + '</td>' +
                '<td>' + escapeHtml(branchName) + '</td>' +
                '<td class="text-right">PKR ' + numberFormat(pool.opening_balance) + '</td>' +
                '<td class="text-right ' + balanceClass + '">PKR ' + numberFormat(pool.cached_balance) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td class="text-center">' + actions + '</td>' +
                '</tr>'
            );
        });

        // Bind edit buttons
        $('.btn-edit-pool').off('click').on('click', function () {
            var btn = $(this);
            var form = $('#form-edit-pool');
            form.find('[name="pool_id"]').val(btn.data('id'));
            form.find('[name="name"]').val(btn.data('name'));
            form.find('[name="type"]').val(btn.data('type'));
            form.find('[name="opening_balance"]').val(btn.data('opening'));

            if (btn.data('frozen')) {
                $('#edit-opening-balance-group').hide();
            } else {
                $('#edit-opening-balance-group').show();
            }

            $('#modal_edit_pool').modal('show');
        });

        // Bind delete buttons
        $('.btn-delete-pool').off('click').on('click', function () {
            var poolId = $(this).data('id');
            var poolName = $(this).data('name');
            if (!confirm('Are you sure you want to delete pool "' + poolName + '"? This cannot be undone.')) return;

            $.ajax({
                url: apiBase + 'pools/' + poolId + '/delete',
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadSettingsData();
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to delete pool.');
                }
            });
        });
    }

    function initializePools() {
        var btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'pools/initialize',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    loadSettingsData();
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to initialize pools.');
            },
            complete: function () {
                btn.prop('disabled', false);
            }
        });
    }

    function recalculatePools() {
        if (!confirm('This will recalculate all pool balances from opening balances + all transactions since go-live date. Continue?')) return;

        var btn = $('#btn-recalculate-pools');
        btn.prop('disabled', true).html('<i class="spinner spinner-white spinner-sm mr-2"></i> Recalculating...');

        $.ajax({
            url: apiBase + 'pools/recalculate',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    if (res.data && res.data.length > 0) {
                        var msg = 'Adjustments made:\n';
                        $.each(res.data, function (i, r) {
                            msg += r.pool + ': ' + r.old_balance + ' → ' + r.new_balance + ' (' + (r.diff > 0 ? '+' : '') + r.diff + ')\n';
                        });
                        alert(msg);
                    }
                    loadSettingsData();
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to recalculate pool balances.');
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="la la-calculator"></i> Recalculate Balances');
            }
        });
    }

    function submitPool() {
        var form = $('#form-add-pool');
        var data = {
            name: form.find('[name="name"]').val(),
            type: form.find('[name="type"]').val(),
            opening_balance: form.find('[name="opening_balance"]').val() || 0
        };

        if (!data.name) {
            toastr.warning('Pool name is required.');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'pools/store',
            type: 'POST',
            data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal_add_pool').modal('hide');
                    form[0].reset();
                    loadSettingsData();
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to create pool.');
            },
            complete: function () {
                btn.prop('disabled', false);
            }
        });
    }

    function submitEditPool() {
        var form = $('#form-edit-pool');
        var poolId = form.find('[name="pool_id"]').val();
        var data = {
            name: form.find('[name="name"]').val(),
            type: form.find('[name="type"]').val(),
            opening_balance: form.find('[name="opening_balance"]').val()
        };

        var btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'pools/' + poolId + '/update',
            type: 'POST',
            data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal_edit_pool').modal('hide');
                    loadSettingsData();
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to update pool.');
            },
            complete: function () {
                btn.prop('disabled', false);
            }
        });
    }

    // ===================== CATEGORIES =====================

    function renderCategories(categories) {
        var tbody = $('#categories-tbody');
        tbody.empty();

        if (!categories || categories.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted">No categories found.</td></tr>');
            return;
        }

        $.each(categories, function (i, cat) {
            var statusBadge = cat.is_active
                ? '<span class="label label-light-success label-inline">Active</span>'
                : '<span class="label label-light-danger label-inline">Inactive</span>';

            var vendorBadge = cat.vendor_emphasis
                ? '<span class="label label-light-warning label-inline">Yes</span>'
                : '<span class="text-muted">No</span>';

            var actions =
                '<button class="btn btn-sm btn-clean btn-icon btn-edit-category" ' +
                'data-id="' + cat.id + '" ' +
                'data-name="' + escapeHtml(cat.name) + '" ' +
                'data-description="' + escapeHtml(cat.description || '') + '" ' +
                'data-vendor="' + (cat.vendor_emphasis ? 1 : 0) + '" ' +
                'title="Edit"><i class="la la-edit text-primary"></i></button>' +
                '<button class="btn btn-sm btn-clean btn-icon btn-toggle-category" ' +
                'data-id="' + cat.id + '" ' +
                'data-active="' + (cat.is_active ? 1 : 0) + '" ' +
                'title="' + (cat.is_active ? 'Deactivate' : 'Activate') + '">' +
                '<i class="la ' + (cat.is_active ? 'la-toggle-on text-success' : 'la-toggle-off text-danger') + '"></i></button>';

            tbody.append(
                '<tr>' +
                '<td>' + escapeHtml(cat.name) + '</td>' +
                '<td>' + escapeHtml(cat.description || '-') + '</td>' +
                '<td>' + vendorBadge + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td class="text-center">' + actions + '</td>' +
                '</tr>'
            );
        });

        // Bind edit
        $('.btn-edit-category').off('click').on('click', function () {
            var btn = $(this);
            var form = $('#form-category');
            form.find('[name="category_id"]').val(btn.data('id'));
            form.find('[name="name"]').val(btn.data('name'));
            form.find('[name="description"]').val(btn.data('description'));
            form.find('[name="vendor_emphasis"]').prop('checked', btn.data('vendor') == 1);
            $('#category-modal-title').text('Edit Category');
            $('#modal_add_category').modal('show');
        });

        // Bind toggle
        $('.btn-toggle-category').off('click').on('click', function () {
            var catId = $(this).data('id');
            $.ajax({
                url: apiBase + 'categories/' + catId + '/toggle',
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadSettingsData();
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to toggle category.');
                }
            });
        });
    }

    function submitCategory() {
        var form = $('#form-category');
        var catId = form.find('[name="category_id"]').val();
        var data = {
            name: form.find('[name="name"]').val(),
            description: form.find('[name="description"]').val(),
            vendor_emphasis: form.find('[name="vendor_emphasis"]').is(':checked') ? 1 : 0
        };

        if (!data.name) {
            toastr.warning('Category name is required.');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true);

        var url = catId ? apiBase + 'categories/' + catId + '/update' : apiBase + 'categories/store';

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal_add_category').modal('hide');
                    form[0].reset();
                    form.find('[name="category_id"]').val('');
                    $('#category-modal-title').text('Add Category');
                    loadSettingsData();
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to save category.');
            },
            complete: function () {
                btn.prop('disabled', false);
            }
        });
    }

    // Reset category modal on close
    $(document).on('hidden.bs.modal', '#modal_add_category', function () {
        $('#form-category')[0].reset();
        $('#form-category').find('[name="category_id"]').val('');
        $('#category-modal-title').text('Add Category');
    });

    // ===================== MODULE RESET (Sec 27.9) =====================

    function resetModule() {
        if (!confirm('Are you sure you want to reset the entire Cash Flow module? This will delete ALL transaction data.')) return;
        var code = prompt('Type RESET to confirm module reset:');
        if (code !== 'RESET') { toastr.warning('Reset cancelled — confirmation code did not match.'); return; }

        $.ajax({
            url: apiBase + 'settings/reset-module',
            type: 'POST',
            data: { confirm: 'RESET' },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message || 'Module reset complete.');
                    loadSettingsData();
                } else {
                    toastr.error(res.message || 'Reset failed.');
                }
            },
            error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Reset failed.'); }
        });
    }

    // ===================== ADVANCE-ELIGIBLE STAFF (Sec 27.5) =====================

    function loadEligibleStaff() {
        $.ajax({
            url: apiBase + 'settings/eligible-staff',
            type: 'GET',
            success: function (res) {
                var tbody = $('#eligible-staff-tbody').empty();
                if (!res.success || !res.data || !res.data.length) {
                    tbody.html('<tr><td colspan="3" class="text-center text-muted py-3">No staff found.</td></tr>');
                    return;
                }
                $.each(res.data, function (i, user) {
                    var checked = user.is_advance_eligible ? 'checked' : '';
                    tbody.append(
                        '<tr>' +
                        '<td class="py-2 px-4">' + escapeHtml(user.name) + '</td>' +
                        '<td class="py-2 px-4">' + escapeHtml(user.email || '-') + '</td>' +
                        '<td class="py-2 px-4 text-center"><input type="checkbox" class="chk-eligible" data-id="' + user.id + '" ' + checked + '></td>' +
                        '</tr>'
                    );
                });

                // Bind toggle
                $('.chk-eligible').off('change').on('change', function () {
                    var userId = $(this).data('id');
                    var eligible = $(this).is(':checked') ? 1 : 0;
                    toggleEligibility(userId, eligible);
                });
            },
            error: function () { toastr.error('Failed to load staff list.'); }
        });
    }

    function toggleEligibility(userId, eligible) {
        $.ajax({
            url: apiBase + 'settings/toggle-eligibility',
            type: 'POST',
            data: { user_id: userId, is_advance_eligible: eligible },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message || 'Eligibility updated.');
                } else {
                    toastr.error(res.message || 'Failed to update.');
                    loadEligibleStaff(); // revert UI
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.');
                loadEligibleStaff();
            }
        });
    }

    // ===================== PAYMENT METHOD → POOL MAPPING =====================

    function renderPmMapping(paymentModes, pools, settings) {
        var container = $('#pm-mapping-container').empty();
        if (!paymentModes || !paymentModes.length) {
            container.html('<p class="text-muted">No payment methods found.</p>');
            return;
        }

        var poolOpts = '<option value="">-- Not Mapped --</option>';
        $.each(pools, function (i, p) {
            var label = p.name;
            poolOpts += '<option value="' + p.id + '">' + escapeHtml(label) + '</option>';
        });

        $.each(paymentModes, function (i, pm) {
            var settingKey = 'pm_pool_' + pm.id;
            var currentPool = settings[settingKey] || '';
            var row = '<div class="form-group row align-items-center">' +
                '<label class="col-md-4 col-form-label font-weight-bold">' + escapeHtml(pm.name) + '</label>' +
                '<div class="col-md-8">' +
                '<select class="form-control pm-pool-select" data-pm-id="' + pm.id + '">' + poolOpts + '</select>' +
                '</div></div>';
            container.append(row);
            if (currentPool) {
                container.find('.pm-pool-select[data-pm-id="' + pm.id + '"]').val(currentPool);
            }
        });
    }

    function savePmMapping() {
        var mappings = {};
        $('.pm-pool-select').each(function () {
            var pmId = $(this).data('pm-id');
            mappings['pm_pool_' + pmId] = $(this).val() || '';
        });

        $.ajax({
            url: apiBase + 'settings/save',
            type: 'POST',
            data: mappings,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) toastr.success('Payment method mapping saved.');
                else toastr.error(res.message || 'Failed.');
            },
            error: function () { toastr.error('Failed to save mapping.'); }
        });
    }

    // ===================== AUDIT TRAIL VIEWER =====================

    var entityLabels = {
        expense: 'Expense', transfer: 'Cash Transfer', vendor: 'Vendor',
        vendor_transaction: 'Vendor Transaction', vendor_request: 'Vendor Request',
        staff_advance: 'Staff Advance', staff_return: 'Staff Return',
        cash_pool: 'Cash Pool', category: 'Category', category_request: 'Category Request',
        settings: 'Settings', period_lock: 'Period Lock', module: 'Module'
    };
    var actionColors = {
        created: 'success', updated: 'primary', voided: 'danger', approved: 'success',
        rejected: 'danger', resubmitted: 'warning', deleted: 'danger', locked: 'dark',
        unlocked: 'info', deactivated: 'secondary', auto_created: 'info', reset: 'danger'
    };
    var actionIcons = {
        created: 'la-plus-circle', updated: 'la-edit', voided: 'la-ban', approved: 'la-check-circle',
        rejected: 'la-times-circle', resubmitted: 'la-redo', deleted: 'la-trash', locked: 'la-lock',
        unlocked: 'la-unlock', deactivated: 'la-power-off', auto_created: 'la-robot', reset: 'la-undo'
    };

    // Fields to show in human-readable summary per entity type
    var entityKeyFields = {
        expense: ['description', 'amount', 'expense_date', 'status'],
        transfer: ['amount', 'transfer_date', 'method'],
        vendor: ['name', 'payment_terms'],
        vendor_transaction: ['amount', 'type', 'description', 'transaction_date'],
        vendor_request: ['name'],
        staff_advance: ['amount', 'description'],
        staff_return: ['amount', 'description'],
        cash_pool: ['name', 'type', 'cached_balance'],
        category: ['name'],
        category_request: ['name'],
        settings: ['key', 'value'],
        period_lock: ['month', 'year']
    };

    // Fields to skip in change details (internal/noisy)
    var skipFields = ['id', 'account_id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'verified_by', 'voided_by', 'user_id', 'pivot'];

    function loadAuditTrail(page) {
        var entityType = $('#audit-entity-filter').val();
        var actionType = $('#audit-action-filter').val();
        var tbody = $('#audit-trail-tbody');
        tbody.html('<tr><td colspan="4" class="text-center py-3"><div class="spinner spinner-primary spinner-sm"></div> Loading...</td></tr>');

        $.ajax({
            url: apiBase + 'audit-logs',
            type: 'GET',
            data: { entity_type: entityType, action: actionType, page: page || 1, per_page: 25 },
            success: function (res) {
                if (!res.success) { tbody.html('<tr><td colspan="4" class="text-center text-danger">Failed to load.</td></tr>'); return; }
                tbody.empty();
                var logs = res.data;
                if (!logs || !logs.length) {
                    tbody.html('<tr><td colspan="4" class="text-center text-muted py-4">No audit logs found.</td></tr>');
                    $('#audit-info').text('');
                    $('#audit-pagination').empty();
                    return;
                }

                $.each(logs, function (i, log) {
                    var ts = log.created_at ? new Date(log.created_at).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
                    var userName = log.user ? escapeHtml(log.user.name) : '<em class="text-muted">System</em>';
                    var color = actionColors[log.action] || 'secondary';
                    var icon = actionIcons[log.action] || 'la-circle';
                    var entLabel = entityLabels[log.entity_type] || log.entity_type;
                    var actionLabel = (log.action || '').charAt(0).toUpperCase() + (log.action || '').slice(1);

                    // Build description
                    var desc = '<span class="label label-' + color + ' label-inline mr-2"><i class="la ' + icon + ' mr-1"></i>' + escapeHtml(actionLabel) + '</span>';
                    desc += '<span class="font-weight-bold">' + escapeHtml(entLabel) + '</span>';
                    desc += ' <span class="text-muted">#' + (log.entity_id || '?') + '</span>';

                    // Add a brief context from new_values
                    var brief = buildBrief(log);
                    if (brief) desc += '<span class="text-muted ml-2">— ' + brief + '</span>';

                    // Reason
                    if (log.reason) {
                        desc += '<div class="font-size-xs text-muted mt-1"><i class="la la-comment-alt mr-1"></i>' + escapeHtml(log.reason) + '</div>';
                    }

                    // IP
                    desc += '<div class="font-size-xs text-muted mt-1"><i class="la la-globe mr-1"></i>' + escapeHtml(log.ip_address || '') + '</div>';

                    // Check if there are meaningful changes to show
                    var hasDetails = (log.old_values && log.new_values && log.action !== 'created') || (log.action === 'created' && log.new_values);
                    var detailBtn = hasDetails ? '<button class="btn btn-sm btn-light-primary btn-icon btn-audit-detail" data-idx="' + i + '" title="View Changes"><i class="la la-eye"></i></button>' : '<span class="text-muted">—</span>';

                    tbody.append(
                        '<tr>' +
                        '<td class="px-4 font-size-sm text-nowrap align-top">' + ts + '</td>' +
                        '<td class="px-4 align-top">' + userName + '</td>' +
                        '<td class="px-4">' + desc + '</td>' +
                        '<td class="px-4 text-center align-top">' + detailBtn + '</td>' +
                        '</tr>'
                    );

                    // Hidden detail row
                    if (hasDetails) {
                        var changeHtml = buildChangeDetails(log);
                        tbody.append('<tr class="audit-detail-row d-none" data-idx="' + i + '"><td colspan="4" class="px-4 py-3 bg-light">' + changeHtml + '</td></tr>');
                    }
                });

                // Toggle detail rows
                tbody.find('.btn-audit-detail').off('click').on('click', function () {
                    var idx = $(this).data('idx');
                    var row = tbody.find('.audit-detail-row[data-idx="' + idx + '"]');
                    row.toggleClass('d-none');
                    $(this).find('i').toggleClass('la-eye la-eye-slash');
                });

                // Pagination
                if (res.meta) {
                    $('#audit-info').text('Page ' + res.meta.current_page + ' of ' + res.meta.last_page + ' (' + res.meta.total + ' logs)');
                    var links = '';
                    if (res.meta.current_page > 1) links += '<button class="btn btn-sm btn-outline-primary mr-1 btn-audit-pg" data-page="' + (res.meta.current_page - 1) + '">&laquo; Prev</button>';
                    if (res.meta.current_page < res.meta.last_page) links += '<button class="btn btn-sm btn-outline-primary btn-audit-pg" data-page="' + (res.meta.current_page + 1) + '">Next &raquo;</button>';
                    $('#audit-pagination').html(links);
                    $('.btn-audit-pg').off('click').on('click', function () { loadAuditTrail($(this).data('page')); });
                }
            },
            error: function () { tbody.html('<tr><td colspan="4" class="text-center text-danger">Failed to load audit trail.</td></tr>'); }
        });
    }

    function buildBrief(log) {
        var vals = log.new_values || log.old_values;
        if (!vals) return '';
        var keys = entityKeyFields[log.entity_type] || [];
        var parts = [];
        for (var k = 0; k < keys.length && parts.length < 2; k++) {
            var field = keys[k];
            // Check nested relation name (e.g. category.name, paid_from_pool.name)
            var v = vals[field];
            if (v === undefined || v === null || v === '') continue;
            if (field === 'amount') {
                parts.push('PKR ' + Number(parseFloat(v) || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 }));
            } else if (typeof v === 'string' && v.length > 40) {
                parts.push(escapeHtml(v.substring(0, 40)) + '...');
            } else {
                parts.push(escapeHtml(String(v)));
            }
        }
        return parts.join(', ');
    }

    function buildChangeDetails(log) {
        if (log.action === 'created' && log.new_values) {
            return buildCreatedDetails(log.new_values, log.entity_type);
        }
        if (!log.old_values || !log.new_values) return '<span class="text-muted">No change details available.</span>';

        var oldV = log.old_values;
        var newV = log.new_values;
        var changes = [];

        // Collect all keys
        var allKeys = {};
        for (var k in oldV) allKeys[k] = true;
        for (var k in newV) allKeys[k] = true;

        for (var field in allKeys) {
            if (skipFields.indexOf(field) !== -1) continue;
            // Skip nested relation objects (arrays/objects)
            if (typeof oldV[field] === 'object' && oldV[field] !== null && !Array.isArray(oldV[field])) continue;
            if (typeof newV[field] === 'object' && newV[field] !== null && !Array.isArray(newV[field])) continue;

            var oldVal = oldV[field];
            var newVal = newV[field];

            // Normalize for comparison
            var oldStr = (oldVal === null || oldVal === undefined) ? '' : String(oldVal);
            var newStr = (newVal === null || newVal === undefined) ? '' : String(newVal);

            if (oldStr !== newStr) {
                var label = formatFieldName(field);
                var oldDisplay = formatFieldValue(field, oldVal, oldV);
                var newDisplay = formatFieldValue(field, newVal, newV);
                changes.push({ field: label, from: oldDisplay, to: newDisplay });
            }
        }

        if (!changes.length) return '<span class="text-muted">No visible changes detected.</span>';

        var html = '<table class="table table-sm table-bordered mb-0" style="font-size:12px;"><thead><tr><th style="width:25%;">Field</th><th style="width:37%;">Old Value</th><th style="width:37%;">New Value</th></tr></thead><tbody>';
        for (var c = 0; c < changes.length; c++) {
            html += '<tr><td class="font-weight-bold">' + escapeHtml(changes[c].field) + '</td>' +
                '<td class="text-danger">' + escapeHtml(changes[c].from || '(empty)') + '</td>' +
                '<td class="text-success">' + escapeHtml(changes[c].to || '(empty)') + '</td></tr>';
        }
        html += '</tbody></table>';
        return html;
    }

    function buildCreatedDetails(vals, entityType) {
        var keys = entityKeyFields[entityType] || Object.keys(vals).slice(0, 8);
        var html = '<div class="font-size-sm"><strong>Created with:</strong><ul class="mb-0 pl-4 mt-1">';
        for (var k = 0; k < keys.length; k++) {
            var field = typeof keys === 'object' ? keys[k] : keys;
            var v = vals[field];
            if (v === undefined || v === null || v === '') continue;
            html += '<li><strong>' + escapeHtml(formatFieldName(field)) + ':</strong> ' + escapeHtml(formatFieldValue(field, v, vals)) + '</li>';
        }
        html += '</ul></div>';
        return html;
    }

    function formatFieldName(field) {
        // Convert snake_case to Title Case
        return field.replace(/_id$/, '').replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });
    }

    function formatFieldValue(field, val, context) {
        if (val === null || val === undefined) return '(empty)';

        // Resolve _id fields by looking for relation name in context
        if (field.endsWith('_id')) {
            var relName = field.replace(/_id$/, '');
            // Try common relation patterns
            var patterns = [relName, relName.replace(/_/g, ''), toCamelCase(relName)];
            for (var p = 0; p < patterns.length; p++) {
                if (context[patterns[p]] && typeof context[patterns[p]] === 'object' && context[patterns[p]].name) {
                    return context[patterns[p]].name + ' (#' + val + ')';
                }
            }
            // Special cases
            if (field === 'category_id' && context.category && context.category.name) return context.category.name + ' (#' + val + ')';
            if (field === 'paid_from_pool_id' && context.paid_from_pool && context.paid_from_pool.name) return context.paid_from_pool.name + ' (#' + val + ')';
            if (field === 'payment_method_id' && context.payment_method && context.payment_method.name) return context.payment_method.name + ' (#' + val + ')';
            if (field === 'for_branch_id' && context.for_branch && context.for_branch.name) return context.for_branch.name + ' (#' + val + ')';
            if (field === 'vendor_id' && context.vendor && context.vendor.name) return context.vendor.name + ' (#' + val + ')';
            if (field === 'from_pool_id' && context.from_pool && context.from_pool.name) return context.from_pool.name + ' (#' + val + ')';
            if (field === 'to_pool_id' && context.to_pool && context.to_pool.name) return context.to_pool.name + ' (#' + val + ')';
            if (field === 'pool_id' && context.pool && context.pool.name) return context.pool.name + ' (#' + val + ')';
            if (field === 'staff_id' && context.staff && context.staff.name) return context.staff.name + ' (#' + val + ')';
            if (field === 'user_id' && context.staff_user && context.staff_user.name) return context.staff_user.name + ' (#' + val + ')';
        }

        if (field === 'amount' || field === 'cached_balance' || field === 'opening_balance' || field === 'cash_amount') {
            return 'PKR ' + Number(parseFloat(val) || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 });
        }

        if (field === 'is_for_general') return val ? 'Yes (General / Company-wide)' : 'No';
        if (field === 'is_active') return val ? 'Active' : 'Inactive';
        if (field === 'is_flagged') return val ? 'Flagged' : 'Not Flagged';

        return String(val);
    }

    function toCamelCase(str) {
        return str.replace(/_([a-z])/g, function (m, c) { return c.toUpperCase(); });
    }

    // ===================== HELPERS =====================

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function numberFormat(num) {
        return parseFloat(num || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 });
    }

    // ===================== PENDING REQUESTS =====================

    function loadPendingRequests() {
        // Category requests
        if ($('#cat-requests-tbody').length) {
            $.ajax({
                url: apiBase + 'category-requests/data',
                type: 'GET',
                data: { status: 'pending' },
                success: function (res) {
                    var tbody = $('#cat-requests-tbody').empty();
                    var items = res.success ? (res.data || []) : [];
                    if (!items.length) {
                        tbody.html('<tr><td colspan="3" class="text-center text-muted py-3">No pending requests.</td></tr>');
                    } else {
                        $.each(items, function (i, req) {
                            tbody.append(
                                '<tr>' +
                                '<td>' + escapeHtml(req.name) + '</td>' +
                                '<td>' + (req.requester ? escapeHtml(req.requester.name) : '-') + '</td>' +
                                '<td class="text-center text-nowrap">' +
                                '<button class="btn btn-sm btn-clean btn-icon btn-approve-cat" data-id="' + req.id + '" title="Approve"><i class="la la-check-circle text-success"></i></button>' +
                                '<button class="btn btn-sm btn-clean btn-icon btn-dismiss-cat" data-id="' + req.id + '" title="Dismiss"><i class="la la-times-circle text-danger"></i></button>' +
                                '</td></tr>'
                            );
                        });
                        bindRequestButtons('cat');
                    }
                    toggleRequestsCard();
                }
            });
        }

        // Vendor requests
        if ($('#vendor-requests-tbody').length) {
            $.ajax({
                url: apiBase + 'vendor-requests/data',
                type: 'GET',
                data: { status: 'pending' },
                success: function (res) {
                    var tbody = $('#vendor-requests-tbody').empty();
                    var items = res.success ? (res.data || []) : [];
                    if (!items.length) {
                        tbody.html('<tr><td colspan="3" class="text-center text-muted py-3">No pending requests.</td></tr>');
                    } else {
                        $.each(items, function (i, req) {
                            tbody.append(
                                '<tr>' +
                                '<td>' + escapeHtml(req.name) + '</td>' +
                                '<td>' + (req.requester ? escapeHtml(req.requester.name) : '-') + '</td>' +
                                '<td class="text-center text-nowrap">' +
                                '<button class="btn btn-sm btn-clean btn-icon btn-approve-vendor" data-id="' + req.id + '" title="Approve"><i class="la la-check-circle text-success"></i></button>' +
                                '<button class="btn btn-sm btn-clean btn-icon btn-dismiss-vendor" data-id="' + req.id + '" title="Dismiss"><i class="la la-times-circle text-danger"></i></button>' +
                                '</td></tr>'
                            );
                        });
                        bindRequestButtons('vendor');
                    }
                    toggleRequestsCard();
                }
            });
        }
    }

    function toggleRequestsCard() {
        // Show the card if either tbody has pending rows (not the "no pending" message)
        var hasCat = $('#cat-requests-tbody .btn-approve-cat').length > 0;
        var hasVendor = $('#vendor-requests-tbody .btn-approve-vendor').length > 0;
        if (hasCat || hasVendor) {
            $('#pending-requests-card').removeClass('d-none');
        }
    }

    function bindRequestButtons(type) {
        var prefix = type === 'cat' ? 'category-requests' : 'vendor-requests';

        $('.btn-approve-' + type).off('click').on('click', function () {
            var id = $(this).data('id');
            if (!confirm('Approve this request?')) return;
            $.ajax({
                url: apiBase + prefix + '/' + id + '/approve',
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadPendingRequests();
                        loadSettingsData();
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });

        $('.btn-dismiss-' + type).off('click').on('click', function () {
            var id = $(this).data('id');
            var notes = prompt('Reason for dismissal (optional):');
            $.ajax({
                url: apiBase + prefix + '/' + id + '/dismiss',
                type: 'POST',
                data: { admin_notes: notes || '' },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadPendingRequests();
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });
    }

    return { init: init };
})();

$(document).ready(function () {
    CashflowSettings.init();
});
