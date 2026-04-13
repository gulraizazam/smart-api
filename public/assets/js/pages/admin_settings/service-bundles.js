var table_url = route('admin.service-bundles.datatable');
let changePages = 1000;
let changePaginate = false;
var isMobile = window.innerWidth < 768;
var servicesData = [];
var collapsedCategories = {};

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

// Column templates — category rows vs bundle rows
function nameTemplate(data) {
    if (data.is_category) {
        return '<span class="category-toggle">' +
            '<i class="la la-angle-down toggle-icon" style="font-size:14px;margin-right:4px;display:inline-block;transition:transform 0.15s ease;"></i>' +
            '<b class="text text-dark" style="font-size: 12px;">' + escapeHtml(data.name) + ' <span class="text-muted" style="font-weight:400;">(' + (parseInt(data.bundle_count) || 0) + ')</span></b>' +
            '</span>';
    }
    return '<span class="ml-3"><a href="javascript:void(0);" class="clickable-name" onclick="detailRow(\'' + route('admin.service-bundles.detail', {id: data.id}) + '\');">' + escapeHtml(data.name) + '<i class="la la-angle-right tap-icon"></i></a></span>';
}

function sessionsTemplate(data) {
    if (data.is_category) return '';
    return '<span>' + data.sessions + '</span>';
}

function regularPriceTemplate(data) {
    if (data.is_category) return '';
    return '<span>' + data.regular_price + '</span>';
}

function priceTemplate(data) {
    if (data.is_category) return '';
    return '<span>' + data.price + '</span>';
}

function youSaveTemplate(data) {
    if (data.is_category) return '';
    return '<span class="text-success">' + data.you_save + '</span>';
}

// Build columns
if (typeof hasEditRights !== 'undefined' && hasEditRights) {
    var table_columns = [
        { field: 'name', title: 'Name', sortable: false, width: 250, template: nameTemplate },
        { field: 'sessions', title: 'Sessions', sortable: false, width: 80, template: sessionsTemplate },
        { field: 'regular_price', title: 'Regular Price', sortable: false, width: 110, template: regularPriceTemplate },
        { field: 'price', title: 'Bundle Price', sortable: false, width: 110, autoHide: false, template: priceTemplate },
        { field: 'you_save', title: 'You Save', sortable: false, width: 100, template: youSaveTemplate },
        {
            field: 'status', title: 'Status', width: 130, sortable: false,
            template: function (data) {
                if (data.is_category) return '';
                return statuses(data, route('admin.service-bundles.status'));
            }
        },
        {
            field: 'actions', title: 'Actions', sortable: false, width: 80,
            overflow: 'visible', autoHide: false,
            template: function (data) {
                if (data.is_category) return '';
                return actions(data);
            }
        }
    ];
} else if (isMobile) {
    var table_columns = [
        { field: 'name', title: 'Name', sortable: false, width: 260, autoHide: false, template: nameTemplate },
        { field: 'price', title: 'Bundle Price', sortable: false, width: 80, autoHide: false, template: priceTemplate }
    ];
} else {
    var table_columns = [
        { field: 'name', title: 'Name', sortable: false, template: nameTemplate },
        { field: 'sessions', title: 'Sessions', sortable: false, width: 80, template: sessionsTemplate },
        { field: 'regular_price', title: 'Regular Price', sortable: false, width: 110, template: regularPriceTemplate },
        { field: 'price', title: 'Bundle Price', sortable: false, width: 110, autoHide: false, template: priceTemplate },
        { field: 'you_save', title: 'You Save', sortable: false, width: 100, template: youSaveTemplate }
    ];
}

function actions(data) {
    var id = data.id;
    var edit_url = route('admin.service-bundles.edit', {id: id});
    var detail_url = route('admin.service-bundles.detail', {id: id});
    var delete_url = route('admin.service-bundles.destroy', {id: id});

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
            if (response.success) {
                setEditData(response.data);
                $("#modal_service_bundles").modal("show");
            }
        },
        error: function (xhr) { errorMessage(xhr); }
    });
}

