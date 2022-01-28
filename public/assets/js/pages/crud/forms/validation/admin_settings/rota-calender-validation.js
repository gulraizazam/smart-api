
var CalenderValidation = function () {
    var validation = function () {
        let modal_id = 'modal_update_calender';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    start_time: {
                        validators: {
                            notEmpty: {
                                message: 'The start time field is required'
                            }
                        }
                    },
                    end_time: {
                        validators: {
                            notEmpty: {
                                message: 'The end time field is required'
                            }
                        }
                    },
                    start_off: {
                        validators: {
                            notEmpty: {
                                message: 'The start off type field is required'
                            }
                        }
                    },
                    end_off: {
                        validators: {
                            notEmpty: {
                                message: 'The end off field is required'
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

        validate.on('core.form.valid', function(event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status == true) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    loadEvents();
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

function loadEvents() {
    calendar.destroy();
    KTCalendarBasic.init();

}


jQuery(document).ready(function() {
    CalenderValidation.init();
});
