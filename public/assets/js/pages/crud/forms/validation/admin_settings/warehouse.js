
var AddUserValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_add_warehouse_form';
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
                    city_id: {
                        validators: {
                            notEmpty: {
                                message: 'The city field is required'
                            }
                        }
                    },
                    manager_name: {
                        validators: {
                            notEmpty: {
                                message: 'The manager name field is required'
                            }
                        }
                    },
                    manager_phone: {
                        validators: {
                            notEmpty: {
                                message: 'The manager phone field is required'
                            },
                            stringLength: {
                                min: 10,
                                max: 12,
                                message: 'The phone number must be between 10 and 12 characters'
                            },
                            regexp: {
                                regexp: /^\d+$/,
                                message: 'The phone number must contain only digits (0-9)'
                            }
                        }
                    },
                    google_map: {
                        validators: {
                            notEmpty: {
                                message: 'The google map field is required'
                            }
                        }
                    },
                    address: {
                        validators: {
                            notEmpty: {
                                message: 'The address field is required'
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

                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable('warehouse');
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        // public functions
        init: function() {
            AddValidation();
        }
    };
}();

var EditUserValidation = function () {
    // Private functions
    var EditValidation = function () {
        let modal_id = 'modal_edit_warehouse_form';
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
                    city_id: {
                        validators: {
                            notEmpty: {
                                message: 'The city field is required'
                            }
                        }
                    },
                    manager_name: {
                        validators: {
                            notEmpty: {
                                message: 'The manager name field is required'
                            }
                        }
                    },
                    manager_phone: {
                        validators: {
                            notEmpty: {
                                message: 'The manager phone field is required'
                            },
                            stringLength: {
                                min: 10,
                                max: 12,
                                message: 'The phone number must be between 10 and 12 characters'
                            },
                            regexp: {
                                regexp: /^\d+$/,
                                message: 'The phone number must contain only digits (0-9)'
                            }
                        }
                    },
                    google_map: {
                        validators: {
                            notEmpty: {
                                message: 'The google map field is required'
                            }
                        }
                    },
                    address: {
                        validators: {
                            notEmpty: {
                                message: 'The address field is required'
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

                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable('warehouse');
                } else {
                    toastr.error(response.message);
                }
            });
        });
    }

    return {
        // public functions
        init: function() {
            EditValidation();
        }
    };
}();

jQuery(document).ready(function() {
    AddUserValidation.init();
    EditUserValidation.init();
});
