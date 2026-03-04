var AddUserValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_bundles_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'The name field is required'
                            }
                        }
                    },
                    price: {
                        validators: {
                            notEmpty: {
                                message: 'The price field is required'
                            }
                        }
                    },
                    start: {
                        validators: {
                            notEmpty: {
                                message: 'The valid from field is required'
                            }
                        }
                    },
                    end: {
                        validators: {
                            notEmpty: {
                                message: 'The valid to field is required'
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
            // select2Validation();
        });
        validate.on('core.form.valid', function (event) {
            let ok = checkOfferPrice();
            if (!ok) {
                toastr.error('Offered price cant be greater than service price.');
                return false;
            }
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable('bundles');
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
jQuery(document).ready(function () {
    AddUserValidation.init();
});

function checkOfferPrice() {

    if (parseFloat($("#bundles_price").val()) <=  parseFloat($("#service_price").val())) {
        return true;
    }

    return false;
}