function detailRow(url) {
    $.ajax({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        url: url, type: "GET", cache: false,
        success: function (response) {
            if (response.success) {
                setDetailData(response.data);
                $("#modal_detail_service_bundle").modal("show");
            }
        },
        error: function (xhr) { errorMessage(xhr); }
    });
}

function setDetailData(data) {
    var bundle = data.bundle;
    var service = bundle.service || {};
    var parent = service.parent || {};
    var unitPrice = parseFloat(service.price || 0);
    var regularTotal = unitPrice * bundle.sessions;
    var bundlePrice = parseFloat(bundle.price);
    var savings = regularTotal - bundlePrice;

    $('#detail_service_name').text(service.name || '-');
    $('#detail_category').text(parent.name || '-');
    $('#detail_sessions').text(bundle.sessions);
    $('#detail_unit_price').text(unitPrice.toFixed(2));
    $('#detail_regular_total').text(regularTotal.toFixed(2));
    $('#detail_price').text(bundlePrice.toFixed(2));
    $('#detail_discount').text(bundle.discount_percentage !== null ? bundle.discount_percentage + '%' : '-');
    $('#detail_savings').text(savings > 0 ? savings.toFixed(2) : '-');
    $('#detail_status').html(bundle.active ? '<span class="label label-lg label-light-success label-inline">Active</span>' : '<span class="label label-lg label-light-danger label-inline">Inactive</span>');
}

function setEditData(data) {
    var bundle = data.bundle;
    $('#bundle-model-title').text('Edit Bundle');
    var action = route('admin.service-bundles.update', {id: bundle.id});
    $("#modal_service_bundles_form").attr("action", action);
    $('#put_input').html('<input type="hidden" name="_method" value="put">');
    $('#bundle_service_id').val(bundle.service_id).trigger('change');
    $('#bundle_sessions').val(bundle.sessions);
    $('#bundle_discount').val(bundle.discount_percentage);
    $('#bundle_price').val(bundle.price);
    calculatePriceInfo();
}

// Price calculation helpers
function calculatePriceInfo() {
    var serviceId = $('#bundle_service_id').val();
    var sessions = parseInt($('#bundle_sessions').val()) || 0;
    var discount = parseFloat($('#bundle_discount').val()) || 0;

    if (!serviceId || sessions <= 0) {
        $('#bundle_price_info').hide();
        return;
    }

    var service = servicesData.find(function(s) { return s.id == serviceId; });
    if (!service) {
        $('#bundle_price_info').hide();
        return;
    }

    var unitPrice = parseFloat(service.price) || 0;
    var regularTotal = unitPrice * sessions;
    var bundlePrice = Math.round(regularTotal * (1 - discount / 100) * 100) / 100;

    $('#info_unit_price').text(unitPrice.toFixed(2));
    $('#info_regular_total').text(regularTotal.toFixed(2));
    $('#info_savings').text((regularTotal - bundlePrice).toFixed(2));
    $('#bundle_price_info').show();

    // Auto-fill price if user hasn't manually overridden
    if (!$('#bundle_price').data('manual')) {
        $('#bundle_price').val(bundlePrice);
    }
}

$('#bundle_service_id, #bundle_sessions, #bundle_discount').on('change input', function () {
    if (this.id !== 'bundle_price') {
        $('#bundle_price').data('manual', false);
    }
    calculatePriceInfo();
});

$('#bundle_price').on('input', function () {
    $(this).data('manual', true);
});

// Reset form on create button click
$('#create-btn').click(function () {
    $("#modal_service_bundles_form").attr("action", route('admin.service-bundles.store'));
    $('#put_input').html('');
    $('#bundle-model-title').text('Create Bundle');
    $('#bundle_service_id').val('').trigger('change');
    $('#bundle_sessions').val('');
    $('#bundle_discount').val('');
    $('#bundle_price').val('').data('manual', false);
    $('#bundle_price_info').hide();
});

// ── Bulk create: checkbox list ──────────────────────

