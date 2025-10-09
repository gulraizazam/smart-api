
var AddValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_add_vouchers_form';
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
        let modal_id = 'modal_edit_vouchers_form';
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
        let modal_id = 'modal_allocate_vouchers_form';
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

var AssignToPatientValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_assign_voucher_to_patient_form';
        let form = document.getElementById(modal_id);

        if (!form) {
            console.error('Form not found:', modal_id);
            return;
        }

        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    amount: {
                        validators: {
                            notEmpty: {
                                message: 'The amount field is required'
                            },
                            numeric: {
                                message: 'The amount must be a number'
                            },
                            greaterThan: {
                                min: 0,
                                message: 'The amount must be greater than 0'
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
            // Check if patient is selected
            if (!$('#assign_patient_id').val()) {
                toastr.error('Please select a patient');
                return false;
            }

            submitForm(route('admin.voucherTypes.assignToPatient'), 'POST', $(form).serialize(), function (response) {
                if (response.status == true) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    $('#modal_assign_voucher_to_patient').modal('hide');
                    $('#assign_voucher_type_name').val('');
                    $('#assign_voucher_id').val('');
                    $('#assign_patient_search').val('');
                    $('#assign_patient_id').val('');
                    $('#assign_amount').val('');
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
    AddValidation.init();
    EditValidation.init();
    AllocateValidation.init();

    // Initialize AssignToPatientValidation after a short delay to ensure modal is in DOM
    setTimeout(function() {
        AssignToPatientValidation.init();
    }, 500);
});

function submitData(callback) {

    let ids = [];
    ids.push($("#locations").val());
    ids.push($("#services").val());

    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.voucherTypes.save_Dervice'),
        type: "POST",
        data: {voucher_id: $("#voucher_id").val(), id: ids.join(',')},
        cache: false,
        success: function (response) {
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
            if (xhr.status == '401') {
                callback({
                    'status': 0,
                    'message': 'You are not authorized to access this resource',
                });
                hideSpinnerRestForm();
            } else {
                callback({
                    'status': 0,
                    'message': 'Unable to process your request, please try again later.',
                });
                hideSpinnerRestForm();
            }
        }
    });
}
