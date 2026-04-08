"use strict";
// Class definition

var KTSummernoteDemo = function () {
    // Private functions
    var demos = function () {
        if ($('.summernote').length) {
            $('.summernote').summernote({
                height: 400,
                tabsize: 2
            });
        }
    }

    return {
        // public functions
        init: function() {
            demos();
        }
    };
}();

// Initialization
jQuery(document).ready(function() {
    KTSummernoteDemo.init();
});
