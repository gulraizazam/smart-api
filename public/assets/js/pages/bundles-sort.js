"use strict";

jQuery(document).ready(function () {
    $.ajax({
        url: route('admin.bundles.get_sort'),
        method: "GET",
        success: function (response) {
            renderList(response.data);
            initSortable();
        },
        error: function () {
            $('#sort-container').html('<div class="alert alert-danger m-3">Failed to load bundles.</div>');
        }
    });
});

function renderList(bundles) {
    if (!bundles || bundles.length === 0) {
        $('#sort-container').html('<div class="text-center text-muted py-5">No active bundles to sort.</div>');
        return;
    }

    var html = '<div class="sort-list" id="bundles-sortable-zone">';
    bundles.forEach(function (bundle, idx) {
        html += '<div class="sort-item draggable" data-id="' + bundle.id + '">';
        html += '<i class="ki ki-menu drag-handle draggable-handle"></i>';
        html += '<span class="sort-num">' + (idx + 1) + '</span>';
        html += '<span class="sort-name">' + escapeHtml(bundle.name) + '</span>';
        html += '</div>';
    });
    html += '</div>';

    $('#sort-container').html(html);
}

function initSortable() {
    var zone = document.querySelector('#bundles-sortable-zone');
    if (!zone) return;

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
            updateNumbers();
            saveOrder();
        }, 10);
    });
}

function updateNumbers() {
    $('#bundles-sortable-zone .sort-item').each(function (idx) {
        $(this).find('.sort-num').text(idx + 1);
    });
}

function saveOrder() {
    var ids = [];
    document.querySelectorAll('#bundles-sortable-zone .sort-item').forEach(function (item) {
        ids.push(item.getAttribute('data-id'));
    });

    if (ids.length === 0) return;

    $.ajax({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        url: route('admin.bundles.sort_save'),
        method: "POST",
        data: { item_ids: ids },
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
