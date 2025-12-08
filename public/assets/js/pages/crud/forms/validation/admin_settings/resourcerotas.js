
var AddValidation = function () {
    var validation = function () {
        let modal_id = 'modal_add_resourcerotas_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
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
                                message: 'The centre field is required'
                            }
                        }
                    },
                    resource_type_id: {
                        validators: {
                            notEmpty: {
                                message: 'The resource type field is required'
                            }
                        }
                    },
                    from: {
                        validators: {
                            notEmpty: {
                                message: 'The from field is required'
                            }
                        }
                    },

                    to: {
                        validators: {
                            notEmpty: {
                                message: 'The to field is required'
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
                    // Save current filter values before reload
                    sessionStorage.setItem('rotaFilters', JSON.stringify({
                        resourcename: $("#search_resource_name").val(),
                        type_id: $("#search_type_id").val(),
                        region_id: $("#search_region_id").val(),
                        city_id: $("#search_city_id").val(),
                        location_id: $("#search_location_id").val(),
                        from: $("#search_from").val(),
                        to: $("#search_to").val(),
                        date_range: $("#date_range").val(),
                        status: $("#search_status").val()
                    }));
                    // Reload the page to reset all form states and prevent null value issues
                    setTimeout(function() {
                        location.reload();
                    }, 500);
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
    var validation = function () {
        let modal_id = 'modal_edit_resourcerotas_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {

                    from: {
                        validators: {
                            notEmpty: {
                                message: 'The from field is required'
                            }
                        }
                    },

                    to: {
                        validators: {
                            notEmpty: {
                                message: 'The to field is required'
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
                    // Save current filter values before reload
                    sessionStorage.setItem('rotaFilters', JSON.stringify({
                        resourcename: $("#search_resource_name").val(),
                        type_id: $("#search_type_id").val(),
                        region_id: $("#search_region_id").val(),
                        city_id: $("#search_city_id").val(),
                        location_id: $("#search_location_id").val(),
                        from: $("#search_from").val(),
                        to: $("#search_to").val(),
                        date_range: $("#date_range").val(),
                        status: $("#search_status").val()
                    }));
                    // Reload the page to reset all form states
                    setTimeout(function() {
                        location.reload();
                    }, 500);
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
});
