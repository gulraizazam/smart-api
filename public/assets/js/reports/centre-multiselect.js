/**
 * Centre multi-select helper.
 *
 * Wires mutex behaviour for any <select multiple> that contains an
 * <option value="all"> sentinel:
 *   - Selecting "All Centres" clears any specific centre selections.
 *   - Selecting a specific centre clears the "All Centres" sentinel.
 *
 * The backend interprets a submitted "all" (or an empty submission) as
 * "fall back to the user's permitted centres" via ACL::getUserCentres().
 */
(function ($) {
    'use strict';

    function wire($select) {
        if ($select.data('centreMultiselectWired')) {
            return;
        }
        $select.data('centreMultiselectWired', true);

        $select.on('select2:select', function (e) {
            var selectedId = String(e.params.data.id);
            var $el = $(this);

            if (selectedId === 'all') {
                // User just picked "All" — keep only "all" selected.
                $el.val(['all']).trigger('change.select2');
            } else {
                // User picked a specific centre — drop the "all" sentinel.
                var vals = ($el.val() || []).filter(function (v) {
                    return String(v) !== 'all';
                });
                $el.val(vals).trigger('change.select2');
            }
        });
    }

    $(function () {
        $('select[multiple]').each(function () {
            var $sel = $(this);
            if ($sel.find('option[value="all"]').length) {
                wire($sel);
            }
        });
    });
})(jQuery);
