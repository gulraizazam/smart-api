
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

        // Handle submit manually to avoid submitButton plugin blocking re-submission
        $(form).on('submit', function(e) {
            e.preventDefault();

            // Manual validation
            let location_id = $("#locations").val();
            let service_ids = $("#services").val();
            let allocation_type = $("#allocation_type").val();
            let allocation_amount = $("#allocation_amount").val();

            let errors = [];
            if (!location_id) errors.push('The centre field is required');
            if (!service_ids || service_ids.length === 0) errors.push('The service field is required');
            if (!allocation_type) errors.push('The type field is required');
            if (!allocation_amount) errors.push('The amount field is required');

            if (errors.length > 0) {
                toastr.error(errors[0]);
                select2Validation();
                return;
            }

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
    let location_id = $("#locations").val();
    let service_ids = $("#services").val(); // Now returns array for multiselect

    // Get allocation-level fields (required in new approach)
    let allocation_type = $("#allocation_type").val();
    let allocation_amount = $("#allocation_amount").val();
    let allocation_slug = $("#allocation_slug").val();

    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/discounts/saveDervice',
        type: "POST",
        data: {
            voucher_id: $("#discount_id").val(), 
            discount_id: $("#discount_id").val(),
            location_id: location_id,
            service_ids: service_ids,
            allocation_type: allocation_type,
            allocation_amount: allocation_amount,
            allocation_slug: allocation_slug
        },
        cache: false,
        timeout: 30000,
        beforeSend: function() {
            console.log('Request sending...');
        },
        success: function (response) {
           
            if (response.status == true) {
                var data = response.data;
                
                // Remove any individual services that were replaced by "All Services"
                if (data.removed_ids && data.removed_ids.length > 0) {
                    data.removed_ids.forEach(function(id) {
                        $('.HR_' + id).remove();
                    });
                }
                
                // Handle multiple records (multiselect services) - display as grouped row
                if (data.records && data.records.length > 0) {
                    let ids = data.records.map(r => r.id);
                    let serviceNames = data.records.map(r => r.service_name).join(', ');
                    let firstRecord = data.records[0];
                    $('#allocate_services').append(serviceLocationGrouped(
                        ids, 
                        firstRecord.location_name, 
                        serviceNames,
                        firstRecord.type || '-',
                        firstRecord.amount || '-',
                        firstRecord.slug || 'default'
                    ));
                }
                
                // Reset allocation form fields after successful add
                $("#services").val(null).trigger('change');
                $("#allocation_type").val('').trigger('change');
                $("#allocation_amount").val('');
                $("#allocation_slug").val('default').trigger('change');
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