function buildBulkServicesList(services) {
    var grouped = {};
    (services || []).forEach(function (s) {
        var catName = (s.parent && s.parent.name) ? s.parent.name : 'Uncategorized';
        if (!grouped[catName]) grouped[catName] = [];
        grouped[catName].push(s);
    });

    var html = '';
    Object.keys(grouped).sort().forEach(function (catName) {
        var safeCat = escapeHtml(catName);
        html += '<div class="mb-3">';
        html += '<label class="form-check form-check-sm form-check-custom form-check-solid mb-2">';
        html += '<input class="form-check-input bulk-cat-check" type="checkbox" data-category="' + safeCat + '">';
        html += '<span class="form-check-label fw-bolder text-primary">' + safeCat + '</span>';
        html += '</label>';
        grouped[catName].forEach(function (s) {
            var safePrice = parseFloat(s.price) || 0;
            html += '<label class="form-check form-check-sm form-check-custom form-check-solid ms-6 mb-1">';
            html += '<input class="form-check-input bulk-svc-check" type="checkbox" value="' + parseInt(s.id) + '" data-category="' + safeCat + '" data-price="' + safePrice + '" data-name="' + escapeHtml(s.name) + '">';
            html += '<span class="form-check-label">' + escapeHtml(s.name) + ' (' + safePrice.toFixed(2) + ')</span>';
            html += '</label>';
        });
        html += '</div>';
    });

    $('#bulk_services_list').html(html || '<div class="text-muted text-center py-5">No services available</div>');
    updateBulkCount();
}

function getSelectedBulkIds() {
    var ids = [];
    $('.bulk-svc-check:checked').each(function () { ids.push($(this).val()); });
    return ids;
}

function updateBulkCount() {
    $('#bulk_selected_count').text(getSelectedBulkIds().length);
}

// Select All
$(document).on('change', '#bulk_select_all', function () {
    var checked = $(this).is(':checked');
    $('.bulk-svc-check, .bulk-cat-check').prop('checked', checked);
    updateBulkCount();
});

// Category checkbox — toggle all services in that category
$(document).on('change', '.bulk-cat-check', function () {
    var cat = $(this).data('category');
    var checked = $(this).is(':checked');
    $('.bulk-svc-check[data-category="' + cat + '"]').prop('checked', checked);
    // Sync Select All
    var allChecked = $('.bulk-svc-check').length === $('.bulk-svc-check:checked').length;
    $('#bulk_select_all').prop('checked', allChecked);
    updateBulkCount();
});

// Individual service checkbox — sync category and Select All
$(document).on('change', '.bulk-svc-check', function () {
    var cat = $(this).data('category');
    var catAll = $('.bulk-svc-check[data-category="' + cat + '"]').length === $('.bulk-svc-check[data-category="' + cat + '"]:checked').length;
    $('.bulk-cat-check[data-category="' + cat + '"]').prop('checked', catAll);
    var allChecked = $('.bulk-svc-check').length === $('.bulk-svc-check:checked').length;
    $('#bulk_select_all').prop('checked', allChecked);
    updateBulkCount();
});

// Bulk create preview
$('#bulk-preview-btn').click(function () {
    var sessions = parseInt($('#bulk_sessions').val()) || 0;
    var discount = parseFloat($('#bulk_discount').val()) || 0;

    if (getSelectedBulkIds().length === 0 || sessions <= 0) {
        toastr.warning('Please select services and set sessions.');
        return;
    }

    var html = '';
    $('.bulk-svc-check:checked').each(function () {
        var unitPrice = parseFloat($(this).data('price')) || 0;
        var name = $(this).data('name');
        var regularTotal = unitPrice * sessions;
        var bundlePrice = Math.round(regularTotal * (1 - discount / 100) * 100) / 100;
        html += '<tr><td>' + escapeHtml(name) + '</td><td>' + unitPrice.toFixed(2) + '</td><td>' + regularTotal.toFixed(2) + '</td><td><strong>' + bundlePrice.toFixed(2) + '</strong></td></tr>';
    });

    if (html) {
        $('#bulk_preview_body').html(html);
        $('#bulk_preview_container').show();
    }
});

