
var AddValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_add_services_form';
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
                    parent_id: {
                        validators: {
                            notEmpty: {
                                message: 'The parent service field is required'
                            }
                        }
                    },
                    color: {
                        validators: {
                            notEmpty: {
                                message: 'The color field is required'
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
        let modal_id = 'modal_edit_services_form';
        let form = document.getElementById(modal_id);
        // Duration/Price are nullable server-side and legitimately blank when
        // editing a parent service — keep client-side validators aligned with
        // AddValidation (name/parent_id/color only) so hidden child-only fields
        // don't block parent-service updates.
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
                    parent_id: {
                        validators: {
                            notEmpty: {
                                message: 'The parent service field is required'
                            }
                        }
                    },
                    color: {
                        validators: {
                            notEmpty: {
                                message: 'The color field is required'
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
            runEditSubmitWithBundleImpactCheck(form, modal_id);
        });
    }

    return {
        init: function() {
            validation();
        }
    };
}();

function runEditSubmitWithBundleImpactCheck(form, modal_id) {
    var action = $(form).attr('action');
    var isDuplicate = action.indexOf('duplicate') !== -1;
    var priceInput = $('#edit_price');
    var originalPrice = priceInput.data('original-price');
    var newPrice = parseFloat(priceInput.val());
    var serviceId = priceInput.data('service-id');

    var priceChanged = originalPrice !== undefined
        && !isNaN(newPrice)
        && parseFloat(originalPrice) !== newPrice;

    if (isDuplicate || !priceChanged || !serviceId) {
        submitEditForm(form, modal_id);
        return;
    }

    var previewUrl = route('admin.services.bundle_impact', {id: serviceId});

    $.ajax({
        url: previewUrl,
        type: 'GET',
        data: { new_price: newPrice },
        success: function (response) {
            var affected = (response && response.data && response.data.affected) || [];
            if (!affected.length) {
                submitEditForm(form, modal_id);
                return;
            }
            showBundleImpactModal(affected, function () {
                submitEditForm(form, modal_id);
            });
        },
        error: function (xhr) {
            errorMessage(xhr);
        }
    });
}

function submitEditForm(form, modal_id) {
    submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
        if (response.status == true) {
            toastr.success(response.message);
            closePopup(modal_id);
            if ($(form).attr('action').indexOf('duplicate') !== -1) {
                location.reload();
            } else {
                reInitTable('service');
            }
        } else {
            toastr.error(response.message);
        }
    }, form);
}

function showBundleImpactModal(affected, onConfirm) {
    var rows = affected.map(function (b) {
        var label = b.sessions + 'x service';
        if (b.discount_percentage) {
            label += ' @ ' + b.discount_percentage + '% off';
        }
        return '<tr>'
            + '<td>' + label + '</td>'
            + '<td class="text-end">' + formatMoney(b.old_bundle_price) + '</td>'
            + '<td class="text-end"><strong>' + formatMoney(b.new_bundle_price) + '</strong></td>'
            + '</tr>';
    }).join('');

    $('#bundle_impact_rows').html(rows);

    var $modal = $('#modal_bundle_impact');
    $modal.modal('show');

    $('#bundle_impact_confirm').off('click').on('click', function () {
        $modal.modal('hide');
        onConfirm();
    });
    $('#bundle_impact_cancel, #bundle_impact_close').off('click').on('click', function () {
        $modal.modal('hide');
    });
}

function formatMoney(value) {
    var n = Number(value) || 0;
    return n.toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

jQuery(document).ready(function() {
    AddValidation.init();
    EditValidation.init();
});
