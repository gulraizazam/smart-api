"use strict";

var CashflowExpenses = (function () {
    var apiBase = '/api/cashflow/';
    var currentPage = 1;
    var formData = null; // cached dropdown data
    var threshold = 10000;
    var expensesXhr = null;

    function initDateRange() {
        $('#filter-date-range').daterangepicker({
            locale: { format: 'MM/DD/YYYY' },
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

    function getDateRange() {
        var picker = $('#filter-date-range').data('daterangepicker');
        if (!picker) return { date_from: '', date_to: '' };
        return {
            date_from: picker.startDate.format('YYYY-MM-DD'),
            date_to: picker.endDate.format('YYYY-MM-DD')
        };
    }

    function init() {
        initDateRange();
        loadFormData();
        loadExpenses();
        bindEvents();

        // Auto-open modal if coming from dashboard quick-action
        if (new URLSearchParams(window.location.search).get('action') === 'add') {
            setTimeout(function () { $('#modal_expense').modal('show'); }, 500);
        }
    }

    function bindEvents() {
        $('#btn-filter').on('click', function () { currentPage = 1; loadExpenses(); });
        $('#filter-status').on('change', function () { currentPage = 1; loadExpenses(); });
        $('#filter-search').on('keypress', function (e) { if (e.which === 13) { currentPage = 1; loadExpenses(); } });
        $('#btn-submit-expense').on('click', submitExpense);
        $('#btn-confirm-reject').on('click', submitReject);
        $('#btn-confirm-void').on('click', submitVoid);
        $('#btn-submit-admin-edit').on('click', submitAdminEdit);
        $('#btn-export-expenses').on('click', exportExpenses);
        $('#btn-vendor-not-listed').on('click', function () { $('#modal_vendor_request').modal('show'); });
        $('#btn-category-not-listed').on('click', function () { $('#modal_category_request').modal('show'); });
        $('#btn-submit-vendor-request').on('click', submitVendorRequest);
        $('#btn-submit-category-request').on('click', submitCategoryRequest);

        // Attachment URL: warn if non-Google-Drive URL (Sec 11.3)
        $('[name="attachment_url"]', '#form-expense').on('change', function () {
            var url = $(this).val();
            if (url && !/drive\.google\.com|docs\.google\.com/i.test(url)) {
                toastr.warning('This URL does not appear to be a Google Drive link. Please verify.');
            }
        });

        // General checkbox disables branch select
        $('#chk-general').on('change', function () {
            $('#expense-branch-select').prop('disabled', $(this).is(':checked'));
            if ($(this).is(':checked')) $('#expense-branch-select').val('');
        });

        // Amount field threshold hint
        $('[name="amount"]', '#form-expense').on('input', function () {
            var val = parseFloat($(this).val()) || 0;
            if (val > threshold) {
                $('#threshold-hint').html('<span class="text-warning">Above PKR ' + numberFormat(threshold) + ' - will need admin approval</span>');
            } else {
                $('#threshold-hint').html('<span class="text-success">Auto-approved (within threshold)</span>');
            }
        });

        // Pre-fill date on modal open
        $('#modal_expense').on('show.bs.modal', function () {
            var dateField = $('#form-expense [name="expense_date"]');
            if (!dateField.val()) dateField.val(getTodayStr());
        });

        // Reset modal on close
        $('#modal_expense').on('hidden.bs.modal', function () {
            $('#form-expense')[0].reset();
            $('#expense-modal-title').text('New Expense');
            $('#threshold-hint').html('');
            $('#expense-branch-select').prop('disabled', false);
            $('#vendor-group').removeClass('bg-light-warning p-3 rounded');
        });
    }

    // ===================== KEYBOARD SHORTCUTS (Sec 15.6) =====================

    $(document).on('keydown', function (e) {
        // Alt+E: Open expense modal
        if (e.altKey && e.key === 'e') {
            e.preventDefault();
            $('#modal_expense').modal('show');
        }
        // Ctrl+Enter: Submit active modal form
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            if ($('#modal_expense').hasClass('show')) { $('#btn-submit-expense').click(); }
            else if ($('#modal_reject').hasClass('show')) { $('#btn-confirm-reject').click(); }
            else if ($('#modal_void').hasClass('show')) { $('#btn-confirm-void').click(); }
            else if ($('#modal_admin_edit').hasClass('show')) { $('#btn-submit-admin-edit').click(); }
        }
    });

    // ===================== LOAD DATA =====================

    function loadFormData() {
        $.ajax({
            url: apiBase + 'expenses/form-data',
            type: 'GET',
            success: function (res) {
                if (res.success) {
                    formData = res.data;
                    threshold = parseFloat(res.data.threshold) || 10000;
                    populateDropdowns(res.data);
                }
            }
        });
    }

    function populateDropdowns(data) {
        // Build HTML strings first, then set once (avoids DOM reflow per append)
        var html;

        // Pools
        html = '<option value="">Select pool</option>';
        $.each(data.pools, function (i, pool) {
            var label = pool.name + (pool.location ? ' (' + pool.location.name + ')' : '');
            html += '<option value="' + pool.id + '">' + escapeHtml(label) + '</option>';
        });
        $('[name="paid_from_pool_id"]', '#form-expense').html(html);

        // Categories
        html = '<option value="">Select category</option>';
        $.each(data.categories, function (i, cat) {
            html += '<option value="' + cat.id + '" data-vendor="' + (cat.vendor_emphasis ? 1 : 0) + '">' + escapeHtml(cat.name) + '</option>';
        });
        $('[name="category_id"]', '#form-expense').html(html);

        // Filter categories
        html = '<option value="">All Categories</option>';
        $.each(data.categories, function (i, cat) {
            html += '<option value="' + cat.id + '">' + escapeHtml(cat.name) + '</option>';
        });
        $('#filter-category').html(html);

        // Branches
        html = '<option value="">Select branch</option>';
        $.each(data.branches, function (i, branch) {
            html += '<option value="' + branch.id + '">' + escapeHtml(branch.name) + '</option>';
        });
        $('[name="for_branch_id"]', '#form-expense').html(html);

        // Filter branches
        html = '<option value="">All Branches</option><option value="general">General</option>';
        $.each(data.branches, function (i, branch) {
            html += '<option value="' + branch.id + '">' + escapeHtml(branch.name) + '</option>';
        });
        $('#filter-branch').html(html);

        // Payment modes
        html = '<option value="">Select method</option>';
        $.each(data.payment_modes, function (i, pm) {
            html += '<option value="' + pm.id + '">' + escapeHtml(pm.name) + '</option>';
        });
        $('[name="payment_method_id"]', '#form-expense').html(html);

        // Vendors
        html = '<option value="">Select vendor (optional)</option>';
        $.each(data.vendors, function (i, v) {
            html += '<option value="' + v.id + '">' + escapeHtml(v.name) + '</option>';
        });
        $('[name="vendor_id"]', '#form-expense').html(html);

        // Edit category select
        html = '<option value="">Select category</option>';
        $.each(data.categories, function (i, cat) {
            html += '<option value="' + cat.id + '">' + escapeHtml(cat.name) + '</option>';
        });
        $('#edit-category-select').html(html);

        // Staff dropdown
        html = '<option value="">Select staff (optional)</option>';
        if (data.staff) {
            $.each(data.staff, function (i, s) {
                html += '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>';
            });
        }
        $('[name="staff_id"]', '#form-expense').html(html);

        // Vendor emphasis: highlight vendor field when category with vendor_emphasis is selected (Sec 5.4)
        $('[name="category_id"]', '#form-expense').on('change', function () {
            var vendorEmphasis = $(this).find(':selected').data('vendor');
            if (vendorEmphasis == 1) {
                $('#vendor-group').addClass('bg-light-warning p-3 rounded').css('opacity', '1');
            } else if ($(this).val()) {
                // Low-vendor category: slightly dimmed
                $('#vendor-group').removeClass('bg-light-warning p-3 rounded').css('opacity', '0.6');
            } else {
                $('#vendor-group').removeClass('bg-light-warning p-3 rounded').css('opacity', '1');
            }
        });
    }

    function loadExpenses() {
        if (expensesXhr) expensesXhr.abort();
        var dr = getDateRange();
        var params = {
            page: currentPage,
            per_page: 25,
            status: $('#filter-status').val(),
            branch_id: $('#filter-branch').val(),
            category_id: $('#filter-category').val(),
            date_from: dr.date_from,
            date_to: dr.date_to,
            search: $('#filter-search').val()
        };

        // Clean empty params
        Object.keys(params).forEach(function (k) { if (!params[k]) delete params[k]; });

        $('#expenses-tbody').html('<tr><td colspan="8" class="text-center"><div class="spinner spinner-primary spinner-sm"></div> Loading...</td></tr>');

        expensesXhr = $.ajax({
            url: apiBase + 'expenses/data',
            type: 'GET',
            data: params,
            success: function (res) {
                if (res.success) {
                    renderExpenses(res.data);
                    renderPagination(res.meta);
                    updateStatusCounts(res.status_counts);
                } else {
                    $('#expenses-tbody').html('<tr><td colspan="8" class="text-center text-danger">' + (res.message || 'Failed to load') + '</td></tr>');
                }
            },
            error: function (xhr) {
                if (xhr.statusText !== 'abort') $('#expenses-tbody').html('<tr><td colspan="8" class="text-center text-danger">Failed to load expenses.</td></tr>');
            },
            complete: function () { expensesXhr = null; }
        });
    }

    // ===================== RENDER =====================

    function renderExpenses(expenses) {
        var tbody = $('#expenses-tbody');
        tbody.empty();

        if (!expenses || expenses.length === 0) {
            tbody.html('<tr><td colspan="8" class="text-center text-muted">No expenses found.</td></tr>');
            return;
        }

        var html = '';
        $.each(expenses, function (i, exp) {
            var rowClass = '';
            if (exp.is_flagged) rowClass += ' expense-flagged';
            if (exp.voided_at) rowClass += ' expense-voided';

            var statusBadge = getStatusBadge(exp);
            var branchLabel = exp.is_for_general ? '<span class="text-muted">General</span>' : (exp.for_branch ? exp.for_branch.name : '-');
            var poolLabel = exp.paid_from_pool ? exp.paid_from_pool.name : '-';

            var actions = buildActions(exp);

            html +=
                '<tr class="' + rowClass + '">' +
                '<td>' + formatDate(exp.expense_date) + '</td>' +
                '<td>' + escapeHtml(truncate(exp.description, 50)) +
                    (exp.is_flagged ? ' <i class="la la-flag text-danger" title="' + escapeHtml(exp.flag_reason || '') + '"></i>' : '') +
                    (exp.voided_at ? ' <span class="label label-dark label-inline font-size-xs">VOID</span>' : '') +
                    (exp.edit_reason ? ' <i class="la la-pencil text-warning" title="' + escapeHtml(buildEditTooltip(exp)) + '"></i>' : '') +
                '</td>' +
                '<td>' + (exp.category ? escapeHtml(exp.category.name) : '-') + '</td>' +
                '<td><small class="text-muted">' + escapeHtml(branchLabel) + '</small><br><small>' + escapeHtml(poolLabel) + '</small></td>' +
                '<td class="text-right amount-cell">PKR ' + numberFormat(exp.amount) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' + (exp.creator ? escapeHtml(exp.creator.name) : '-') + '</td>' +
                '<td class="text-center text-nowrap">' + actions + '</td>' +
                '</tr>';
        });
        tbody.html(html);

        bindActionButtons();
    }

    function getStatusBadge(exp) {
        var badges = '';
        if (exp.voided_at) return '<span class="label label-danger label-inline status-badge">Voided</span>';
        switch (exp.status) {
            case 'approved': badges = '<span class="label label-light-success label-inline status-badge">Approved</span>'; break;
            case 'pending': badges = '<span class="label label-warning label-inline status-badge">Pending</span>'; break;
            case 'rejected': badges = '<span class="label label-outline-danger label-inline status-badge">Rejected</span>'; break;
            default: badges = exp.status;
        }
        if (exp.is_flagged) badges += ' <span class="label label-light-warning label-inline font-size-xs">Flagged</span>';
        if (exp.edit_reason) badges += ' <span class="label label-light-primary label-inline font-size-xs">Edited</span>';
        return badges;
    }

    function buildActions(exp) {
        var btns = '';

        var perms = window.cfPerms || {};

        // Approve / Reject (admin only, pending, non-voided)
        if (perms.canApprove && exp.status === 'pending' && !exp.voided_at) {
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-approve" data-id="' + exp.id + '" data-attachment="' + (exp.attachment_url ? '1' : '0') + '" title="Approve"><i class="la la-check-circle text-success"></i></button>';
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-reject" data-id="' + exp.id + '" title="Reject"><i class="la la-times-circle text-danger"></i></button>';
        }

        // Resubmit (rejected entries — accountant can resubmit own, admin can resubmit any)
        if (exp.status === 'rejected' && !exp.voided_at) {
            if (perms.canEdit || (perms.canCreate && exp.created_by === perms.userId)) {
                btns += '<button class="btn btn-sm btn-clean btn-icon btn-resubmit" data-id="' + exp.id + '" title="Resubmit"><i class="la la-redo text-info"></i></button>';
            }
        }

        // Admin Edit (non-voided, admin only — Sec 5.7)
        if (perms.canEdit && !exp.voided_at) {
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-admin-edit" data-id="' + exp.id + '" ' +
                'data-amount="' + exp.amount + '" ' +
                'data-category="' + exp.category_id + '" ' +
                'data-description="' + escapeHtml(exp.description) + '" ' +
                'data-reference="' + escapeHtml(exp.reference_no || '') + '" ' +
                'data-attachment="' + escapeHtml(exp.attachment_url || '') + '" ' +
                'title="Edit"><i class="la la-edit text-primary"></i></button>';
        }

        // Unflag (admin only, flagged, non-voided)
        if (perms.canApprove && exp.is_flagged && !exp.voided_at) {
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-unflag" data-id="' + exp.id + '" title="Dismiss Flag"><i class="la la-flag text-warning"></i></button>';
        }

        // Void (admin only, non-voided — Sec 11.5)
        if (perms.canVoid && !exp.voided_at) {
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-void" data-id="' + exp.id + '" title="Void"><i class="la la-ban text-dark"></i></button>';
        }

        // Audit trail
        btns += '<button class="btn btn-sm btn-clean btn-icon btn-audit" data-id="' + exp.id + '" title="Audit Trail"><i class="la la-history text-muted"></i></button>';

        return btns;
    }

    function bindActionButtons() {
        $('.btn-approve').off('click').on('click', function () {
            var id = $(this).data('id');
            var hasAttachment = $(this).data('attachment');
            if (!hasAttachment) {
                toastr.error('Cannot approve: attachment must be present before approval.');
                return;
            }
            if (!confirm('Approve this expense?')) return;
            $.ajax({
                url: apiBase + 'expenses/' + id + '/approve',
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    toastr.success(res.message || 'Expense approved.');
                    loadExpenses();
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to approve.');
                }
            });
        });

        $('.btn-reject').off('click').on('click', function () {
            $('#reject-expense-id').val($(this).data('id'));
            $('#rejection-reason').val('');
            $('#modal_reject').modal('show');
        });

        $('.btn-resubmit').off('click').on('click', function () {
            var id = $(this).data('id');
            if (!confirm('Resubmit this expense for approval?')) return;
            $.ajax({
                url: apiBase + 'expenses/' + id + '/resubmit',
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    toastr.success(res.message || 'Expense resubmitted.');
                    loadExpenses();
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to resubmit.');
                }
            });
        });

        $('.btn-admin-edit').off('click').on('click', function () {
            var btn = $(this);
            var form = $('#form-admin-edit');
            form.find('[name="expense_id"]').val(btn.data('id'));
            form.find('[name="amount"]').val(btn.data('amount'));
            form.find('[name="category_id"]').val(btn.data('category'));
            form.find('[name="description"]').val(btn.data('description'));
            form.find('[name="reference_no"]').val(btn.data('reference'));
            form.find('[name="attachment_url"]').val(btn.data('attachment'));
            form.find('[name="edit_reason"]').val('');
            $('#modal_admin_edit').modal('show');
        });

        $('.btn-unflag').off('click').on('click', function () {
            var id = $(this).data('id');
            if (!confirm('Dismiss the flag on this expense?')) return;
            $.ajax({
                url: apiBase + 'expenses/' + id + '/unflag',
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    toastr.success(res.message || 'Flag dismissed.');
                    loadExpenses();
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to unflag.');
                }
            });
        });

        $('.btn-void').off('click').on('click', function () {
            $('#void-expense-id').val($(this).data('id'));
            $('#void-reason').val('');
            $('#modal_void').modal('show');
        });

        $('.btn-audit').off('click').on('click', function () {
            var id = $(this).data('id');
            loadAuditTrail(id);
        });
    }

    function renderPagination(meta) {
        if (!meta) return;
        $('#pagination-info').text('Showing page ' + meta.current_page + ' of ' + meta.last_page + ' (' + meta.total + ' total)');

        var links = '';
        if (meta.current_page > 1) {
            links += '<button class="btn btn-sm btn-outline-primary mr-1 btn-page" data-page="' + (meta.current_page - 1) + '">&laquo; Prev</button>';
        }
        if (meta.current_page < meta.last_page) {
            links += '<button class="btn btn-sm btn-outline-primary btn-page" data-page="' + (meta.current_page + 1) + '">Next &raquo;</button>';
        }
        $('#pagination-links').html(links);
        $('.btn-page').off('click').on('click', function () {
            currentPage = $(this).data('page');
            loadExpenses();
        });
    }

    function updateStatusCounts(counts) {
        if (!counts) return;
        $('#count-pending').text(counts.pending || 0);
        $('#count-approved').text(counts.approved || 0);
        $('#count-rejected').text(counts.rejected || 0);
        $('#count-flagged').text(counts.flagged || 0);
    }

    // ===================== SUBMIT ACTIONS =====================

    function submitExpense() {
        var form = $('#form-expense');
        var data = {};
        form.find('input, select, textarea').each(function () {
            var name = $(this).attr('name');
            if (!name) return;
            if ($(this).is(':checkbox')) {
                data[name] = $(this).is(':checked') ? 1 : 0;
            } else {
                data[name] = $(this).val();
            }
        });

        if (!data.expense_date || !data.amount || !data.category_id || !data.paid_from_pool_id || !data.payment_method_id || !data.description) {
            toastr.warning('Please fill all required fields.');
            return;
        }

        // For Branch must be selected (branch or General checkbox)
        if (!data.is_for_general && !data.for_branch_id) {
            toastr.warning('Please select a branch or mark as General / Company-wide.');
            return;
        }

        // Cash expenses MUST have attachment
        var selectedPM = $('[name="payment_method_id"] option:selected', form).text().toLowerCase();
        if (selectedPM.indexOf('cash') !== -1 && !data.attachment_url) {
            toastr.error('Attachment is mandatory for cash expenses.');
            return;
        }

        // Whole numbers only
        if (data.amount && data.amount % 1 !== 0) {
            toastr.warning('Amount must be a whole number (no decimals).');
            return;
        }

        var btn = $('#btn-submit-expense');
        btn.prop('disabled', true).html('<i class="spinner spinner-white spinner-sm mr-2"></i> Submitting...');

        $.ajax({
            url: apiBase + 'expenses/store',
            type: 'POST',
            data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    loadExpenses();

                    // Post-save: form stays open, only date resets to today
                    form.find('[name="amount"]').val('');
                    form.find('[name="description"]').val('');
                    form.find('[name="reference_no"]').val('');
                    form.find('[name="attachment_url"]').val('');
                    form.find('[name="notes"]').val('');
                    form.find('[name="category_id"]').val('');
                    form.find('[name="paid_from_pool_id"]').val('');
                    form.find('[name="for_branch_id"]').val('');
                    form.find('[name="payment_method_id"]').val('');
                    form.find('[name="vendor_id"]').val('');
                    form.find('[name="staff_id"]').val('');
                    form.find('#chk-general').prop('checked', false);
                    form.find('#expense-branch-select').prop('disabled', false);
                    form.find('[name="expense_date"]').val(getTodayStr());
                    $('#threshold-hint').html('');

                    // Detailed confirmation popup per spec
                    var exp = res.data || {};
                    var poolObj = exp.paid_from_pool || {};
                    var pool = poolObj.name || 'N/A';
                    var newBalance = poolObj.cached_balance !== undefined ? 'PKR ' + numberFormat(poolObj.cached_balance) : 'N/A';
                    var amt = 'PKR ' + numberFormat(exp.amount || 0);
                    var status = (exp.status === 'approved') ? '<span class="text-success font-weight-bold">Auto-Approved</span>' : '<span class="text-warning font-weight-bold">Pending Approval</span>';
                    var attach = exp.attachment_url ? '<span class="text-success"><i class="la la-check-circle"></i> Attached</span>' : '<span class="text-danger"><i class="la la-times-circle"></i> No attachment</span>';

                    Swal.fire({
                        icon: 'success',
                        title: 'Expense Saved',
                        html: '<div class="text-left">' +
                            '<table class="table table-sm mb-0">' +
                            '<tr><td class="font-weight-bold">Amount</td><td>' + amt + '</td></tr>' +
                            '<tr><td class="font-weight-bold">Pool</td><td>' + escapeHtml(pool) + '</td></tr>' +
                            '<tr><td class="font-weight-bold">New Balance</td><td>' + newBalance + '</td></tr>' +
                            '<tr><td class="font-weight-bold">Status</td><td>' + status + '</td></tr>' +
                            '<tr><td class="font-weight-bold">Attachment</td><td>' + attach + '</td></tr>' +
                            '</table></div>',
                        confirmButtonText: 'OK',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false,
                    });
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                var resp = xhr.responseJSON;
                if (resp && resp.errors) {
                    $.each(resp.errors, function (field, msgs) {
                        toastr.error(msgs[0]);
                    });
                } else {
                    toastr.error(resp ? resp.message : 'Failed to submit expense.');
                }
            },
            complete: function () {
                btn.prop('disabled', false).html('Submit Expense');
            }
        });
    }

    function submitReject() {
        var id = $('#reject-expense-id').val();
        var reason = $('#rejection-reason').val();

        if (!reason || reason.length < 5) {
            toastr.warning('Rejection reason must be at least 5 characters.');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'expenses/' + id + '/reject',
            type: 'POST',
            data: { rejection_reason: reason },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                toastr.success(res.message || 'Expense rejected.');
                $('#modal_reject').modal('hide');
                loadExpenses();
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to reject.');
            },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    function submitVoid() {
        var id = $('#void-expense-id').val();
        var reason = $('#void-reason').val();

        if (!reason || reason.length < 10) {
            toastr.warning('Void reason must be at least 10 characters.');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'expenses/' + id + '/void',
            type: 'POST',
            data: { void_reason: reason },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                toastr.success(res.message || 'Expense voided.');
                $('#modal_void').modal('hide');
                loadExpenses();
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to void.');
            },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    function submitAdminEdit() {
        var form = $('#form-admin-edit');
        var id = form.find('[name="expense_id"]').val();
        var data = {};
        form.find('input, select, textarea').each(function () {
            var name = $(this).attr('name');
            if (name && name !== 'expense_id') {
                var val = $(this).val();
                if (val) data[name] = val;
            }
        });

        if (!data.edit_reason || data.edit_reason.length < 5) {
            toastr.warning('Edit reason must be at least 5 characters.');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'expenses/' + id + '/edit',
            type: 'POST',
            data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal_admin_edit').modal('hide');
                    loadExpenses();
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                var resp = xhr.responseJSON;
                if (resp && resp.errors) {
                    $.each(resp.errors, function (field, msgs) { toastr.error(msgs[0]); });
                } else {
                    toastr.error(resp ? resp.message : 'Failed to update.');
                }
            },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    // ===================== AUDIT TRAIL =====================

    function loadAuditTrail(expenseId) {
        $('#audit-loading').removeClass('d-none');
        $('#audit-timeline').addClass('d-none').empty();
        $('#modal_audit').modal('show');

        $.ajax({
            url: apiBase + 'expenses/' + expenseId + '/audit',
            type: 'GET',
            success: function (res) {
                $('#audit-loading').addClass('d-none');
                if (res.success && res.data.length > 0) {
                    var html = '';
                    var actionIcons = {
                        created: 'la-plus-circle', updated: 'la-edit', approved: 'la-check-circle',
                        rejected: 'la-times-circle', voided: 'la-ban', resubmitted: 'la-redo',
                        locked: 'la-lock', unlocked: 'la-unlock', deactivated: 'la-toggle-off',
                        auto_created: 'la-magic', reset: 'la-undo'
                    };
                    var actionColors = {
                        created: '#3699FF', updated: '#8950FC', approved: '#1BC5BD',
                        rejected: '#F64E60', voided: '#181C32', resubmitted: '#FFA800',
                        locked: '#7E8299', unlocked: '#7E8299', deactivated: '#F64E60',
                        auto_created: '#3699FF', reset: '#FFA800'
                    };

                    $.each(res.data, function (i, log) {
                        var actionBadge = getActionBadge(log.action);
                        var userName = log.user ? log.user.name : 'System';
                        var time = new Date(log.created_at).toLocaleString();
                        var icon = actionIcons[log.action] || 'la-history';
                        var color = actionColors[log.action] || '#7E8299';
                        var isLast = (i === res.data.length - 1);

                        html += '<div class="d-flex align-items-start' + (isLast ? '' : ' mb-4') + '">' +
                            '<div class="flex-shrink-0 mr-4 text-center" style="width:40px;">' +
                            '<div style="width:36px;height:36px;border-radius:50%;background:' + color + '15;display:flex;align-items:center;justify-content:center;">' +
                            '<i class="la ' + icon + '" style="font-size:18px;color:' + color + ';"></i></div>' +
                            (isLast ? '' : '<div style="width:2px;height:20px;background:#E4E6EF;margin:4px auto 0;"></div>') +
                            '</div>' +
                            '<div class="flex-grow-1 pb-3' + (isLast ? '' : ' border-bottom') + '">' +
                            '<div class="d-flex justify-content-between align-items-center">' +
                            '<div>' + actionBadge + ' <span class="font-weight-bold ml-1">' + escapeHtml(userName) + '</span></div>' +
                            '<span class="text-muted font-size-xs">' + time + '</span>' +
                            '</div>' +
                            (log.reason ? '<div class="text-muted font-size-sm mt-1"><i class="la la-comment-alt mr-1"></i>' + escapeHtml(log.reason) + '</div>' : '') +
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

    function getActionBadge(action) {
        var colors = {
            created: 'primary', updated: 'info', approved: 'success', rejected: 'danger',
            voided: 'dark', resubmitted: 'warning', locked: 'secondary', unlocked: 'secondary',
            deactivated: 'danger', auto_created: 'primary', reset: 'warning'
        };
        var color = colors[action] || 'secondary';
        return '<span class="label label-light-' + color + ' label-inline">' + action.replace('_', ' ').toUpperCase() + '</span>';
    }

    // ===================== VENDOR / CATEGORY REQUEST =====================

    function submitVendorRequest() {
        var form = $('#form-vendor-request');
        var name = form.find('[name="name"]').val();
        if (!name) { toastr.warning('Vendor name is required.'); return; }

        var btn = $('#btn-submit-vendor-request');
        btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'vendor-requests/store',
            type: 'POST',
            data: { name: name, phone: form.find('[name="phone"]').val(), note: form.find('[name="note"]').val() },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                toastr.success(res.message || 'Vendor request submitted.');
                $('#modal_vendor_request').modal('hide');
                form[0].reset();
            },
            error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to submit request.'); },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    function submitCategoryRequest() {
        var form = $('#form-category-request');
        var name = form.find('[name="name"]').val();
        if (!name) { toastr.warning('Category name is required.'); return; }

        var btn = $('#btn-submit-category-request');
        btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'category-requests/store',
            type: 'POST',
            data: { name: name, description: form.find('[name="description"]').val() },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                toastr.success(res.message || 'Category suggestion submitted.');
                $('#modal_category_request').modal('hide');
                form[0].reset();
            },
            error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to submit suggestion.'); },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    // ===================== EXPORT =====================

    function exportExpenses() {
        var params = {
            status: $('#filter-status').val() || '',
            search: $('#filter-search').val() || '',
            branch_id: $('#filter-branch').val() || '',
            category_id: $('#filter-category').val() || '',
            date_from: getDateRange().date_from,
            date_to: getDateRange().date_to,
        };
        var qs = $.param(params);
        window.open(apiBase + 'expenses/export?' + qs, '_blank');
    }

    // ===================== HELPERS =====================

    function buildEditTooltip(exp) {
        var parts = ['Edited'];
        if (exp.last_edit_log) {
            if (exp.last_edit_log.user) parts.push('by ' + exp.last_edit_log.user.name);
            if (exp.last_edit_log.created_at) {
                var d = new Date(exp.last_edit_log.created_at);
                parts.push('on ' + ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear());
            }
        }
        if (exp.edit_reason) parts.push('— Reason: ' + exp.edit_reason);
        return parts.join(' ');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function numberFormat(num) {
        return parseFloat(num || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 });
    }

    function getTodayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function truncate(str, len) {
        if (!str) return '';
        return str.length > len ? str.substring(0, len) + '...' : str;
    }

    return { init: init };
})();

$(document).ready(function () {
    CashflowExpenses.init();
});
