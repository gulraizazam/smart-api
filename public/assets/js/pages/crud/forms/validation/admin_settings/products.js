
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
                    sku: {
                        validators: {
                            notEmpty: {
                                message: 'The sku field is required'
                            }
                        }
                    },
                    // purchase_price: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Purchase field is required'
                    //         }
                    //     }
                    // },
                    // quantity: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Quantity field is required'
                    //         }
                    //     }
                    // },
                    // total_purchase_price: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Total Purchase field is required'
                    //         }
                    //     }
                    // },
                    // product_type: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Product Type field is required'
                    //         }
                    //     }
                    // },
                    // warehouse_id:{
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Warehouse field is required'
                    //         }
                    //     }
                    // }
                   
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
                    sku: {
                        validators: {
                            notEmpty: {
                                message: 'The sku field is required'
                            }
                        }
                    },
                    // purchase_price: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Purchase field is required'
                    //         }
                    //     }
                    // },
                    // quantity: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Quantity field is required'
                    //         }
                    //     }
                    // },
                    // total_purchase_price: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Total Purchase field is required'
                    //         }
                    //     }
                    // },
                    // product_type: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Product Type field is required'
                    //         }
                    //     }
                    // },
                    // product_type_option: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The Product Type Option field is required'
                    //         }
                    //     }
                    // },
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
var AllocateValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_allocate_products_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    location_id: {
                        validators: {
                            notEmpty: {
                                message: 'The centre field is required'
                            }
                        }
                    },
                    service_id: {
                        validators: {
                            notEmpty: {
                                message: 'The service field is required'
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
            submitData(function (response) {
                if (response.status == true) {
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
            });
        });
    }

    return {
        init: function() {
            validation();
        }
    };
}();
jQuery(document).ready(function () {
    AddUserValidation.init();
    EditUserValidation.init();
    UpdateSalePriceValidation.init();
    AddStockValidation.init();
    TransferProductValidation.init();
    AllocateValidation.init();
});
function submitData(callback) {
    
    var location_id = $("#locations").val();
    var quantity =$("#quantity").val();
    var sale_price = $("#allocate_sale_price").val();
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.products.save_allocate'),
        type: "POST",
        data: {product_id: $("#product_id").val(), location_id:location_id, quantity:quantity, sale_price:sale_price},
        cache: false,
        success: function (response) {
            if (response.status == true) {
                var data = response.data;
              
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                $("form").trigger("reset");
                hideSpinnerRestForm();
            } else {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm();
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status == '401') {
                callback({
                    'status': 0,
                    'message': 'You are not authorized to access this resource',
                });
                hideSpinnerRestForm();
            } else {
                callback({
                    'status': 0,
                    'message': 'Unable to process your request, please try again later.',
                });
                hideSpinnerRestForm();
            }
        }
    });
}