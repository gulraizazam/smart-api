// Edit Bundle Plan Functions
var total_amountArray = [];
var edit_amountArray = [];
var ExistingTotal = 0;

function editBundle(url, id) {
    total_amountArray = [];
    edit_amountArray = [];
    ExistingTotal = 0;
    $('.error-msg').html('');
    $('#edit_bundle_service_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    hideMessages();
    $("#update_plane_form")[0].reset();

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
        let appointmentArray = response.data.appointmentArray;
        let end_previous_date = response.data.end_previous_date;
        let grand_total = response.data.grand_total;
        let locationhasservice = response.data.locationhasservice;
        let locations = response.data.locations;
        let package = response.data.package;
        let packageadvances = response.data.packageadvances;
        let packagebundles = response.data.packagebundles;
        let packageservices = response.data.packageservices;
        let paymentmodes = response.data.paymentmodes;
        let users = response.data.users;
        let range = response.data.range;
        let total_price = response.data.total_price;
        let patient = package.user;
        let location = package.location;
        let history_options = noRecordFoundTable(5);
        let membership = response.data.membership;
        let selected_user_id = response.data.selectedUserId;
        
        if (packageadvances.length) {
            history_options = '';
            Object.values(packageadvances).forEach(function (packageadvance) {
                if (packageadvance.cash_amount != '0' || packageadvance.cash_flow == 'out') {
                    let selector = 'history_cash_row_' + packageadvance.id;
                    history_options += '<tr id="' + selector + '">';
                    if (packageadvance.is_tax == 1 && packageadvance.cash_flow == 'out') {
                        history_options += '<td>Tax</td>';
                    } else {
                        history_options += '<td>' + packageadvance?.paymentmode?.name + '</td>';
                    }

                    if (packageadvance.is_refund == 1) {
                        history_options += '<td>out / refund</td>';
                    } else if (packageadvance.is_setteled == 1) {
                        history_options += '<td>out / settled</td>';
                    } else {
                        history_options += '<td>' + packageadvance.cash_flow + '</td>';
                    }

                    history_options += '<td>' + packageadvance.cash_amount + '</td>';
                    history_options += '<td>' + formatDate(packageadvance.created_at, 'MMM, DD yyyy hh:mm A') + '</td>';
                    history_options += '<td>';
                    
                    if (packageadvance?.cash_flow == 'in') {
                        if (permissions.plans_cash_edit) {
                            history_options += '<a onclick="planeEdit(' + packageadvance.id + ', ' + package.id + ');" class="btn btn-sm btn-info" href="javascript:void(0);">Edit</a>&nbsp;';
                        }
                        if (permissions.plans_cash_delete) {
                            history_options += '<button onclick="deletePlaneHistory(`' + route('admin.packages.delete_cash') + '`, ' + packageadvance.id + ');" class="btn btn-sm btn-danger">Delete</button>';
                        }
                    }
                    history_options += '</td></tr>';
                }
            });
        }

        let service_options = noRecordFoundTable(10);

        if (packagebundles.length) {
            service_options = '';
            Object.values(packagebundles).forEach(function (packagebundle) {
                var del_icon;
                var editIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3699FF" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/></svg>';
                
                if (permissions.plans_edit_sold_by) {
                    del_icon = "<td><button type='button' class='btn btn-icon btn-sm btn-light btn-sm me-2' onClick='editBundleSoldBy(" + packagebundle.id + ", " + location.id + ")'>" + editIcon + "</button><button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRow(" + packagebundle.id + ", `edit_`)'>" + trashBtn() + "</button></td>";
                } else {
                    del_icon = "<td><button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRow(" + packagebundle.id + ", `edit_`)'>" + trashBtn() + "</button></td>";
                }

                service_options += '<tr class="HR_' + packagebundle.id + '">';
                
                // Count child services for this bundle
                let childServiceCount = Object.values(packageservices).filter(function (ps) {
                    return ps.package_bundle_id == packagebundle.id;
                }).length;

                // Only add toggle link if bundle has more than 1 child service
                let bundleName = packagebundle.bundle.name;
                if (childServiceCount > 1) {
                    bundleName = '<a href="javascript:void(0);" onclick="toggle(' + packagebundle.id + ')">' + packagebundle.bundle.name + '</a>';
                }
                service_options += '<td>' + bundleName + '</td>';
                service_options += '<td>' + packagebundle.service_price.toFixed(2) + '</td>';
                service_options += '<td>' + packagebundle.tax_exclusive_net_amount + '</td>';
                service_options += '<td>' + packagebundle.tax_price + '</td>';
                service_options += '<td>' + packagebundle.tax_including_price + '</td>';

                // Get consumed status, consumed_at, and sold_by names for this bundle
                let isConsumed = 'No';
                let consumedAt = 'N/A';
                let soldByNames = [];
                Object.values(packageservices).forEach(function (ps) {
                    if (ps.package_bundle_id == packagebundle.id) {
                        if (ps.sold_by && ps.sold_by.name) {
                            if (!soldByNames.includes(ps.sold_by.name)) {
                                soldByNames.push(ps.sold_by.name);
                            }
                        }
                        if (ps.is_consumed == '1' || ps.is_consumed === true) {
                            isConsumed = 'Yes';
                            if (ps.consumed_at) {
                                let date = new Date(ps.consumed_at);
                                let day = String(date.getDate()).padStart(2, '0');
                                let month = String(date.getMonth() + 1).padStart(2, '0');
                                let year = String(date.getFullYear()).slice(-2);
                                let hours = String(date.getHours()).padStart(2, '0');
                                let minutes = String(date.getMinutes()).padStart(2, '0');
                                consumedAt = day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
                            }
                        }
                    }
                });

                if (childServiceCount > 1) {
                    service_options += '<td>-</td>';
                    service_options += '<td>-</td>';
                } else {
                    service_options += '<td>' + isConsumed + '</td>';
                    service_options += '<td>' + consumedAt + '</td>';
                }
                service_options += '<td>' + (soldByNames.length > 0 ? soldByNames.join(', ') : 'N/A') + '</td>';
                service_options += del_icon;
                service_options += '</tr>';

                // Add child service rows only if more than 1 child service
                if (childServiceCount > 1) {
                    Object.values(packageservices).forEach(function (packageservice) {
                        if (packageservice.package_bundle_id == packagebundle.id) {
                            let consume = packageservice.is_consumed == '0' ? 'No' : 'Yes';
                            let psConsumedAt = 'N/A';
                            if (packageservice.consumed_at) {
                                let date = new Date(packageservice.consumed_at);
                                let day = String(date.getDate()).padStart(2, '0');
                                let month = String(date.getMonth() + 1).padStart(2, '0');
                                let year = String(date.getFullYear()).slice(-2);
                                let hours = String(date.getHours()).padStart(2, '0');
                                let minutes = String(date.getMinutes()).padStart(2, '0');
                                psConsumedAt = day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
                            }
                            let actualPrice = packageservice.actual_price ? parseFloat(packageservice.actual_price).toFixed(2) : '-';
                            service_options += '<tr class="' + packagebundle.id + '" style="display: none; background-color: #f9f9f9;">';
                            service_options += '<td style="padding-left: 30px; font-style: italic;">&nbsp;&nbsp;↳ ' + packageservice.service.name + '</td>';
                            service_options += '<td>' + actualPrice + '</td>';
                            service_options += '<td>' + packageservice.tax_exclusive_price + '</td>';
                            service_options += '<td>' + packageservice.tax_price + '</td>';
                            service_options += '<td>' + packageservice.tax_including_price + '</td>';
                            service_options += '<td>' + consume + '</td>';
                            service_options += '<td>' + psConsumedAt + '</td>';
                            service_options += '<td>' + (packageservice.sold_by ? packageservice.sold_by.name : 'N/A') + '</td>';
                            service_options += '<td></td>';
                            service_options += '</tr>';
                        }
                    });
                }
            });
        }

        let selectedAppointmentId = response.data.selectedAppointmentId;
        let appointment_options = '<option value="">Select Appointment</option>';
        if (appointmentArray && Object.keys(appointmentArray).length > 0) {
            Object.values(appointmentArray).forEach(function (appointment) {
                let selected = (appointment.id === selectedAppointmentId) ? 'selected' : '';
                appointment_options += '<option value="' + appointment.id + '" ' + selected + '>' + appointment.name + '</option>';
            });
        }

        let userOptions = '<option value="">Select</option>';
        if (users) {
            Object.entries(users).forEach(function ([id, name]) {
                let selected = (parseInt(id) === parseInt(selected_user_id)) ? 'selected' : '';
                userOptions += '<option value="' + id + '" ' + selected + '>' + name + '</option>';
            });
        }

        // Load bundles instead of services for bundle modal
        let serviceOptions = '<option value="">Select Service</option>';
        if (location?.id) {
            loadBundlesForLocation(location.id);
        }
        
        let payment_options = '<option value="">Select Payment Mode</option>';
        if (paymentmodes) {
            Object.entries(paymentmodes).forEach(function (paymentmode) {
                payment_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
            });
        }

        $("#edit_bundle_appointment_id").html(appointment_options);
        $("#edit-bundle-membership-name").text(membership);
        $("#edit_bundle_service_id").html(serviceOptions);
        
        // Remove "No record found" placeholder before adding data
        if (packagebundles && packagebundles.length > 0) {
            $('#edit_bundle_plan_services .service_not_found').remove();
        }
        
        $("#edit_bundle_plan_services").html(service_options);
        $(".edit_bundle_plan_history").html(history_options);
        $("#edit_bundle_payment_mode_id").html(payment_options);
        $("#edit_bundle_sold_by").html(userOptions);
        $("#edit-bundle-patient-name").text(patient?.name);
        $("#edit-bundle-location-name").text(location?.name);
        $("#edit_bundle_random_id").val(package?.random_id);
        $("#edit_bundle_parent_id").val(package?.patient_id);
        $("#edit_bundle_location_id").val(package?.location?.id);
        $("#edit_bundle_random_id_1").val(package?.random_id);
        $("#edit_bundle_package_total_1").val(total_price.toFixed(2));
        $("#edit_bundle_grand_total_1").val(grand_total);
        $('#edit_bundle_cash_amount_1').prop('disabled', true);

        // Disable service fields if bundle services exist
        if (packagebundles && packagebundles.length > 0) {
            $('#edit_bundle_service_id').prop('disabled', true);
            $('#edit_bundle_net_amount_1').prop('disabled', true);
            $('#edit_bundle_sold_by').prop('disabled', true);
            $('#EditBundlePackage').prop('disabled', true);
        } else {
            $('#edit_bundle_service_id').prop('disabled', false);
            $('#edit_bundle_net_amount_1').prop('disabled', false);
            $('#edit_bundle_sold_by').prop('disabled', false);
            $('#EditBundlePackage').prop('disabled', false);
        }

    } catch (error) {
        showException(error);
    }
}

