
var AddUserValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_add_user_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    email: {
                        validators: {
                            notEmpty: {
                                message: 'The email field is required'
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
                    gender: {
                        validators: {
                            notEmpty: {
                                message: 'The gender field is required'
                            }
                        }
                    },
                    password: {
                        validators: {
                            notEmpty: {
                                message: 'The password field is required'
                            }
                        }
                    },
                    'roles[]': {
                        validators: {
                            notEmpty: {
                                message: 'The roles field is required'
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
                    'centers[]': {
                        validators: {
                            notEmpty: {
                                message: 'The centre field is required'
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
                    // Display validation errors
                    if (response.errors) {
                        // Clear previous errors
                        $('.is-invalid').removeClass('is-invalid');
                        $('.fv-help-block').remove();
                        
                        // Display each field error
                        Object.keys(response.errors).forEach(function(field) {
                            let fieldName = field.replace('[]', '');
                            let $field = $('#add_user_' + fieldName);
                            
                            if ($field.length) {
                                $field.addClass('is-invalid');
                                
                                // Add error message after the field
                                let errorHtml = '<div class="fv-help-block">';
                                response.errors[field].forEach(function(msg) {
                                    errorHtml += msg + '<br>';
                                });
                                errorHtml += '</div>';
                                
                                if (fieldName === 'password') {
                                    $("#validate-msg").html('<div class="fv-help-block pass-msg">' + response.errors[field].join('<br>') + '</div>');
                                } else {
                                    $field.after(errorHtml);
                                }
                            }
                        });
                    }
                    
                    // Show error message in toastr
                    if (response.message) {
                        toastr.error(response.message);
                    }
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
        let modal_id = 'modal_edit_user_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    email: {
                        validators: {
                            notEmpty: {
                                message: 'The email field is required'
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
                    gender: {
                        validators: {
                            notEmpty: {
                                message: 'The gender field is required'
                            }
                        }
                    },
                    password: {
                        validators: {
                            notEmpty: {
                                message: 'The password field is required'
                            }
                        }
                    },
                    'roles[]': {
                        validators: {
                            notEmpty: {
                                message: 'The roles field is required'
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
                    'centers[]': {
                        validators: {
                            notEmpty: {
                                message: 'The centre field is required'
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
                    reInitTable('user');
                } else {
                    // Display validation errors
                    if (response.errors) {
                        // Clear previous errors
                        $('.is-invalid').removeClass('is-invalid');
                        $('.fv-help-block').remove();
                        
                        // Display each field error
                        Object.keys(response.errors).forEach(function(field) {
                            let fieldName = field.replace('[]', '');
                            let $field = $('#edit_user_' + fieldName);
                            
                            if ($field.length) {
                                $field.addClass('is-invalid');
                                
                                // Add error message after the field
                                let errorHtml = '<div class="fv-help-block">';
                                response.errors[field].forEach(function(msg) {
                                    errorHtml += msg + '<br>';
                                });
                                errorHtml += '</div>';
                                
                                $field.after(errorHtml);
                            }
                        });
                    }
                    
                    // Show error message in toastr
                    if (response.message) {
                        toastr.error(response.message);
                    }
                }
            }, form);
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
