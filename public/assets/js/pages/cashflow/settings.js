"use strict";

var CashflowSettings = (function () {
    var apiBase = '/api/cashflow/';

    function init() {
        loadSettingsData();
        bindEvents();
    }

    function bindEvents() {
        $('#btn-save-settings').on('click', saveSettings);
        $('#btn-init-pools').on('click', initializePools);
        $('#btn-submit-pool').on('click', submitPool);
        $('#btn-submit-edit-pool').on('click', submitEditPool);
        $('#btn-submit-category').on('click', submitCategory);
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

    // ===================== HELPERS =====================

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function numberFormat(num) {
        return parseFloat(num || 0).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    return { init: init };
})();

$(document).ready(function () {
    CashflowSettings.init();
});