function hideMessages() {
    $('#edit_duplicateErr').hide();
    $('#edit_successMessage').hide();
    $('#edit_inputfieldMessage').hide();
    $('#edit_wrongMessage').hide();
    $('#edit_consume').hide();
    $('#edit_percentageMessage').hide();
    $('#edit_AlreadyExitMessage').hide();
    $('#casesetteled').hide();
    $('#casesetteledamount').hide();
    $('#edit_datanotexist').hide();
    $('#edit_DiscountRange').hide();
}

function toggle(id) {
    $('.' + id).toggle();
}

// Handle Add button click in edit bundle modal
$(document).on('click', '#EditBundlePackage', function() {
    $('.error-msg').html('');
    
    if (!$('#edit_bundle_service_id').val()) {
        $('#edit_bundle_service_id_error').html('Please select service');
        return false;
    }

    if (!$('#edit_bundle_sold_by').val()) {
        $('#edit_bundle_sold_by_errorr').html('Please select sold by');
        return false;
    }

    $(this).attr("disabled", true);
    
    var random_id = $('#edit_bundle_random_id').val();
    var service_id = $('#edit_bundle_service_id').val(); // Bundle id
    var net_amount = $('#edit_bundle_net_amount_1').val();
    var package_total = $('#edit_bundle_package_total_1').val();
    var sold_by = $('#edit_bundle_sold_by').val();
    var location_id = $('#edit_bundle_location_id').val();
    var user_id = $('#edit_bundle_parent_id').val();

    if (service_id && net_amount && location_id) {
        var formData = {
            'random_id': random_id,
            'bundle_id': service_id,
            'discount_id': null,
            'net_amount': net_amount,
            'discount_type': null,
            'discount_price': null,
            'package_total': package_total,
            'is_exclusive': null,
            'location_id': location_id,
            'user_id': user_id,
            'package_bundles[]': [],
            'sold_by': sold_by
        };

        $(".package_bundles_bundle").each(function () {
            formData['package_bundles[]'].push($(this).val());
        });

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'post',
            url: route('admin.packages.savebundle_service'),
            data: formData,
            success: function (response) {
                if (response.status) {
                    let servicesData = response.data.servicesData;
                    let bundlesData = servicesData.bundlesData;
                    let packageServicesData = servicesData.packageServicesData;

                    // Calculate total
                    let totalAmount = bundlesData.tax_including_price.toLocaleString();
                    let grandTotal = totalAmount.replace(/,/g, '');
                    
                    // Update package total
                    let currentTotal = parseFloat($('#edit_bundle_package_total_1').val() || 0);
                    let newTotal = currentTotal + parseFloat(bundlesData.tax_including_price);
                    $("#edit_bundle_package_total_1").val(newTotal.toFixed(2));
                    $("#edit_bundle_grand_total_1").val(newTotal.toFixed(2));

                    // Build sold by names
                    let soldByNames = sold_by ? [sold_by] : [];
                    
                    // Add row to table
                    var editIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3699FF" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/></svg>';
                    
                    let del_icon = "<td><button type='button' class='btn btn-icon btn-sm btn-light btn-sm me-2' onClick='editBundleSoldBy(" + bundlesData.id + ", " + location_id + ")'>" + editIcon + "</button><button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRow(" + bundlesData.id + ", `edit_`)'>" + trashBtn() + "</button></td>";

                    // Remove "No record found" placeholder row if it exists
                    $('#edit_bundle_plan_services .service_not_found').remove();

                    let service_row = '<tr class="HR_' + bundlesData.id + '">';
                    service_row += '<td><a href="javascript:void(0);" onclick="toggle(' + bundlesData.id + ')">' + servicesData.service_name + '</a></td>';
                    service_row += '<td>' + servicesData.service_price.toFixed(2) + '</td>';
                    service_row += '<td>' + bundlesData.tax_exclusive_net_amount + '</td>';
                    service_row += '<td>' + bundlesData.tax_price + '</td>';
                    service_row += '<td>' + bundlesData.tax_including_price + '</td>';
                    service_row += '<td>No</td>';
                    service_row += '<td>N/A</td>';
                    service_row += '<td><input type="hidden" class="package_bundles_sold_by" name="sold_by[]" value="' + sold_by + '" />N/A</td>';
                    service_row += del_icon;
                    service_row += '</tr>';

                    $('#edit_bundle_plan_services').append(service_row);

                    // Add child services (expandable rows)
                    jQuery.each(packageServicesData, function (i, packageService) {
                        let consume = packageService.is_consumed == '0' ? 'No' : 'Yes';
                        let psConsumedAt = 'N/A';
                        if (packageService.consumed_at) {
                            let date = new Date(packageService.consumed_at);
                            let day = String(date.getDate()).padStart(2, '0');
                            let month = String(date.getMonth() + 1).padStart(2, '0');
                            let year = String(date.getFullYear()).slice(-2);
                            let hours = String(date.getHours()).padStart(2, '0');
                            let minutes = String(date.getMinutes()).padStart(2, '0');
                            psConsumedAt = day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
                        }
                        let child_row = '<tr class="' + bundlesData.id + '" style="display: none; background-color: #f9f9f9;">';
                        child_row += '<td style="padding-left: 30px; font-style: italic;">&nbsp;&nbsp;↳ ' + packageService.name + '</td>';
                        child_row += '<td>-</td>';
                        child_row += '<td>' + packageService.tax_exclusive_price + '</td>';
                        child_row += '<td>' + packageService.tax_price + '</td>';
                        child_row += '<td>' + packageService.tax_including_price + '</td>';
                        child_row += '<td>' + consume + '</td>';
                        child_row += '<td>' + psConsumedAt + '</td>';
                        child_row += '<td>N/A</td>';
                        child_row += '<td></td>';
                        child_row += '</tr>';
                        $('#edit_bundle_plan_services').append(child_row);
                    });

                    // Clear form fields
                    $('#edit_bundle_service_id').val('').trigger('change');
                    $('#edit_bundle_net_amount_1').val('');
                    $('#edit_bundle_sold_by').val('').trigger('change');

                    // Disable fields after adding bundle
                    $("#edit_bundle_service_id").prop("disabled", true);
                    $("#edit_bundle_sold_by").prop("disabled", true);
                    $("#EditBundlePackage").prop("disabled", true);

                    //toastr.success('Bundle added successfully.');
                } else {
                    toastr.error(response.message || 'Failed to add bundle');
                    $("#EditBundlePackage").attr("disabled", false);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error adding bundle:', error);
                toastr.error('Failed to add service');
                $("#EditBundlePackage").attr("disabled", false);
            }
        });
    } else {
        toastr.error('Please fill all required fields');
        $(this).attr("disabled", false);
    }
});

