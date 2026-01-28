// Edit Bundle Plan Functions

function editBundle(url, id) {
    hideMessages();
    
    // Reset form
    $('#edit_bundle_services').html('');
    $('#edit_bundle_package_total').val('');
    $('#edit_bundle_grand_total').val('');
    $('#edit_bundle_cash_amount').val('').prop('disabled', true);
    $('#edit_bundle_payment_mode_id').val('').trigger('change');
    
    $("#modal_edit_bundle").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setEditBundleData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setEditBundleData(response) {
    try {
        let package = response.data.package;
        let packageBundles = response.data.package_bundles;
        let paymentModes = response.data.payment_modes;
        let appointments = response.data.appointments;
        
        // Set basic info
        $('#edit_bundle_package_id').val(package.id);
        $('#edit_bundle_random_id').val(package.random_id);
        $('#edit_bundle_parent_id').val(package.patient_id);
        $('#edit_bundle_location_id').val(package.location_id);
        
        // Set patient info
        $('#edit-bundle-patient-name').text(package.patient?.name || '-');
        $('#edit-bundle-membership-name').text(package.patient?.membership?.name || '-');
        $('#edit-bundle-location-name').text(package.location?.name || '-');
        
        // Set appointments dropdown
        let appointmentOptions = '<option value="">Select Appointment</option>';
        if (appointments && appointments.length > 0) {
            appointments.forEach(function(appointment) {
                let selected = appointment.id == package.appointment_id ? 'selected' : '';
                appointmentOptions += `<option value="${appointment.id}" ${selected}>${appointment.name}</option>`;
            });
        }
        $('#edit_bundle_appointment_id').html(appointmentOptions);
        
        // Set payment modes dropdown
        let paymentModeOptions = '<option value="">Select Payment Mode</option>';
        if (paymentModes) {
            Object.entries(paymentModes).forEach(function([id, name]) {
                paymentModeOptions += `<option value="${id}">${name}</option>`;
            });
        }
        $('#edit_bundle_payment_mode_id').html(paymentModeOptions);
        
        // Display bundle services
        let bundleServicesHtml = '';
        let totalAmount = 0;
        
        if (packageBundles && packageBundles.length > 0) {
            packageBundles.forEach(function(bundle) {
                totalAmount += parseFloat(bundle.tax_including_price || 0);
                
                bundleServicesHtml += `
                    <tr>
                        <td>${bundle.bundle?.name || '-'}</td>
                        <td>${parseFloat(bundle.service_price || 0).toLocaleString()}</td>
                        <td>${parseFloat(bundle.tax_exclusive_net_amount || 0).toLocaleString()}</td>
                        <td>${parseFloat(bundle.tax_price || 0).toFixed(2)}</td>
                        <td>${parseFloat(bundle.tax_including_price || 0).toLocaleString()}</td>
                    </tr>
                `;
            });
        }
        
        $('#edit_bundle_services').html(bundleServicesHtml);
        $('#edit_bundle_package_total').val(totalAmount.toFixed(2));
        $('#edit_bundle_grand_total').val(totalAmount.toFixed(2));
        
    } catch (error) {
        console.error('Error setting edit bundle data:', error);
        toastr.error('Failed to load bundle data');
    }
}

function resetVoucherEditBundle(event) {
    if (event) {
        event.preventDefault();
    }
    $("#modal_edit_bundle").modal("hide");
    $('#update_bundle_form')[0].reset();
    $('#edit_bundle_services').html('');
}

// Handle payment mode change
$(document).on('change', '#edit_bundle_payment_mode_id', function() {
    if ($(this).val()) {
        $('#edit_bundle_cash_amount').prop('disabled', false);
        $('#edit_bundle_cash_amount').val('');
        $('#edit_bundle_grand_total').val($('#edit_bundle_package_total').val());
    } else {
        $('#edit_bundle_cash_amount').val('').prop('disabled', true);
        $('#edit_bundle_grand_total').val($('#edit_bundle_package_total').val());
    }
});

// Handle cash amount input
$(document).on('input', '#edit_bundle_cash_amount', function() {
    let cashAmount = parseFloat($(this).val()) || 0;
    let total = parseFloat($('#edit_bundle_package_total').val()) || 0;
    
    if (cashAmount > 0 && total > 0) {
        $.ajax({
            type: 'GET',
            url: route('admin.packages.getgrandtotal'),
            data: {
                'cash_amount': cashAmount,
                'total': total,
            },
            success: function(response) {
                if (response.status) {
                    $('#edit_bundle_grand_total').val(response.data.grand_total || 0);
                }
            }
        });
    } else {
        $('#edit_bundle_grand_total').val(total.toFixed(2));
    }
});

// Handle update button click
$(document).on('click', '#EditBundleFinal', function() {
    let packageId = $('#edit_bundle_package_id').val();
    let appointmentId = $('#edit_bundle_appointment_id').val();
    let paymentModeId = $('#edit_bundle_payment_mode_id').val();
    let cashAmount = $('#edit_bundle_cash_amount').val() || 0;
    let grandTotal = $('#edit_bundle_grand_total').val();
    
    // Validation
    if (!appointmentId) {
        toastr.error('Please select an appointment');
        return false;
    }
    
    if (paymentModeId && !cashAmount) {
        toastr.error('Please enter cash amount');
        return false;
    }
    
    if (cashAmount > 0 && !paymentModeId) {
        toastr.error('Please select payment mode');
        return false;
    }
    
    $(this).attr('disabled', true);
    
    let formData = {
        package_id: packageId,
        appointment_id: appointmentId,
        payment_mode_id: paymentModeId,
        cash_amount: cashAmount,
        grand_total: grandTotal
    };
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.packages.updatebundle'),
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.status) {
                toastr.success(response.message || 'Bundle plan updated successfully');
                $('#edit_bundle_successMessage').show();
                
                setTimeout(function() {
                    $("#modal_edit_bundle").modal("hide");
                    reInitTable();
                }, 1500);
            } else {
                toastr.error(response.message || 'Failed to update bundle plan');
                $('#edit_bundle_wrongMessage').show();
            }
            $('#EditBundleFinal').attr('disabled', false);
        },
        error: function(xhr) {
            console.error('Error updating bundle:', xhr);
            toastr.error('Failed to update bundle plan');
            $('#edit_bundle_wrongMessage').show();
            $('#EditBundleFinal').attr('disabled', false);
        }
    });
});

function hideMessages() {
    $('#edit_bundle_successMessage').hide();
    $('#edit_bundle_wrongMessage').hide();
}
