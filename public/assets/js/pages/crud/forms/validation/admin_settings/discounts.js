
var AddValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_add_discounts_form';
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
                    type: {
                        validators: {
                            notEmpty: {
                                message: 'The amount type field is required'
                            }
                        }
                    },
                    // amount: {
                    //     validators: {
                    //         notEmpty: {
                    //             message: 'The amount field is required'
                    //         }
                    //     }
                    // },

                    start: {
                        validators: {
                            notEmpty: {
                                message: 'The start field is required'
                            }
                        }
                    },

                    end: {
                        validators: {
                            notEmpty: {
                                message: 'The end field is required'
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

var EditValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_edit_discounts_form';
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
                    type: {
                        validators: {
                            notEmpty: {
                                message: 'The amount type field is required'
                            }
                        }
                    },
                    amount: {
                        validators: {
                            notEmpty: {
                                message: 'The amount field is required'
                            }
                        }
                    },

                    start: {
                        validators: {
                            notEmpty: {
                                message: 'The start field is required'
                            }
                        }
                    },

                    end: {
                        validators: {
                            notEmpty: {
                                message: 'The end field is required'
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
                    reInitTable('discount');
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

var AllocateValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_allocate_discounts_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    location_id: {
                        validators: {
                            notEmpty: {
                                message: 'The centre field is required'
                            }
                        }
                    },
                    service_id: {
                        validators: {
                            notEmpty: {
                                message: 'The service field is required'
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
            submitData(function (response) {
                if (response.status == true) {
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
            });
        });
    }

    return {
        init: function() {
            validation();
        }
    };
}();

jQuery(document).ready(function() {
    AddValidation.init();
    EditValidation.init();
    AllocateValidation.init();
});

function submitData(callback) {

    let ids = [];
    ids.push($("#locations").val());
    ids.push($("#services").val());

    console.log('=== DISCOUNT ALLOCATE DEBUG ===');
    console.log('Location ID:', $("#locations").val());
    console.log('Service ID:', $("#services").val());
    console.log('Discount ID:', $("#discount_id").val());
    console.log('Combined IDs:', ids.join(','));
    console.log('URL:', '/api/saveDervice');
    console.log('Data:', {voucher_id: $("#discount_id").val(), id: ids.join(',')});

    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/saveDervice',
        type: "POST",
        data: {voucher_id: $("#discount_id").val(), id: ids.join(',')},
        cache: false,
        timeout: 30000,
        beforeSend: function() {
            console.log('Request sending...');
        },
        success: function (response) {
            console.log('Success Response:', response);
            if (response.status == true) {
                var data = response.data;
                $('#allocate_services').append(serviceLocation(data.record.id, data.record_locaiton_name, data.record_service_name));
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm();
            } else {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm();
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('=== AJAX ERROR DETAILS ===');
            console.error('Status:', xhr.status);
            console.error('Status Text:', xhr.statusText);
            console.error('Thrown Error:', thrownError);
            console.error('Response Text:', xhr.responseText);
            console.error('Ready State:', xhr.readyState);
            console.error('Full XHR:', xhr);

            let errorMsg = 'Unknown error';
            if (xhr.status == '401') {
                errorMsg = 'You are not authorized to access this resource';
            } else if (thrownError === 'timeout') {
                errorMsg = 'Request timeout. Please check your connection and try again.';
            } else if (xhr.status == 0) {
                errorMsg = 'Network error. Please check your internet connection or CORS settings.';
            } else if (xhr.status == 500) {
                errorMsg = 'Server error (500). Check server logs.';
            } else if (xhr.status == 404) {
                errorMsg = 'Route not found (404). URL: /api/saveDervice';
            } else {
                errorMsg = 'Error ' + xhr.status + ': ' + thrownError;
            }

            callback({
                'status': 0,
                'message': errorMsg,
            });
            hideSpinnerRestForm();
        },
        complete: function() {
            console.log('Request completed');
        }
    });
}