// Handle Save button click in edit bundle modal
$(document).on('click', '#EditBundleFinal', function(e) {
    e.preventDefault();
    
    $('.error-msg').html('');
    hideMessages();
    
    var random_id = $('#edit_bundle_random_id_1').val();
    var patient_id = $('#edit_bundle_parent_id').val();
    var total = $('#edit_bundle_package_total_1').val();
    var payment_mode_id = $('#edit_bundle_payment_mode_id').val();
    var cash_amount = $('#edit_bundle_cash_amount_1').val();
    var grand_total = $('#edit_bundle_grand_total_1').val();
    var location_id = $('#edit_bundle_location_id').val();
    var appointment_id = $('#edit_bundle_appointment_id').val();
    
    var formData = {
        'random_id': random_id,
        'patient_id': patient_id,
        'location_id': location_id,
        'total': total,
        'payment_mode_id': payment_mode_id,
        'cash_amount': cash_amount,
        'grand_total': grand_total,
        'is_exclusive': null,
        'appointment_id': appointment_id
        // NOTE: Do NOT send package_bundles for bundle plans.
        // Bundle services are already saved to DB immediately via the Add button (savebundle_service).
        // The Save button only handles payment and appointment updates.
    };

    var status = 0;
    if (cash_amount > 0) {
        status = 1;
    }

    if (payment_mode_id == '' && cash_amount > 0) {
        $('#payment_mode_id').html('Please select payment mode');
        return false;
    }

    if (payment_mode_id && cash_amount == '') {
        $('#cash_amount_error').html('Please enter cash amount');
        return false;
    }

  

    if (random_id && (patient_id > 0) && total && (status == 1 ? payment_mode_id : true) && cash_amount >= 0 && grand_total && location_id) {
        $(this).attr('disabled', true);
        
        $.ajax({
            type: 'get',
            url: route('admin.packages.updatepackages'),
            data: formData,
            success: function(response) {
                if (response.status) {
                    toastr.success(response.message || 'Bundle plan updated successfully');
                    $("#modal_edit_bundle").modal("hide");
                    reInitTable();
                } else {
                    if (response.data?.setteled == 1) {
                        $('#casesetteledamount').show();
                    } else {
                        toastr.error(response.message || 'Failed to update bundle plan');
                    }
                }
                $('#EditBundleFinal').attr('disabled', false);
            },
            error: function(xhr) {
                console.error('Error updating bundle:', xhr);
                toastr.error('Failed to update bundle plan');
                $('#EditBundleFinal').attr('disabled', false);
            }
        });
    } else {
        toastr.error("Kindly enter required fields or you enter wrong value.");
        $(this).attr("disabled", false);
    }
});

