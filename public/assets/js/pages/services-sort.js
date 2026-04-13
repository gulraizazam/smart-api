"use strict";

jQuery(document).ready(function () {
    $.ajax({
        url: route('admin.services.get_sort'),
        method: "GET",
        success: function (response) {
            renderGroups(response.data);
            initCategorySortable();
            initChildSortables();
        },
        error: function () {
            $('#sort-container').html('<div class="alert alert-danger m-3">Failed to load services.</div>');
        }
    });
});

function renderGroups(groups) {
    var html = '';
    groups.forEach(function (parent, idx) {
        html += '<div class="sort-parent-group draggable-category" data-parent-id="' + parent.id + '">';
        html += '<div class="sort-parent-header">';
        html += '<div class="d-flex align-items-center">';
        html += '<i class="ki ki-menu drag-handle category-drag-handle mr-3" style="cursor:grab;color:#B5B5C3;font-size:16px;"></i>';
        html += '<span class="sort-num category-num">' + (idx + 1) + '</span>';
        html += '<h5 style="margin:0;font-size:14px;font-weight:600;color:#3F4254;"><i class="fa fa-folder-open mr-2 text-primary" style="font-size:13px;"></i>' + escapeHtml(parent.name) + ' <span class="text-muted" style="font-size:12px;font-weight:400;">(' + parent.children.length + ')</span></h5>';
        html += '</div>';
        html += '<i class="fa fa-chevron-down toggle-icon" style="cursor:pointer;" onclick="toggleGroup(this.closest(\'.sort-parent-header\'))"></i>';
        html += '</div>';
        html += '<div class="sort-children-zone">';

        if (parent.children.length === 0) {
            html += '<div class="sort-empty-msg">No child services</div>';
        } else {
            parent.children.forEach(function (child, cidx) {
                html += '<div class="sort-child-item draggable" id="sort-item-' + child.id + '" data-id="' + child.id + '">';
                html += '<i class="ki ki-menu drag-handle draggable-handle"></i>';
                html += '<span class="sort-num">' + (cidx + 1) + '</span>';
                html += '<span class="sort-name">' + escapeHtml(child.name) + '</span>';
                html += '</div>';
            });
        }

        html += '</div></div>';
    });

    $('#sort-container').html(html);
}

function initCategorySortable() {
    var container = document.getElementById('sort-container');
    if (!container) return;

    var sortable = new Sortable.default([container], {
        draggable: '.draggable-category',
        handle: '.category-drag-handle',
        mirror: {
            appendTo: 'body',
            constrainDimensions: true
        }
    });

    sortable.on('sortable:stop', function () {
        setTimeout(function () {
            updateCategoryNumbers();
            saveCategoryOrder();
        }, 10);
    });
}

function initChildSortables() {
    var zones = document.querySelectorAll('.sort-children-zone');
    if (zones.length === 0) return;

    zones.forEach(function (zone) {
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
                saveGroup(zone.closest('.sort-parent-group'));
            }, 10);
        });
    });
}

function updateCategoryNumbers() {
    $('#sort-container').find('.draggable-category').each(function (idx) {
        $(this).find('.category-num').first().text(idx + 1);
    });
}

function saveCategoryOrder() {
    var ids = [];
    document.querySelectorAll('.draggable-category').forEach(function (group) {
        ids.push(group.getAttribute('data-parent-id'));
    });

    if (ids.length === 0) return;

    $.ajax({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        url: route('admin.services.category_sort_save'),
        method: "POST",
        data: { category_ids: ids },
        success: function (data) {
            toastr.success(data.message);
        }
    });
}

function updateNumbers(zone) {
    $(zone).find('.sort-child-item').each(function (idx) {
        $(this).find('.sort-num').text(idx + 1);
    });
}

function saveGroup(group) {
    var parentId = group.getAttribute('data-parent-id');
    var ids = [];
    group.querySelectorAll('.sort-child-item').forEach(function (item) {
        ids.push(item.getAttribute('data-id'));
    });

    if (ids.length === 0) return;

    $.ajax({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        url: route('admin.services.sort_save'),
        method: "POST",
        data: { parent_id: parentId, item_ids: ids },
        success: function (data) {
            toastr.success(data.message);
        }
    });
}

function toggleGroup(header) {
    $(header).toggleClass('collapsed');
    $(header).next('.sort-children-zone').slideToggle(200);
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
