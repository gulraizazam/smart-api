
var AddOrderValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_create_order_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    location_id: {
                        validators: {
                            notEmpty: {
                                message: 'The Location field is required'
                            }
                        }
                    },
                    // patient_id: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Patient id field is required'
                    //         }
                    //     }
                    // },
                    // doctor_id: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Doctor field is required'
                    //         }
                    //     }
                    // },
                    payment_mode: {
                        validators: {
                            notEmpty: {
                                message: 'The Payment method field is required'
                            }
                        }
                    }
                },

                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    // Bootstrap Framework Integration
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                    // Validate fields when clicking the Submit button
                    submitButton: new FormValidation.plugins.SubmitButton(),
                }
            }
        );
         validate.on('core.form.invalid', function (e) {
            select2Validation();
        });
        validate.on('core.form.valid', function (event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status) {
                    toastr.success(response.message);

                    openInNewTab(response.data);
                    closePopup(modal_id);
                    $("#product_list").empty();
                    $("#product_discount").text('');
                    
                    reInitTable();
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        // public functions
        init: function () {
            AddValidation();
        }
    };
}();

var RefundOrderValidation = function () {
    // Private functions
    var EditValidation = function () {
        let modal_id = 'modal_order_refund_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    patient_id: {
                        validators: {
                            notEmpty: {
                                message: 'The Patient id field is required'
                            }
                        }
                    }
                },

                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    // Bootstrap Framework Integration
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                    // Validate fields when clicking the Submit button
                    submitButton: new FormValidation.plugins.SubmitButton(),
                }
            }
        );
        validate.on('core.form.invalid', function (e) {
            select2Validation();
        });
        validate.on('core.form.valid', function (event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable();
                } else {
                    toastr.error(response.message);
                    $("#modal_refund_order").modal("hide");
                }
            });
        });
    }

    return {
        // public functions
        init: function () {
            EditValidation();
        }
    };
}();

jQuery(document).ready(function () {
    AddOrderValidation.init();
    RefundOrderValidation.init();
});
