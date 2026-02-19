function createBundle(url, id) {
    total_amountArray = [];
    edit_amountArray = [];
    ExistingTotal = 0;
    $('#add_service_id_bundle').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    
    // Re-enable all fields that might have been disabled
    $("#add_bundle_location_id").prop("disabled", false);
    $("#add_service_id_bundle").prop("disabled", false);
    $("#add_sold_by_bundle").prop("disabled", false);
    $("#AddPackageBundle").prop("disabled", false);
    
    setTimeout(function () {
        $("#add_discount_id_bundle").html('<option value="">Select Discount</option>');
        
        // If in patient card context, pre-fill patient ID
        if (typeof window.isPatientCardContext !== 'undefined' && window.isPatientCardContext && typeof window.patientCardPatientId !== 'undefined') {
            $("#add_patient_id_bundle").val(window.patientCardPatientId).trigger('change');
            // Hide patient search field since patient is already selected
            $("#add_patient_id_bundle").closest('.fv-row').find('.select2-container').hide();
            // Load patient info for bundle modal
            loadPatientInfoForBundle(window.patientCardPatientId);
        } else {
            $("#add_patient_id_bundle").val(null).trigger('change');
        }
        
        $(".search_patient").val('');
        $("#net_amount_bundle").val('');
        $("#package_total_bundle").val('');
        $("#grand_total_bundle").val('');
        $('#bundles_add').find('#patient_membership_bundle').val('');
        $('#bundles_add').find('#discount_value_bundle').val('');
        $('#bundles_add').find("#add_appointment_id_bundle").empty();
        $('#bundles_add').find('#add_appointment_id_bundle').val(null).trigger('change');
    }, 500)

    $("#add_discount_type_bundle").attr('disabled', true);
    $("#add_discount_value_bundle").val('');
    $("#add_discount_value_bundle").attr('disabled', true);

    $('#successMessageBundle').hide();
    hideSpinner("-save");
    hideSpinner("-add");
    hideMessages();

    $("#bundle_services").html("");
    $("#modal_add_bundle").modal("show");
    
    // Initialize Select2 on bundle form dropdowns (except patient search which is handled by referred-by-patient-search.js)
    setTimeout(function() {
        $('#add_bundle_location_id').select2({
            dropdownParent: $('#modal_add_bundle')
        });
        // Patient search is initialized by referred-by-patient-search.js with AJAX search
        // Just reinitialize it to ensure it works in the modal
        if ($('#add_patient_id_bundle').hasClass('select2-hidden-accessible')) {
            $('#add_patient_id_bundle').select2('destroy');
        }
        $('#add_patient_id_bundle').select2({
            dropdownParent: $('#modal_add_bundle'),
            width: '100%',
            placeholder: 'Search Patient by Name or Phone',
            allowClear: true,
            ajax: {
                url: route('admin.users.getpatient.optimized'),
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term
                    };
                },
                processResults: function (data) {
                    if (data.status && data.data && data.data.patients) {
                        return {
                            results: data.data.patients.map(function(patient) {
                                return {
                                    id: patient.id,
                                    text: patient.name + ' - ' + patient.phone
                                };
                            })
                        };
                    }
                    return { results: [] };
                },
                cache: true
            },
            minimumInputLength: 3
        });
        $('#add_appointment_id_bundle').select2({
            dropdownParent: $('#modal_add_bundle')
        });
        $('#add_service_id_bundle').select2({
            dropdownParent: $('#modal_add_bundle')
        });
        $('#add_sold_by_bundle').select2({
            dropdownParent: $('#modal_add_bundle')
        });
        $('#payment_mode_id_bundle').select2({
            dropdownParent: $('#modal_add_bundle')
        });
    }, 100);

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setBundleData(response);
            $("#cash_amount_bundle").val(0);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setBundleData(response) {
    let locations = response.data.locations
    let discounts = response.data.discounts;
    let random_id = response.data.random_id;
    let appointmentinformation = response.data.appointmentinformation;
    let paymentmodes = response.data.paymentmodes;

    let location_options = '<option value="">Select Centre</option>';

    if (locations) {
        Object.entries(locations).forEach(function (location) {
            if (location[1] !== 'All Cities-All Centres') {
                location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
            }
        });
    }

    let discount_options = '<option value="">Select Discount</option>';

    if (discounts) {
        Object.values(discounts).forEach(function (discount) {
            discount_options += '<option value="' + discount.id + '">' + discount.name + '</option>';
        });
    }

    let payment_options = '<option value="">Select Payment Mode</option>';
    if (paymentmodes) {
        Object.entries(paymentmodes).forEach(function (paymentmode) {
            payment_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
        });
    }

    $("#add_discount_id_bundle").html(discount_options);
    $("#payment_mode_id_bundle").html(payment_options);

    $("#add_bundle_location_id").html(location_options).val(appointmentinformation?.location_id);
    $("#random_id_bundle").val(random_id);
    $('#cash_amount_bundle').prop('disabled', true);

    // Auto-select location if user has only one location assigned
    if (locations) {
        // Filter out "All Cities-All Centres" option
        var validLocations = Object.entries(locations).filter(function(location) {
            return location[1] !== 'All Cities-All Centres';
        });
        if (validLocations.length === 1) {
            $("#add_bundle_location_id").val(validLocations[0][0]).trigger('change');
        }
    }

    getServicesBundle();

    getUserCentre();
}

