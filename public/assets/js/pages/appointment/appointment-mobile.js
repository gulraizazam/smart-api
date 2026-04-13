"use strict";

/*
 * Appointments/Treatments listing mobile transformer.
 *
 * On every KTDatatable redraw, reads the column titles from the rendered
 * head row and tags each body cell with a matching data-label attribute.
 * The mobile list layout in appointment-mobile.css then shows only the
 * four cells we care about (Patient, Scheduled, Service, Status) and hides
 * the rest — rendered as a two-line list row.
 *
 * Tapping a row opens the Detail modal by invoking the Patient ID cell's
 * existing viewDetail() link, so the full record stays one tap away.
 */
(function ($) {
    if (!$) {
        return;
    }

    function syncCellLabels() {
        var $table = $('#kt_datatable');
        if (!$table.length) {
            return;
        }

        var labels = [];
        $table.find('.datatable-head .datatable-row').first()
            .children('.datatable-cell').each(function () {
                labels.push($(this).find('span').first().text().trim());
            });

        if (!labels.length) {
            return;
        }

        $table.find('.datatable-body .datatable-row').each(function () {
            var $row = $(this);
            $row.children('.datatable-cell').each(function (i) {
                var label = labels[i] || '';
                if (label) {
                    $(this).attr('data-label', label);
                } else {
                    $(this).removeAttr('data-label');
                }
            });
        });

        stripYearOnMobile($table);
    }

    // On small screens, trim ", YYYY" from the Scheduled cell to save space.
    // Walks text nodes only so inline icons/anchors stay intact.
    function stripYearOnMobile($table) {
        if (window.innerWidth >= 768) {
            return;
        }
        $table.find('.datatable-body .datatable-cell[data-label="Scheduled"]').each(function () {
            var nodes = $(this).find('*').addBack().contents().filter(function () {
                return this.nodeType === 3;
            });
            nodes.each(function () {
                var trimmed = this.nodeValue.replace(/,\s?\d{4}/g, '');
                if (trimmed !== this.nodeValue) {
                    this.nodeValue = trimmed;
                }
            });
        });
    }

    // Delegated tap handler: on mobile, the whole row opens the Detail modal.
    // Inline links (Edit Schedule, Status, Patient Card) are disabled via
    // pointer-events:none in appointment-mobile.css, so every tap lands here.
    // Call viewDetail() directly with the extracted URL rather than
    // dispatching a click on the inner anchor — dispatching would re-bubble
    // back into this delegated handler and recurse.
    $(document).on('click', '#kt_datatable .datatable-body .datatable-row', function (e) {
        if (window.innerWidth >= 768) {
            return;
        }
        var onclickAttr = $(this).find('.datatable-cell').first()
            .find('a[onclick*="viewDetail"]').first().attr('onclick');
        if (!onclickAttr) {
            return;
        }
        var match = onclickAttr.match(/viewDetail\(\s*[`'"]([^`'"]+)[`'"]\s*\)/);
        if (match && typeof viewDetail === 'function') {
            e.preventDefault();
            e.stopPropagation();
            viewDetail(match[1]);
        }
    });

    $(document).on('datatable-on-layout-updated', '#kt_datatable', function () {
        syncCellLabels();
    });

    $(function () {
        setTimeout(syncCellLabels, 500);
    });
})(jQuery);
