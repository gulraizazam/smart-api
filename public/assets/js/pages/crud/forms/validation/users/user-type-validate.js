/**
 * User Types Form Validation - Optimized
 * Handles validation for create and edit forms using API endpoints
 */

var UserAddTypeValidation = function () {
    var validation = function () {
        let modal_id = 'user_type_add_form';
        let form = document.getElementById(modal_id);
        
        if (!form) return;

        let validate = FormValidation.formValidation(form, {
            fields: {
                name: {
                    validators: {
                        notEmpty: {
                            message: 'The name field is required'
                        }
                    }
                },
                type: {
                    validators: {
                        notEmpty: {
                            message: 'The type field is required'
                        }
                    }
                }
            },
            plugins: {
                trigger: new FormValidation.plugins.Trigger(),
                bootstrap: new FormValidation.plugins.Bootstrap(),
                submitButton: new FormValidation.plugins.SubmitButton(),
            }
        });

        validate.on('core.form.invalid', function () {
            select2Validation();
        });

        validate.on('core.form.valid', function () {
            submitApiForm(form, modal_id, function () {
                reInitTable();
            });
        });
    };

    return {
        init: function () {
            validation();
        }
    };
}();

var UserTypeValidation = function () {
    var validation = function () {
        let modal_id = 'modal_user_type_form';
        let form = document.getElementById(modal_id);
        
        if (!form) return;

        let validate = FormValidation.formValidation(form, {
            fields: {
                name: {
                    validators: {
                        notEmpty: {
                            message: 'The name field is required'
                        }
                    }
                },
                type: {
                    validators: {
                        notEmpty: {
                            message: 'The type field is required'
                        }
                    }
                }
            },
            plugins: {
                trigger: new FormValidation.plugins.Trigger(),
                bootstrap: new FormValidation.plugins.Bootstrap(),
                submitButton: new FormValidation.plugins.SubmitButton(),
            }
        });

        validate.on('core.form.invalid', function () {
            select2Validation();
        });

        validate.on('core.form.valid', function () {
            submitApiForm(form, modal_id, function () {
                reInitTable('userType');
            });
        });
    };

    return {
        init: function () {
            validation();
        }
    };
}();

/**
 * Submit form to API endpoint
 */
function submitApiForm(form, modal_id, successCallback) {
    let $form = $(form);
    let action = $form.attr('action');
    let method = $form.data('method') || $form.attr('method') || 'POST';
    
    submitForm(action, method, $form.serialize(), function (response) {
        if (response.status === true) {
            toastr.success(response.message);
            closePopup(modal_id);
            if (typeof successCallback === 'function') {
                successCallback();
            }
        } else {
            toastr.error(response.message || 'An error occurred');
        }
    }, form);
}

jQuery(document).ready(function () {
    UserAddTypeValidation.init();
    UserTypeValidation.init();
});