function getServicesBundle(action) {
    hideMessages();

    let location = $("#add_bundle_location_id").val();

    // Don't call API if no location is selected
    if (!location || location == '') {
        $("#add_service_id_bundle").html('<option value="">Select Service</option>');
        return;
    }

    let url = route('admin.packages.getbundles', {
        _query: {
            location_id: location
        }
    });

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setBundles(response);
            
            // In patient card context, also load appointments when location changes
            if (typeof window.isPatientCardContext !== 'undefined' && window.isPatientCardContext && typeof window.patientCardPatientId !== 'undefined') {
                getAppointmentsBundle(window.patientCardPatientId);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            $('#datanotexistBundle').show();
            $("#add_service_id_bundle").html('<option value="">Select Service</option>');
        }
    });
}

function setBundles(response) {
    try {
        // Check if response has data and bundles array
        if (!response.data || !response.data.bundles) {
            $('#datanotexistBundle').show();
            $("#add_service_id_bundle").html('<option value="">Select Service</option>');
            return;
        }

        let bundles = response.data.bundles;
        let bundle_options = '<option value="">Select Service</option>';

        Object.values(bundles).forEach(function (bundle) {
            bundle_options += '<option value="' + bundle.id + '">' + bundle.name + ' - Rs. ' + bundle.price + '</option>';
        });

        $("#add_service_id_bundle").html(bundle_options);

    } catch (error) {
        console.error('Error setting bundles:', error);
        showException(error);
    }
}

