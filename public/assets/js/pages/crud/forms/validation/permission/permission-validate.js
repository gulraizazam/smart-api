
var UserValidation = function () {
    // Private functions
    var permissionValidation = function () {
        let form_id = 'permissions-form';
        let form = document.getElementById(form_id);
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
                    commission: {
                        validators: {
                            notEmpty: {
                                message: 'The commission field is required'
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
            autoFocusFields(validate);
        });
        validate.on('core.form.valid', function(event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status == true) {
                   window.location.href = route('admin.roles.index')
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        // public functions
        init: function() {
            permissionValidation();
        }
    };
}();

jQuery(document).ready(function() {
    UserValidation.init();
});
