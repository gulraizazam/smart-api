"use strict";

var CashflowVendors = (function () {
    var apiBase = '/api/cashflow/';
    var currentPage = 1;
    var currentLedgerVendorId = null;

    function init() {
        loadVendors();
        loadVendorRequests();
        bindEvents();

        // Init Select2 on page-level filter selects
        $('#filter-active').select2();

        // Auto-open modal if coming from dashboard quick-action
        if (new URLSearchParams(window.location.search).get('action') === 'add') {
            setTimeout(function () { $('#modal_vendor_purchase').modal('show'); }, 500);
        }
    }

    function bindEvents() {
        $('#btn-filter').on('click', function () { currentPage = 1; loadVendors(); });
        $('#filter-search').on('keypress', function (e) { if (e.which === 13) { currentPage = 1; loadVendors(); } });
        $('#btn-reset-filters').on('click', function () {
            $('#filter-active').val('').trigger('change');
            $('#filter-search').val('');
            currentPage = 1;
            loadVendors();
        });
        $('#btn-submit-vendor').on('click', submitVendor);
        $('#btn-submit-request').on('click', submitVendorRequest);
        $('#btn-submit-transaction').on('click', submitPurchase);
        $('#btn-close-ledger').on('click', function () { $('#vendor-ledger-card').addClass('d-none'); currentLedgerVendorId = null; });
        $('#modal_vendor').on('shown.bs.modal', function () {
            var mb = $(this).find('.modal-body');
            mb.find('[name="payment_terms"]').select2({ placeholder: 'Select payment terms', dropdownParent: mb });
        }).on('hidden.bs.modal', function () {
            $(this).find('.kt-select2-general').select2('destroy');
            $('#form-vendor')[0].reset(); $('#form-vendor [name="vendor_id"]').val(''); $('#vendor-modal-title').text('Add Vendor');
        });
        $('#modal_vendor_request').on('hidden.bs.modal', function () { $('#form-vendor-request')[0].reset(); });
        $('#modal_transaction').on('hidden.bs.modal', function () { $('#form-transaction')[0].reset(); });
    }

    // ===================== VENDORS =====================

    function loadVendors() {
        var params = { page: currentPage, search: $('#filter-search').val(), is_active: $('#filter-active').val() };
        Object.keys(params).forEach(function (k) { if (params[k] === '' || params[k] === undefined) delete params[k]; });

        $('#vendors-tbody').html('<tr><td colspan="7" class="text-center"><div class="spinner spinner-primary spinner-sm"></div></td></tr>');

        $.ajax({
            url: apiBase + 'vendors/data', type: 'GET', data: params,
            success: function (res) {
                if (res.success) { renderVendors(res.data); renderPagination(res.meta, '#vendors-pagination-info', '#vendors-pagination-links', loadVendors); }
            },
            error: function () { $('#vendors-tbody').html('<tr><td colspan="7" class="text-center text-danger">Failed to load.</td></tr>'); }
        });
    }

    function renderVendors(vendors) {
        var tbody = $('#vendors-tbody').empty();
        if (!vendors || vendors.length === 0) { tbody.html('<tr><td colspan="7" class="text-center text-muted">No vendors found.</td></tr>'); return; }

        var termsLabels = { upfront: 'Upfront', net_7: 'Net 7', net_15: 'Net 15', net_30: 'Net 30', custom: 'Custom' };

        $.each(vendors, function (i, v) {
            var statusBadge = v.is_active ? '<span class="label label-light-success label-inline">Active</span>' : '<span class="label label-light-danger label-inline">Inactive</span>';
            var balClass = parseFloat(v.cached_balance) < 0 ? 'text-danger' : '';

            tbody.append(
                '<tr>' +
                '<td><a href="javascript:;" class="btn-view-ledger font-weight-bold" data-id="' + v.id + '" data-name="' + esc(v.name) + '">' + esc(v.name) + '</a></td>' +
                '<td>' + esc(v.contact_person || '-') + '</td>' +
                '<td>' + esc(v.phone || '-') + '</td>' +
                '<td>' + (termsLabels[v.payment_terms] || v.payment_terms) + '</td>' +
                '<td class="text-right ' + balClass + '">PKR ' + nf(v.cached_balance) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td class="text-center">' +
                    ((typeof cfPerms !== 'undefined' && cfPerms.canManage) ? '<button class="btn btn-sm btn-clean btn-icon btn-edit-vendor" data-vendor=\'' + JSON.stringify(v) + '\' title="Edit"><i class="la la-edit text-primary"></i></button>' : '') +
                '</td>' +
                '</tr>'
            );
        });

        $('.btn-view-ledger').off('click').on('click', function () {
            currentLedgerVendorId = $(this).data('id');
            $('#ledger-vendor-name').text($(this).data('name'));
            $('#tx-vendor-id').val(currentLedgerVendorId);
            loadLedger(currentLedgerVendorId);
            $('#vendor-ledger-card').removeClass('d-none');
            $('html, body').animate({ scrollTop: $('#vendor-ledger-card').offset().top - 80 }, 300);
        });

        $('.btn-edit-vendor').off('click').on('click', function () {
            var v = $(this).data('vendor');
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
        });
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
                if (res.success) { toastr.success(res.message); $('#modal_vendor').modal('hide'); loadVendors(); }
                else toastr.error(res.message);
            },
            error: function (xhr) { var r = xhr.responseJSON; if (r && r.errors) $.each(r.errors, function (f, m) { toastr.error(m[0]); }); else toastr.error(r ? r.message : 'Failed.'); },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    // ===================== LEDGER =====================

    function loadLedger(vendorId) {
        $('#ledger-tbody').html('<tr><td colspan="6" class="text-center"><div class="spinner spinner-primary spinner-sm"></div></td></tr>');

        $.ajax({
            url: apiBase + 'vendors/' + vendorId + '/ledger', type: 'GET',
            success: function (res) {
                if (!res.success) return;
                var d = res.data;
                $('#ledger-opening').text('PKR ' + nf(d.vendor.opening_balance));
                $('#ledger-balance').text('PKR ' + nf(d.vendor.cached_balance));
                $('#ledger-count').text(d.transactions.total || d.transactions.data.length);
                renderLedger(d.transactions.data || d.transactions);
            }
        });
    }

    function renderLedger(txs) {
        var tbody = $('#ledger-tbody').empty();
        if (!txs || txs.length === 0) { tbody.html('<tr><td colspan="6" class="text-center text-muted">No transactions.</td></tr>'); return; }

        var typeLabels = { purchase: '<span class="label label-light-danger label-inline">Purchase</span>', payment: '<span class="label label-light-success label-inline">Payment</span>' };

        $.each(txs, function (i, tx) {
            tbody.append(
                '<tr>' +
                '<td>' + fd(tx.created_at) + '</td>' +
                '<td>' + (typeLabels[tx.type] || tx.type) + '</td>' +
                '<td class="text-right font-weight-bold">PKR ' + nf(tx.amount) + '</td>' +
                '<td>' + esc(tx.description || '-') + '</td>' +
                '<td>' + esc(tx.reference_no || '-') + '</td>' +
                '<td>' + (tx.creator ? esc(tx.creator.name) : '-') + '</td>' +
                '</tr>'
            );
        });
    }

    function submitPurchase() {
        var form = $('#form-transaction');
        var data = {};
        form.find('input, textarea').each(function () { var n = $(this).attr('name'); if (n && n !== 'vendor_id') data[n] = $(this).val(); });

        if (!currentLedgerVendorId || !data.amount) { toastr.warning('Please fill required fields.'); return; }

        var btn = $(this); btn.prop('disabled', true);

        $.ajax({
            url: apiBase + 'vendors/' + currentLedgerVendorId + '/purchase', type: 'POST', data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) { toastr.success(res.message); $('#modal_transaction').modal('hide'); loadLedger(currentLedgerVendorId); loadVendors(); }
                else toastr.error(res.message);
            },
            error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); },
            complete: function () { btn.prop('disabled', false); }
        });
    }

    // ===================== VENDOR REQUESTS =====================

    function loadVendorRequests() {
        $.ajax({
            url: apiBase + 'vendor-requests/data', type: 'GET', data: { status: 'pending' },
            success: function (res) { if (res.success) renderRequests(res.data); }
        });
    }

    function renderRequests(reqs) {
        var tbody = $('#requests-tbody').empty();
        if (!reqs || reqs.length === 0) { tbody.html('<tr><td colspan="6" class="text-center text-muted">No pending requests.</td></tr>'); return; }

        var statusLabels = { pending: 'label-light-warning', approved: 'label-light-success', dismissed: 'label-light-danger' };

        $.each(reqs, function (i, r) {
            var actions = '';
            if (r.status === 'pending' && typeof cfPerms !== 'undefined' && cfPerms.canManage) {
                actions = '<button class="btn btn-sm btn-clean btn-icon btn-approve-req" data-id="' + r.id + '" title="Approve"><i class="la la-check text-success"></i></button>' +
                          '<button class="btn btn-sm btn-clean btn-icon btn-dismiss-req" data-id="' + r.id + '" title="Dismiss"><i class="la la-times text-danger"></i></button>';
            }

            tbody.append(
                '<tr>' +
                '<td>' + esc(r.name) + '</td>' +
                '<td>' + esc(r.phone || '-') + '</td>' +
                '<td>' + esc(r.note || '-') + '</td>' +
                '<td>' + (r.requester ? esc(r.requester.name) : '-') + '</td>' +
                '<td><span class="label ' + (statusLabels[r.status] || 'label-secondary') + ' label-inline">' + r.status + '</span></td>' +
                '<td class="text-center">' + actions + '</td>' +
                '</tr>'
            );
        });

        $('.btn-approve-req').off('click').on('click', function () {
            var id = $(this).data('id');
            if (!confirm('Approve this vendor request? A new vendor will be created.')) return;
            $.ajax({
                url: apiBase + 'vendor-requests/' + id + '/approve', type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) { toastr.success(res.message || 'Approved.'); loadVendorRequests(); loadVendors(); },
                error: function (xhr) { toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'Failed.'); }
            });
        });

        $('.btn-dismiss-req').off('click').on('click', function () {
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

    // ===================== HELPERS =====================

    function renderPagination(meta, infoSel, linksSel, loadFn) {
        if (!meta) return;
        $(infoSel).text('Page ' + meta.current_page + ' of ' + meta.last_page + ' (' + meta.total + ' total)');
        var links = '';
        if (meta.current_page > 1) links += '<button class="btn btn-sm btn-outline-primary mr-1 btn-pg" data-page="' + (meta.current_page - 1) + '">&laquo;</button>';
        if (meta.current_page < meta.last_page) links += '<button class="btn btn-sm btn-outline-primary btn-pg" data-page="' + (meta.current_page + 1) + '">&raquo;</button>';
        $(linksSel).html(links);
        $(linksSel + ' .btn-pg').off('click').on('click', function () { currentPage = $(this).data('page'); loadFn(); });
    }

    function esc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
    function nf(n) { return parseFloat(n||0).toLocaleString('en-PK',{maximumFractionDigits:0}); }
    function fd(d) { if(!d) return '-'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }

    return { init: init };
})();

$(document).ready(function () { CashflowVendors.init(); });
