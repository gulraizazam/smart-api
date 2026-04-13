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
                                message: 'The package price field is required'
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
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                    submitButton: new FormValidation.plugins.SubmitButton(),
                }
            }
        );
        validate.on('core.form.invalid', function (e) {
        });
        validate.on('core.form.valid', function (event) {
            if (!checkServicesExist()) {
                toastr.error('Please add at least one service to the package.');
                return false;
            }
            if (!checkOfferPrice()) {
                toastr.error('Package price cannot be greater than regular price.');
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
        init: function () {
            AddValidation();
        }
    };
}();
jQuery(document).ready(function () {
    AddUserValidation.init();
});

function checkServicesExist() {
    return $("#service_body tr").length > 0;
}

function checkOfferPrice() {
    var packagePrice = parseFloat($("#bundles_price").val()) || 0;
    var regularPrice = parseFloat($("#service_price").val()) || 0;
    return packagePrice <= regularPrice;
}
