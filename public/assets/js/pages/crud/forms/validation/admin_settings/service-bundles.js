var ServiceBundleValidation = function () {
    var AddValidation = function () {
        var modal_id = 'modal_service_bundles_form';
        var form = document.getElementById(modal_id);
        var validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    service_id: {
                        validators: {
                            notEmpty: {
                                message: 'Please select a service'
                            }
                        }
                    },
                    sessions: {
                        validators: {
                            notEmpty: {
                                message: 'Number of sessions is required'
                            },
                            greaterThan: {
                                min: 1,
                                message: 'Sessions must be at least 1'
                            }
                        }
                    },
                    price: {
                        validators: {
                            notEmpty: {
                                message: 'Bundle price is required'
                            },
                            greaterThan: {
                                min: 0,
                                inclusive: true,
                                message: 'Price must be at least 0'
                            }
                        }
                    },
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                    submitButton: new FormValidation.plugins.SubmitButton(),
                }
            }
        );

        validate.on('core.form.valid', function () {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable('service_bundles');
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    };

    var BulkValidation = function () {
        var modal_id = 'modal_bulk_bundles_form';
        var form = document.getElementById(modal_id);
        var validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    sessions: {
                        validators: {
                            notEmpty: {
                                message: 'Number of sessions is required'
                            },
                            greaterThan: {
                                min: 1,
                                message: 'Sessions must be at least 1'
                            }
                        }
                    },
                    discount_percentage: {
                        validators: {
                            notEmpty: {
                                message: 'Discount percentage is required'
                            },
                            between: {
                                min: 0,
                                max: 100,
                                message: 'Discount must be between 0 and 100'
                            }
                        }
                    },
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                    submitButton: new FormValidation.plugins.SubmitButton(),
                }
            }
        );

        validate.on('core.form.valid', function () {
            var selectedIds = [];
            $('.bulk-svc-check:checked').each(function () { selectedIds.push($(this).val()); });
            if (selectedIds.length === 0) {
                toastr.error('Please select at least one service.');
                return;
            }

            // Inject selected IDs as hidden inputs for serialization
            $(form).find('.bulk-hidden-id').remove();
            selectedIds.forEach(function (id) {
                $(form).append('<input type="hidden" class="bulk-hidden-id" name="service_ids[]" value="' + id + '">');
            });

            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    $('#modal_bulk_bundles').modal('hide');
                    reInitTable('service_bundles');
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    };

    return {
        init: function () {
            AddValidation();
            BulkValidation();
        }
    };
}();

jQuery(document).ready(function () {
    ServiceBundleValidation.init();
});