function getServiceDiscountBundle(element) {
    hideMessages();
    var bundle_id = element.val();
    var patient_id = $('#add_patient_id_bundle').val();
    var location_id = $('#add_bundle_location_id').val();

    if (bundle_id == "") {
        $("#add_service_id_bundle_error").html('Service is required').show();
    } else {
        $("#add_service_id_bundle_error").html('').hide();
    }

    // Clear price and sold by if no bundle selected
    if (!bundle_id) {
        $("#net_amount_bundle").val('');
        $("#add_sold_by_bundle").html('<option value="">Select</option>');
        return;
    }

    if (bundle_id && patient_id && location_id) {
        $.ajax({
            type: 'get',
            url: route('admin.packages.getserviceinfo'),
            data: {
                'bundle_id': bundle_id,
                'location_id': location_id,
                'patient_id': patient_id
            },
            success: function (response) {
                if (response.data && response.data.net_amount !== undefined) {
                    var netAmount = parseFloat(response.data.net_amount).toFixed(2);
                    $("#net_amount_bundle").val(netAmount);
                    $("#net_amount_bundle").prop("disabled", true);
                    // Update Total and Cash Received Remain fields
                    $("#package_total_bundle").val(netAmount);
                    $("#grand_total_bundle").val(netAmount);
                } else {
                    // Fallback: get price from the selected option text (format: "Name - Rs. XXXXX")
                    var selectedText = element.find('option:selected').text();
                    var priceMatch = selectedText.match(/Rs\.\s*([\d,]+(?:\.\d+)?)/);
                    if (priceMatch) {
                        var price = priceMatch[1].replace(/,/g, '');
                        $("#net_amount_bundle").val(parseFloat(price).toFixed(2));
                        $("#net_amount_bundle").prop("disabled", true);
                        $("#package_total_bundle").val(parseFloat(price).toFixed(2));
                        $("#grand_total_bundle").val(parseFloat(price).toFixed(2));
                    }
                }

                // Fetch sold by users
                getSoldByBundle(location_id);
            },
            error: function(xhr, status, error) {
                console.error('Error fetching bundle info:', error);
                $("#net_amount_bundle").val('');
            }
        });
    } else if (bundle_id && location_id) {
        // If patient not selected, still get price and sold by
        $.ajax({
            type: 'get',
            url: route('admin.packages.getbundles'),
            data: {
                'location_id': location_id
            },
            success: function (response) {
                if (response.data && response.data.bundles) {
                    let selectedBundle = response.data.bundles.find(b => b.id == bundle_id);
                    if (selectedBundle) {
                        var netAmount = parseFloat(selectedBundle.price).toFixed(2);
                        $("#net_amount_bundle").val(netAmount);
                        $("#net_amount_bundle").prop("disabled", true);
                        // Update Total and Cash Received Remain fields
                        $("#package_total_bundle").val(netAmount);
                        $("#grand_total_bundle").val(netAmount);
                    }
                }
                // Fetch sold by users
                getSoldByBundle(location_id);
            }
        });
    }
}

