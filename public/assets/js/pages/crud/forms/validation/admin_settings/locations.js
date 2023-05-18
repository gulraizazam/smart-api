
var Validation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_add_location_form';
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
                    fdo_name: {
                        validators: {
                            notEmpty: {
                                message: 'The fdo name field is required'
                            }
                        }
                    },
                    fdo_phone: {
                        validators: {
                            notEmpty: {
                                message: 'The fdo phone field is required'
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
                    address: {
                        validators: {
                            notEmpty: {
                                message: 'The address field is required'
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
                    "services[]": {
                        validators: {
                            notEmpty: {
                                message: 'The services field is required'
                            }
                        }
                    },
                    tax_percentage: {
                        validators: {
                            notEmpty: {
                                message: 'The tax percentage field is required'
                            }
                        }
                    },
                    ntn: {
                        validators: {
                            notEmpty: {
                                message: 'The ntn field is required'
                            }
                        }
                    },
                    stn: {
                        validators: {
                            notEmpty: {
                                message: 'The stn field is required'
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
            submitFileForm($(form).attr('action'), $(form).attr('method'), modal_id, function (response) {
                if (response.status == true) {
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
        init: function() {
            validation();
        }
    };
}();

var EditValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_edit_location_form';
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
                    fdo_name: {
                        validators: {
                            notEmpty: {
                                message: 'The fdo name field is required'
                            }
                        }
                    },
                    fdo_phone: {
                        validators: {
                            notEmpty: {
                                message: 'The fdo phone field is required'
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
                    address: {
                        validators: {
                            notEmpty: {
                                message: 'The address field is required'
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
                    "services[]": {
                        validators: {
                            notEmpty: {
                                message: 'The services field is required'
                            }
                        }
                    },
                    tax_percentage: {
                        validators: {
                            notEmpty: {
                                message: 'The tax percentage field is required'
                            }
                        }
                    },
                    ntn: {
                        validators: {
                            notEmpty: {
                                message: 'The ntn field is required'
                            }
                        }
                    },
                    stn: {
                        validators: {
                            notEmpty: {
                                message: 'The stn field is required'
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
            submitFileForm($(form).attr('action'), $(form).attr('method'), modal_id, function (response) {
                if (response.status == true) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable('centre');
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

jQuery(document).ready(function() {
    Validation.init();
    EditValidation.init();
});
