// Class definition
var KTFormControls = function () {
    // Private functions
    var _initDemo1 = function () {
        FormValidation.formValidation(
            document.getElementById('permissions-form'),
            {
                fields: {
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'Email is required'
                            },
                        }
                    },

                    commission: {
                        validators: {
                            notEmpty: {
                                message: 'Website URL is required'
                            },
                        }
                    },

                    digits: {
                        validators: {
                            notEmpty: {
                                message: 'Digits is required'
                            },
                            digits: {
                                message: 'The velue is not a valid digits'
                            }
                        }
                    },

                    creditcard: {
                        validators: {
                            notEmpty: {
                                message: 'Credit card number is required'
                            },
                            creditCard: {
                                message: 'The credit card number is not valid'
                            }
                        }
                    },

                    phone: {
                        validators: {
                            notEmpty: {
                                message: 'US phone number is required'
                            },
                            phone: {
                                country: 'US',
                                message: 'The value is not a valid US phone number'
                            }
                        }
                    },

                    option: {
                        validators: {
                            notEmpty: {
                                message: 'Please select an option'
                            }
                        }
                    },

                    options: {
                        validators: {
                            choice: {
                                min:2,
                                max:5,
                                message: 'Please select at least 2 and maximum 5 options'
                            }
                        }
                    },

                    memo: {
                        validators: {
                            notEmpty: {
                                message: 'Please enter memo text'
                            },
                            stringLength: {
                                min:50,
                                max:100,
                                message: 'Please enter a menu within text length range 50 and 100'
                            }
                        }
                    },

                    checkbox: {
                        validators: {
                            choice: {
                                min:1,
                                message: 'Please kindly check this'
                            }
                        }
                    },

                    checkboxes: {
                        validators: {
                            choice: {
                                min:2,
                                max:5,
                                message: 'Please check at least 1 and maximum 2 options'
                            }
                        }
                    },

                    radios: {
                        validators: {
                            choice: {
                                min:1,
                                message: 'Please kindly check this'
                            }
                        }
                    },
                },

                plugins: { //Learn more: https://formvalidation.io/guide/plugins
                    trigger: new FormValidation.plugins.Trigger(),
                    // Bootstrap Framework Integration
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                    // Validate fields when clicking the Submit button
                    submitButton: new FormValidation.plugins.SubmitButton(),
                    // Submit the form when all fields are valid
                    defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
                }
            }
        );
    }

    return {
        // public functions
        init: function() {
            _initDemo1();
        }
    };
}();

jQuery(document).ready(function() {
    KTFormControls.init();
});