function getSoldByBundle(location_id) {
    let patient_id = $('#add_patient_id_bundle').val();
    
    if (!location_id) {
        return;
    }

    // If patient is selected, fetch from appointment info (includes users)
    if (patient_id) {
        $.ajax({
            type: 'get',
            url: route('admin.packages.getappointmentinfo'),
            data: {
                'location_id': location_id,
                'patient_id': patient_id
            },
            success: function (response) {
                if (response.status && response.data && response.data.users) {
                    setSoldByBundle(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching sold by users:', error);
            }
        });
    } else {
        // If no patient, just fetch users for the location
        $.ajax({
            type: 'get',
            url: route('admin.packages.getservice'),
            data: {
                'location_id': location_id
            },
            success: function (response) {
                // Try to get users from response, if not available set empty
                if (response.data && response.data.users) {
                    setSoldByBundle(response.data);
                } else {
                    // Set empty sold by dropdown
                    $("#add_sold_by_bundle").html('<option value="">Select</option>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching sold by users:', error);
                $("#add_sold_by_bundle").html('<option value="">Select</option>');
            }
        });
    }
}

function setSoldByBundle(data) {
    try {
        let users = data.users;
        let selected_doctor_id = data.selected_doctor_id;
        let user_options = '<option value="">Select</option>';

        if (users) {
            Object.entries(users).forEach(function ([id, name]) {
                let selected = (parseInt(id) === parseInt(selected_doctor_id)) ? 'selected' : '';
                user_options += '<option value="' + id + '" ' + selected + '>' + name + '</option>';
            });
        }

        $("#add_sold_by_bundle").html(user_options);

    } catch (error) {
        console.error('Error setting sold by:', error);
        showException(error);
    }
}

function getDiscountInfoBundle(element) {
    // Bundle functionality - can be implemented later if needed
}

function changeDiscountBundle(element) {
    // Bundle functionality - can be implemented later if needed
}

function getDiscountValueBundle(element) {
    // Bundle functionality - can be implemented later if needed
}

function getAppointmentsBundle(patient) {
    let location = $("#add_bundle_location_id").val();

    if (location != '' && patient != '') {
        let url = route('admin.packages.getappointmentinfo', {
            _query: {
                location_id: location,
                patient_id: patient,
            }
        });

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: url,
            type: "GET",
            cache: false,
            success: function (response) {
                setAppointmentsBundle(response);
                setSoldByBundle(response.data);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });
    }
}

function setAppointmentsBundle(response) {
    try {
        let appointments = response.data.appointments;
        let latestConsultationId = response.data.latest_consultation_id;
        let appointment_options = '<option value="">Select Appointment</option>';
        let membership = response.data.membership;
        let appointmentKeys = [];

        // Check if appointments object has any keys
        if (appointments && Object.keys(appointments).length > 0) {
            Object.entries(appointments).forEach(function ([id, value]) {
                appointment_options += '<option value="' + id + '">' + value.name + '</option>';
                appointmentKeys.push(id);
            });
        }
        
        $("#add_appointment_id_bundle").html(appointment_options);
        
        // Pre-select the latest consultation
        if (latestConsultationId) {
            $("#add_appointment_id_bundle").val(latestConsultationId).trigger('change');
        } else if (appointmentKeys.length === 1) {
            // Fallback: Auto-select if only one appointment exists
            $("#add_appointment_id_bundle").val(appointmentKeys[0]).trigger('change');
        }
        
        $("#patient_membership_bundle").val(membership);
        $("#patient_membership_bundle").attr('disabled', true);
    } catch (error) {
        console.error('Error setting appointments:', error);
        showException(error);
    }
}

function keyfunction_grandtotal_bundle() {
    hideMessages();

    var cash_amount = $('#cash_amount_bundle').val();
    var total = $('#package_total_bundle').val();

    if (total) {
        $.ajax({
            type: 'GET',
            url: route('admin.packages.getgrandtotal'),
            data: {
                'cash_amount': cash_amount ?? 0,
                'total': total,
            },
            success: function (response) {
                if (response.status) {
                    if (response?.data?.grand_total == 1 || response?.data?.grand_total == 0) {
                        $("#grand_total_bundle").val(0);
                    } else {
                        $("#grand_total_bundle").val(response?.data?.grand_total ?? 0);
                    }
                } else {
                    $('#wrongMessageBundle').show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error calculating grand total:', error);
            }
        });
    }
}

// Initialize patient selection event handlers
$(document).ready(function() {
    // Handle cash amount input to calculate grand total
    $("#cash_amount_bundle").on('input', function () {
        var value = $(this).val();
        
        // Allow only numbers and decimal point
        if (!/^\d*\.?\d*$/.test(value)) {
            $(this).val(value.slice(0, -1));
            return;
        }

        // Trigger grand total calculation
        keyfunction_grandtotal_bundle();
    });

    // Handle payment mode change
    $('#payment_mode_id_bundle').on('change', function () {
        var packageTotal = $('#package_total_bundle').val() || '0';
        if ($(this).val()) {
            $('#cash_amount_bundle').prop('disabled', false);
            $('#cash_amount_bundle').val('');
            // Keep grand_total as package_total until cash amount is entered
            $("#grand_total_bundle").val(packageTotal);
        } else {
            $('#cash_amount_bundle').val('');
            $('#cash_amount_bundle').prop('disabled', true);
            // Set grand_total to package_total when no payment
            $("#grand_total_bundle").val(packageTotal);
        }
    });

    // Handle patient selection from Select2 dropdown
    $('#add_patient_id_bundle').on('select2:select', function (e) {
        var patientId = $(this).val();
        if (patientId) {
            // Clear appointment dropdown
            $("#add_appointment_id_bundle").empty();
            $('#add_appointment_id_bundle').append('<option value="">Select Appointment</option>');
            $('#add_appointment_id_bundle').val(null).trigger('change');
            
            // Load appointments and membership for selected patient
            getAppointmentsBundle(patientId);
        }
    });

    // Clear appointments and membership when patient is cleared
    $('#add_patient_id_bundle').on('select2:clear', function (e) {
        $("#add_appointment_id_bundle").empty();
        $('#add_appointment_id_bundle').append('<option value="">Select Appointment</option>');
        $('#add_appointment_id_bundle').val(null).trigger('change');
        $('#patient_membership_bundle').val('No data');
    });

    // Handle Save button click (final save)
    $("#AddPackageFinalBundle").click(function () {
        $('.create-bundle-error').html('');
        
        // Validate payment mode and cash amount
        if ($('#payment_mode_id_bundle').val()) {
            if (!$('#cash_amount_bundle').val()) {
                $('#cash_amount_bundle_error').html('Please enter cash amount');
                return false;
            }
        }

        hideMessages();

        var random_id = $('#random_id_bundle').val();
        var patient_id = $('#add_patient_id_bundle').val();
        var total = $('#package_total_bundle').val();
        var payment_mode_id = $('#payment_mode_id_bundle').val();
        var cash_amount = $('#cash_amount_bundle').val();
        var grand_total = $('#grand_total_bundle').val();
        // If grand_total is empty, use package_total (for cases with no payment)
        if (!grand_total || grand_total === '') {
            grand_total = total;
            $('#grand_total_bundle').val(grand_total);
        }
        var location_id = $('#add_bundle_location_id').val();
        var appointment_id = $('#add_appointment_id_bundle').val();
        
        console.log('Validation Debug:', {
            random_id: random_id,
            patient_id: patient_id,
            total: total,
            payment_mode_id: payment_mode_id,
            cash_amount: cash_amount,
            grand_total: grand_total,
            location_id: location_id,
            appointment_id: appointment_id
        });

        var formData = {
            'random_id': random_id,
            'patient_id': patient_id,
            'location_id': location_id,
            'total': total,
            'payment_mode_id': payment_mode_id,
            'cash_amount': cash_amount,
            'grand_total': grand_total,
            'is_exclusive': null,
            'plan_type': 'bundle',
            'appointment_id': appointment_id,
            package_bundles: []
        };

        // Collect bundle data from table
        // Bundle table structure: Service Name | Regular Price | Amount | Tax | Total | Action
        // IMPORTANT: Keep commas in numbers - backend will strip them
        // CRITICAL: Use original_bundle_id (from bundles table), NOT package_bundles_bundle (from package_bundles table)
        $('#bundle_services').find('tr:not(.inner_records_hr)').each(function () {
            var $row = $(this);
            var bundleData = {
                serviceName: $row.find('td:nth-child(1) a').text().trim(),
                RegularPrice: $row.find('td:nth-child(2)').text().trim(), // Keep commas
                DiscountName: '-',
                Type: '-',
                DiscountValue: '0.00', // Match format with .00
                Amount: $row.find('td:nth-child(3)').text().trim(), // Keep commas
                Tax: $row.find('td:nth-child(4)').text().trim(),
                Total: $row.find('td:nth-child(5)').text().trim(), // Keep commas
                bundleId: $row.find('td:nth-child(6) input.original_bundle_id').val(), // Original bundle ID from bundles table
                sold_by: $row.find('td:nth-child(6) input.package_bundles_sold_by_bundle').val()
            };
            
            console.log('Bundle data collected:', bundleData);
            formData['package_bundles'].push(bundleData);
        });
        
        console.log('Final formData:', formData);
        
        // Validate that we have bundle data
        if (formData['package_bundles'].length === 0) {
            toastr.error('No bundle found in table. Please add a bundle first.');
            $('#inputfieldMessageBundle').show();
            $(this).attr("disabled", false);
            hideSpinner("-save");
            return false;
        }

        var status = 0;
        if (cash_amount > 0) {
            status = 1;
        }

        if (payment_mode_id == '' && cash_amount > 0) {
            toastr.error("Please select the payment mode");
            return false;
        }

        // Validate required fields
        // cash_amount can be empty string, 0, or a positive number - all are valid
        var cashAmountValid = cash_amount === '' || cash_amount === '0' || parseFloat(cash_amount) >= 0;
        
        console.log('Validation Checks:', {
            'random_id': !!random_id,
            'patient_id > 0': patient_id > 0,
            'total': !!total,
            'status': status,
            'payment_mode_id (if status=1)': status == 1 ? !!payment_mode_id : 'not required',
            'cashAmountValid': cashAmountValid,
            'grand_total': !!grand_total,
            'location_id': !!location_id
        });
        
        if (random_id && (patient_id > 0) && total && (status == 1 ? payment_mode_id : true) && cashAmountValid && grand_total && location_id) {
            showSpinner("-save");

            $.ajax({
                type: 'post',
                url: route('admin.packages.savepackages'),
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    if (response.status) {
                        toastr.success("Bundle plan successfully created");
                        
                        // Close modal and refresh table
                        setTimeout(function() {
                            $('#modal_add_bundle').modal('hide');
                            if (typeof reInitTable === 'function') {
                                reInitTable();
                            } else {
                                location.reload();
                            }
                        }, 1500);
                    } else {
                        toastr.error(response.message || 'Failed to create bundle plan');
                    }

                    hideSpinner("-save");
                },
                error: function (xhr, status, error) {
                    console.error('Error saving bundle plan:', error);
                    toastr.error('Failed to create bundle plan');
                    hideSpinner("-save");
                }
            });
        } else {
            $('#inputfieldMessageBundle').show();
            toastr.error('Please fill all required fields');
            $(this).attr("disabled", false);
            hideSpinner("-save");
        }
    });

    // Handle Add button click
    $("#AddPackageBundle").click(function () {
        $('.create-bundle-error').html('');

        // Check if a service already exists in the table
        if ($('.package_bundles_bundle').length > 0) {
            toastr.error('A plan can have only one bundle at a time. Please remove the current bundle to add a new one.');
            return false;
        }

        // Validation
        if (!$('#add_bundle_location_id').val()) {
            $('#add_bundle_location_id_error').html('Please select centre');
            return false;
        }

        if (!$('#add_patient_id_bundle').val()) {
            $('#add_patient_id_bundle_error').html('Please select patient');
            return false;
        }

        if (!$('#add_appointment_id_bundle').val()) {
            $('#add_appointment_id_bundle_error').html('Please select appointment');
            return false;
        }

        if (!$('#add_service_id_bundle').val()) {
            $('#add_service_id_bundle_error').html('Please select service');
            return false;
        }

        if (!$('#add_sold_by_bundle').val()) {
            $('#add_sold_by_bundle_errorr').html('Please select sold by');
            return false;
        }

        $(this).attr("disabled", true);
        
        var random_id = $('#random_id_bundle').val();
        var service_id = $('#add_service_id_bundle').val(); // Bundle id
        var net_amount = $('#net_amount_bundle').val();
        var package_total = $('#package_total_bundle').val();
        var sold_by = $('#add_sold_by_bundle').val();
        var location_id = $('#add_bundle_location_id').val();
        var user_id = $('#add_patient_id_bundle').val();

        if (service_id && net_amount && location_id) {
            showSpinner("-add");

            var formData = {
                'random_id': random_id,
                'bundle_id': service_id,
                'discount_id': null, // No discounts for bundles
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

                        // Calculate total - use the tax_including_price from response (don't add to existing)
                        let totalAmount = bundlesData.tax_including_price.toLocaleString();
                        let grandTotal = parseFloat(bundlesData.tax_including_price).toFixed(2);
                        
                        // Update package total with the actual total from response (not adding)
                        $("#package_total_bundle").val(grandTotal);
                        $("#grand_total_bundle").val(grandTotal);

                        // Add row to table
                        // bundlesData.id now contains the original bundle_id from bundles table (not package_bundles.id)
                        // Count child services to determine if toggle should be shown
                        let childServiceCount = packageServicesData.length;
                        let serviceNameCell = childServiceCount > 1 
                            ? "<a href='javascript:void(0)' onClick='toggle(" + bundlesData.id + ")'>" + servicesData.service_name + "</a>"
                            : servicesData.service_name;
                        
                        $('#bundle_services').append(
                            "<tr id='table_bundle' class='HR_" + random_id + " HR_" + bundlesData.id + "'>" +
                            "<td>" + serviceNameCell + "</td>" +
                            "<td>" + servicesData.service_price.toLocaleString() + "</td>" +
                            "<td>" + bundlesData.tax_exclusive_net_amount.toLocaleString() + "</td>" +
                            "<td>" + bundlesData.tax_price + "</td>" +
                            "<td>" + grandTotal + "</td>" +
                            "<td>" +
                            "<input type='hidden' class='original_bundle_id' value='" + bundlesData.id + "' />" +
                            "<input type='hidden' class='package_bundles_sold_by_bundle' name='sold_by[]' value='" + servicesData.sold_by + "' />" +
                            "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deleteBundleRowTem(" + bundlesData.id + ")'>" + trashBtn() + "</button>" +
                            "</td>" +
                            "</tr>"
                        );

                        // Add child services only if more than 1 child service
                        if (childServiceCount > 1) {
                            jQuery.each(packageServicesData, function (i, packageService) {
                                let actualPrice = packageService.actual_price ? parseFloat(packageService.actual_price).toFixed(2) : '-';
                                $('#bundle_services').append(
                                    "<tr class='inner_records_hr HR_" + bundlesData.id + " " + bundlesData.id + "' style='display: none; background-color: #f9f9f9;'>" +
                                    "<td>" + packageService.name + "</td>" +
                                    "<td>" + actualPrice + "</td>" +
                                    "<td>" + packageService.tax_exclusive_price.toLocaleString() + "</td>" +
                                    "<td>" + packageService.tax_price + "</td>" +
                                    "<td>" + packageService.tax_including_price.toLocaleString() + "</td>" +
                                    "<td></td>" +
                                    "</tr>"
                                );
                            });

                            // Toggle to show child services
                            toggle(bundlesData.id);
                        }

                        // Clear form fields
                        $('#add_service_id_bundle').val(null).trigger('change');
                        $('#net_amount_bundle').val('');
                        $('#add_sold_by_bundle').val(null).trigger('change');
                        
                        // Hide service required validation alert
                        $('#add_service_id_bundle_error').html('').hide();

                        // Disable location and Add button after service added (only 1 service allowed)
                        $("#add_bundle_location_id").prop("disabled", true);
                        $("#AddPackageBundle").prop("disabled", true);
                        
                        // Disable service and sold by fields
                        $("#add_service_id_bundle").prop("disabled", true);
                        $("#add_sold_by_bundle").prop("disabled", true);

                        //toastr.success('Bundle added successfully.');
                    } else {
                        toastr.error(response.message || 'Failed to add bundle');
                        $("#AddPackageBundle").attr("disabled", false);
                    }

                    hideSpinner("-add");
                },
                error: function (xhr, status, error) {
                    console.error('Error adding bundle:', error);
                    toastr.error('Failed to add service');
                    hideSpinner("-add");
                    $("#AddPackageBundle").attr("disabled", false);
                }
            });
        } else {
            toastr.error('Please fill all required fields');
            $(this).attr("disabled", false);
        }
    });
});

