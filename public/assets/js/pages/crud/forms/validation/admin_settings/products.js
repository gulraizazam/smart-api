
var AddUserValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_add_products_form';
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
                    brand_id: {
                        validators: {
                            notEmpty: {
                                message: 'The Brand field is required'
                            }
                        }
                    },
                    sale_price: {
                        validators: {
                            notEmpty: {
                                message: 'The Sale Price field is required'
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
                    reInitTable('product');
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
        let modal_id = 'modal_edit_products_form';
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
                    brand_id: {
                        validators: {
                            notEmpty: {
                                message: 'The Brand field is required'
                            }
                        }
                    },
                    purchase_price: {
                        validators: {
                            notEmpty: {
                                message: 'The Purchase field is required'
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
                    total_purchase_price: {
                        validators: {
                            notEmpty: {
                                message: 'The Total Purchase field is required'
                            }
                        }
                    },
                    product_type: {
                        validators: {
                            notEmpty: {
                                message: 'The Product Type field is required'
                            }
                        }
                    },
                    product_type_option: {
                        validators: {
                            notEmpty: {
                                message: 'The Product Type Option field is required'
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
                    reInitTable('product');
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

var UpdateSalePriceValidation = function () {
    // Private functions
    var UpdateSaleValidation = function () {
        let modal_id = 'modal_edit_products_sale_price_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    sale_price: {
                        validators: {
                            notEmpty: {
                                message: 'The Sale price field is required'
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
            UpdateSaleValidation();
        }
    };
}();

var TransferProductValidation = function () {
    // Private functions
    var TransferValidation = function () {
        let modal_id = 'modal_transfer_products_form_submit';
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
            TransferValidation();
        }
    };
}();

var AddStockValidation = function () {
    // Private functions
    var stockValidation = function () {
        let modal_id = 'modal_add_product_stock_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    quantity: {
                        validators: {
                            notEmpty: {
                                message: 'The quantity field is required'
                            }
                        }
                    },
                    purchase_price: {
                        validators: {
                            notEmpty: {
                                message: 'The purchase price field is required'
                            }
                        }
                    },
                    total_purchase_price: {
                        validators: {
                            notEmpty: {
                                message: 'The total purchase price field is required'
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
            stockValidation();
        }
    };
}();

jQuery(document).ready(function () {
    AddUserValidation.init();
    EditUserValidation.init();
    UpdateSalePriceValidation.init();
    AddStockValidation.init();
    TransferProductValidation.init();
});