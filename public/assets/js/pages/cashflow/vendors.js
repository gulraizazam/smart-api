"use strict";

var CashflowVendors = (function () {
    var apiBase = '/api/cashflow/';
    var currentPage = 1;
    var ledgerPage = 1;
    var currentVendorId = null;
    var currentVendorData = null;
    var termsLabels = { upfront: 'Upfront', net_7: 'Net 7', net_15: 'Net 15', net_30: 'Net 30', custom: 'Custom' };
    var ledgerDateFrom = null;
    var ledgerDateTo = null;

    function init() {
        loadVendors();
        loadVendorRequests();
        bindEvents();
        initLedgerDatePicker();
    }

    function bindEvents() {
        $('#btn-filter').on('click', function () { currentPage = 1; loadVendors(); });
        $('#filter-search').on('keypress', function (e) { if (e.which === 13) { currentPage = 1; loadVendors(); } });
        $('#btn-reset-filters').on('click', function () {
            $('#filter-active').val('');
            $('#filter-search').val('');
            currentPage = 1;
            loadVendors();
        });
        $('#btn-submit-vendor').on('click', submitVendor);
        $('#btn-submit-request').on('click', submitVendorRequest);

        // Ledger type filter
        $('#ledger-type-filter').on('change', function () { ledgerPage = 1; loadLedger(currentVendorId); });

        // Record Purchase → open purchase modal (new)
        $('#btn-record-purchase').on('click', function () {
            if (!currentVendorId) return;
            openPurchaseModal(null);
        });
        $('#btn-submit-purchase').on('click', submitPurchase);

        // Export ledger CSV
        $('#btn-export-ledger').on('click', exportLedger);

        // Init date picker on purchase transaction_date field (last 7 days only, whole field clickable)
        $('#form-purchase [name="transaction_date"]').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            autoUpdateInput: false,
            minDate: moment().subtract(7, 'days'),
            maxDate: moment(),
            locale: { format: 'YYYY-MM-DD' }
        }).on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD'));
        });

        // Attachment URL: warn if non-Google-Drive URL
        $('[name="attachment_url"]', '#form-purchase').on('change', function () {
            var url = $(this).val();
            if (url && !/drive\.google\.com|docs\.google\.com/i.test(url)) {
                toastr.warning('This URL does not appear to be a Google Drive link. Please verify.');
            }
        });

        // Edit current vendor from ledger header
        $('#btn-edit-current-vendor').on('click', function () {
            if (!currentVendorData) return;
            openEditVendor(currentVendorData);
        });

        // Modal events
        $('#modal_vendor').on('shown.bs.modal', function () {
            var mb = $(this).find('.modal-body');
            mb.find('[name="payment_terms"]').select2({ placeholder: 'Select payment terms', dropdownParent: mb });
        }).on('hidden.bs.modal', function () {
            $(this).find('.kt-select2-general').select2('destroy');
            $('#form-vendor')[0].reset();
            $('#form-vendor [name="vendor_id"]').val('');
            $('#vendor-modal-title').text('Add Vendor');
        });
        $('#modal_vendor_request').on('hidden.bs.modal', function () { $('#form-vendor-request')[0].reset(); });
        $('#modal_purchase').on('hidden.bs.modal', function () {
            $('#form-purchase')[0].reset();
            $('#form-purchase [name="transaction_id"]').val('');
            $('#form-purchase .is-invalid').removeClass('is-invalid');
            $('#purchase-modal-title').html('<i class="la la-shopping-cart mr-1"></i>Record Purchase — <span id="purchase-vendor-name" class="text-primary"></span>');
            $('#btn-submit-purchase').html('<i class="la la-check mr-1"></i>Record Purchase');
        });
    }

    function initLedgerDatePicker() {
        var start = moment().startOf('month');
        var end = moment();
        ledgerDateFrom = start.format('YYYY-MM-DD');
        ledgerDateTo = end.format('YYYY-MM-DD');

        $('#ledger-date-range').daterangepicker({
            startDate: start,
            endDate: end,
            ranges: {
                'This Month': [moment().startOf('month'), moment()],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Quarter': [moment().startOf('quarter'), moment()],
                'This Year': [moment().startOf('year'), moment()]
            },
            locale: { format: 'DD MMM YYYY' },
            alwaysShowCalendars: true,
            autoUpdateInput: true,
            opens: 'left'
        }, function (s, e) {
            ledgerDateFrom = s.format('YYYY-MM-DD');
            ledgerDateTo = e.format('YYYY-MM-DD');
            ledgerPage = 1;
            if (currentVendorId) loadLedger(currentVendorId);
        });
    }

    // ===================== VENDORS LIST (Left Panel) =====================

    function loadVendors() {
        var params = { page: currentPage, search: $('#filter-search').val(), is_active: $('#filter-active').val() };
        Object.keys(params).forEach(function (k) { if (params[k] === '' || params[k] === undefined) delete params[k]; });

        $('#vendors-list').html('<div class="text-center text-muted py-5"><div class="spinner spinner-primary spinner-sm"></div></div>');

        $.ajax({
            url: apiBase + 'vendors/data', type: 'GET', data: params,
            success: function (res) {
                if (res.success) {
                    renderVendors(res.data);
                    renderPagination(res.meta, '#vendors-pagination-info', '#vendors-pagination-links', function () { loadVendors(); });
                }
            },
            error: function () { $('#vendors-list').html('<div class="text-center text-danger py-3">Failed to load.</div>'); }
        });
    }

    function renderVendors(vendors) {
        var container = $('#vendors-list').empty();
        if (!vendors || vendors.length === 0) {
            container.html('<div class="text-center text-muted py-5">No vendors found.</div>');
            return;
        }

        $.each(vendors, function (i, v) {
            var bal = parseFloat(v.cached_balance) || 0;
            var balClass = bal < 0 ? 'text-danger' : (bal > 0 ? 'text-warning' : 'text-success');
            var activeClass = v.is_active ? '' : ' opacity-50';
            var selectedClass = (currentVendorId === v.id) ? ' bg-light-primary' : '';

            var item = $(
                '<div class="d-flex align-items-center justify-content-between py-2 px-3 rounded cursor-pointer vendor-item' + activeClass + selectedClass + '" data-id="' + v.id + '" style="border-bottom:1px solid #F3F6F9;transition:background .15s;">' +
                    '<div class="d-flex align-items-center min-w-0">' +
                        '<div class="symbol symbol-35 symbol-circle symbol-light-primary mr-3 flex-shrink-0">' +
                            '<span class="symbol-label font-weight-bold">' + esc(v.name.charAt(0).toUpperCase()) + '</span>' +
                        '</div>' +
                        '<div class="min-w-0">' +
                            '<div class="font-weight-bold text-dark-75 text-truncate" style="max-width:180px;">' + esc(v.name) + '</div>' +
                            '<div class="text-muted font-size-xs">' + (termsLabels[v.payment_terms] || '') +
                                (!v.is_active ? ' <span class="label label-light-danger label-inline font-size-xs py-0 px-1">Inactive</span>' : '') +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="text-right flex-shrink-0 ml-2">' +
                        '<div class="font-weight-bolder font-size-sm ' + balClass + '">PKR ' + nf(bal) + '</div>' +
                        ((typeof cfPerms !== 'undefined' && cfPerms.canManage) ?
                            '<a href="javascript:;" class="btn-edit-vendor-inline text-hover-primary" data-vendor-json=\'' + JSON.stringify(v).replace(/'/g, '&#39;') + '\' title="Edit"><i class="la la-edit font-size-sm text-muted"></i></a>' : '') +
                    '</div>' +
                '</div>'
            );

            container.append(item);
        });

        // Click vendor → open ledger
        container.find('.vendor-item').off('click').on('click', function (e) {
            if ($(e.target).closest('.btn-edit-vendor-inline').length) return;
            var id = $(this).data('id');
            selectVendor(id, vendors);
        });

        // Edit vendor inline
        container.find('.btn-edit-vendor-inline').off('click').on('click', function (e) {
            e.stopPropagation();
            var v = $(this).data('vendor-json');
            if (typeof v === 'string') v = JSON.parse(v);
            openEditVendor(v);
        });

        // Hover effect
        container.find('.vendor-item').hover(
            function () { if (!$(this).hasClass('bg-light-primary')) $(this).css('background', '#F3F6F9'); },
            function () { if (!$(this).hasClass('bg-light-primary')) $(this).css('background', ''); }
        );
    }

    function selectVendor(vendorId, vendorsList) {
        var vendor = null;
        if (vendorsList) {
            for (var i = 0; i < vendorsList.length; i++) {
                if (vendorsList[i].id === vendorId) { vendor = vendorsList[i]; break; }
            }
        }

        currentVendorId = vendorId;
        currentVendorData = vendor;

        $('.vendor-item').removeClass('bg-light-primary').css('background', '');
        $('.vendor-item[data-id="' + vendorId + '"]').addClass('bg-light-primary');

        $('#ledger-empty-state').addClass('d-none');
        $('#vendor-ledger-card').removeClass('d-none');

        if (vendor) {
            $('#ledger-vendor-name').text(vendor.name);
            $('#ledger-vendor-contact').html(vendor.contact_person ? '<i class="la la-user mr-1"></i>' + esc(vendor.contact_person) : '');
            $('#ledger-vendor-phone').html(vendor.phone ? '<i class="la la-phone mr-1"></i>' + esc(vendor.phone) : '');
            var termsHtml = '<i class="la la-calendar mr-1"></i>' + (termsLabels[vendor.payment_terms] || 'N/A');
            // Overdue indicator
            if (vendor.payment_terms && vendor.payment_terms !== 'upfront' && parseFloat(vendor.cached_balance) > 0) {
                termsHtml += ' <span class="label label-light-warning label-inline font-size-xs py-0 px-2 ml-1">Balance due</span>';
            }
            $('#ledger-vendor-terms').html(termsHtml);
        }

        ledgerPage = 1;
        $('#ledger-type-filter').val('');
        loadLedger(vendorId);
    }

    function openEditVendor(v) {
        var form = $('#form-vendor');
        form.find('[name="vendor_id"]').val(v.id);
        form.find('[name="name"]').val(v.name);
        form.find('[name="contact_person"]').val(v.contact_person || '');
        form.find('[name="phone"]').val(v.phone || '');
        form.find('[name="email"]').val(v.email || '');
        form.find('[name="payment_terms"]').val(v.payment_terms);
        form.find('[name="category"]').val(v.category || '');
        form.find('[name="opening_balance"]').val(v.opening_balance);
        form.find('[name="address"]').val(v.address || '');
        form.find('[name="notes"]').val(v.notes || '');
        $('#vendor-modal-title').text('Edit Vendor');
        $('#modal_vendor').modal('show');
    }

    function submitVendor() {
        var form = $('#form-vendor');
        var vendorId = form.find('[name="vendor_id"]').val();
        var data = {};
        form.find('input, select, textarea').each(function () { var n = $(this).attr('name'); if (n && n !== 'vendor_id') data[n] = $(this).val(); });

        if (!data.name) { toastr.warning('Vendor name is required.'); return; }

        var btn = $(this); btn.prop('disabled', true);
        var url = vendorId ? apiBase + 'vendors/' + vendorId + '/update' : apiBase + 'vendors/store';

        $.ajax({
            url: url, type: 'POST', data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal_vendor').modal('hide');
                    loadVendors();
                    if (vendorId && currentVendorId == vendorId) {
                        currentVendorData = res.data;
                        $('#ledger-vendor-name').text(res.data.name);
                        loadLedger(currentVendorId);
                    }
                } else toastr.error(res.message);
            },
            error: function (xhr) { var r = xhr.responseJSON; if (r && r.errors) $.each(r.errors, function (f, m) { toastr.error(m[0]); }); else toastr.error(r ? r.message : 'Failed.'); },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    // ===================== LEDGER (Right Panel) =====================

    function loadLedger(vendorId) {
        var typeFilter = $('#ledger-type-filter').val();
        var params = { page: ledgerPage, per_page: 50 };
        if (typeFilter) params.type = typeFilter;
        if (ledgerDateFrom) params.date_from = ledgerDateFrom;
        if (ledgerDateTo) params.date_to = ledgerDateTo;

        $('#ledger-transactions').html('<div class="text-center text-muted py-5"><div class="spinner spinner-primary spinner-sm"></div></div>');

        $.ajax({
            url: apiBase + 'vendors/' + vendorId + '/ledger', type: 'GET', data: params,
            success: function (res) {
                if (!res.success) return;
                var d = res.data;
                var bal = parseFloat(d.vendor.cached_balance) || 0;

                // Opening balance (computed for the period start)
                $('#ledger-opening').text('PKR ' + nf(d.opening_balance));
                $('#ledger-opening-date').text(fd(d.date_from));

                // Outstanding (overall current balance)
                $('#ledger-balance').text('PKR ' + nf(bal));
                var borderColor = bal > 0 ? '#F64E60' : (bal < 0 ? '#1BC5BD' : '#FFA800');
                $('#ledger-balance-card').css('border-left-color', borderColor);
                $('#ledger-balance').toggleClass('text-danger', bal > 0).toggleClass('text-success', bal < 0);

                // Period stats
                var stats = d.period_stats || {};
                $('#ledger-count').text(stats.count || 0);
                $('#ledger-stat-purchases').text('PKR ' + nf(stats.total_purchases));
                $('#ledger-stat-payments').text('PKR ' + nf(stats.total_payments));
                var net = parseFloat(stats.net) || 0;
                $('#ledger-stat-net').text((net >= 0 ? '+' : '') + 'PKR ' + nf(net))
                    .toggleClass('text-danger', net > 0).toggleClass('text-success', net < 0).toggleClass('text-muted', net === 0);

                var txData = d.transactions.data || d.transactions;
                renderLedger(txData);

                if (d.transactions.last_page) {
                    renderPagination({
                        current_page: d.transactions.current_page,
                        last_page: d.transactions.last_page,
                        total: d.transactions.total,
                        per_page: d.transactions.per_page
                    }, '#ledger-pagination-info', '#ledger-pagination-links', function () { loadLedger(vendorId); });
                }
            }
        });
    }

    function renderLedger(txs) {
        var container = $('#ledger-transactions').empty();
        if (!txs || txs.length === 0) {
            container.html('<div class="text-center text-muted py-5"><i class="la la-inbox" style="font-size:36px;"></i><br>No transactions found for this period.</div>');
            return;
        }

        $.each(txs, function (i, tx) {
            var isPurchase = tx.type === 'purchase';
            var typeIcon = isPurchase ? 'la-arrow-up text-danger' : 'la-arrow-down text-success';
            var typeLabel = isPurchase ? 'Purchase' : 'Payment';
            var amtClass = isPurchase ? 'text-danger' : 'text-success';
            var amtPrefix = isPurchase ? '+' : '-';
            var desc = (tx.expense && tx.expense.description) ? tx.expense.description : (tx.description || '-');
            var dateStr = fd(tx.transaction_date || (tx.expense && tx.expense.expense_date ? tx.expense.expense_date : null) || tx.created_at);

            // Attachment preview icon (from purchase attachment_url or linked expense attachment_url)
            var attachUrl = tx.attachment_url || (tx.expense && tx.expense.attachment_url ? tx.expense.attachment_url : null);
            var attachIcon = '';
            if (attachUrl) {
                var previewUrl = getDrivePreviewUrl(attachUrl);
                if (previewUrl) {
                    attachIcon = ' <a href="javascript:;" class="btn-preview-attach" data-url="' + esc(previewUrl) + '" title="Preview attachment"><i class="la la-paperclip text-primary"></i></a>';
                } else {
                    attachIcon = ' <a href="' + esc(attachUrl) + '" target="_blank" title="View attachment"><i class="la la-paperclip text-primary"></i></a>';
                }
            }

            // Branch info
            var branchLabel = '';
            if (tx.is_for_general) {
                branchLabel = '<span class="label label-light-info label-inline font-size-xs py-0 px-2 ml-1">General</span>';
            } else if (tx.for_branch && tx.for_branch.name) {
                branchLabel = '<span class="label label-light-info label-inline font-size-xs py-0 px-2 ml-1">' + esc(tx.for_branch.name) + '</span>';
            }

            // Running balance
            var runBal = parseFloat(tx.running_balance) || 0;
            var runBalClass = runBal > 0 ? 'text-danger' : (runBal < 0 ? 'text-success' : 'text-muted');

            // Description: clickable link to expense for payment entries
            var descHtml;
            if (!isPurchase && tx.expense_id) {
                descHtml = '<a href="/admin/cashflow/expenses?highlight=' + tx.expense_id + '" class="font-weight-bold font-size-sm text-primary text-truncate" style="max-width:260px;display:inline-block;" title="View expense #' + tx.expense_id + '">' + esc(desc) + '</a>';
            } else {
                descHtml = '<span class="font-weight-bold font-size-sm text-dark-75 text-truncate" style="max-width:260px;display:inline-block;">' + esc(desc) + '</span>';
            }

            // Edit/Delete actions for standalone purchase entries (no expense_id)
            var actions = '';
            if (!tx.expense_id && typeof cfPerms !== 'undefined' && cfPerms.canTransaction) {
                actions =
                    '<a href="javascript:;" class="btn-edit-tx text-hover-primary ml-2" title="Edit" data-tx=\'' + JSON.stringify(tx).replace(/'/g, '&#39;') + '\'><i class="la la-edit font-size-sm text-muted"></i></a>' +
                    '<a href="javascript:;" class="btn-delete-tx text-hover-danger ml-1" title="Delete" data-id="' + tx.id + '"><i class="la la-trash font-size-sm text-muted"></i></a>';
            }
            // Audit trail button (all entries, if user has audit permission)
            if (typeof cfPerms !== 'undefined' && cfPerms.canAudit) {
                actions += '<a href="javascript:;" class="btn-tx-audit text-hover-info ml-1" title="Audit Trail" data-id="' + tx.id + '"><i class="la la-history font-size-sm text-muted"></i></a>';
            }

            container.append(
                '<div class="d-flex align-items-center py-2" style="border-bottom:1px solid #F3F6F9;">' +
                    '<div class="flex-shrink-0 mr-3">' +
                        '<div style="width:32px;height:32px;border-radius:50%;background:' + (isPurchase ? '#FFF5F8' : '#E8FFF3') + ';display:flex;align-items:center;justify-content:center;">' +
                            '<i class="la ' + typeIcon + '" style="font-size:16px;"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="flex-grow-1 min-w-0">' +
                        '<div class="d-flex justify-content-between align-items-start">' +
                            '<div class="min-w-0">' +
                                '<div>' + descHtml + attachIcon + actions + '</div>' +
                                '<div class="text-muted font-size-xs mt-1">' +
                                    '<span class="mr-2">' + dateStr + '</span>' +
                                    '<span class="label label-' + (isPurchase ? 'light-danger' : 'light-success') + ' label-inline font-size-xs py-0 px-2">' + typeLabel + '</span>' +
                                    branchLabel +
                                    (tx.reference_no ? ' <span class="ml-1 text-muted">Ref: ' + esc(tx.reference_no) + '</span>' : '') +
                                '</div>' +
                            '</div>' +
                            '<div class="d-flex align-items-center flex-shrink-0 ml-3" style="gap:16px;">' +
                                '<div class="text-right" style="min-width:90px;">' +
                                    '<div class="font-size-xs text-muted">Amount</div>' +
                                    '<div class="font-weight-bolder font-size-sm ' + amtClass + '">PKR ' + nf(tx.amount) + '</div>' +
                                '</div>' +
                                '<div class="text-right" style="min-width:90px;">' +
                                    '<div class="font-size-xs text-muted">Balance</div>' +
                                    '<div class="font-weight-bolder font-size-sm ' + runBalClass + '">PKR ' + nf(runBal) + '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        });

        // Bind edit/delete actions
        container.find('.btn-edit-tx').off('click').on('click', function (e) {
            e.stopPropagation();
            var tx = $(this).data('tx');
            if (typeof tx === 'string') tx = JSON.parse(tx);
            openPurchaseModal(tx);
        });
        container.find('.btn-delete-tx').off('click').on('click', function (e) {
            e.stopPropagation();
            var id = $(this).data('id');
            deletePurchase(id);
        });

        // Preview attachment in iframe modal
        container.find('.btn-preview-attach').off('click').on('click', function (e) {
            e.stopPropagation();
            var url = $(this).data('url');
            Swal.fire({
                html: '<iframe src="' + url + '" style="width:100%;height:70vh;border:none;"></iframe>',
                width: '80%',
                showConfirmButton: false,
                showCloseButton: true
            });
        });

        // Audit trail
        container.find('.btn-tx-audit').off('click').on('click', function (e) {
            e.stopPropagation();
            var txId = $(this).data('id');
            loadTxAuditTrail(txId);
        });
    }

    // ===================== VENDOR REQUESTS (Left Panel) =====================

    function loadVendorRequests() {
        $.ajax({
            url: apiBase + 'vendor-requests/data', type: 'GET', data: { status: 'pending' },
            success: function (res) { if (res.success) renderRequests(res.data); }
        });
    }

    function renderRequests(reqs) {
        var container = $('#requests-list').empty();
        if (!reqs || reqs.length === 0) {
            container.html('<div class="text-center text-muted py-3 font-size-sm">No pending requests.</div>');
            return;
        }

        $.each(reqs, function (i, r) {
            var actions = '';
            if (r.status === 'pending' && typeof cfPerms !== 'undefined' && cfPerms.canManage) {
                actions =
                    '<a href="javascript:;" class="btn-approve-req text-success mr-2" data-id="' + r.id + '" title="Approve"><i class="la la-check font-size-lg"></i></a>' +
                    '<a href="javascript:;" class="btn-dismiss-req text-danger" data-id="' + r.id + '" title="Dismiss"><i class="la la-times font-size-lg"></i></a>';
            }

            container.append(
                '<div class="d-flex align-items-center justify-content-between py-2 px-1" style="border-bottom:1px solid #F3F6F9;">' +
                    '<div class="min-w-0">' +
                        '<div class="font-weight-bold font-size-sm text-truncate" style="max-width:200px;">' + esc(r.name) + '</div>' +
                        '<div class="text-muted font-size-xs">' +
                            (r.phone ? esc(r.phone) + ' &middot; ' : '') +
                            (r.requester ? esc(r.requester.name) : '') +
                        '</div>' +
                    '</div>' +
                    '<div class="flex-shrink-0">' + actions + '</div>' +
                '</div>'
            );
        });

        container.find('.btn-approve-req').off('click').on('click', function () {
            var id = $(this).data('id');
            if (!confirm('Approve this vendor request? A new vendor will be created.')) return;
            $.ajax({
                url: apiBase + 'vendor-requests/' + id + '/approve', type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) { toastr.success(res.message || 'Approved.'); loadVendorRequests(); loadVendors(); },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });

        container.find('.btn-dismiss-req').off('click').on('click', function () {
            var id = $(this).data('id');
            var notes = prompt('Enter reason for dismissal (optional):');
            $.ajax({
                url: apiBase + 'vendor-requests/' + id + '/dismiss', type: 'POST',
                data: { admin_notes: notes },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) { toastr.success(res.message || 'Dismissed.'); loadVendorRequests(); },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });
    }

    function submitVendorRequest() {
        var form = $('#form-vendor-request');
        var data = {};
        form.find('input, textarea').each(function () { var n = $(this).attr('name'); if (n) data[n] = $(this).val(); });
        if (!data.name) { toastr.warning('Vendor name is required.'); return; }

        var btn = $(this); btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'vendor-requests/store', type: 'POST', data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) { if (res.success) { toastr.success(res.message); $('#modal_vendor_request').modal('hide'); loadVendorRequests(); } else toastr.error(res.message); },
            error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    // ===================== RECORD / EDIT PURCHASE =====================

    function openPurchaseModal(tx) {
        var form = $('#form-purchase');
        form[0].reset();
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('[name="transaction_id"]').val('');

        $('#purchase-vendor-name').text(currentVendorData ? currentVendorData.name : '');

        if (tx) {
            // Edit mode
            form.find('[name="transaction_id"]').val(tx.id);
            form.find('[name="description"]').val(tx.description || '');
            form.find('[name="amount"]').val(Math.round(parseFloat(tx.amount)));
            form.find('[name="transaction_date"]').val(tx.transaction_date ? tx.transaction_date.substring(0, 10) : '');
            form.find('[name="reference_no"]').val(tx.reference_no || '');
            form.find('[name="attachment_url"]').val(tx.attachment_url || '');
            // Branch/general
            if (tx.is_for_general) {
                form.find('[name="for_branch_id"]').val('general');
            } else if (tx.for_branch_id) {
                form.find('[name="for_branch_id"]').val(tx.for_branch_id);
            }
            $('#purchase-modal-title').html('<i class="la la-edit mr-1"></i>Edit Purchase — <span id="purchase-vendor-name" class="text-primary">' + esc(currentVendorData ? currentVendorData.name : '') + '</span>');
            $('#btn-submit-purchase').html('<i class="la la-check mr-1"></i>Update Purchase');
        } else {
            // New mode
            form.find('[name="transaction_date"]').val(getTodayStr());
        }

        $('#modal_purchase').modal('show');
    }

    function submitPurchase() {
        var form = $('#form-purchase');
        var txId = form.find('[name="transaction_id"]').val();
        var data = {};
        form.find('input, select, textarea').each(function () {
            var n = $(this).attr('name');
            if (n && n !== 'transaction_id') data[n] = $(this).val();
        });

        // Handle branch/general
        if (data.for_branch_id === 'general') {
            data.is_for_general = 1;
            data.for_branch_id = '';
        } else {
            data.is_for_general = 0;
        }

        // Validate
        form.find('.is-invalid').removeClass('is-invalid');
        var missing = [];
        var requiredFields = ['description', 'amount', 'transaction_date', 'attachment_url'];
        $.each(requiredFields, function (i, name) {
            if (!data[name]) { form.find('[name="' + name + '"]').addClass('is-invalid'); missing.push(name); }
        });
        // Validate branch: original select value (before conversion)
        if (!form.find('[name="for_branch_id"]').val()) {
            form.find('[name="for_branch_id"]').addClass('is-invalid');
            missing.push('for_branch_id');
        }
        if (missing.length) { toastr.warning('Please fill the highlighted fields.'); return; }

        if (data.amount && data.amount % 1 !== 0) {
            toastr.warning('Amount must be a whole number.'); return;
        }

        var btn = $('#btn-submit-purchase');
        btn.prop('disabled', true).html('<i class="spinner spinner-white spinner-sm mr-1"></i> Saving...');

        var url, successMsg;
        if (txId) {
            url = apiBase + 'vendors/' + currentVendorId + '/transactions/' + txId + '/update';
            successMsg = 'Purchase updated.';
        } else {
            url = apiBase + 'vendors/' + currentVendorId + '/purchase';
            successMsg = 'Purchase recorded.';
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message || successMsg);
                    $('#modal_purchase').modal('hide');
                    loadLedger(currentVendorId);
                    loadVendors();
                } else {
                    toastr.error(res.message || 'Failed.');
                }
            },
            error: function (xhr) {
                var msg = 'Failed.';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        var errs = xhr.responseJSON.errors;
                        msg = Object.keys(errs).map(function (k) { return errs[k][0]; }).join('\n');
                    } else if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                }
                toastr.error(msg);
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="la la-check mr-1"></i>' + (txId ? 'Update Purchase' : 'Record Purchase'));
            }
        });
    }

    function deletePurchase(txId) {
        if (!confirm('Delete this purchase entry? The vendor balance will be adjusted.')) return;

        $.ajax({
            url: apiBase + 'vendors/' + currentVendorId + '/transactions/' + txId + '/delete',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message || 'Deleted.');
                    loadLedger(currentVendorId);
                    loadVendors();
                } else {
                    toastr.error(res.message || 'Failed.');
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.');
            }
        });
    }

    // ===================== EXPORT =====================

    function exportLedger() {
        if (!currentVendorId) return;
        var params = [];
        if (ledgerDateFrom) params.push('date_from=' + ledgerDateFrom);
        if (ledgerDateTo) params.push('date_to=' + ledgerDateTo);
        var typeFilter = $('#ledger-type-filter').val();
        if (typeFilter) params.push('type=' + typeFilter);
        var url = apiBase + 'vendors/' + currentVendorId + '/ledger/export' + (params.length ? '?' + params.join('&') : '');
        window.open(url, '_blank');
    }

    // ===================== AUDIT TRAIL =====================

    function loadTxAuditTrail(txId) {
        $('#tx-audit-loading').removeClass('d-none');
        $('#tx-audit-timeline').addClass('d-none').empty();
        $('#modal_tx_audit').modal('show');

        $.ajax({
            url: apiBase + 'vendors/' + currentVendorId + '/transactions/' + txId + '/audit',
            type: 'GET',
            success: function (res) {
                $('#tx-audit-loading').addClass('d-none');
                if (res.success && res.data.length > 0) {
                    var html = '';
                    var actionIcons = {
                        created: 'la-plus-circle', updated: 'la-edit', approved: 'la-check-circle',
                        rejected: 'la-times-circle', voided: 'la-ban', deleted: 'la-trash',
                        auto_created: 'la-magic', reset: 'la-undo'
                    };
                    var actionColors = {
                        created: '#3699FF', updated: '#8950FC', approved: '#1BC5BD',
                        rejected: '#F64E60', voided: '#181C32', deleted: '#F64E60',
                        auto_created: '#3699FF', reset: '#FFA800'
                    };

                    $.each(res.data, function (i, log) {
                        var actionBadge = getTxActionBadge(log.action);
                        var userName = log.user ? log.user.name : 'System';
                        var time = new Date(log.created_at).toLocaleString();
                        var icon = actionIcons[log.action] || 'la-history';
                        var color = actionColors[log.action] || '#7E8299';
                        var isLast = (i === res.data.length - 1);
                        var changeSummary = buildTxChangeSummary(log);

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
                    $('#tx-audit-timeline').html(html).removeClass('d-none');
                } else {
                    $('#tx-audit-timeline').html('<div class="text-center text-muted py-5"><i class="la la-inbox" style="font-size:40px;"></i><br>No audit records found.</div>').removeClass('d-none');
                }
            },
            error: function () {
                $('#tx-audit-loading').addClass('d-none');
                $('#tx-audit-timeline').html('<div class="text-center text-danger py-3">Failed to load audit trail.</div>').removeClass('d-none');
            }
        });
    }

    function buildTxChangeSummary(log) {
        if (!log.old_values || !log.new_values) return '';
        if (log.action === 'created') return '';

        var oldV = log.old_values;
        var newV = log.new_values;
        var changes = [];

        var fieldLabels = {
            transaction_date: 'Date',
            amount: 'Amount',
            description: 'Description',
            reference_no: 'Reference No',
            attachment_url: 'Attachment',
            for_branch_id: 'Branch',
            is_for_general: 'General/Company-wide'
        };

        function getRelName(values, field) {
            var map = { for_branch_id: 'for_branch' };
            var rel = map[field];
            if (rel && values[rel] && values[rel].name) return values[rel].name;
            return null;
        }

        function formatVal(field, val, values) {
            if (val === null || val === undefined || val === '') return '(empty)';
            var relName = getRelName(values, field);
            if (relName) return relName;
            if (field === 'transaction_date') { var d = String(val).substring(0, 10); return d || '(empty)'; }
            if (field === 'amount') return 'PKR ' + parseInt(val).toLocaleString();
            if (field === 'is_for_general') return val ? 'Yes' : 'No';
            if (field === 'attachment_url') return val ? 'Attached' : '(none)';
            return esc(String(val));
        }

        $.each(fieldLabels, function (field, label) {
            var oldVal = oldV[field];
            var newVal = newV[field];
            var oldNorm = (oldVal === null || oldVal === undefined) ? '' : String(oldVal);
            var newNorm = (newVal === null || newVal === undefined) ? '' : String(newVal);
            if (field === 'transaction_date') { oldNorm = oldNorm.substring(0, 10); newNorm = newNorm.substring(0, 10); }

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

    function getTxActionBadge(action) {
        var colors = {
            created: 'primary', updated: 'info', approved: 'success', rejected: 'danger',
            voided: 'dark', deleted: 'danger', auto_created: 'primary', reset: 'warning'
        };
        var color = colors[action] || 'secondary';
        return '<span class="label label-light-' + color + ' label-inline">' + action.replace('_', ' ').toUpperCase() + '</span>';
    }

    // ===================== HELPERS ======================

    function getDrivePreviewUrl(url) {
        if (!url) return null;
        var match;
        match = url.match(/drive\.google\.com\/file\/d\/([^\/\?]+)/);
        if (match) return 'https://drive.google.com/file/d/' + match[1] + '/preview';
        match = url.match(/drive\.google\.com\/open\?id=([^&]+)/);
        if (match) return 'https://drive.google.com/file/d/' + match[1] + '/preview';
        if (/docs\.google\.com/.test(url)) return url.replace(/\/edit.*$/, '/preview');
        return null;
    }

    function getTodayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function renderPagination(meta, infoSel, linksSel, loadFn) {
        if (!meta) return;
        $(infoSel).text(meta.total + ' records (page ' + meta.current_page + '/' + meta.last_page + ')');
        var links = '';
        if (meta.current_page > 1) links += '<button class="btn btn-xs btn-outline-primary mr-1 btn-pg" data-page="' + (meta.current_page - 1) + '">&laquo;</button>';
        if (meta.current_page < meta.last_page) links += '<button class="btn btn-xs btn-outline-primary btn-pg" data-page="' + (meta.current_page + 1) + '">&raquo;</button>';
        $(linksSel).html(links);
        $(linksSel + ' .btn-pg').off('click').on('click', function () {
            var pg = $(this).data('page');
            if (linksSel.indexOf('ledger') > -1) { ledgerPage = pg; }
            else { currentPage = pg; }
            loadFn();
        });
    }

    function esc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
    function nf(n) { return parseFloat(n||0).toLocaleString('en-PK',{maximumFractionDigits:0}); }
    function fd(d) { if(!d) return '-'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }

    return { init: init };
})();

$(document).ready(function () { CashflowVendors.init(); });
