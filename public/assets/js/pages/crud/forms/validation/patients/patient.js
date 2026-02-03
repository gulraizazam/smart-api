
var AddValidation = function () {
    // Private functions
    var AddPatientValidation = function () {
        let modal_id = 'modal_add_patients_form';
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
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        // public functions
        init: function() {
            AddPatientValidation();
        }
    };
}();

var EditValidation = function () {
    // Private functions
    var Validation = function () {
        let modal_id = 'modal_edit_patients_form';
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
                    reInitTable('patient');
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        // public functions
        init: function() {
            Validation();
        }
    };
}();
var AssignMembershipValidation = function () {
    // Private functions
    var AssignValidation = function () {
        let modal_id = 'modal_edit_memberships_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    membership_code: {
                        validators: {
                            notEmpty: {
                                message: 'The Code field is required'
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
                    reInitTable('patient');
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        // public functions
        init: function() {
            AssignValidation();
        }
    };
}();
var AssignVoucherValidation = function () {
    // Private functions
    var AssignValidation = function () {
        let modal_id = 'modal_edit_vouchers_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    voucher_id: {
                        validators: {
                            notEmpty: {
                                message: 'The Voucher field is required'
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
                    
                    // Check if we're on patient detail page or main patients page
                    if (typeof patientCardID !== 'undefined' && patientCardID) {
                        // Refresh voucher datatable on patient detail page
                        if (typeof patientDatatable !== 'undefined' && patientDatatable['.voucher-form']) {
                            patientDatatable['.voucher-form'].search({datatable_reload: 'reload'}, 'search');
                        }
                    } else {
                        // Refresh main patients datatable
                        reInitTable('patient');
                    }
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        // public functions
        init: function() {
            AssignValidation();
        }
    };
}();
jQuery(document).ready(function() {
    AddValidation.init();
    EditValidation.init();
    AssignMembershipValidation.init();
    AssignVoucherValidation.init();
});
