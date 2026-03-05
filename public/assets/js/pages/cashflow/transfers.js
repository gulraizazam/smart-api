"use strict";

var CashflowTransfers = (function () {
    var apiBase = '/api/cashflow/';
    var currentPage = 1;

    function init() {
        loadPools();
        loadTransfers();
        bindEvents();

        // Auto-open modal if coming from dashboard quick-action
        if (new URLSearchParams(window.location.search).get('action') === 'add') {
            setTimeout(function () { $('#modal_transfer').modal('show'); }, 500);
        }
    }

    function bindEvents() {
        $('#btn-filter').on('click', function () { currentPage = 1; loadTransfers(); });
        $('#filter-search').on('keypress', function (e) { if (e.which === 13) { currentPage = 1; loadTransfers(); } });
        $('#btn-submit-transfer').on('click', submitTransfer);
        $('#modal_transfer').on('hidden.bs.modal', function () { $('#form-transfer')[0].reset(); });

        // Keyboard shortcuts
        $(document).on('keydown', function (e) {
            if (e.altKey && e.key === 't') { e.preventDefault(); $('#modal_transfer').modal('show'); }
            if (e.ctrlKey && e.key === 'Enter' && $('#modal_transfer').hasClass('show')) { e.preventDefault(); $('#btn-submit-transfer').click(); }
        });
    }

    function loadPools() {
        $.ajax({
            url: apiBase + 'lookups',
            type: 'GET',
            success: function (res) {
                if (!res.success) return;
                var pools = res.data.pools;
                var opts = '<option value="">All Pools</option>';
                var formOpts = '<option value="">Select pool</option>';
                $.each(pools, function (i, p) {
                    var label = p.name + (p.location ? ' (' + p.location.name + ')' : '');
                    opts += '<option value="' + p.id + '">' + escapeHtml(label) + '</option>';
                    formOpts += '<option value="' + p.id + '" data-balance="' + (p.cached_balance || 0) + '">' + escapeHtml(label) + '</option>';
                });
                $('#filter-pool').html(opts);
                $('[name="from_pool_id"]').html(formOpts);
                $('[name="to_pool_id"]').html(formOpts);
            }
        });
    }

    function loadTransfers() {
        var params = {
            page: currentPage,
            pool_id: $('#filter-pool').val(),
            method: $('#filter-method').val(),
            date_from: $('#filter-date-from').val(),
            date_to: $('#filter-date-to').val(),
            search: $('#filter-search').val()
        };
        Object.keys(params).forEach(function (k) { if (!params[k]) delete params[k]; });

        $('#transfers-tbody').html('<tr><td colspan="7" class="text-center"><div class="spinner spinner-primary spinner-sm"></div> Loading...</td></tr>');

        $.ajax({
            url: apiBase + 'transfers/data',
            type: 'GET',
            data: params,
            success: function (res) {
                if (res.success) {
                    renderTransfers(res.data);
                    renderPagination(res.meta);
                }
            },
            error: function () {
                $('#transfers-tbody').html('<tr><td colspan="7" class="text-center text-danger">Failed to load.</td></tr>');
            }
        });
    }

    function renderTransfers(transfers) {
        var tbody = $('#transfers-tbody');
        tbody.empty();

        if (!transfers || transfers.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center text-muted">No transfers found.</td></tr>');
            return;
        }

        var methodLabels = {
            physical_cash: '<span class="label label-light-info label-inline">Cash</span>',
            bank_deposit: '<span class="label label-light-success label-inline">Bank</span>'
        };

        $.each(transfers, function (i, t) {
            var fromLabel = t.from_pool ? t.from_pool.name : '-';
            if (t.from_pool && t.from_pool.location) fromLabel += ' <small class="text-muted">(' + t.from_pool.location.name + ')</small>';
            var toLabel = t.to_pool ? t.to_pool.name : '-';
            if (t.to_pool && t.to_pool.location) toLabel += ' <small class="text-muted">(' + t.to_pool.location.name + ')</small>';

            tbody.append(
                '<tr>' +
                '<td>' + formatDate(t.transfer_date) + '</td>' +
                '<td>' + fromLabel + '</td>' +
                '<td>' + toLabel + '</td>' +
                '<td class="text-right font-weight-bold">PKR ' + numberFormat(t.amount) + '</td>' +
                '<td>' + (methodLabels[t.method] || t.method) + '</td>' +
                '<td>' + escapeHtml(t.reference_no || '-') + '</td>' +
                '<td>' + (t.creator ? escapeHtml(t.creator.name) : '-') + '</td>' +
                '</tr>'
            );
        });
    }

    function renderPagination(meta) {
        if (!meta) return;
        $('#pagination-info').text('Page ' + meta.current_page + ' of ' + meta.last_page + ' (' + meta.total + ' total)');
        var links = '';
        if (meta.current_page > 1) links += '<button class="btn btn-sm btn-outline-primary mr-1 btn-page" data-page="' + (meta.current_page - 1) + '">&laquo;</button>';
        if (meta.current_page < meta.last_page) links += '<button class="btn btn-sm btn-outline-primary btn-page" data-page="' + (meta.current_page + 1) + '">&raquo;</button>';
        $('#pagination-links').html(links);
        $('.btn-page').off('click').on('click', function () { currentPage = $(this).data('page'); loadTransfers(); });
    }

    function submitTransfer() {
        var form = $('#form-transfer');
        var data = {};
        form.find('input, select, textarea').each(function () {
            var n = $(this).attr('name');
            if (n) data[n] = $(this).val();
        });

        if (!data.transfer_date || !data.amount || !data.from_pool_id || !data.to_pool_id || !data.reference_no || !data.attachment_url) {
            toastr.warning('Please fill all required fields.');
            return;
        }
        if (data.from_pool_id === data.to_pool_id) {
            toastr.warning('Source and destination pools must be different.');
            return;
        }

        // Warn if From pool will go negative (Sec 6.3)
        var fromBalance = parseFloat($('[name="from_pool_id"] option:selected').data('balance')) || 0;
        var amt = parseFloat(data.amount) || 0;
        if (fromBalance - amt < 0) {
            if (!confirm('Warning: This transfer will make the source pool balance negative (Current: PKR ' + Math.round(fromBalance).toLocaleString() + '). Continue?')) {
                return;
            }
        }

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="spinner spinner-white spinner-sm mr-2"></i> Submitting...');

        $.ajax({
            url: apiBase + 'transfers/store',
            type: 'POST',
            data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal_transfer').modal('hide');
                    form[0].reset();
                    loadTransfers();
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                var resp = xhr.responseJSON;
                if (resp && resp.errors) {
                    $.each(resp.errors, function (f, msgs) { toastr.error(msgs[0]); });
                } else {
                    toastr.error(resp ? resp.message : 'Failed to submit transfer.');
                }
            },
            complete: function () {
                btn.prop('disabled', false).html('Submit Transfer');
            }
        });
    }

    function escapeHtml(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
    function numberFormat(n) { return parseFloat(n||0).toLocaleString('en-PK',{maximumFractionDigits:0}); }
    function formatDate(d) { if(!d) return '-'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }

    return { init: init };
})();

$(document).ready(function () { CashflowTransfers.init(); });
