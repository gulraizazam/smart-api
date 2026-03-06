"use strict";

var CashflowTransfers = (function () {
    var apiBase = '/api/cashflow/';
    var currentPage = 1;
    var transfersXhr = null;

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
        $('#btn-reset-filters').on('click', function () {
            $('#filter-pool').val('').trigger('change');
            $('#filter-method').val('').trigger('change');
            $('#filter-search').val('');
            var picker = $('#filter-date-range').data('daterangepicker');
            if (picker) { picker.setStartDate(moment().startOf('month')); picker.setEndDate(moment()); }
            currentPage = 1;
            loadTransfers();
        });
        $('#btn-submit-transfer').on('click', submitTransfer);

        // Init date picker on transfer_date field
        $('#form-transfer [name="transfer_date"]').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            autoUpdateInput: false,
            locale: { format: 'YYYY-MM-DD' }
        }).on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD'));
        });

        $('#modal_transfer').on('shown.bs.modal', function () {
            var mb = $(this).find('.modal-body');
            var dateField = mb.find('[name="transfer_date"]');
            if (!dateField.val()) dateField.val(moment().format('YYYY-MM-DD'));
            mb.find('[name="method"]').select2({ placeholder: 'Select method', dropdownParent: mb });
            mb.find('[name="from_pool_id"]').select2({ placeholder: 'Select source pool', dropdownParent: mb });
            mb.find('[name="to_pool_id"]').select2({ placeholder: 'Select destination pool', dropdownParent: mb });

            // Apply initial pool filtering based on default method
            var initMethod = mb.find('[name="method"]').val();
            if (initMethod) filterTransferPools(initMethod);
        }).on('hidden.bs.modal', function () {
            $(this).find('.kt-select2-general').select2('destroy');
            $('#form-transfer')[0].reset();
            $('#form-transfer [name="transfer_date"]').val('');
        });

        // Filter pools when transfer method changes
        $(document).on('change', '#form-transfer [name="method"]', function () {
            filterTransferPools($(this).val());
        });

        // Keyboard shortcuts
        $(document).on('keydown', function (e) {
            if (e.altKey && e.key === 't') { e.preventDefault(); $('#modal_transfer').modal('show'); }
            if (e.ctrlKey && e.key === 'Enter' && $('#modal_transfer').hasClass('show')) { e.preventDefault(); $('#btn-submit-transfer').click(); }
        });
    }

    function filterTransferPools(method) {
        var fromSelect = $('[name="from_pool_id"]', '#form-transfer');
        var toSelect = $('[name="to_pool_id"]', '#form-transfer');

        // Show all if no method
        if (!method) {
            fromSelect.find('option').prop('disabled', false).show();
            toSelect.find('option').prop('disabled', false).show();
            return;
        }

        // physical_cash: from=cash pools, to=cash pools
        // bank_deposit: from=cash pools, to=bank pools (depositing cash into bank)
        var fromTypes, toTypes;
        if (method === 'bank_deposit') {
            fromTypes = ['branch_cash', 'head_office_cash'];
            toTypes = ['bank_account'];
        } else {
            // physical_cash: both should be cash pools
            fromTypes = ['branch_cash', 'head_office_cash'];
            toTypes = ['branch_cash', 'head_office_cash'];
        }

        [{ sel: fromSelect, types: fromTypes }, { sel: toSelect, types: toTypes }].forEach(function (cfg) {
            var hasMatch = false;
            cfg.sel.find('option').each(function () {
                var opt = $(this);
                if (!opt.val()) return;
                var type = opt.data('type') || '';
                if (cfg.types.indexOf(type) !== -1) {
                    opt.prop('disabled', false).show();
                    hasMatch = true;
                } else {
                    opt.prop('disabled', true).hide();
                }
            });
            // If no match, show all (fallback)
            if (!hasMatch) {
                cfg.sel.find('option').prop('disabled', false).show();
            }
            // Reset if current selection is now disabled
            var curVal = cfg.sel.val();
            if (curVal && cfg.sel.find('option[value="' + curVal + '"]').prop('disabled')) {
                cfg.sel.val('').trigger('change.select2');
            }
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
                    var label = p.name;
                    opts += '<option value="' + p.id + '">' + escapeHtml(label) + '</option>';
                    formOpts += '<option value="' + p.id + '" data-type="' + (p.type || '') + '" data-balance="' + (p.cached_balance || 0) + '">' + escapeHtml(label) + '</option>';
                });
                $('#filter-pool').html(opts);
                $('[name="from_pool_id"]', '#form-transfer').html(formOpts);
                $('[name="to_pool_id"]', '#form-transfer').html(formOpts);

                // Init Select2 on page-level filter selects (after options are populated)
                $('#filter-pool').select2();
                $('#filter-method').select2();
            }
        });
    }

    function loadTransfers() {
        if (transfersXhr) transfersXhr.abort();
        var dr = getDateRange();
        var params = {
            page: currentPage,
            pool_id: $('#filter-pool').val(),
            method: $('#filter-method').val(),
            date_from: dr.date_from,
            date_to: dr.date_to,
            search: $('#filter-search').val()
        };
        Object.keys(params).forEach(function (k) { if (!params[k]) delete params[k]; });

        $('#transfers-tbody').html('<tr><td colspan="7" class="text-center"><div class="spinner spinner-primary spinner-sm"></div> Loading...</td></tr>');

        transfersXhr = $.ajax({
            url: apiBase + 'transfers/data',
            type: 'GET',
            data: params,
            success: function (res) {
                if (res.success) {
                    renderTransfers(res.data);
                    renderPagination(res.meta);
                }
            },
            error: function (xhr) {
                if (xhr.statusText !== 'abort') $('#transfers-tbody').html('<tr><td colspan="7" class="text-center text-danger">Failed to load.</td></tr>');
            },
            complete: function () { transfersXhr = null; }
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
            var toLabel = t.to_pool ? t.to_pool.name : '-';
            var isVoided = !!t.voided_at;
            var rowClass = isVoided ? 'text-muted' : '';
            var amtStyle = isVoided ? 'text-decoration:line-through;' : '';

            var statusBadge = '';
            if (isVoided) {
                statusBadge = ' <span class="label label-light-dark label-inline" title="' + escapeHtml(t.void_reason || '') + '">VOID</span>';
            }

            var actions = '';
            if (!isVoided) {
                actions = '<button class="btn btn-sm btn-clean btn-icon btn-void-transfer" data-id="' + t.id + '" title="Void this transfer"><i class="la la-ban text-danger"></i></button>';
            }

            var descTip = t.description ? ' title="' + escapeHtml(t.description) + '"' : '';
            var attachIcon = t.attachment_url ? ' <a href="javascript:;" class="btn-preview" data-url="' + escapeHtml(t.attachment_url) + '" title="Preview attachment"><i class="la la-paperclip text-primary"></i></a>' : '';

            tbody.append(
                '<tr class="' + rowClass + '">' +
                '<td' + descTip + '>' + formatDate(t.transfer_date) + statusBadge + attachIcon + '</td>' +
                '<td>' + fromLabel + '</td>' +
                '<td>' + toLabel + '</td>' +
                '<td class="text-right font-weight-bold" style="' + amtStyle + '">PKR ' + numberFormat(t.amount) + '</td>' +
                '<td>' + (methodLabels[t.method] || t.method) + '</td>' +
                '<td>' + (t.creator ? escapeHtml(t.creator.name) : '-') + '</td>' +
                '<td class="text-right">' + actions + '</td>' +
                '</tr>'
            );
        });

        // Bind preview handler
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

        // Bind void button handler
        $('.btn-void-transfer').off('click').on('click', function () {
            var id = $(this).data('id');
            var reason = prompt('Reason for voiding this transfer (min 5 chars):');
            if (reason === null) return;
            if (!reason || reason.length < 5) {
                toastr.warning('Void reason must be at least 5 characters.');
                return;
            }
            voidTransfer(id, reason);
        });
    }

    function voidTransfer(id, reason) {
        $.ajax({
            url: apiBase + 'transfers/' + id + '/void',
            type: 'POST',
            data: { void_reason: reason },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    loadTransfers();
                } else {
                    toastr.error(res.message);
                }
            },
            error: function (xhr) {
                var resp = xhr.responseJSON;
                toastr.error(resp ? resp.message : 'Failed to void transfer.');
            }
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
        form.find('input, select').each(function () {
            var n = $(this).attr('name');
            if (n) data[n] = $(this).val();
        });

        if (!data.transfer_date || !data.amount || !data.from_pool_id || !data.to_pool_id || !data.attachment_url) {
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

        var btn = $('#btn-submit-transfer');
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

    function escapeHtml(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
    function numberFormat(n) { return parseFloat(n||0).toLocaleString('en-PK',{maximumFractionDigits:0}); }
    function formatDate(d) { if(!d) return '-'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }

    return { init: init };
})();

$(document).ready(function () { CashflowTransfers.init(); });
