
var SettingsValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_settings_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    data: {
                        validators: {
                            notEmpty: {
                                message: 'The data field is required'
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
            // validation();
        }
    };
}();

jQuery(document).ready(function() {
    SettingsValidation.init();
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