// Payment mode change handler for edit bundle modal
$(document).on('change', '#edit_bundle_payment_mode_id', function() {
    if ($(this).val()) {
        $('#edit_bundle_cash_amount_1').prop('disabled', false);
    } else {
        $('#edit_bundle_cash_amount_1').val('');
        $('#edit_bundle_cash_amount_1').prop('disabled', true);
        edit_bundle_keyfunction_grandtotal();
    }
});

// Cash amount input handler for edit bundle modal
$(document).on('input', '#edit_bundle_cash_amount_1', function() {
    let val = $(this).val();

    // Reset if first character is 0 and length > 1 and doesn't start with "0."
    if (val.length > 1 && val.startsWith("0") && !val.startsWith("0.")) {
        $(this).val('');
        return;
    }

    // Reset if value is negative
    if (parseFloat(val) < 0) {
        $(this).val('');
        return;
    }

    // Call grand total calculation if value is valid
    edit_bundle_keyfunction_grandtotal();
});

// Calculate grand total for edit bundle modal
function edit_bundle_keyfunction_grandtotal() {
    hideMessages();

    var cash_amount = $('#edit_bundle_cash_amount_1').val();
    var total = $('#edit_bundle_package_total_1').val();
    var random_id = $('#edit_bundle_random_id').val();

    if (total) {
        $.ajax({
            type: 'GET',
            url: route('admin.packages.getgrandtotal_update'),
            data: {
                'cash_amount': cash_amount ?? 0,
                'total': total,
                'random_id': random_id
            },
            success: function (response) {
                if (response.status) {
                    $("#edit_bundle_grand_total_1").val(response?.data?.grand_total ?? 0);
                } else {
                    console.error('Failed to calculate grand total');
                }
            },
            error: function(xhr) {
                console.error('Error calculating grand total:', xhr);
            }
        });
    }
}

