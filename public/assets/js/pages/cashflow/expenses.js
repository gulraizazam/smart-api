"use strict";

var CashflowExpenses = (function () {
    var apiBase = '/api/cashflow/';
    var currentPage = 1;
    var formData = null; // cached dropdown data
    var threshold = 10000;
    var expensesXhr = null;

    var dateRangeSelected = false;

    function initDateRange() {
        $('#filter-date-range').daterangepicker({
            locale: { format: 'MM/DD/YYYY', cancelLabel: 'Clear' },
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
            autoUpdateInput: false
        });

        $('#filter-date-range').on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
            dateRangeSelected = true;
        });

        $('#filter-date-range').on('cancel.daterangepicker', function () {
            $(this).val('');
            dateRangeSelected = false;
        });
    }

    function getDateRange() {
        if (!dateRangeSelected) return { date_from: '', date_to: '' };
        var picker = $('#filter-date-range').data('daterangepicker');
        if (!picker) return { date_from: '', date_to: '' };
        return {
            date_from: picker.startDate.format('YYYY-MM-DD'),
            date_to: picker.endDate.format('YYYY-MM-DD')
        };
    }

    function init() {
        initDateRange();
        var formDataReady = loadFormData();
        loadExpenses();
        bindEvents();

        // Auto-open modal if coming from dashboard quick-action or vendor Record Purchase
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'add') {
            var preVendorId = urlParams.get('vendor_id');
            formDataReady.done(function () {
                if (preVendorId) {
                    $('#form-expense [name="vendor_id"]').val(preVendorId);
                }
                $('#modal_expense').modal('show');
                // After modal shown, trigger vendor select2 update
                if (preVendorId) {
                    setTimeout(function () { $('#form-expense [name="vendor_id"]').val(preVendorId).trigger('change'); }, 300);
                }
            });
        }
    }

    function bindEvents() {
        $('#btn-filter').on('click', function () { currentPage = 1; loadExpenses(); });
        $('#filter-status').on('change', function () { currentPage = 1; loadExpenses(); });
        $('#filter-search').on('keypress', function (e) { if (e.which === 13) { currentPage = 1; loadExpenses(); } });
        $('#btn-reset-filters').on('click', function () {
            $('#filter-status').val('').trigger('change');
            $('#filter-branch').val('').trigger('change');
            $('#filter-pool').val('').trigger('change');
            $('#filter-category').val('').trigger('change');
            $('#filter-search').val('');
            $('#filter-date-range').val('');
            dateRangeSelected = false;
            currentPage = 1;
            loadExpenses();
        });
        $('#btn-submit-expense').on('click', submitExpense);
        $('#btn-confirm-reject').on('click', submitReject);
        $('#btn-confirm-void').on('click', submitVoid);
        $('#btn-submit-admin-edit').on('click', submitAdminEdit);
        $('#btn-export-expenses').on('click', exportExpenses);
        $('#btn-vendor-not-listed').on('click', function () { $('#modal_vendor_request').modal('show'); });
        $('#btn-category-not-listed').on('click', function () { $('#modal_category_request').modal('show'); });
        $('#btn-submit-vendor-request').on('click', submitVendorRequest);
        $('#btn-submit-category-request').on('click', submitCategoryRequest);

        // Remove validation highlight on input
        $('#form-expense').on('input change', '.is-invalid', function () {
            $(this).removeClass('is-invalid');
            $(this).siblings('.select2-container').css('border', '').css('border-radius', '');
        });

        // Attachment URL: warn if non-Google-Drive URL (Sec 11.3)
        $('[name="attachment_url"]', '#form-expense').on('change', function () {
            var url = $(this).val();
            if (url && !/drive\.google\.com|docs\.google\.com/i.test(url)) {
                toastr.warning('This URL does not appear to be a Google Drive link. Please verify.');
            }
        });

        // Receipt image preview + clear (add modal)
        $(document).on('change', '#expense-image-input', function () {
            var file = this.files && this.files[0];
            if (!file) {
                $('#expense-image-preview-wrap').addClass('d-none');
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#expense-image-preview').attr('src', e.target.result);
                $('#expense-image-preview-wrap').removeClass('d-none');
            };
            reader.readAsDataURL(file);
        });
        $(document).on('click', '#expense-image-clear', function () {
            $('#expense-image-input').val('');
            $('#expense-image-preview').attr('src', '');
            $('#expense-image-preview-wrap').addClass('d-none');
        });

        // Receipt image preview + clear (admin edit modal)
        $(document).on('change', '#edit-image-input', function () {
            var file = this.files && this.files[0];
            if (!file) {
                // No new file picked — revert to existing preview if any.
                var existing = $('#form-admin-edit').data('existing-image-url');
                if (existing) {
                    $('#edit-image-preview').attr('src', existing);
                    $('#edit-image-view').attr('href', existing);
                    $('#edit-image-preview-wrap').removeClass('d-none');
                } else {
                    $('#edit-image-preview-wrap').addClass('d-none');
                }
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#edit-image-preview').attr('src', e.target.result);
                $('#edit-image-view').attr('href', e.target.result);
                $('#edit-image-preview-wrap').removeClass('d-none');
            };
            reader.readAsDataURL(file);
        });
        $(document).on('click', '#edit-image-clear', function () {
            $('#edit-image-input').val('').trigger('click');
        });

        // Filter pools when payment method changes
        $('[name="payment_method_id"]', '#form-expense').on('change', function () {
            var pmText = $(this).find('option:selected').text().toLowerCase();
            filterPoolsByPaymentMethod(pmText);
        });

        // Show/hide vendor field based on category vendor_emphasis
        $('[name="category_id"]', '#form-expense').on('change', function () {
            var opt = $(this).find('option:selected');
            var vendorEmphasis = opt.data('vendor') == 1;
            var vendorGroup = $('#vendor-group');
            if (vendorEmphasis) {
                vendorGroup.show().addClass('bg-light-warning p-3 rounded');
            } else {
                vendorGroup.hide().removeClass('bg-light-warning p-3 rounded');
                $('#form-expense [name="vendor_id"]').val('').trigger('change');
            }
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

        // Init date picker on expense_date field (last 7 days only)
        $('#form-expense [name="expense_date"]').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            autoUpdateInput: false,
            minDate: moment().subtract(7, 'days'),
            maxDate: moment(),
            locale: { format: 'YYYY-MM-DD' }
        }).on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD'));
        });

        // Pre-fill date and init Select2 on modal open
        $('#modal_expense').on('shown.bs.modal', function () {
            var dateField = $('#form-expense [name="expense_date"]');
            if (!dateField.val()) dateField.val(getTodayStr());
            var modalBody = $(this).find('.modal-body');
            modalBody.find('[name="category_id"]').select2({ placeholder: 'Select category', dropdownParent: modalBody });
            modalBody.find('[name="paid_from_pool_id"]').select2({ placeholder: 'Select pool or staff', dropdownParent: modalBody });
            modalBody.find('[name="payment_method_id"]').select2({ placeholder: 'Select method', dropdownParent: modalBody });
            modalBody.find('[name="for_branch_id"]').select2({ placeholder: 'Select', dropdownParent: modalBody });
            modalBody.find('[name="vendor_id"]').select2({ placeholder: 'Select vendor', dropdownParent: modalBody });

            // After Select2 init, filter pools by pre-selected payment method (for duplicate-from-voided)
            var initPm = modalBody.find('[name="payment_method_id"] option:selected').text().toLowerCase();
            if (initPm && initPm !== 'select method') {
                filterPoolsByPaymentMethod(initPm, '#form-expense');
            }

            // Show/hide vendor group based on selected category
            var catOpt = modalBody.find('[name="category_id"] option:selected');
            var hasVendorEmphasis = catOpt.data('vendor') == 1;
            var hasVendorPreFilled = !!modalBody.find('[name="vendor_id"]').val();
            if (hasVendorEmphasis || hasVendorPreFilled) {
                $('#vendor-group').show();
                if (hasVendorEmphasis) $('#vendor-group').addClass('bg-light-warning p-3 rounded');
            } else {
                $('#vendor-group').hide();
            }

            // Sync Select2 with pre-filled values
            modalBody.find('select.kt-select2-general').trigger('change.select2');
        });

        // Reset modal on close
        $('#modal_expense').on('hidden.bs.modal', function () {
            $(this).find('.kt-select2-general').select2('destroy');
            $('#form-expense')[0].reset();
            $('#expense-modal-title').text('New Expense');
            $('#threshold-hint').html('');
            $('#vendor-group').removeClass('bg-light-warning p-3 rounded').show();
            $('#expense-image-preview').attr('src', '');
            $('#expense-image-preview-wrap').addClass('d-none');
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
        return $.ajax({
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

        // Pools + Staff (include type for filtering by payment method)
        html = '<option value="">Select</option>';
        html += '<optgroup label="Cash Pools">';
        $.each(data.pools, function (i, pool) {
            html += '<option value="' + pool.id + '" data-type="' + (pool.type || '') + '">' + escapeHtml(pool.name) + '</option>';
        });
        html += '</optgroup>';
        if (data.staff && data.staff.length) {
            html += '<optgroup label="Staff Advances">';
            $.each(data.staff, function (i, s) {
                html += '<option value="staff_' + s.id + '" data-type="staff">' + escapeHtml(s.name) + '</option>';
            });
            html += '</optgroup>';
        }
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

        // Branches ("For" field with General as first option)
        html = '<option value="">Select</option><option value="general">General / Company-wide</option>';
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

        // Filter pools (paid from)
        html = '<option value="">All Pools</option>';
        $.each(data.pools, function (i, pool) {
            html += '<option value="' + pool.id + '">' + escapeHtml(pool.name) + '</option>';
        });
        $('#filter-pool').html(html);

        // Payment modes (exclude 'card' type)
        html = '<option value="">Select method</option>';
        $.each(data.payment_modes, function (i, pm) {
            if (pm.name && pm.name.toLowerCase().indexOf('card') !== -1) return; // skip card
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
            html += '<option value="' + cat.id + '" data-vendor="' + (cat.vendor_emphasis ? 1 : 0) + '">' + escapeHtml(cat.name) + '</option>';
        });
        $('#edit-category-select').html(html);

        // Filter pools on initial load if payment method is pre-set (e.g. duplicate)
        var initPm = $('[name="payment_method_id"]', '#form-expense').find('option:selected').text().toLowerCase();
        if (initPm) filterPoolsByPaymentMethod(initPm);

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

        // Init Select2 on page-level filter selects (after options are populated)
        $('#filter-status').select2();
        $('#filter-branch').select2();
        $('#filter-pool').select2();
        $('#filter-category').select2();
    }

    /**
     * Filter pool dropdown options based on selected payment method.
     * Cash → branch_cash, head_office_cash pools
     * Bank/Wire Transfer → bank_account pools
     * No selection → show all
     */
    function filterPoolsByPaymentMethod(pmText, formSelector) {
        formSelector = formSelector || '#form-expense';
        var poolSelect = $('[name="paid_from_pool_id"]', formSelector);
        var currentVal = poolSelect.val();

        // Determine allowed pool types based on payment method name
        var allowedTypes = [];
        if (!pmText || pmText === 'select method' || pmText === 'keep current') {
            // No filter — show all
            allowedTypes = [];
        } else if (pmText.indexOf('card') !== -1 || pmText.indexOf('credit') !== -1 ||
                   pmText.indexOf('bank') !== -1 || pmText.indexOf('wire') !== -1 || pmText.indexOf('transfer') !== -1) {
            // Card and Bank/Wire both come from bank account pools — staff advances not applicable
            allowedTypes = ['bank_account'];
        } else {
            // Cash or any other — show cash pools AND staff advances
            allowedTypes = ['branch_cash', 'head_office_cash', 'staff'];
        }

        // Count how many matching options exist
        var matchCount = 0;
        poolSelect.find('option').each(function () {
            var opt = $(this);
            if (!opt.val()) return;
            var type = opt.data('type') || '';
            if (allowedTypes.length === 0 || allowedTypes.indexOf(type) !== -1) {
                matchCount++;
            }
        });

        // If no matching pools exist for this payment method, show all pools (don't block user)
        if (matchCount === 0) {
            allowedTypes = [];
        }

        var lastMatch = null;
        poolSelect.find('option, optgroup').each(function () {
            var opt = $(this);
            if (opt.is('optgroup')) return;
            if (!opt.val()) return; // keep placeholder
            var type = opt.data('type') || '';
            if (allowedTypes.length === 0 || allowedTypes.indexOf(type) !== -1) {
                opt.prop('disabled', false).show();
                lastMatch = opt.val();
            } else {
                opt.prop('disabled', true).hide();
            }
        });

        // If current selection is now disabled, reset
        var selectedOpt = poolSelect.find('option[value="' + currentVal + '"]');
        if (currentVal && selectedOpt.prop('disabled')) {
            poolSelect.val('').trigger('change.select2');
        }

        // Auto-select if only one matching pool
        if (matchCount === 1 && !poolSelect.val()) {
            poolSelect.val(lastMatch).trigger('change.select2');
        }
    }

    function loadExpenses() {
        if (expensesXhr) expensesXhr.abort();
        var dr = getDateRange();
        var params = {
            page: currentPage,
            per_page: 25,
            status: $('#filter-status').val(),
            branch_id: $('#filter-branch').val(),
            pool_id: $('#filter-pool').val(),
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
            if (exp.status === 'rejected') rowClass += ' expense-rejected';
            if (exp.voided_at) rowClass += ' expense-voided';

            var statusBadge = getStatusBadge(exp);
            var branchLabel = exp.is_for_general ? '<span class="text-muted">General</span>' : (exp.for_branch ? exp.for_branch.name : '-');
            var poolLabel = exp.staff ? exp.staff.name : (exp.paid_from_pool ? exp.paid_from_pool.name : '-');

            var actions = buildActions(exp);

            // Build the attachment icon: prefer Drive preview when both
            // exist, fall back to the uploaded image otherwise.
            var attachIcon = '';
            if (exp.attachment_url) {
                attachIcon = ' <a href="javascript:;" class="btn-preview" data-url="' + escapeHtml(exp.attachment_url) + '" title="Preview attachment"><i class="la la-paperclip text-primary"></i></a>';
            } else if (exp.attachment_image) {
                attachIcon = ' <a href="' + escapeHtml(buildImageUrl(exp.attachment_image)) + '" target="_blank" title="View receipt image"><i class="la la-image text-primary"></i></a>';
            }

            html +=
                '<tr class="' + rowClass + '">' +
                '<td>' + formatDate(exp.expense_date) + '</td>' +
                '<td>' + 
                    (exp.description.length > 50 ? 
                        '<span class="expense-description" title="' + escapeHtml(exp.description) + '">' + escapeHtml(truncate(exp.description, 50)) + '</span>' :
                        escapeHtml(exp.description)
                    ) +
                    attachIcon +
                    (exp.is_flagged ? ' <i class="la la-flag text-danger" title="' + escapeHtml(exp.flag_reason || '') + '"></i>' : '') +
                    (exp.voided_at ? ' <span class="label label-dark label-inline font-size-xs">VOID</span>' : '') +
                    (exp.edit_reason ? ' <i class="la la-pencil text-warning" title="' + escapeHtml(buildEditTooltip(exp)) + '"></i>' : '') +
                '</td>' +
                '<td>' + (exp.category ? escapeHtml(exp.category.name) : '-') + '</td>' +
                '<td><small>' + escapeHtml(poolLabel) + '</small></td>' +
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
        if (exp.voided_at) return '<span class="label label-danger label-inline status-badge" title="' + escapeHtml(exp.void_reason || '') + '">Voided</span>';
        switch (exp.status) {
            case 'approved': badges = '<span class="label label-light-success label-inline status-badge">Approved</span>'; break;
            case 'pending': badges = '<span class="label label-warning label-inline status-badge">Pending</span>'; break;
            case 'rejected': badges = '<span class="label label-outline-danger label-inline status-badge" title="' + escapeHtml(exp.rejection_reason || '') + '">Rejected</span>'; break;
            default: badges = exp.status;
        }
        if (exp.is_flagged) badges += ' <span class="label label-light-warning label-inline font-size-xs">Flagged</span>';
        if (exp.edit_reason) badges += ' <span class="label label-light-primary label-inline font-size-xs">Edited</span>';
        return badges;
    }

    function buildActions(exp) {
        var btns = '';

        var perms = window.cfPerms || {};

        // View (always available)
        btns += '<button class="btn btn-sm btn-clean btn-icon btn-view-expense" data-expense=\'' + JSON.stringify(exp).replace(/'/g, '&#39;') + '\' title="View"><i class="la la-eye text-dark-50"></i></button>';

        // Approve (pending, non-voided)
        if (perms.canApprove && exp.status === 'pending' && !exp.voided_at) {
            var hasAttach = (exp.attachment_url || exp.attachment_image) ? '1' : '0';
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-approve" data-id="' + exp.id + '" data-attachment="' + hasAttach + '" title="Approve"><i class="la la-check-circle text-success"></i></button>';
        }

        // Reject (pending, non-voided)
        if (perms.canReject && exp.status === 'pending' && !exp.voided_at) {
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-reject" data-id="' + exp.id + '" title="Reject"><i class="la la-times-circle text-danger"></i></button>';
        }

        // Resubmit (rejected entries)
        if (perms.canResubmit && exp.status === 'rejected' && !exp.voided_at) {
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-resubmit" data-id="' + exp.id + '" ' +
                'data-date="' + ((exp.expense_date || '').substring(0, 10)) + '" ' +
                'data-amount="' + (parseInt(exp.amount) || 0) + '" ' +
                'data-category="' + exp.category_id + '" ' +
                'data-pool="' + (exp.paid_from_pool_id || '') + '" ' +
                'data-staff="' + (exp.staff_id || '') + '" ' +
                'data-payment="' + (exp.payment_method_id || '') + '" ' +
                'data-branch="' + (exp.for_branch_id || '') + '" ' +
                'data-general="' + (exp.is_for_general ? 1 : 0) + '" ' +
                'data-vendor="' + (exp.vendor_id || '') + '" ' +
                'data-description="' + escapeHtml(exp.description) + '" ' +
                'data-attachment="' + escapeHtml(exp.attachment_url || '') + '" ' +
                'data-image="' + escapeHtml(exp.attachment_image || '') + '" ' +
                'title="Edit & Resubmit"><i class="la la-redo text-info"></i></button>';
        }

        // Admin Edit (non-voided, admin only — Sec 5.7)
        if (perms.canEdit && !exp.voided_at) {
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-admin-edit" data-id="' + exp.id + '" ' +
                'data-date="' + ((exp.expense_date || '').substring(0, 10)) + '" ' +
                'data-amount="' + (parseInt(exp.amount) || 0) + '" ' +
                'data-category="' + exp.category_id + '" ' +
                'data-pool="' + (exp.paid_from_pool_id || '') + '" ' +
                'data-staff="' + (exp.staff_id || '') + '" ' +
                'data-payment="' + (exp.payment_method_id || '') + '" ' +
                'data-branch="' + (exp.for_branch_id || '') + '" ' +
                'data-general="' + (exp.is_for_general ? 1 : 0) + '" ' +
                'data-vendor="' + (exp.vendor_id || '') + '" ' +
                'data-description="' + escapeHtml(exp.description) + '" ' +
                'data-attachment="' + escapeHtml(exp.attachment_url || '') + '" ' +
                'data-image="' + escapeHtml(exp.attachment_image || '') + '" ' +
                'title="Edit"><i class="la la-edit text-primary"></i></button>';
        }

        // Unflag (flagged, non-voided)
        if (perms.canUnflag && exp.is_flagged && !exp.voided_at) {
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-unflag" data-id="' + exp.id + '" title="Dismiss Flag"><i class="la la-flag text-warning"></i></button>';
        }

        // Void (admin only, non-voided — Sec 11.5)
        if (perms.canVoid && !exp.voided_at) {
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-void" data-id="' + exp.id + '" title="Void"><i class="la la-ban text-dark"></i></button>';
        }

        // Duplicate as New (voided expenses — re-enter with same data)
        if (exp.voided_at && perms.canDuplicate) {
            var dupDate = (exp.expense_date || '').substring(0, 10);
            var dupAmount = parseInt(exp.amount) || 0;
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-duplicate" ' +
                'data-date="' + dupDate + '" ' +
                'data-amount="' + dupAmount + '" ' +
                'data-category="' + exp.category_id + '" ' +
                'data-pool="' + (exp.paid_from_pool_id || '') + '" ' +
                'data-staff="' + (exp.staff_id || '') + '" ' +
                'data-payment="' + (exp.payment_method_id || '') + '" ' +
                'data-branch="' + (exp.for_branch_id || '') + '" ' +
                'data-general="' + (exp.is_for_general ? 1 : 0) + '" ' +
                'data-vendor="' + (exp.vendor_id || '') + '" ' +
                'data-description="' + escapeHtml(exp.description || '') + '" ' +
                'data-attachment="' + escapeHtml(exp.attachment_url || '') + '" ' +
                'data-image="' + escapeHtml(exp.attachment_image || '') + '" ' +
                'title="Duplicate as New"><i class="la la-copy text-info"></i></button>';
        }

        // Audit trail
        if (typeof cfPerms !== 'undefined' && cfPerms.canAudit) {
            btns += '<button class="btn btn-sm btn-clean btn-icon btn-audit" data-id="' + exp.id + '" title="Audit Trail"><i class="la la-history text-muted"></i></button>';
        }

        return btns;
    }

    function bindActionButtons() {
        // View expense detail
        $('.btn-view-expense').off('click').on('click', function () {
            var exp = $(this).data('expense');
            if (typeof exp === 'string') exp = JSON.parse(exp);

            var statusHtml = '';
            if (exp.voided_at) {
                statusHtml = '<span class="label label-danger label-inline">Voided</span>';
            } else if (exp.status === 'approved') {
                statusHtml = '<span class="label label-light-success label-inline">Approved</span>';
            } else if (exp.status === 'pending') {
                statusHtml = '<span class="label label-warning label-inline">Pending</span>';
            } else if (exp.status === 'rejected') {
                statusHtml = '<span class="label label-outline-danger label-inline">Rejected</span>';
            }
            if (exp.is_flagged) statusHtml += ' <span class="label label-light-warning label-inline">Flagged</span>';

            $('#view-exp-date').text(formatDate(exp.expense_date));
            $('#view-exp-amount').text('PKR ' + numberFormat(exp.amount));
            $('#view-exp-category').text(exp.category ? exp.category.name : '-');
            $('#view-exp-payment-method').text(exp.payment_method ? exp.payment_method.name : '-');
            $('#view-exp-paid-from').text(exp.staff ? exp.staff.name : (exp.paid_from_pool ? exp.paid_from_pool.name : '-'));
            $('#view-exp-branch').text(exp.is_for_general ? 'General' : (exp.for_branch ? exp.for_branch.name : '-'));
            $('#view-exp-vendor').text(exp.vendor ? exp.vendor.name : '-');
            $('#view-exp-description').text(exp.description || '-');
            $('#view-exp-status').html(statusHtml);
            $('#view-exp-created-by').text(exp.creator ? exp.creator.name : '-');
            $('#view-exp-verified-by').text(exp.verifier ? exp.verifier.name : '-');

            // Attachment — Drive URL link
            if (exp.attachment_url) {
                var previewUrl = getDrivePreviewUrl(exp.attachment_url);
                if (previewUrl) {
                    $('#view-exp-attachment').html('<a href="javascript:;" class="btn-preview text-primary" data-url="' + escapeHtml(exp.attachment_url) + '"><i class="la la-paperclip mr-1"></i>View Drive Link</a>');
                } else {
                    $('#view-exp-attachment').html('<a href="' + escapeHtml(exp.attachment_url) + '" target="_blank" class="text-primary"><i class="la la-paperclip mr-1"></i>View Drive Link</a>');
                }
            } else if (!exp.attachment_image) {
                $('#view-exp-attachment').text('-');
            } else {
                $('#view-exp-attachment').empty();
            }

            // Attachment — uploaded image thumbnail
            if (exp.attachment_image) {
                var imgUrl = buildImageUrl(exp.attachment_image);
                $('#view-exp-image').attr('src', imgUrl);
                $('#view-exp-image-link').attr('href', imgUrl).attr('target', '_blank');
                $('#view-exp-image-wrap').removeClass('d-none');
            } else {
                $('#view-exp-image').attr('src', '');
                $('#view-exp-image-wrap').addClass('d-none');
            }

            // Rejection reason
            if (exp.status === 'rejected' && exp.rejection_reason) {
                $('#view-exp-rejection-row').show();
                $('#view-exp-rejection-reason').text(exp.rejection_reason);
            } else {
                $('#view-exp-rejection-row').hide();
            }

            // Void reason
            if (exp.voided_at && exp.void_reason) {
                $('#view-exp-void-row').show();
                $('#view-exp-void-reason').text(exp.void_reason);
            } else {
                $('#view-exp-void-row').hide();
            }

            // Flag reason
            if (exp.is_flagged && exp.flag_reason) {
                $('#view-exp-flag-row').show();
                $('#view-exp-flag-reason').text(exp.flag_reason);
            } else {
                $('#view-exp-flag-row').hide();
            }

            // Edit reason
            if (exp.edit_reason) {
                $('#view-exp-edit-row').show();
                $('#view-exp-edit-reason').text(exp.edit_reason);
            } else {
                $('#view-exp-edit-row').hide();
            }

            $('#modal_view_expense').modal('show');
        });

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
            var btn = $(this);
            var form = $('#form-admin-edit');

            // Mark as resubmit mode
            form.data('resubmit-mode', true);
            form.find('[name="expense_id"]').val(btn.data('id'));
            form.find('[name="expense_date"]').val(btn.data('date') || '');
            form.find('[name="amount"]').val(btn.data('amount'));
            form.find('[name="description"]').val(btn.data('description'));
            form.find('[name="attachment_url"]').val(btn.data('attachment'));
            form.find('[name="attachment_image"]').val('');
            applyExistingImagePreview(form, btn.data('image') || '');

            // Populate category dropdown
            var catHtml = '<option value="">Select category</option>';
            $('[name="category_id"]', '#form-expense').find('option').each(function () {
                if ($(this).val()) {
                    catHtml += '<option value="' + $(this).val() + '" data-vendor="' + ($(this).data('vendor') || '') + '">' + $(this).text() + '</option>';
                }
            });
            form.find('[name="category_id"]').html(catHtml).val(btn.data('category') || '');

            // Populate pool dropdown
            var poolHtml = '<option value="">Select pool or staff</option>';
            if (formData && formData.pools) {
                poolHtml += '<optgroup label="Cash Pools">';
                $.each(formData.pools, function (i, pool) {
                    poolHtml += '<option value="' + pool.id + '" data-type="' + (pool.type || '') + '">' + escapeHtml(pool.name) + '</option>';
                });
                poolHtml += '</optgroup>';
            }
            if (formData && formData.staff && formData.staff.length) {
                poolHtml += '<optgroup label="Staff Advances">';
                $.each(formData.staff, function (i, s) {
                    poolHtml += '<option value="staff_' + s.id + '" data-type="staff">' + escapeHtml(s.name) + '</option>';
                });
                poolHtml += '</optgroup>';
            }
            var poolVal = btn.data('pool') || '';
            var staffVal = btn.data('staff') || '';
            if (!poolVal && staffVal) poolVal = 'staff_' + staffVal;
            form.find('[name="paid_from_pool_id"]').html(poolHtml).val(poolVal);

            // Populate payment method dropdown
            var pmHtml = '<option value="">Select method</option>';
            $('[name="payment_method_id"]', '#form-expense').find('option').each(function () {
                if ($(this).val()) pmHtml += '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
            });
            form.find('[name="payment_method_id"]').html(pmHtml).val(btn.data('payment') || '');

            // Populate branch dropdown
            var branchHtml = '<option value="">Select</option>';
            $('[name="for_branch_id"]', '#form-expense').find('option').each(function () {
                if ($(this).val()) branchHtml += '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
            });
            if (btn.data('general') == 1) {
                form.find('[name="for_branch_id"]').html(branchHtml).val('general');
            } else {
                form.find('[name="for_branch_id"]').html(branchHtml).val(btn.data('branch') || '');
            }

            // Populate vendor dropdown
            var vendorHtml = '<option value="">Select vendor (optional)</option><option value="0">-- No Vendor --</option>';
            $('[name="vendor_id"]', '#form-expense').find('option').each(function () {
                if ($(this).val()) vendorHtml += '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
            });
            form.find('[name="vendor_id"]').html(vendorHtml).val(btn.data('vendor') || '');

            // Clear validation
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.select2-container').css('border', '').css('border-radius', '');

            // Hide edit reason field, update modal title & button
            form.find('[name="edit_reason"]').closest('.form-group').hide();
            $('#modal_admin_edit .modal-title').text('Edit & Resubmit Expense');
            $('#btn-submit-admin-edit').text('Resubmit');

            $('#modal_admin_edit').modal('show');
        });

        $('.btn-admin-edit').off('click').on('click', function () {
            var btn = $(this);
            var form = $('#form-admin-edit');
            form.find('[name="expense_id"]').val(btn.data('id'));
            form.find('[name="expense_date"]').val(btn.data('date') || '');
            form.find('[name="amount"]').val(btn.data('amount'));
            form.find('[name="description"]').val(btn.data('description'));
            form.find('[name="attachment_url"]').val(btn.data('attachment'));
            form.find('[name="attachment_image"]').val('');
            applyExistingImagePreview(form, btn.data('image') || '');
            form.find('[name="edit_reason"]').val('');

            // Populate category dropdown from create form options
            var catHtml = '<option value="">Select category</option>';
            $('[name="category_id"]', '#form-expense').find('option').each(function () {
                if ($(this).val()) {
                    catHtml += '<option value="' + $(this).val() + '" data-vendor="' + ($(this).data('vendor') || '') + '">' + $(this).text() + '</option>';
                }
            });
            form.find('[name="category_id"]').html(catHtml).val(btn.data('category') || '');

            // Populate pool dropdown from cached formData (includes staff optgroup)
            var poolHtml = '<option value="">Select pool or staff</option>';
            if (formData && formData.pools) {
                poolHtml += '<optgroup label="Cash Pools">';
                $.each(formData.pools, function (i, pool) {
                    poolHtml += '<option value="' + pool.id + '" data-type="' + (pool.type || '') + '">' + escapeHtml(pool.name) + '</option>';
                });
                poolHtml += '</optgroup>';
            }
            if (formData && formData.staff && formData.staff.length) {
                poolHtml += '<optgroup label="Staff Advances">';
                $.each(formData.staff, function (i, s) {
                    poolHtml += '<option value="staff_' + s.id + '" data-type="staff">' + escapeHtml(s.name) + '</option>';
                });
                poolHtml += '</optgroup>';
            }
            var poolVal = btn.data('pool') || '';
            var staffVal = btn.data('staff') || '';
            if (!poolVal && staffVal) {
                poolVal = 'staff_' + staffVal;
            }
            form.find('[name="paid_from_pool_id"]').html(poolHtml).val(poolVal);

            // Populate payment method dropdown from create form options
            var pmHtml = '<option value="">Select method</option>';
            $('[name="payment_method_id"]', '#form-expense').find('option').each(function () {
                if ($(this).val()) {
                    pmHtml += '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
                }
            });
            form.find('[name="payment_method_id"]').html(pmHtml).val(btn.data('payment') || '');

            // Populate branch/for dropdown from create form options
            var branchHtml = '<option value="">Select</option>';
            $('[name="for_branch_id"]', '#form-expense').find('option').each(function () {
                if ($(this).val()) {
                    branchHtml += '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
                }
            });
            if (btn.data('general') == 1) {
                form.find('[name="for_branch_id"]').html(branchHtml).val('general');
            } else {
                form.find('[name="for_branch_id"]').html(branchHtml).val(btn.data('branch') || '');
            }

            // Populate vendor dropdown from create form options
            var vendorHtml = '<option value="">Select vendor (optional)</option><option value="0">-- No Vendor --</option>';
            $('[name="vendor_id"]', '#form-expense').find('option').each(function () {
                if ($(this).val()) {
                    vendorHtml += '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
                }
            });
            form.find('[name="vendor_id"]').html(vendorHtml).val(btn.data('vendor') || '');

            // Clear validation highlights
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.select2-container').css('border', '').css('border-radius', '');

            // Set admin-edit mode (not resubmit)
            form.data('resubmit-mode', false);
            form.find('[name="edit_reason"]').closest('.form-group').show();
            $('#modal_admin_edit .modal-title').text('Edit Expense (Admin)');
            $('#btn-submit-admin-edit').text('Save Changes');

            $('#modal_admin_edit').modal('show');
        });

        // Filter pools when payment method changes in admin edit form
        $(document).on('change', '#form-admin-edit [name="payment_method_id"]', function () {
            var pmText = $(this).find('option:selected').text().toLowerCase();
            filterPoolsByPaymentMethod(pmText, '#form-admin-edit');
        });

        // Show/hide vendor field based on category vendor_emphasis in edit form
        $(document).on('change', '#form-admin-edit [name="category_id"]', function () {
            var vendorEmphasis = $(this).find(':selected').data('vendor') == 1;
            var hasVendorPreFilled = !!$('#form-admin-edit [name="vendor_id"]').val();
            if (vendorEmphasis || hasVendorPreFilled) {
                $('#edit-vendor-group').show();
                if (vendorEmphasis) $('#edit-vendor-group').addClass('bg-light-warning p-3 rounded');
                else $('#edit-vendor-group').removeClass('bg-light-warning p-3 rounded');
            } else {
                $('#edit-vendor-group').hide().removeClass('bg-light-warning p-3 rounded');
                $('#form-admin-edit [name="vendor_id"]').val('').trigger('change');
            }
        });

        // Init date picker for edit modal (last 7 days only)
        $('#form-admin-edit [name="expense_date"]').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            autoUpdateInput: false,
            minDate: moment().subtract(7, 'days'),
            maxDate: moment(),
            locale: { format: 'YYYY-MM-DD' }
        }).on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD'));
        });

        $('#modal_admin_edit').on('shown.bs.modal', function () {
            var mb = $(this).find('.modal-body');
            mb.find('[name="category_id"]').select2({ placeholder: 'Select category', dropdownParent: mb });
            mb.find('[name="payment_method_id"]').select2({ placeholder: 'Select method', dropdownParent: mb });
            mb.find('[name="paid_from_pool_id"]').select2({ placeholder: 'Select pool or staff', dropdownParent: mb });
            mb.find('[name="for_branch_id"]').select2({ placeholder: 'Select', dropdownParent: mb });
            mb.find('[name="vendor_id"]').select2({ placeholder: 'Select vendor', dropdownParent: mb });

            // Apply initial pool filter based on pre-selected payment method
            var initPm = mb.find('[name="payment_method_id"] option:selected').text().toLowerCase();
            filterPoolsByPaymentMethod(initPm, '#form-admin-edit');

            // Show/hide vendor group based on selected category
            var catOpt = mb.find('[name="category_id"] option:selected');
            var hasVendorEmphasis = catOpt.data('vendor') == 1;
            var hasVendorPreFilled = !!mb.find('[name="vendor_id"]').val();
            if (hasVendorEmphasis || hasVendorPreFilled) {
                $('#edit-vendor-group').show();
                if (hasVendorEmphasis) $('#edit-vendor-group').addClass('bg-light-warning p-3 rounded');
            } else {
                $('#edit-vendor-group').hide().removeClass('bg-light-warning p-3 rounded');
            }

            // Sync Select2 with pre-filled values
            mb.find('select.kt-select2-general').trigger('change.select2');
        }).on('hidden.bs.modal', function () {
            $(this).find('.kt-select2-general').select2('destroy');
            $('#form-admin-edit').removeData('existing-image-url');
            $('#edit-image-preview').attr('src', '');
            $('#edit-image-preview-wrap').addClass('d-none');
            $('#edit-image-input').val('');
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

        $('.btn-duplicate').off('click').on('click', function () {
            var btn = $(this);
            var form = $('#form-expense');
            form[0].reset();
            form.find('[name="expense_date"]').val(btn.data('date') || getTodayStr());
            form.find('[name="amount"]').val(btn.data('amount'));
            form.find('[name="category_id"]').val(btn.data('category')).trigger('change');
            var dupPool = btn.data('pool') || '';
            var dupStaff = btn.data('staff') || '';
            if (!dupPool && dupStaff) dupPool = 'staff_' + dupStaff;
            form.find('[name="paid_from_pool_id"]').val(dupPool).trigger('change');
            form.find('[name="payment_method_id"]').val(btn.data('payment')).trigger('change');
            form.find('[name="vendor_id"]').val(btn.data('vendor') || '').trigger('change');
            form.find('[name="description"]').val(btn.data('description'));
            form.find('[name="attachment_url"]').val(btn.data('attachment'));
            // Image attachment is intentionally NOT carried over — it would
            // re-link the same physical file across two expense rows, and
            // deleting the duplicate later would orphan the original.
            form.find('[name="attachment_image"]').val('');
            $('#expense-image-preview').attr('src', '');
            $('#expense-image-preview-wrap').addClass('d-none');
            if (btn.data('general') == 1) {
                form.find('[name="for_branch_id"]').val('general').trigger('change');
            } else {
                form.find('[name="for_branch_id"]').val(btn.data('branch')).trigger('change');
            }
            $('#expense-modal-title').text('New Expense (from voided)');
            $('#modal_expense').modal('show');
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

        $('.btn-preview').off('click').on('click', function () {
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

        $('#modal_preview').off('hidden.bs.modal').on('hidden.bs.modal', function () {
            $('#preview-iframe').attr('src', '');
        });
    }

    /**
     * Build the public URL for a stored attachment image (relative path
     * stored on the `public` filesystem disk → /storage/<path>).
     */
    function buildImageUrl(path) {
        if (!path) return '';
        if (/^https?:\/\//i.test(path)) return path;
        return '/storage/' + path.replace(/^\//, '');
    }

    /**
     * Show the existing receipt image (if any) in the admin-edit / resubmit
     * preview slot, and stash the public URL on the form's data store so
     * the cash-receipt validator can detect "image already on file".
     */
    function applyExistingImagePreview(form, imagePath) {
        var $form = form && form.length ? form : $('#form-admin-edit');
        $form.removeData('existing-image-url');
        if (imagePath) {
            var url = buildImageUrl(imagePath);
            $form.data('existing-image-url', url);
            $('#edit-image-preview').attr('src', url);
            $('#edit-image-view').attr('href', url);
            $('#edit-image-preview-wrap').removeClass('d-none');
        } else {
            $('#edit-image-preview').attr('src', '');
            $('#edit-image-preview-wrap').addClass('d-none');
        }
    }

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
            var $el = $(this);
            var name = $el.attr('name');
            if (!name) return;
            // File inputs are appended to FormData below — skip here so we
            // don't send the browser's "C:\fakepath\..." pseudo-path.
            if ($el.is('[type="file"]')) return;
            if ($el.is(':checkbox')) {
                data[name] = $el.is(':checked') ? 1 : 0;
            } else {
                data[name] = $el.val();
            }
        });

        // Handle merged branch/general dropdown
        if (data.for_branch_id === 'general') {
            data.is_for_general = 1;
            data.for_branch_id = '';
        } else {
            data.is_for_general = 0;
        }

        // Detect staff-advance selection and remap fields
        var poolVal = data['paid_from_pool_id'] || '';
        if (typeof poolVal === 'string' && poolVal.indexOf('staff_') === 0) {
            data['staff_id'] = poolVal.replace('staff_', '');
            data['paid_from_pool_id'] = '';
        } else {
            data['staff_id'] = '';
        }

        // Pull the receipt-image File object (if any) from the DOM directly
        // so the cash-receipt rule below can accept either an image upload
        // or a Drive URL.
        var imageInput = form.find('[name="attachment_image"]')[0];
        var imageFile = imageInput && imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;

        // Highlight missing required fields
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.select2-container').css('border', '').css('border-radius', '');
        var requiredFields = ['expense_date', 'amount', 'category_id', 'payment_method_id', 'description', 'for_branch_id'];
        var missing = [];
        $.each(requiredFields, function (i, name) {
            var val = (name === 'for_branch_id') ? form.find('[name="for_branch_id"]').val() : data[name];
            if (!val) {
                var el = form.find('[name="' + name + '"]');
                el.addClass('is-invalid');
                el.siblings('.select2-container').css('border', '1px solid #F64E60').css('border-radius', '0.42rem');
                missing.push(name);
            }
        });
        // Must have either a pool OR a staff member
        if (!data['paid_from_pool_id'] && !data['staff_id']) {
            var poolEl = form.find('[name="paid_from_pool_id"]');
            poolEl.addClass('is-invalid');
            poolEl.siblings('.select2-container').css('border', '1px solid #F64E60').css('border-radius', '0.42rem');
            missing.push('paid_from_pool_id');
        }
        // Cash expenses MUST have an attachment — either a Drive URL OR an uploaded image
        var selectedPM = $('[name="payment_method_id"] option:selected', form).text().toLowerCase();
        if (selectedPM.indexOf('cash') !== -1 && !data.attachment_url && !imageFile) {
            form.find('[name="attachment_image"]').addClass('is-invalid');
            form.find('[name="attachment_url"]').addClass('is-invalid');
            missing.push('attachment_image');
            toastr.warning('Cash expenses require a receipt — upload an image or paste a Google Drive URL.');
        }
        if (missing.length) {
            if (selectedPM.indexOf('cash') === -1 || data.attachment_url || imageFile) {
                toastr.warning('Please fill the highlighted fields.');
            }
            var first = form.find('.is-invalid:visible, .select2-container[style*="border"]').first();
            if (first.length) first[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // Whole numbers only
        if (data.amount && data.amount % 1 !== 0) {
            toastr.warning('Amount must be a whole number (no decimals).');
            return;
        }

        // Build multipart FormData so the browser sets the correct
        // Content-Type boundary for the file upload.
        var fd = new FormData();
        Object.keys(data).forEach(function (k) {
            if (data[k] !== null && data[k] !== undefined) {
                fd.append(k, data[k]);
            }
        });
        if (imageFile) {
            fd.append('attachment_image', imageFile);
        }

        var btn = $('#btn-submit-expense');
        btn.prop('disabled', true).html('<i class="spinner spinner-white spinner-sm mr-2"></i> Submitting...');

        $.ajax({
            url: apiBase + 'expenses/store',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    loadExpenses();
                    $('#modal_expense').modal('hide');

                    // Reset form fields
                    form.find('[name="amount"]').val('');
                    form.find('[name="description"]').val('');
                    form.find('[name="attachment_url"]').val('');
                    form.find('[name="attachment_image"]').val('');
                    form.find('[name="category_id"]').val('');
                    form.find('[name="paid_from_pool_id"]').val('');
                    form.find('[name="for_branch_id"]').val('');
                    form.find('[name="payment_method_id"]').val('');
                    form.find('[name="vendor_id"]').val('');
                    form.find('[name="staff_id"]').val('');
                    form.find('[name="expense_date"]').val(getTodayStr());
                    $('#threshold-hint').html('');
                    $('#expense-image-preview').attr('src', '');
                    $('#expense-image-preview-wrap').addClass('d-none');

                    // Detailed confirmation popup per spec
                    var exp = res.data || {};
                    var poolObj = exp.paid_from_pool || {};
                    var pool = poolObj.name || 'N/A';
                    var newBalance = poolObj.cached_balance !== undefined ? 'PKR ' + numberFormat(poolObj.cached_balance) : 'N/A';
                    var amt = 'PKR ' + numberFormat(exp.amount || 0);
                    var status = (exp.status === 'approved') ? '<span class="text-success font-weight-bold">Auto-Approved</span>' : '<span class="text-warning font-weight-bold">Pending Approval</span>';
                    var attach = (exp.attachment_url || exp.attachment_image) ? '<span class="text-success"><i class="la la-check-circle"></i> Attached</span>' : '<span class="text-danger"><i class="la la-times-circle"></i> No attachment</span>';

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
            var $el = $(this);
            var name = $el.attr('name');
            if (!name || name === 'expense_id') return;
            // File inputs are appended to FormData below.
            if ($el.is('[type="file"]')) return;
            if ($el.is(':checkbox')) {
                data[name] = $el.is(':checked') ? 1 : 0;
            } else {
                data[name] = $el.val();
            }
        });

        // Handle merged branch/general dropdown
        if (data.for_branch_id === 'general') {
            data.is_for_general = 1;
            data.for_branch_id = '';
        } else {
            data.is_for_general = 0;
        }

        // Handle vendor clear (value "0" means remove vendor)
        if (data.vendor_id === '0') {
            data.vendor_id = '';
        }

        // Detect staff-advance selection and remap fields
        var poolVal = data['paid_from_pool_id'] || '';
        if (typeof poolVal === 'string' && poolVal.indexOf('staff_') === 0) {
            data['staff_id'] = poolVal.replace('staff_', '');
            data['paid_from_pool_id'] = '';
        } else {
            data['staff_id'] = '';
        }

        // New image upload (if any). The existing image stays untouched
        // when no new file is picked, because the controller only writes
        // attachment_image when $request->hasFile() is true.
        var imageInput = form.find('[name="attachment_image"]')[0];
        var imageFile = imageInput && imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;

        // True when an image attachment is already saved on the expense
        // and was not just replaced with a new upload — used by the cash
        // rule below so admins editing an old expense aren't forced to
        // re-upload the same receipt.
        var hasExistingImage = !!form.data('existing-image-url');

        // Highlight missing required fields (same as add expense)
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.select2-container').css('border', '').css('border-radius', '');
        var isResubmit = form.data('resubmit-mode') === true;
        var requiredFields = ['expense_date', 'amount', 'category_id', 'payment_method_id', 'description', 'for_branch_id'];
        if (!isResubmit) requiredFields.push('edit_reason');
        var missing = [];
        $.each(requiredFields, function (i, name) {
            var val = (name === 'for_branch_id') ? form.find('[name="for_branch_id"]').val() : data[name];
            if (!val) {
                var el = form.find('[name="' + name + '"');
                el.addClass('is-invalid');
                el.siblings('.select2-container').css('border', '1px solid #F64E60').css('border-radius', '0.42rem');
                missing.push(name);
            }
        });
        // Must have either a pool OR a staff member
        if (!data['paid_from_pool_id'] && !data['staff_id']) {
            var poolEl = form.find('[name="paid_from_pool_id"]');
            poolEl.addClass('is-invalid');
            poolEl.siblings('.select2-container').css('border', '1px solid #F64E60').css('border-radius', '0.42rem');
            missing.push('paid_from_pool_id');
        }

        // Edit reason min length (admin edit only)
        if (!isResubmit && data.edit_reason && data.edit_reason.length < 5) {
            form.find('[name="edit_reason"]').addClass('is-invalid');
            if (missing.indexOf('edit_reason') === -1) missing.push('edit_reason');
        }

        // Cash expenses MUST have attachment — Drive URL OR a (new or existing) image
        var selectedPM = $('[name="payment_method_id"] option:selected', form).text().toLowerCase();
        if (selectedPM.indexOf('cash') !== -1 && !data.attachment_url && !imageFile && !hasExistingImage) {
            form.find('[name="attachment_image"]').addClass('is-invalid');
            form.find('[name="attachment_url"]').addClass('is-invalid');
            missing.push('attachment_image');
            toastr.warning('Cash expenses require a receipt — upload an image or paste a Google Drive URL.');
        }

        if (missing.length) {
            if (selectedPM.indexOf('cash') === -1 || data.attachment_url || imageFile || hasExistingImage) {
                toastr.warning('Please fill the highlighted fields.');
            }
            var first = form.find('.is-invalid:visible, .select2-container[style*="border"]').first();
            if (first.length) first[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // Whole numbers only
        if (data.amount && data.amount % 1 !== 0) {
            toastr.warning('Amount must be a whole number (no decimals).');
            return;
        }

        // Strip empty optional fields so they don't overwrite existing values with blank
        // NOTE: vendor_id must always be sent so the backend can detect vendor changes/clearing
        if (!data.attachment_url) delete data.attachment_url;

        // Build multipart FormData so the file rides along with the rest of the payload.
        var fd = new FormData();
        Object.keys(data).forEach(function (k) {
            if (data[k] !== null && data[k] !== undefined) {
                fd.append(k, data[k]);
            }
        });
        if (imageFile) {
            fd.append('attachment_image', imageFile);
        }

        var btn = $(this);
        btn.prop('disabled', true);

        var endpoint = isResubmit ? apiBase + 'expenses/' + id + '/resubmit' : apiBase + 'expenses/' + id + '/edit';

        $.ajax({
            url: endpoint,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message || (isResubmit ? 'Expense resubmitted.' : 'Changes saved.'));
                    $('#modal_admin_edit').modal('hide');
                    loadExpenses();
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                var resp = xhr.responseJSON;
                if (resp && resp.errors) {
                    // Highlight fields with server-side validation errors
                    $.each(resp.errors, function (field, msgs) {
                        var el = form.find('[name="' + field + '"]');
                        if (el.length) {
                            el.addClass('is-invalid');
                            el.siblings('.select2-container').css('border', '1px solid #F64E60').css('border-radius', '0.42rem');
                        }
                        toastr.error(msgs[0]);
                    });
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

                        var changeSummary = buildChangeSummary(log);

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

    /**
     * Build a human-readable summary of field-level changes from audit log old/new values.
     */
    function buildChangeSummary(log) {
        if (!log.old_values || !log.new_values) return '';
        if (log.action === 'created') return '';

        var oldV = log.old_values;
        var newV = log.new_values;
        var changes = [];

        // Fields to track with human-readable labels
        var fieldLabels = {
            expense_date: 'Date',
            amount: 'Amount',
            description: 'Description',
            status: 'Status',
            category_id: 'Category',
            paid_from_pool_id: 'Paid From Pool',
            payment_method_id: 'Payment Method',
            for_branch_id: 'Branch',
            vendor_id: 'Vendor',
            staff_id: 'Staff',
            attachment_url: 'Drive Link',
            attachment_image: 'Receipt Image',
            reference_no: 'Reference No',
            notes: 'Notes',
            is_for_general: 'General/Company-wide',
            rejection_reason: 'Rejection Reason',
            void_reason: 'Void Reason'
        };

        // Relationship name lookups from nested objects in old/new values
        function getRelName(values, field) {
            var map = {
                category_id: 'category',
                paid_from_pool_id: 'paid_from_pool',
                payment_method_id: 'payment_method',
                for_branch_id: 'for_branch',
                vendor_id: 'vendor',
                staff_id: 'staff'
            };
            var rel = map[field];
            if (rel && values[rel] && values[rel].name) return values[rel].name;
            return null;
        }

        function formatVal(field, val, values) {
            if (val === null || val === undefined || val === '') {
                if (field === 'for_branch_id' && values['is_for_general']) return 'General / Company-wide';
                return '(empty)';
            }
            var relName = getRelName(values, field);
            if (relName) return relName;
            if (field === 'expense_date') { var d = String(val).substring(0, 10); return d || '(empty)'; }
            if (field === 'amount') return 'PKR ' + parseInt(val).toLocaleString();
            if (field === 'is_for_general') return val ? 'Yes' : 'No';
            if (field === 'attachment_url') return val ? 'Attached' : '(none)';
            if (field === 'attachment_image') return val ? 'Image attached' : '(none)';
            return escapeHtml(String(val));
        }

        $.each(fieldLabels, function (field, label) {
            var oldVal = oldV[field];
            var newVal = newV[field];

            // Normalize for comparison
            var oldNorm = (oldVal === null || oldVal === undefined) ? '' : String(oldVal);
            var newNorm = (newVal === null || newVal === undefined) ? '' : String(newVal);
            // Normalize dates to YYYY-MM-DD to avoid false positives from format differences
            if (field === 'expense_date') { oldNorm = oldNorm.substring(0, 10); newNorm = newNorm.substring(0, 10); }

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
