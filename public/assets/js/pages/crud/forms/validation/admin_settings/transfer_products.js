
var AddUserValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_add_transfer_products_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    product_id: {
                        validators: {
                            notEmpty: {
                                message: 'The Product id field is required'
                            }
                        }
                    },
                    quantity: {
                        validators: {
                            notEmpty: {
                                message: 'The Quantity field is required'
                            }
                        }
                    },
                    product_type_option_from: {
                        validators: {
                            notEmpty: {
                                message: 'The Product Type Option field is required'
                            }
                        }
                    },
                    product_type_option_to: {
                        validators: {
                            notEmpty: {
                                message: 'The Product Type Option field is required'
                            }
                        }
                    },
                    transfer_date: {
                        validators: {
                            notEmpty: {
                                message: 'The Transfer Date field is required'
                            }
                        }
                    },
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

var EditUserValidation = function () {
    // Private functions
    var EditValidation = function () {
        let modal_id = 'modal_edit_transfer_products_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    product_id: {
                        validators: {
                            notEmpty: {
                                message: 'The Product id field is required'
                            }
                        }
                    },
                    quantity: {
                        validators: {
                            notEmpty: {
                                message: 'The Quantity field is required'
                            }
                        }
                    },
                    product_type_option_from: {
                        validators: {
                            notEmpty: {
                                message: 'The Product Type Option field is required'
                            }
                        }
                    },
                    product_type_option_to: {
                        validators: {
                            notEmpty: {
                                message: 'The Product Type Option field is required'
                            }
                        }
                    },
                    transfer_date: {
                        validators: {
                            notEmpty: {
                                message: 'The Transfer Date field is required'
                            }
                        }
                    },
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
    AddUserValidation.init();
    EditUserValidation.init();
});