// Reset bulk form when modal closes
$('#modal_bulk_bundles').on('hidden.bs.modal', function () {
    $('.bulk-svc-check, .bulk-cat-check, #bulk_select_all').prop('checked', false);
    $('#bulk_sessions').val('');
    $('#bulk_discount').val('');
    $('#bulk_preview_body').html('');
    $('#bulk_preview_container').hide();
    updateBulkCount();
});

// Filters
function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        datatable.search({
            name: $("#search_name").val(),
            category: $("#search_category").val(),
            status: $("#search_status").val(),
            filter: 'filter',
        }, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function () {
        $('#search_name').val('');
        $('#search_category').val('');
        $('#search_status').val('');
        datatable.search({
            name: '', category: '', status: '',
            filter: 'filter_cancel',
        }, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    if (filter_values.status) {
        var status_options = '<option value="">All Status</option>';
        Object.entries(filter_values.status).forEach(function (value) {
            status_options += '<option value="' + escapeHtml(value[0]) + '">' + escapeHtml(value[1]) + '</option>';
        });
        $("#search_status").html(status_options);
    }

    if (filter_values.categories) {
        var cat_options = '<option value="">All Categories</option>';
        filter_values.categories.forEach(function (cat) {
            cat_options += '<option value="' + parseInt(cat.id) + '">' + escapeHtml(cat.name) + '</option>';
        });
        $("#search_category").html(cat_options);
    }

    // Populate service dropdowns from datatable response
    if (filter_values.services && servicesData.length === 0) {
        setServicesData(filter_values.services);
    }

    $("#search_name").val(active_filters.name || '');
    $("#search_category").val(active_filters.category || '');
    $("#search_status").val(active_filters.status || '');
}

// Called from datatable onDataReceived to populate service dropdowns
function setServicesData(services) {
    servicesData = services || [];

    // Group by parent (category)
    var grouped = {};
    servicesData.forEach(function (s) {
        var catName = (s.parent && s.parent.name) ? s.parent.name : 'Uncategorized';
        if (!grouped[catName]) grouped[catName] = [];
        grouped[catName].push(s);
    });

    // Single service dropdown
    var singleHtml = '<option value="">Select Service</option>';
    Object.keys(grouped).sort().forEach(function (catName) {
        singleHtml += '<optgroup label="' + escapeHtml(catName) + '">';
        grouped[catName].forEach(function (s) {
            singleHtml += '<option value="' + parseInt(s.id) + '" data-price="' + (parseFloat(s.price) || 0) + '">' + escapeHtml(s.name) + ' (' + (parseFloat(s.price) || 0).toFixed(2) + ')</option>';
        });
        singleHtml += '</optgroup>';
    });
    $('#bundle_service_id').html(singleHtml);

    // Checkbox list for bulk
    buildBulkServicesList(services);
}

// ── Collapsible categories ─────────────────────────

function setupCategoryCollapse() {
    var $rows = $('#kt_datatable table tbody tr');
    if ($rows.length === 0) return;

    $rows.each(function () {
        var $row = $(this);
        var $toggle = $row.find('.category-toggle');
        if ($toggle.length > 0) {
            var catKey = $toggle.text().trim();
            if (collapsedCategories[catKey]) {
                $row.nextUntil('tr:has(.category-toggle)').hide();
                $toggle.find('.toggle-icon').css('transform', 'rotate(-90deg)');
            }
        }
    });
}

$(document).on('click', '.category-toggle', function (e) {
    e.stopPropagation();
    var $categoryRow = $(this).closest('tr');
    var $children = $categoryRow.nextUntil('tr:has(.category-toggle)');
    var $icon = $(this).find('.toggle-icon');
    var catKey = $(this).text().trim();

    if ($children.first().is(':visible')) {
        $children.slideUp(150);
        $icon.css('transform', 'rotate(-90deg)');
        collapsedCategories[catKey] = true;
    } else {
        $children.slideDown(150);
        $icon.css('transform', 'rotate(0deg)');
        delete collapsedCategories[catKey];
    }
});

$(document).ready(function () {
    $('#kt_datatable').on('datatable-on-layout-updated', function () {
        setupCategoryCollapse();
    });
});
