
var ConvertValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_convert_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    city_id: {
                        validators: {
                            notEmpty: {
                                message: 'The city field is required'
                            }
                        }
                    },
                    location_id: {
                        validators: {
                            notEmpty: {
                                message: 'The location field is required'
                            }
                        }
                    },
                    doctor_id: {
                        validators: {
                            notEmpty: {
                                message: 'The doctor field is required'
                            }
                        }
                    },
                    consultancy_type_id: {
                        validators: {
                            notEmpty: {
                                message: 'The consultancy type field is required'
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
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {

                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    //reInitTable();
                
                    window.location.href =  route('admin.appointments.index', {
                        tab: response.data.appointment_type,
                        city_id: response.data.city_id,
                        location_id: response.data.location_id,
                        doctor_id: response.data.doctor_id,
                    });
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

var AddValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_add_leads_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    service_id: {
                        validators: {
                            notEmpty: {
                                message: 'The service field is required'
                            }
                        }
                    },
                    phone: {
                        validators: {
                            notEmpty: {
                                message: 'The phone field is required'
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

                    city_id: {
                        validators: {
                            notEmpty: {
                                message: 'The city field is required'
                            }
                        }
                    },
                    location_id: {
                        validators: {
                            notEmpty: {
                                message: 'The centre field is required'
                            }
                        }
                    },
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'The name field is required'
                            }
                        }
                    },
                    gender: {
                        validators: {
                            notEmpty: {
                                message: 'The gender field is required'
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
                    $('select[name="child_service_id"]').empty();
                    $(".msg_new_lead").hide();
                    $('select[name="location_id"]').empty();
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
        init: function() {
            AddValidation();
        }
    };
}();

var EditValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_edit_leads_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    phone: {
                        validators: {
                            notEmpty: {
                                message: 'The phone field is required'
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

                    city_id: {
                        validators: {
                            notEmpty: {
                                message: 'The city field is required'
                            }
                        }
                    },
                    location_id: {
                        validators: {
                            notEmpty: {
                                message: 'The centre field is required'
                            }
                        }
                    },
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'The name field is required'
                            }
                        }
                    },
                    gender: {
                        validators: {
                            notEmpty: {
                                message: 'The gender field is required'
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
                    reInitTable('lead');
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

jQuery(document).ready(function() {
    ConvertValidation.init();
    AddValidation.init();
    EditValidation.init();
});