function deleteBundleRowTem(id) {
    if (confirm('Are you sure you want to delete this service?')) {
        $('.HR_' + id).remove();
        
        // Reset total to 0
        $("#package_total_bundle").val('0');
        
        // Re-enable all fields and Add button since service is deleted
        $("#add_bundle_location_id").prop("disabled", false);
        $("#AddPackageBundle").prop("disabled", false);
        $("#add_service_id_bundle").prop("disabled", false);
        $("#add_sold_by_bundle").prop("disabled", false);
        
        // Clear the fields
        $('#add_service_id_bundle').val(null).trigger('change');
        $('#net_amount_bundle').val('');
        $('#add_sold_by_bundle').val(null).trigger('change');
        
        toastr.success('Service deleted successfully');
    }
}

function resetVoucherAddBundle(event) {
    if (event) {
        event.preventDefault();
    }
    
    $('#modal_add_bundle').modal('hide');
    
    setTimeout(function() {
        $("#add_discount_id_bundle").html('<option value="">Select Discount</option>');
        $("#add_patient_id_bundle").val(null).trigger('change');
        $("#net_amount_bundle").val('');
        $("#package_total_bundle").val('');
        $("#grand_total_bundle").val('');
        $('#bundles_add').find('#patient_membership_bundle').val('');
        $('#bundles_add').find('#discount_value_bundle').val('');
        $('#bundles_add').find("#add_appointment_id_bundle").empty();
        $('#bundles_add').find('#add_appointment_id_bundle').val(null).trigger('change');
        $("#bundle_services").html("");
        $('#add_service_id_bundle').val(null).trigger('change');
        $('#add_sold_by_bundle').html('<option value="">Select</option>').val(null).trigger('change');
        
        $('#successMessageBundle').hide();
        $('#duplicateErrBundle').hide();
        $('#inputfieldMessageBundle').hide();
        $('#wrongMessageBundle').hide();
        $('#percentageMessageBundle').hide();
        $('#AlreadyExitMessageBundle').hide();
        $('#datanotexistBundle').hide();
        $('#DiscountRangeBundle').hide();
        
        $('.create-bundle-error').html('');
    }, 300);
}

