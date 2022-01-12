// Class definition
var KTFormControls = function () {
	// Private functions
	var profileValidation = function () {
        let form = document.getElementById('kt_form_1');
		let validate = FormValidation.formValidation(
            form,
			{
				fields: {
                    current_password: {
						validators: {
							notEmpty: {
								message: 'Password is required'
							}
						}
					},
                    new_password: {
                        validators: {
                            notEmpty: {
                                message: 'New Password is required'
                            }
                        }
                    },
                    new_password_confirmation: {
                        validators: {
                            notEmpty: {
                                message: 'Confirm password is required'
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
            		// Submit the form when all fields are valid
            		//defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
				}
			}
		);
        validate.on('core.form.valid', function(event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status == true) {
                    toastr.success(response.message);
                   /* setTimeout(function () {
                        window.location = route('admin.home');
                    }, 500);*/
                    $(".profile-message").addClass("d-none");
                    $(form)[0].reset();
                } else {
                    $(".profile-message").removeClass("d-none");
                    $(".message-body").text(response.message);
                }
            });
        });
	}

	return {
		// public functions
		init: function() {
            profileValidation();
		}
	};
}();

jQuery(document).ready(function() {
	KTFormControls.init();
});


function submitForm(action, method, data, callback) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: action,
        type: method,
        data: data,
        cache: false,
        success: function (response) {
            if (response.status == true) {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
            } else {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status == '401') {
                callback({
                    'status': 0,
                    'message': 'You are not authorized to access this resource',
                });
            } else {
                callback({
                    'status': 0,
                    'message': 'Unable to process your request, please try again later.',
                });
            }
        }
    });
}
