var statusValidate = {};

var UpdateStatusValidation = function () {

    var statusValidation = function () {
        let modal_id = 'modal_update_status_form';
        let form = document.getElementById(modal_id);
        statusValidate = FormValidation.formValidation(
            form,
            {
                fields: {
                    base_appointment_status_id: {
                        validators: {
                            notEmpty: {
                                message: 'The status field is required'
                            }
                        }
                    }
                },

                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                    submitButton: new FormValidation.plugins.SubmitButton(),
                }
            }
        );
        statusValidate.on('core.form.invalid', function (e) {
        });
        statusValidate.on('core.form.valid', function(event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {

                if (response.status) {

                    toastr.success(response.message);
                    closePopup(modal_id);
                    let query = get_query();

                    if(response.data.appontment_type_id==1){
                        var appointment = 'consultancy';
                    }else {
                        var appointment = 'treatment';
                    }
                    reInitTable(appointment);
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        init: function() {
            statusValidation();
        }
    };
}();

var EditAppointmentValidation = function () {
    // Private functions
    var Validation = function () {
        let modal_id = 'modal_edit_appointment_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    consultancy_type: {
                        validators: {
                            notEmpty: {
                                message: 'The consultancy type field is required'
                            }
                        }
                    },
                    city_id: {
                        validators: {
                            notEmpty: {
                                message: 'The city field is required'
                            }
                        }
                    },
                    location_id: {
                        validators: {
                            notEmpty: {
                                message: 'The location field is required'
                            }
                        }
                    },
                    doctor_id: {
                        validators: {
                            notEmpty: {
                                message: 'The doctor field is required'
                            }
                        }
                    },
                    scheduled_date: {
                        validators: {
                            notEmpty: {
                                message: 'The scheduled date field is required'
                            }
                        }
                    },
                    scheduled_time: {
                        validators: {
                            notEmpty: {
                                message: 'The scheduled time field is required'
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
                  reInitTable('consultancy');
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

var CreateConsultancytValidation = function () {
    // Private functions
    var Validation = function () {
        let modal_id = 'modal_create_consultancy_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    consultancy_type: {
                        validators: {
                            notEmpty: {
                                message: 'The consultancy type field is required'
                            }
                        }
                    },
                    service_id: {
                        validators: {
                            notEmpty: {
                                message: 'The consultancy field is required'
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
                            }
                        }
                    },
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'The patient name field is required'
                            }
                        }
                    },
                    gender: {
                        validators: {
                            notEmpty: {
                                message: 'The gender field is required'
                            }
                        }
                    }

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
                    reInitCalendar(start_date, calendar, ConsultancyCalendar);
                } else {
                    toastr.error(response.message);
                }
            }, null);
        });
    }

    return {
        // public functions
        init: function() {
            Validation();
        }
    };
}();

var CreateTreatmentValidation = function () {
    // Private functions
    var Validation = function () {
        let modal_id = 'modal_create_treatment_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    base_service_id: {
                        validators: {
                            notEmpty: {
                                message: 'The service field is required'
                            }
                        }
                    },
                    service_id: {
                        validators: {
                            notEmpty: {
                                message: 'The child service field is required'
                            }
                        }
                    },
                    patient_id: {
                        validators: {
                            notEmpty: {
                                message: 'The patient field is required'
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
                            }
                        }
                    },
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'The patient name field is required'
                            }
                        }
                    },
                    gender: {
                        validators: {
                            notEmpty: {
                                message: 'The gender field is required'
                            }
                        }
                    }

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
                    reInitCalendar(start_treatment_date, treatment_calendar, TreatmentCalendar);
                } else {
                    toastr.error(response.message);
                }
            }, null);
        });
    }

    return {
        // public functions
        init: function() {
            Validation();
        }
    };
}();

var EditTreatmentValidation = function () {
    // Private functions
    var Validation = function () {
        let modal_id = 'modal_edit_treatment_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    service_id: {
                        validators: {
                            notEmpty: {
                                message: 'The service type field is required'
                            }
                        }
                    },
                    city_id: {
                        validators: {
                            notEmpty: {
                                message: 'The city field is required'
                            }
                        }
                    },
                    location_id: {
                        validators: {
                            notEmpty: {
                                message: 'The location field is required'
                            }
                        }
                    },
                    machine_id: {
                        validators: {
                            notEmpty: {
                                message: 'The machine field is required'
                            }
                        }
                    },
                    scheduled_date: {
                        validators: {
                            notEmpty: {
                                message: 'The scheduled date field is required'
                            }
                        }
                    },
                    scheduled_time: {
                        validators: {
                            notEmpty: {
                                message: 'The scheduled time field is required'
                            }
                        }
                    },
                    phone: {
                        validators: {
                            notEmpty: {
                                message: 'The location field is required'
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
                    gender: {
                        validators: {
                            notEmpty: {
                                message: 'The gender field is required'
                            }
                        }
                    }

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
                    reInitTable('treatment');
                } else {
                    toastr.error(response.message);
                }
            }, null);
        });
    }

    return {
        // public functions
        init: function() {
            Validation();
        }
    };
}();

var AppointPlanValidation = function () {
    // Private functions
    var Validation = function () {
        let modal_id = 'modal_appoitment_add_plan_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    service_id: {
                        validators: {
                            notEmpty: {
                                message: 'The service type field is required'
                            }
                        }
                    },
                    city_id: {
                        validators: {
                            notEmpty: {
                                message: 'The city field is required'
                            }
                        }
                    },
                    location_id: {
                        validators: {
                            notEmpty: {
                                message: 'The location field is required'
                            }
                        }
                    },
                    machine_id: {
                        validators: {
                            notEmpty: {
                                message: 'The machine field is required'
                            }
                        }
                    },
                    scheduled_date: {
                        validators: {
                            notEmpty: {
                                message: 'The scheduled date field is required'
                            }
                        }
                    },
                    scheduled_time: {
                        validators: {
                            notEmpty: {
                                message: 'The scheduled time field is required'
                            }
                        }
                    },
                    phone: {
                        validators: {
                            notEmpty: {
                                message: 'The location field is required'
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
                    gender: {
                        validators: {
                            notEmpty: {
                                message: 'The gender field is required'
                            }
                        }
                    }

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
                    reInitCalendar(start_treatment_date, treatment_calendar, TreatmentCalendar);
                } else {
                    toastr.error(response.message);
                }
            }, null);
        });
    }

    return {
        // public functions
        init: function() {
            Validation();
        }
    };
}();

jQuery(document).ready(function() {
    UpdateStatusValidation.init();
    EditAppointmentValidation.init();
    CreateConsultancytValidation.init();
    CreateTreatmentValidation.init();
    EditTreatmentValidation.init();
    AppointPlanValidation.init();
});
