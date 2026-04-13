var table_url = route('admin.bundles.datatable');
let changePages = 1000;
let changePaginate = false;
var isMobile = window.innerWidth < 768;

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// Inject clickable styles
(function () {
    if (!document.getElementById('datatable-clickable-styles')) {
        var s = document.createElement('style');
        s.id = 'datatable-clickable-styles';
        s.textContent =
            '.clickable-name { color: #3F4254; text-decoration: none; cursor: pointer; transition: color 0.2s ease; }' +
            '.clickable-name:hover { color: #3699FF; }' +
            '.clickable-name .tap-icon { color: #B5B5C3; font-size: 13px; margin-left: 5px; transition: color 0.2s ease; }' +
            '.clickable-name:hover .tap-icon { color: #3699FF; }' +
            '.category-toggle { cursor: pointer; padding: 2px 8px 2px 4px; border-radius: 4px; transition: background-color 0.2s ease; display: inline-block; }' +
            '.category-toggle:hover { background-color: #F3F6F9; }';
        document.head.appendChild(s);
    }
})();

// Column templates
function nameTemplate(data) {
    return '<a href="javascript:void(0);" class="clickable-name" onclick="detailRow(\'' + route('admin.bundles.detail', {id: data.id}) + '\');">' + escapeHtml(data.name) + '<i class="la la-angle-right tap-icon"></i></a>';
}

function regularPriceTemplate(data) {
    if (typeof data.regular_price !== 'undefined' && data.regular_price !== null && data.regular_price !== '') {
        return '<span>' + data.regular_price + '</span>';
    }
    return '';
}

function priceTemplate(data) {
    if (typeof data.price !== 'undefined' && data.price !== null && data.price !== '') {
        return '<span>' + data.price + '</span>';
    }
    return '';
}

function youSaveTemplate(data) {
    if (data.you_save) {
        return '<span class="text-success">' + data.you_save + '</span>';
    }
    return '';
}

function dateTemplate(data, field) {
    if (data[field]) {
        return '<span>' + data[field] + '</span>';
    }
    return '';
}

// Build columns based on edit rights + screen size
if (typeof hasEditRights !== 'undefined' && hasEditRights) {
    var table_columns = [
        { field: 'name', title: 'Name', width: 190, template: nameTemplate },
        { field: 'total_services', title: 'Total Services', width: 100 },
        { field: 'regular_price', title: 'Regular Price', width: 110, template: regularPriceTemplate },
        { field: 'price', title: 'Package Price', width: 110, autoHide: false, template: priceTemplate },
        { field: 'you_save', title: 'You Save', width: 90, template: youSaveTemplate },
        { field: 'start', title: 'Valid From', width: 'auto', template: function(d) { return dateTemplate(d, 'start'); } },
        { field: 'end', title: 'Valid To', width: 'auto', template: function(d) { return dateTemplate(d, 'end'); } },
        {
            field: 'status', title: 'Status', width: 130, sortable: false,
            template: function (data) { return statuses(data, route('admin.bundles.status')); }
        },
        {
            field: 'actions', title: 'Actions', sortable: false, width: 80,
            overflow: 'visible', autoHide: false,
            template: function (data) { return actions(data); }
        }
    ];
} else if (isMobile) {
    // View-only mobile: Name + Package Price
    var table_columns = [
        { field: 'name', title: 'Name', width: 260, autoHide: false, template: nameTemplate },
        { field: 'price', title: 'Package Price', width: 80, autoHide: false, template: priceTemplate }
    ];
} else {
    // View-only desktop
    var table_columns = [
        { field: 'name', title: 'Name', template: nameTemplate },
        { field: 'total_services', title: 'Total Services', width: 100 },
        { field: 'regular_price', title: 'Regular Price', width: 110, template: regularPriceTemplate },
        { field: 'price', title: 'Package Price', width: 110, autoHide: false, template: priceTemplate },
        { field: 'you_save', title: 'You Save', width: 90, template: youSaveTemplate },
        { field: 'start', title: 'Valid From', width: 'auto', template: function(d) { return dateTemplate(d, 'start'); } },
        { field: 'end', title: 'Valid To', width: 'auto', template: function(d) { return dateTemplate(d, 'end'); } }
    ];
}


