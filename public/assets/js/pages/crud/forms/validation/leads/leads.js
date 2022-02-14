
var AddUserValidation = function () {
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
                   /* patient_id: {
                        validators: {
                            notEmpty: {
                                message: 'The patient field is required'
                            }
                        }
                    },*/
                    phone: {
                        validators: {
                            notEmpty: {
                                message: 'The phone field is required'
                            }
                        }
                    },
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'The full name field is required'
                            }
                        }
                    },
                    gender: {
                        validators: {
                            notEmpty: {
                                message: 'The gander field is required'
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

var EditUserValidation = function () {
    // Private functions
    var EditValidation = function () {
        let modal_id = 'modal_edit_regions_form';
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
                    reInitTable();
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
