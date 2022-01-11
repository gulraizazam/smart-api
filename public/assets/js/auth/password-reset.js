"use strict";
var KTPasswordResetGeneral = (function () {
    var t, e, i;
    return {
        init: function () {
            (t = document.querySelector("#kt_password_reset_form")),
                (e = document.querySelector("#kt_password_reset_submit")),
                (i = FormValidation.formValidation(t, {
                    fields: { email: { validators: { notEmpty: { message: "Email address is required" }, emailAddress: { message: "The value is not a valid email address" } } } },
                    plugins: { trigger: new FormValidation.plugins.Trigger(), bootstrap: new FormValidation.plugins.Bootstrap5({ rowSelector: ".fv-row", eleInvalidClass: "", eleValidClass: "" }) },
                })),
                e.addEventListener("click", function (o) {
                    o.preventDefault(),
                        i.validate().then(function (i) {
                           if ("Valid" == i) {
                               t.submit();
                           }

                        });
                });
        },
    };
})();
KTUtil.onDOMContentLoaded(function () {
    KTPasswordResetGeneral.init();
});