function actions(data) {
    var id = data.id;
    var edit_url = route('admin.bundles.edit', {id: id});
    var detail_url = route('admin.bundles.detail', {id: id});
    var delete_url = route('admin.bundles.destroy', {id: id});

    if (permissions.details || permissions.edit || permissions.delete) {
        var html = '<div class="dropdown dropdown-inline action-dots">\
            <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action:\
                    </li>';
        if (permissions.details) {
            html += '<li class="navi-item">\
                <a href="javascript:void(0);" onclick="detailRow(`' + detail_url + '`);" class="navi-link">\
                    <span class="navi-icon"><i class="la la-eye"></i></span>\
                    <span class="navi-text">Detail</span>\
                </a>\
            </li>';
        }
        if (permissions.edit) {
            html += '<li class="navi-item">\
                <a href="javascript:void(0);" onclick="editRow(`' + edit_url + '`);" class="navi-link">\
                    <span class="navi-icon"><i class="la la-pencil"></i></span>\
                    <span class="navi-text">Edit</span>\
                </a>\
            </li>';
        }
        if (permissions.delete) {
            html += '<li class="navi-item">\
                <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">\
                    <span class="navi-icon"><i class="la la-trash"></i></span>\
                    <span class="navi-text">Delete</span>\
                </a>\
            </li>';
        }
        html += '</ul></div></div>';
        return html;
    }
    return '';
}

function editRow(url) {
    $.ajax({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        url: url, type: "GET", cache: false,
        success: function (response) {
            $("#modal_bundles").modal("show");
            setEditData(response);
        },
        error: function (xhr) { errorMessage(xhr); }
    });
}

function detailRow(url) {
    $.ajax({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        url: url, type: "GET", cache: false,
        success: function (response) {
            $("#modal_details_bundles").modal("show");
            setDetailData(response);
        },
        error: function (xhr) { errorMessage(xhr); }
    });
}

function setDetailData(response) {
    var bundle = response.data.bundle;
    var bundle_services = response.data.bundle_services;
    var relationships = response.data.relationships;
    $('#detail_name').text(bundle.name);
    $('#detail_total_services').text(bundle.total_services);
    $('#detail_services_price').text(parseFloat(bundle.services_price || 0).toFixed(2));
    $('#detail_price').text(parseFloat(bundle.price || 0).toFixed(2));
    var savings = parseFloat(bundle.services_price || 0) - parseFloat(bundle.price || 0);
    $('#detail_you_save').text(savings > 0 ? savings.toFixed(2) : '-');
    if (bundle.created_at) {
        var date = new Date(bundle.created_at);
        $('#detail_created_at').text(date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' }) + ' ' +
            date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }));
    } else {
        $('#detail_created_at').text('-');
    }
    $('.DETAIL_SERVICES').remove();
    Object.entries(relationships).forEach(function (value) {
        var svc = bundle_services[value[1].service_id];
        $("#detail-service-body").append('<tr class="DETAIL_SERVICES"><td>' + escapeHtml(svc.name) + '</td><td>' + escapeHtml(svc.price) + '</td></tr>');
    });
}

function setEditData(response) {
    $('#model-title').html('Edit Package');
    var bundle = response.data.bundle;
    var bundle_services = response.data.bundle_services;
    var relationships = response.data.relationships;
    var action = route('admin.bundles.update', {id: bundle.id});
    $("#modal_bundles_form").attr("action", action);
    $('#put_input').html('<input type="hidden" name="_method" value="put">');
    $('#bundles_name').val(bundle.name);

    // Populate services table
    $('.HR_SERVICES').remove();
    Object.entries(relationships).forEach(function (value, index) {
        var svc = bundle_services[value[1].service_id];
        $('#service_body').append(setService(index + 1, value[1].service_id, svc.name, svc.price));
    });
    calculateServicesTotal();

    // Set pricing: compute discount % from regular vs package price
    var regularPrice = parseFloat($('#service_price').val()) || 0;
    var packagePrice = parseFloat(bundle.price) || 0;

    if (regularPrice > 0 && packagePrice <= regularPrice) {
        var discountPct = ((regularPrice - packagePrice) / regularPrice * 100);
        var roundedDiscount = Math.round(discountPct * 100) / 100;
        setPricingMode('discount');
        $('#bundles_discount').val(roundedDiscount);
        $('#bundles_price').val(packagePrice);
    } else {
        setPricingMode('net');
        $('#bundles_price').val(packagePrice);
        $('#bundles_discount').val('');
    }

    $('input[name="tax_treatment_type_id"][value="' + bundle.tax_treatment_type_id + '"]').prop('checked', true);
    $('input[name="apply_discount"]').prop('checked', !!bundle.apply_discount);
    $('#start').val(bundle.start);
    $('#end').val(bundle.end);

    updateYouSave();
}

// ── Add service row ──────────────────────────────────
function addRow() {
    if ($('#services').val() !== '') {
        var $sel = $('#services').find(':selected');
        $('#service_body').append(setService(
            $("#service_body tr").length + 1,
            $sel.attr('data-id'), $sel.attr('data-name'), $sel.attr('data-price')
        ));
        calculateServicesTotal();
        recalculateFromPricingMode();
    }
}

