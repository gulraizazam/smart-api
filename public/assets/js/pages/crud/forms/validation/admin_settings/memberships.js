

var AddValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_add_memberships_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    code: {
                        validators: {
                            notEmpty: {
                                message: 'The code field is required'
                            }
                        }
                    },
                    membership_type_id: {
                        validators: {
                            notEmpty: {
                                message: 'The membership type field is required'
                            },
                           
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

var EditValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_edit_memberships_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    code: {
                        validators: {
                            notEmpty: {
                                message: 'The code field is required'
                            }
                        }
                    },
                    membership_type_id: {
                        validators: {
                            notEmpty: {
                                message: 'The membership type field is required'
                            },
                           
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

jQuery(document).ready(function() {
   
    AddValidation.init();
    EditValidation.init();
});
