"use strict";

var KTSigninGeneral = (function () {
    var t, e, i;
    return {
        init: function () {
            (t = document.querySelector("#kt_sign_in_form")),
                (e = document.querySelector("#kt_sign_in_submit")),
                (i = FormValidation.formValidation(t, {
                    fields: {
                        email: { validators: { notEmpty: { message: "Email address is required" }, emailAddress: { message: "The value is not a valid email address" } } },
                        password: { validators: { notEmpty: { message: "The password is required" } } },
                    },
                    plugins: { trigger: new FormValidation.plugins.Trigger(), bootstrap: new FormValidation.plugins.Bootstrap5({ rowSelector: ".fv-row" }) },
                })),
                e.addEventListener("click", function (n) {
                    n.preventDefault(),
                        i.validate().then(function (i) {
                            if (i == 'Valid') {
                                t.submit();
                            }
                        });
                });
        },
    };
})();

KTUtil.onDOMContentLoaded(function () {
    KTSigninGeneral.init();
});