// ── Add bundle row (N service rows at regular price) ─
function addBundleRow() {
    var $sel = $('#bundles_dropdown').find(':selected');
    if (!$sel.val()) return;

    var sessions = parseInt($sel.attr('data-sessions')) || 0;
    var serviceId = $sel.attr('data-service-id');
    var serviceName = $sel.attr('data-service-name');
    var servicePrice = $sel.attr('data-service-price');

    if (sessions <= 0 || !serviceId) return;

    var startIdx = $("#service_body tr").length + 1;
    for (var i = 0; i < sessions; i++) {
        $('#service_body').append(setService(startIdx + i, serviceId, serviceName, servicePrice));
    }
    calculateServicesTotal();
    recalculateFromPricingMode();
}

function calculateServicesTotal() {
    var totalPrice = 0, total_services = 0;
    $('.servicePriceValue').each(function () {
        totalPrice += parseFloat($(this).val());
        total_services++;
    });
    $('#service_price').val(totalPrice.toFixed(2));
    $('#total_services').val(total_services);
}

function setService(id, service_id, service_name, price) {
    var safeId = parseInt(service_id) || 0;
    var safePrice = parseFloat(price) || 0;
    return '<tr class="HR_SERVICES HR_' + parseInt(id) + '">' +
        '<input type="hidden" name="service_id[]" value="' + safeId + '">' +
        '<input type="hidden" name="service_price[]" value="' + safePrice + '">' +
        '<input type="hidden" class="servicePriceValue" value="' + safePrice + '">' +
        '<td>' + escapeHtml(service_name) + '</td><td class="text-right">' + safePrice.toFixed(2) + '</td><td class="text-center">' + deleteIcon(id) + '</td></tr>';
}

function deleteIcon(id) {
    return '<a href="javascript:void(0);" onclick="deleteModel(' + id + ')" class="btn btn-icon btn-light btn-hover-danger btn-sm">' +
        '<span class="svg-icon svg-icon-md svg-icon-danger"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">' +
        '<g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"><rect x="0" y="0" width="24" height="24"/>' +
        '<path d="M6,8 L6,20.5 C6,21.3284271 6.67157288,22 7.5,22 L16.5,22 C17.3284271,22 18,21.3284271 18,20.5 L18,8 L6,8 Z" fill="#000000" fill-rule="nonzero"/>' +
        '<path d="M14,4.5 L14,4 C14,3.44771525 13.5522847,3 13,3 L11,3 C10.4477153,3 10,3.44771525 10,4 L10,4.5 L5.5,4.5 C5.22385763,4.5 5,4.72385763 5,5 L5,5.5 C5,5.77614237 5.22385763,6 5.5,6 L18.5,6 C18.7761424,6 19,5.77614237 19,5.5 L19,5 C19,4.72385763 18.7761424,4.5 18.5,4.5 L14,4.5 Z" fill="#000000" opacity="0.3"/>' +
        '</g></svg></span></a>';
}

function deleteModel(id) {
    $('.HR_' + id).remove();
    calculateServicesTotal();
    recalculateFromPricingMode();
}

// ── Pricing mode ─────────────────────────────────────

function getPricingMode() {
    return $('input[name="pricing_mode"]:checked').val() || 'discount';
}

function setPricingMode(mode) {
    $('input[name="pricing_mode"][value="' + mode + '"]').prop('checked', true);
    togglePricingFields(mode);
}

function togglePricingFields(mode) {
    if (mode === 'discount') {
        $('#discount_field').show();
        $('#bundles_price').prop('readonly', true).css('background', '#f5f8fa');
    } else {
        $('#discount_field').hide();
        $('#bundles_price').prop('readonly', false).css('background', '');
    }
}

// When pricing mode changes
$('input[name="pricing_mode"]').on('change', function () {
    var mode = $(this).val();
    togglePricingFields(mode);
    if (mode === 'discount') {
        recalculateFromDiscount();
    }
    updateYouSave();
});

// When discount % changes → recalculate package price
$('#bundles_discount').on('input change', function () {
    recalculateFromDiscount();
});

// When package price changes manually (net mode) → update you save
$('#bundles_price').on('input change', function () {
    updateYouSave();
});

function recalculateFromDiscount() {
    var regularPrice = parseFloat($('#service_price').val()) || 0;
    var discount = parseFloat($('#bundles_discount').val()) || 0;
    if (discount < 0) discount = 0;
    if (discount > 100) discount = 100;

    var packagePrice = Math.round(regularPrice * (1 - discount / 100) * 100) / 100;
    $('#bundles_price').val(packagePrice.toFixed(2));
    updateYouSave();
}