// Load bundles for location in edit bundle modal
function loadBundlesForLocation(locationId) {
    $.ajax({
        type: 'GET',
        url: route('admin.packages.getbundles'),
        data: {
            location_id: locationId
        },
        success: function(response) {
            if (response.status && response.data.bundles) {
                let bundleOptions = '<option value="">Select Service</option>';
                response.data.bundles.forEach(function(bundle) {
                    bundleOptions += '<option value="' + bundle.id + '">' + bundle.name + '</option>';
                });
                $('#edit_bundle_service_id').html(bundleOptions);
            } else {
                $('#edit_bundle_service_id').html('<option value="">No bundles available</option>');
            }
        },
        error: function(xhr) {
            console.error('Error loading bundles:', xhr);
            $('#edit_bundle_service_id').html('<option value="">Error loading bundles</option>');
        }
    });
}

// Handle bundle selection change in edit bundle modal
$(document).on('change', '#edit_bundle_service_id', function() {
    let bundleId = $(this).val();
    let locationId = $('#edit_bundle_location_id').val();
    let patientId = $('#edit_bundle_parent_id').val();
    
    if (bundleId && locationId && patientId) {
        getBundleServiceInfo(bundleId, locationId, patientId);
    } else {
        // Clear price field if no bundle selected
        $('#edit_bundle_net_amount_1').val('');
    }
});

// Get bundle service info from API
function getBundleServiceInfo(bundleId, locationId, patientId) {
    $.ajax({
        type: 'GET',
        url: route('admin.packages.getserviceinfo'),
        data: {
            bundle_id: bundleId,
            location_id: locationId,
            patient_id: patientId
        },
        success: function(response) {
            if (response.status) {
                // Set the price from net_amount
                $('#edit_bundle_net_amount_1').val((response.data.net_amount).toFixed(2));
                $('#edit_bundle_net_amount_1').prop('disabled', true);
            } else {
                // Set the price even if no discounts
                $('#edit_bundle_net_amount_1').val((response.data.net_amount).toFixed(2));
                $('#edit_bundle_net_amount_1').prop('disabled', true);
            }
        },
        error: function(xhr) {
            console.error('Error loading bundle info:', xhr);
            $('#edit_bundle_net_amount_1').val('');
        }
    });
}