// Load patient info for bundle create modal when in patient card context
function loadPatientInfoForBundle(patientId) {
    $.ajax({
        url: route('admin.patients.getPatient', { id: patientId }),
        type: 'GET',
        success: function(response) {
            if (response.status && response.data) {
                let patient = response.data.patient;
                let membership = response.data.membership;
                
                // Scope selectors to the modal to avoid conflicts with other elements on page
                let $modal = $('#modal_add_bundle');
                
                // Set patient name (h3 element in patient card context)
                $modal.find('#add-patient-name-bundle').text(patient?.name || '');
                
                // Set membership info (h4 element in patient card context, input in main module)
                let membershipText = 'No Membership';
                if (membership) {
                    // Format: Gold - CA12345 - Active (Exp: Jan 29, 2027)
                    let statusText = membership.is_active ? 'Active' : 'Inactive';
                    let expDate = membership.end_date ? new Date(membership.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
                    membershipText = (membership.type || 'Gold') + ' - ' + (membership.code || '') + ' - ' + statusText + (expDate ? ' (Exp: ' + expDate + ')' : '');
                }
                let $membershipEl = $modal.find('#patient_membership_bundle');
                if ($membershipEl.is('h4')) {
                    $membershipEl.text(membershipText);
                } else {
                    $membershipEl.val(membershipText);
                }
                
                // Set hidden patient ID
                $modal.find('#add_patient_id_bundle').val(patientId);
                // Trigger patient change to load appointments
                getAppointmentsBundle(patientId);
            }
        },
        error: function() {
            console.log('Failed to load patient info for bundle');
        }
    });
}