function recalculateFromPricingMode() {
    if (getPricingMode() === 'discount') {
        recalculateFromDiscount();
    }
    updateYouSave();
}

function updateYouSave() {
    var regularPrice = parseFloat($('#service_price').val()) || 0;
    var packagePrice = parseFloat($('#bundles_price').val()) || 0;
    var savings = regularPrice - packagePrice;
    $('#you_save_display').val(savings > 0 ? savings.toFixed(2) : '-');
}

// Initialize pricing mode on page load
$(document).ready(function () {
    togglePricingFields(getPricingMode());
});

// ── Create button reset ──────────────────────────────
$('#create-btn').click(function () {
    $("#modal_bundles_form").attr("action", route('admin.bundles.store'));
    $('#put_input').html('');
    $('#model-title').html('Add Package');
    $('.HR_SERVICES').remove();
    $('#bundles_name').val('');
    setPricingMode('discount');
    $('#bundles_discount').val('');
    $('#bundles_price').val('');
    $('#start').val('');
    $('#end').val('');
    $('input[name="apply_discount"]').prop('checked', false);
    $('#services').val('').trigger('change');
    $('#bundles_dropdown').val('').trigger('change');
    calculateServicesTotal();
    updateYouSave();
});

// ── Filters ──────────────────────────────────────────
function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        datatable.search({
            name: $("#search_name").val(),
            startdate: $("#search_startdate").val(),
            enddate: $("#search_enddate").val(),
            status: $("#search_status").val(),
            filter: 'filter',
        }, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function () {
        $('#search_name').val('');
        $('#search_startdate').val('');
        $('#search_enddate').val('');
        $('#search_status').val('');
        datatable.search({
            name: '', startdate: '', enddate: '', status: '',
            filter: 'filter_cancel',
        }, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    if (filter_values.status) {
        var status_options = '<option value="">All Status</option>';
        Object.entries(filter_values.status).forEach(function (value) {
            status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });
        $("#search_status").html(status_options);
    }

    if (filter_values.tax_treatment_types) {
        var tax_options = '';
        Object.entries(filter_values.tax_treatment_types).forEach(function (value, i) {
            tax_options += '<label class="radio"><input ' + (i === 0 ? 'checked ' : '') + 'name="tax_treatment_type_id" value="' + parseInt(value[1].id) + '" type="radio"/><span></span>' + escapeHtml(value[1].name) + '</label>';
        });
        $("#bundles_tax").html(tax_options);
    }

    // Populate services dropdown
    if (filter_values.services) {
        var service_options = '<option value="">Select Service</option>';
        Object.entries(filter_values.services).forEach(function (value) {
            var svc = value[1];
            var safePrice = parseFloat(svc.price) || 0;
            service_options += '<option value="' + parseInt(svc.id) + '" data-name="' + escapeHtml(svc.name) + '" data-price="' + safePrice + '" data-id="' + parseInt(svc.id) + '">' + escapeHtml(svc.name) + ' (' + safePrice.toFixed(2) + ')</option>';
        });
        $("#services").html(service_options);
    }

    // Populate service bundles dropdown (grouped by category)
    if (filter_values.service_bundles) {
        var grouped = {};
        filter_values.service_bundles.forEach(function (sb) {
            var cat = sb.category || 'Uncategorized';
            if (!grouped[cat]) grouped[cat] = [];
            grouped[cat].push(sb);
        });

        var bundle_options = '<option value="">Select Bundle</option>';
        Object.keys(grouped).sort().forEach(function (catName) {
            bundle_options += '<optgroup label="' + escapeHtml(catName) + '">';
            grouped[catName].forEach(function (sb) {
                var unitPrice = parseFloat(sb.service_price) || 0;
                bundle_options += '<option value="' + parseInt(sb.id) + '" data-service-id="' + parseInt(sb.service_id) + '" data-service-name="' + escapeHtml(sb.service_name) + '" data-service-price="' + unitPrice + '" data-sessions="' + parseInt(sb.sessions) + '">' + escapeHtml(sb.name) + ' (' + (unitPrice * sb.sessions).toFixed(2) + ')</option>';
            });
            bundle_options += '</optgroup>';
        });
        $("#bundles_dropdown").html(bundle_options);
    }

    $("#search_name").val(active_filters.name || '');
    $("#search_startdate").val(active_filters.start || '');
    $("#search_enddate").val(active_filters.end || '');
    $("#search_status").val(active_filters.status || '');
}
