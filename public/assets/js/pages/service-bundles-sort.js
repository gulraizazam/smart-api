"use strict";

jQuery(document).ready(function () {
    $.ajax({
        url: route('admin.service-bundles.get_sort'),
        method: "GET",
        success: function (response) {
            renderCategories(response.data);
            initSortable();
        },
        error: function () {
            $('#sort-container').html('<div class="alert alert-danger m-3">Failed to load categories.</div>');
        }
    });
});

function renderCategories(categories) {
    if (!categories || categories.length === 0) {
        $('#sort-container').html('<div class="text-center text-muted py-5">No active bundles to sort.</div>');
        return;
    }

    var html = '<div class="sort-list" id="sort-zone-categories">';
    categories.forEach(function (cat, idx) {
        var bundlePreview = (cat.bundles || []).slice(0, 3).join(', ');
        if ((cat.bundles || []).length > 3) bundlePreview += ', ...';

        html += '<div class="sort-item draggable" data-id="' + cat.id + '">';
        html += '<i class="ki ki-menu drag-handle draggable-handle"></i>';
        html += '<span class="sort-num">' + (idx + 1) + '</span>';
        html += '<div style="flex:1;">';
        html += '<span class="sort-name" style="font-weight:600;">' + escapeHtml(cat.name) + '</span>';
        html += '<span class="text-muted" style="font-size:12px; margin-left:8px;">(' + cat.count + ' bundle' + (cat.count !== 1 ? 's' : '') + ')</span>';
        if (bundlePreview) {
            html += '<div class="text-muted" style="font-size:11px; margin-top:2px;">' + escapeHtml(bundlePreview) + '</div>';
        }
        html += '</div>';
        html += '</div>';
    });
    html += '</div>';

    $('#sort-container').html(html);
}

function initSortable() {
    var zone = document.getElementById('sort-zone-categories');
    if (!zone || zone.querySelectorAll('.draggable').length === 0) return;

    var sortable = new Sortable.default([zone], {
        draggable: '.draggable',
        handle: '.draggable .draggable-handle',
        mirror: {
            appendTo: 'body',
            constrainDimensions: true
        }
    });

    sortable.on('sortable:stop', function () {
        setTimeout(function () {
            updateNumbers(zone);
            saveOrder(zone);
        }, 10);
    });
}

function updateNumbers(zone) {
    $(zone).find('.sort-item').each(function (idx) {
        $(this).find('.sort-num').text(idx + 1);
    });
}

function saveOrder(zone) {
    var ids = [];
    zone.querySelectorAll('.sort-item').forEach(function (item) {
        ids.push(item.getAttribute('data-id'));
    });

    if (ids.length === 0) return;

    $.ajax({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        url: route('admin.service-bundles.sort_save'),
        method: "POST",
        data: { category_ids: ids },
        success: function (data) {
            toastr.success(data.message);
        }
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
