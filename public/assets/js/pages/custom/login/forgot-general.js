
    
    var _handleForgotForm = function(e) {
       
        var validation;
        // Init form validation rules. For more info check the FormValidation plugin's official documentation:https://formvalidation.io/
       

        // Handle submit button
        $('#kt_login_forgot_submit').on('click', function (e) {
            validation = FormValidation.formValidation(
                KTUtil.getById('kt_login_forgot_form'),
                {
                    fields: {
                        email: {
                            validators: {
                                notEmpty: {
                                    message: 'Email address is required'
                                },
                                emailAddress: {
                                    message: 'The value is not a valid email address'
                                }
                            }
                        }
                    },
                    plugins: {
                        trigger: new FormValidation.plugins.Trigger(),
                        submitButton: new FormValidation.plugins.SubmitButton(),
                        bootstrap: new FormValidation.plugins.Bootstrap()
                    }
                }
            );
            e.preventDefault();
            validation.validate().then(function(status) {
		        if (status == 'Valid') {
                    // Submit form
                    $('#kt_login_forgot_form').submit();
				}
		    });
        });
    }

// Class Initialization
jQuery(document).ready(function() {
    _handleForgotForm();
});
