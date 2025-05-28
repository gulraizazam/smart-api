
var FeedbackValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_add_feedback_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    treatment: {
                        validators: {
                            notEmpty: {
                                message: 'The treatment field is required'
                            }
                        }
                    },
                    rating: {
                        validators: {
                            notEmpty: {
                                message: 'The rating field is required'
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
var EditFeedbackValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_edit_feedbacks_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    rating: {
                        validators: {
                            notEmpty: {
                                message: 'The rating field is required'
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
jQuery(document).ready(function() {
    FeedbackValidation.init();
    EditFeedbackValidation.init();
});
