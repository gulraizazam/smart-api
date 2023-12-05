
var RefundValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_edit_refunds_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    plan: {
                        validators: {
                            notEmpty: {
                                message: 'The plan field is required'
                            }
                        }
                    },
                    refund_amount: {
                        validators: {
                            notEmpty: {
                                message: 'The refund amount field is required'
                            }
                        }
                    },
                    payment_mode_id: {
                        validators: {
                            notEmpty: {
                                message: 'The payment mode field is required'
                            }
                        }
                    },
                    refund_note: {
                        validators: {
                            notEmpty: {
                                message: 'The refund note field is required'
                            }
                        }
                    },
                    
                    created_at: {
                        validators: {
                            notEmpty: {
                                message: 'The date field is required'
                            }
                        }
                    },
                    refund_amount: {
                        validators: {
                            notEmpty: {
                                message: 'The Amount field is required'
                            }
                        }
                    },
                    payment_mode_id: {
                        validators: {
                            notEmpty: {
                                message: 'The payment mode field is required'
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
        validate.on('core.form.valid', function(event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status == true) {
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
        init: function() {
            validation();
        }
    };
}();
var EditRefundValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'edit_refunds_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    refund_note: {
                        validators: {
                            notEmpty: {
                                message: 'The refund note field is required'
                            }
                        }
                    },
                    created_at: {
                        validators: {
                            notEmpty: {
                                message: 'The date field is required'
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
        validate.on('core.form.valid', function(event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status == true) {
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
        init: function() {
            validation();
        }
    };
}();
jQuery(document).ready(function() {
    RefundValidation.init();
    EditRefundValidation.init();
});
