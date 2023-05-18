
var AddTownValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_add_towns_form';
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
                    }
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

var EditTownValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_edit_towns_form';
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
                    }
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
                    reInitTable('town');
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
    AddTownValidation.init();
    EditTownValidation.init();
});
