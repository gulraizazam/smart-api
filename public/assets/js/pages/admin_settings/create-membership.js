function createMembership(url, id) {
    total_amountArray = [];
    edit_amountArray = [];
    ExistingTotal = 0;
    $('#add_service_id_membership').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    
    // Re-enable all fields that might have been disabled
    $("#add_membership_location_id").prop("disabled", false);
    $("#add_service_id_membership").prop("disabled", false);
    $("#add_sold_by_membership").prop("disabled", false);
    $("#AddPackageMembership").prop("disabled", false);
    
    setTimeout(function () {
        $("#add_discount_id_membership").html('<option value="">Select Discount</option>');
        
        // If in patient card context, pre-fill patient ID
        if (typeof window.isPatientCardContext !== 'undefined' && window.isPatientCardContext && typeof window.patientCardPatientId !== 'undefined') {
            $("#add_patient_id_membership").val(window.patientCardPatientId).trigger('change');
            // Hide patient search field since patient is already selected
            $("#add_patient_id_membership").closest('.fv-row').find('.select2-container').hide();
            // Load patient info for membership modal
            loadPatientInfoForMembership(window.patientCardPatientId);
        } else {
            $("#add_patient_id_membership").val(null).trigger('change');
        }
        
        $(".search_patient").val('');
        $("#net_amount_membership").val('');
        $("#package_total_membership").val('');
        $("#grand_total_membership").val('');
        $('#add_membership_code').val(null).trigger('change');
        $('#memberships_add').find('#patient_membership_membership').val('');
        $('#memberships_add').find('#discount_value_membership').val('');
        $('#memberships_add').find("#add_appointment_id_membership").empty();
        $('#memberships_add').find('#add_appointment_id_membership').val(null).trigger('change');
    }, 500)

    $("#add_discount_type_membership").attr('disabled', true);
    $("#add_discount_value_membership").val('');
    $("#add_discount_value_membership").attr('disabled', true);

    $('#successMessageMembership').hide();
    hideSpinner("-save");
    hideSpinner("-add");
    hideMessages();

    $("#membership_services").html("");
    $("#modal_add_membership").modal("show");
    
    // Initialize Select2 on membership form dropdowns
    setTimeout(function() {
        $('#add_membership_location_id').select2({
            dropdownParent: $('#modal_add_membership')
        });
        // Patient search is initialized with AJAX search
        if ($('#add_patient_id_membership').hasClass('select2-hidden-accessible')) {
            $('#add_patient_id_membership').select2('destroy');
        }
        $('#add_patient_id_membership').select2({
            dropdownParent: $('#modal_add_membership'),
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
        $('#add_appointment_id_membership').select2({
            dropdownParent: $('#modal_add_membership')
        });
        $('#add_service_id_membership').select2({
            dropdownParent: $('#modal_add_membership')
        });
        $('#add_sold_by_membership').select2({
            dropdownParent: $('#modal_add_membership')
        });
        $('#payment_mode_id_membership').select2({
            dropdownParent: $('#modal_add_membership')
        });
        // Initialize Code select2 with AJAX search
        initMembershipCodeSelect2();
    }, 100);

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setMembershipData(response);
            $("#cash_amount_membership").val(0);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setMembershipData(response) {
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

    $("#add_discount_id_membership").html(discount_options);
    $("#payment_mode_id_membership").html(payment_options);

    $("#add_membership_location_id").html(location_options).val(appointmentinformation?.location_id);
    $("#random_id_membership").val(random_id);
    $('#cash_amount_membership').prop('disabled', true);

    getServicesMembership();

    getUserCentre();
}

function getServicesMembership(action) {
    hideMessages();

    let location = $("#add_membership_location_id").val();
    let patientId = $("#add_patient_id_membership").val();

    // Don't call API if no location is selected
    if (!location || location == '') {
        $("#add_service_id_membership").html('<option value="">Select Membership Type</option>');
        return;
    }

    let queryParams = {
        location_id: location
    };
    
    // Include patient_id if selected (to show renewals for expired memberships)
    if (patientId) {
        queryParams.patient_id = patientId;
    }

    let url = route('admin.packages.getmemberships', {
        _query: queryParams
    });

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setMemberships(response);
            
            // In patient card context, also load appointments when location changes
            if (typeof window.isPatientCardContext !== 'undefined' && window.isPatientCardContext && typeof window.patientCardPatientId !== 'undefined') {
                getAppointmentsMembership(window.patientCardPatientId);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            $('#datanotexistMembership').show();
            $("#add_service_id_membership").html('<option value="">Select Membership Type</option>');
        }
    });
}

function setMemberships(response) {
    try {
        // Check if response has data and memberships array
        if (!response.data || !response.data.memberships) {
            $('#datanotexistMembership').show();
            $("#add_service_id_membership").html('<option value="">Select </option>');
            return;
        }

        let memberships = response.data.memberships;
        let membership_options = '<option value="">Select </option>';

        Object.values(memberships).forEach(function (membership) {
            membership_options += '<option value="' + membership.id + '">' + membership.name + ' - Rs. ' + membership.price + '</option>';
        });

        $("#add_service_id_membership").html(membership_options);

    } catch (error) {
        console.error('Error setting memberships:', error);
        showException(error);
    }
}

function getServiceDiscountMembership(element) {
    hideMessages();
    var membership_type_id = element.val();
    var location_id = $('#add_membership_location_id').val();

    if (membership_type_id == "") {
        $("#add_service_id_membership_error").html('Service is required').show();
    } else {
        $("#add_service_id_membership_error").html('').hide();
    }

    // Clear price, code and sold by if no membership selected
    if (!membership_type_id) {
        $("#net_amount_membership").val('');
        $("#add_sold_by_membership").html('<option value="">Select</option>');
        $('#add_membership_code').val(null).trigger('change');
        return;
    }

    // Reset code dropdown when service changes
    $('#add_membership_code').val(null).trigger('change');
    // Re-init code select2 to filter by new membership type
    initMembershipCodeSelect2();

    // Fetch price from getmembershipinfo
    $.ajax({
        type: 'get',
        url: route('admin.packages.getmembershipinfo'),
        data: {
            'membership_id': membership_type_id
        },
        success: function (response) {
            if (response.status && response.data) {
                $("#net_amount_membership").val((response.data.net_amount).toFixed(2));
                $("#net_amount_membership").prop("disabled", true);
            } else {
                $("#net_amount_membership").val('');
            }
            // Fetch sold by users
            if (location_id) {
                getSoldByMembership(location_id);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching membership info:', error);
            $("#net_amount_membership").val('');
        }
    });
}

function initMembershipCodeSelect2() {
    if ($('#add_membership_code').hasClass('select2-hidden-accessible')) {
        $('#add_membership_code').select2('destroy');
    }
    $('#add_membership_code').select2({
        dropdownParent: $('#modal_add_membership'),
        width: '100%',
        placeholder: 'Search Code',
        allowClear: true,
        ajax: {
            url: route('admin.packages.searchmembershipcodes'),
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return {
                    search: params.term,
                    membership_type_id: $('#add_service_id_membership').val()
                };
            },
            processResults: function (data) {
                if (data.status && data.data && data.data.codes) {
                    return {
                        results: data.data.codes.map(function(item) {
                            var label = item.code;
                            if (item.is_assigned) {
                                label += ' (Assigned)';
                            }
                            return {
                                id: item.id,
                                text: label,
                                code: item.code,
                                is_assigned: item.is_assigned
                            };
                        })
                    };
                }
                return { results: [] };
            },
            cache: true
        },
        minimumInputLength: 2
    });

    // Handle code selection - check if assigned
    $('#add_membership_code').off('select2:select.membership').on('select2:select.membership', function (e) {
        var selectedData = e.params.data;
        if (selectedData.is_assigned) {
            toastr.error('This code is already assigned to another patient.');
            $(this).val(null).trigger('change');
            $('#add_membership_code_error').html('Code already assigned');
        } else {
            $('#add_membership_code_error').html('');
        }
    });
}

function getSoldByMembership(location_id) {
    let patient_id = $('#add_patient_id_membership').val();
    
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
                    setSoldByMembership(response.data);
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
                    setSoldByMembership(response.data);
                } else {
                    // Set empty sold by dropdown
                    $("#add_sold_by_membership").html('<option value="">Select</option>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching sold by users:', error);
                $("#add_sold_by_membership").html('<option value="">Select</option>');
            }
        });
    }
}

function setSoldByMembership(data) {
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

        $("#add_sold_by_membership").html(user_options);

    } catch (error) {
        console.error('Error setting sold by:', error);
        showException(error);
    }
}

function getDiscountInfoMembership(element) {
    // Membership functionality - can be implemented later if needed
}

function changeDiscountMembership(element) {
    // Membership functionality - can be implemented later if needed
}

function getDiscountValueMembership(element) {
    // Membership functionality - can be implemented later if needed
}

function getAppointmentsMembership(patient) {
    let location = $("#add_membership_location_id").val();

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
                setAppointmentsMembership(response);
                setSoldByMembership(response.data);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });
    }
}

function setAppointmentsMembership(response) {
    try {
        let appointments = response.data.appointments;
        let appointment_options = '<option value="">Select Appointment</option>';
        let membership = response.data.membership;
        let appointmentKeys = [];

        // Check if appointments object has any keys
        if (appointments && Object.keys(appointments).length > 0) {
            Object.values(appointments).forEach(function (value) {
                appointment_options += '<option value="' + value.id + '">' + value.name + '</option>';
                appointmentKeys.push(value.id);
            });
        }
        
        $("#add_appointment_id_membership").html(appointment_options);
        
        // Auto-select if only one appointment exists
        if (appointmentKeys.length === 1) {
            $("#add_appointment_id_membership").val(appointmentKeys[0]).trigger('change');
        }
        
        $("#patient_membership_membership").val(membership);
        $("#patient_membership_membership").attr('disabled', true);
    } catch (error) {
        console.error('Error setting appointments:', error);
        showException(error);
    }
}

function keyfunction_grandtotal_membership() {
    hideMessages();

    var cash_amount = parseFloat($('#cash_amount_membership').val()) || 0;
    var total = parseFloat($('#package_total_membership').val()) || 0;

    // Calculate remaining = Total - Cash Amount
    var remaining = total - cash_amount;
    
    // Ensure remaining is not negative
    if (remaining < 0) {
        remaining = 0;
    }
    
    $("#grand_total_membership").val(remaining.toFixed(2));
}

// Initialize patient selection event handlers
$(document).ready(function() {
    // Handle cash amount input to calculate grand total
    $("#cash_amount_membership").on('input', function () {
        var value = $(this).val();
        
        // Allow only numbers and decimal point
        if (!/^\d*\.?\d*$/.test(value)) {
            $(this).val(value.slice(0, -1));
            return;
        }

        // Trigger grand total calculation
        keyfunction_grandtotal_membership();
    });

    // Handle payment mode change
    $('#payment_mode_id_membership').on('change', function () {
        if ($(this).val()) {
            // Payment mode selected - enable cash amount field
            $('#cash_amount_membership').prop('disabled', false);
        } else {
            // No payment mode selected - disable cash amount and set grand_total to package_total
            $('#cash_amount_membership').prop('disabled', true);
            var packageTotal = $('#package_total_membership').val() || '0';
            $("#grand_total_membership").val(packageTotal);
        }
    });

    // Handle patient selection from Select2 dropdown
    $('#add_patient_id_membership').on('select2:select', function (e) {
        var patientId = $(this).val();
        if (patientId) {
            // Clear appointment dropdown
            $("#add_appointment_id_membership").empty();
            $('#add_appointment_id_membership').append('<option value="">Select Appointment</option>');
            $('#add_appointment_id_membership').val(null).trigger('change');
            
            // Load appointments and membership for selected patient
            getAppointmentsMembership(patientId);
            
            // Refresh membership types to show renewals for expired memberships
            getServicesMembership();
        }
    });

    // Clear appointments and membership when patient is cleared
    $('#add_patient_id_membership').on('select2:clear', function (e) {
        $("#add_appointment_id_membership").empty();
        $('#add_appointment_id_membership').append('<option value="">Select Appointment</option>');
        $('#add_appointment_id_membership').val(null).trigger('change');
        $('#patient_membership_membership').val('No data');
        
        // Refresh membership types (will show only parent memberships when no patient selected)
        getServicesMembership();
    });

    // Handle Save button click (final save)
    $("#AddPackageFinalMembership").click(function () {
        $('.create-membership-error').html('');
        
        // Validate payment mode and cash amount
        if ($('#payment_mode_id_membership').val()) {
            if (!$('#cash_amount_membership').val()) {
                $('#cash_amount_membership_error').html('Please enter cash amount');
                return false;
            }
        }

        hideMessages();

        var random_id = $('#random_id_membership').val();
        var patient_id = $('#add_patient_id_membership').val();
        var total = $('#package_total_membership').val();
        var payment_mode_id = $('#payment_mode_id_membership').val();
        var cash_amount = $('#cash_amount_membership').val();
        var grand_total = $('#grand_total_membership').val();
        // If grand_total is empty, use package_total (for cases with no payment)
        if (!grand_total || grand_total === '') {
            grand_total = total;
            $('#grand_total_membership').val(grand_total);
        }
        var location_id = $('#add_membership_location_id').val();
        var appointment_id = $('#add_appointment_id_membership').val();
        
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
            'plan_type': 'membership',
            'appointment_id': appointment_id,
            package_memberships: []
        };

        // Collect membership data from table
        $('#membership_services').find('tr:not(.inner_records_hr)').each(function () {
            var $row = $(this);
            var totalTd = $row.find('td:nth-child(5)');
            var membershipData = {
                serviceName: $row.find('td:nth-child(1) a').text().trim(),
                RegularPrice: $row.find('td:nth-child(2)').text().trim(),
                DiscountName: '-',
                Type: '-',
                DiscountValue: '0.00',
                Amount: $row.find('td:nth-child(3)').text().trim(),
                Tax: $row.find('td:nth-child(4)').text().trim(),
                Total: totalTd.clone().children().remove().end().text().trim(),
                membershipId: totalTd.find('input.original_membership_id').val(),
                membershipCodeId: totalTd.find('input.membership_code_id_hidden').val(),
                sold_by: totalTd.find('input.package_memberships_sold_by_membership').val()
            };
            
            console.log('Membership data collected:', membershipData);
            formData['package_memberships'].push(membershipData);
        });
        
        console.log('Final formData:', formData);
        
        // Validate that we have membership data
        if (formData['package_memberships'].length === 0) {
            toastr.error('No membership found in table. Please add a membership first.');
            $('#inputfieldMessageMembership').show();
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
                type: 'get',
                url: route('admin.packages.savepackages'),
                data: formData,
                success: function (response) {
                    if (response.status) {
                        toastr.success("Membership plan successfully created");
                        
                        // Close modal and refresh table
                        setTimeout(function() {
                            $('#modal_add_membership').modal('hide');
                            if (typeof reInitTable === 'function') {
                                reInitTable();
                            } else {
                                location.reload();
                            }
                        }, 1500);
                    } else {
                        toastr.error(response.message || 'Failed to create membership plan');
                    }

                    hideSpinner("-save");
                },
                error: function (xhr, status, error) {
                    console.error('Error saving membership plan:', error);
                    toastr.error('Failed to create membership plan');
                    hideSpinner("-save");
                }
            });
        } else {
            $('#inputfieldMessageMembership').show();
            toastr.error('Please fill all required fields');
            $(this).attr("disabled", false);
            hideSpinner("-save");
        }
    });

    // Handle Add button click
    $("#AddPackageMembership").click(function () {
        $('.create-membership-error').html('');

        // Check if a service already exists in the table
        if ($('.package_memberships_membership').length > 0) {
            toastr.error('A plan can have only one membership at a time. Please remove the current membership to add a new one.');
            return false;
        }

        // Validation
        if (!$('#add_membership_location_id').val()) {
            $('#add_membership_location_id_error').html('Please select centre');
            return false;
        }

        if (!$('#add_patient_id_membership').val()) {
            $('#add_patient_id_membership_error').html('Please select patient');
            return false;
        }

        if (!$('#add_appointment_id_membership').val()) {
            $('#add_appointment_id_membership_error').html('Please select appointment');
            return false;
        }

        if (!$('#add_service_id_membership').val()) {
            $('#add_service_id_membership_error').html('Please select service');
            return false;
        }

        if (!$('#add_membership_code').val()) {
            $('#add_membership_code_error').html('Please select a code');
            return false;
        }

        if (!$('#add_sold_by_membership').val()) {
            $('#add_sold_by_membership_errorr').html('Please select sold by');
            return false;
        }

        $(this).attr("disabled", true);
        
        var random_id = $('#random_id_membership').val();
        var service_id = $('#add_service_id_membership').val(); // Membership type id
        var membership_code_id = $('#add_membership_code').val(); // Membership code id
        var net_amount = $('#net_amount_membership').val();
        var package_total = $('#package_total_membership').val();
        var sold_by = $('#add_sold_by_membership').val();
        var location_id = $('#add_membership_location_id').val();
        var user_id = $('#add_patient_id_membership').val();

        if (service_id && net_amount && location_id) {
            showSpinner("-add");

            var formData = {
                'random_id': random_id,
                'membership_id': service_id,
                'membership_code_id': membership_code_id,
                'discount_id': null,
                'net_amount': net_amount,
                'discount_type': null,
                'discount_price': null,
                'package_total': package_total,
                'is_exclusive': null,
                'location_id': location_id,
                'user_id': user_id,
                'package_memberships[]': [],
                'sold_by': sold_by
            };

            $(".package_memberships_membership").each(function () {
                formData['package_memberships[]'].push($(this).val());
            });

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'post',
                url: route('admin.packages.savemembership_service'),
                data: formData,
                success: function (response) {
                    if (response.status) {
                        let servicesData = response.data.servicesData;
                        let membershipsData = servicesData.membershipsData;
                        let packageServicesData = servicesData.packageServicesData;

                        // Calculate total
                        let totalAmount = membershipsData.tax_including_price.toLocaleString();
                        let grandTotal = totalAmount.replace(/,/g, '');
                        
                        // Update package total
                        let currentTotal = parseFloat($('#package_total_membership').val() || 0);
                        let newTotal = currentTotal + parseFloat(membershipsData.tax_including_price);
                        $("#package_total_membership").val(newTotal.toFixed(2));

                        // Add row to table (no Action column for membership)
                        $('#membership_services').append(
                            "<tr id='table_membership' class='HR_" + random_id + " HR_" + membershipsData.id + "'>" +
                            "<td><a href='javascript:void(0)' onClick='toggle(" + membershipsData.id + ")'>" + servicesData.service_name + "</a></td>" +
                            "<td>" + servicesData.service_price.toLocaleString() + "</td>" +
                            "<td>" + membershipsData.tax_exclusive_net_amount.toLocaleString() + "</td>" +
                            "<td>" + membershipsData.tax_price + "</td>" +
                            "<td>" + grandTotal +
                            "<input type='hidden' class='original_membership_id' value='" + membershipsData.id + "' />" +
                            "<input type='hidden' class='membership_code_id_hidden' value='" + (membership_code_id || '') + "' />" +
                            "<input type='hidden' class='package_memberships_sold_by_membership' name='sold_by[]' value='" + servicesData.sold_by + "' />" +
                            "</td>" +
                            "</tr>"
                        );

                        // Add child services
                        jQuery.each(packageServicesData, function (i, packageService) {
                            let consume = packageService.is_consumed == '0' ? 'No' : 'Yes';
                            $('#membership_services').append(
                                "<tr class='inner_records_hr HR_" + membershipsData.id + " " + membershipsData.id + "'>" +
                                "<td></td>" +
                                "<td>" + packageService.name + "</td>" +
                                "<td>Amount: " + packageService.tax_exclusive_price.toLocaleString() + "</td>" +
                                "<td>Tax: " + packageService.tax_price + "</td>" +
                                "<td>Total Amount: " + packageService.tax_including_price.toLocaleString() + "</td>" +
                                "<td></td>" +
                                "</tr>"
                            );
                        });

                        // Toggle to show child services
                        toggle(membershipsData.id);

                        // Clear form fields
                        $('#add_service_id_membership').val(null).trigger('change');
                        $('#net_amount_membership').val('');
                        $('#add_sold_by_membership').val(null).trigger('change');
                        
                        // Hide service required validation alert
                        $('#add_service_id_membership_error').html('').hide();

                        // Set cash amount to 0 by default and calculate remaining
                        var totalVal = $('#package_total_membership').val();
                        $('#cash_amount_membership').val(0);
                        $('#grand_total_membership').val(totalVal); // Remaining = Total - Cash Amount
                        
                        // Enable cash amount field for editing
                        $('#cash_amount_membership').prop('disabled', false);

                        // Disable location and Add button after service added (only 1 service allowed)
                        $("#add_membership_location_id").prop("disabled", true);
                        $("#AddPackageMembership").prop("disabled", true);
                        
                        // Disable service and sold by fields
                        $("#add_service_id_membership").prop("disabled", true);
                        $("#add_sold_by_membership").prop("disabled", true);

                    } else {
                        toastr.error(response.message || 'Failed to add membership');
                        $("#AddPackageMembership").attr("disabled", false);
                    }

                    hideSpinner("-add");
                },
                error: function (xhr, status, error) {
                    console.error('Error adding membership:', error);
                    toastr.error('Failed to add service');
                    hideSpinner("-add");
                    $("#AddPackageMembership").attr("disabled", false);
                }
            });
        } else {
            toastr.error('Please fill all required fields');
            $(this).attr("disabled", false);
        }
    });
});

function deleteMembershipRowTem(id) {
    if (confirm('Are you sure you want to delete this service?')) {
        $('.HR_' + id).remove();
        
        // Reset total to 0
        $("#package_total_membership").val('0');
        
        // Re-enable all fields and Add button since service is deleted
        $("#add_membership_location_id").prop("disabled", false);
        $("#AddPackageMembership").prop("disabled", false);
        $("#add_service_id_membership").prop("disabled", false);
        $("#add_sold_by_membership").prop("disabled", false);
        
        // Clear the fields
        $('#add_service_id_membership').val(null).trigger('change');
        $('#add_membership_code').val(null).trigger('change');
        $('#net_amount_membership').val('');
        $('#add_sold_by_membership').val(null).trigger('change');
        
        toastr.success('Service deleted successfully');
    }
}

function resetVoucherAddMembership(event) {
    if (event) {
        event.preventDefault();
    }
    
    $('#modal_add_membership').modal('hide');
    
    setTimeout(function() {
        $("#add_discount_id_membership").html('<option value="">Select Discount</option>');
        $("#add_patient_id_membership").val(null).trigger('change');
        $("#net_amount_membership").val('');
        $("#package_total_membership").val('');
        $("#grand_total_membership").val('');
        $('#memberships_add').find('#patient_membership_membership').val('');
        $('#memberships_add').find('#discount_value_membership').val('');
        $('#memberships_add').find("#add_appointment_id_membership").empty();
        $('#memberships_add').find('#add_appointment_id_membership').val(null).trigger('change');
        $("#membership_services").html("");
        $('#add_service_id_membership').val(null).trigger('change');
        $('#add_membership_code').val(null).trigger('change');
        $('#add_sold_by_membership').html('<option value="">Select</option>').val(null).trigger('change');
        
        $('#successMessageMembership').hide();
        $('#duplicateErrMembership').hide();
        $('#inputfieldMessageMembership').hide();
        $('#wrongMessageMembership').hide();
        $('#percentageMessageMembership').hide();
        $('#AlreadyExitMessageMembership').hide();
        $('#datanotexistMembership').hide();
        $('#DiscountRangeMembership').hide();
        
        $('.create-membership-error').html('');
    }, 300);
}

// Load patient info for membership create modal when in patient card context
function loadPatientInfoForMembership(patientId) {
    $.ajax({
        url: route('admin.patients.getPatient', { id: patientId }),
        type: 'GET',
        success: function(response) {
            if (response.status && response.data) {
                let patient = response.data.patient;
                let membership = response.data.membership;
                
                // Scope selectors to the modal to avoid conflicts with other elements on page
                let $modal = $('#modal_add_membership');
                
                // Set patient name (h3 element in patient card context)
                $modal.find('#add-patient-name-membership').text(patient?.name || '');
                
                // Set membership info (h4 element in patient card context, input in main module)
                let membershipText = 'No Membership';
                if (membership) {
                    let statusText = membership.is_active ? 'Active' : 'Inactive';
                    let expDate = membership.end_date ? new Date(membership.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
                    membershipText = (membership.type || 'Gold') + ' - ' + (membership.code || '') + ' - ' + statusText + (expDate ? ' (Exp: ' + expDate + ')' : '');
                }
                let $membershipEl = $modal.find('#patient_membership_membership');
                if ($membershipEl.is('h4')) {
                    $membershipEl.text(membershipText);
                } else {
                    $membershipEl.val(membershipText);
                }
                
                // Set hidden patient ID
                $modal.find('#add_patient_id_membership').val(patientId);
                // Trigger patient change to load appointments
                getAppointmentsMembership(patientId);
            }
        },
        error: function() {
            console.log('Failed to load patient info for membership');
        }
    });
}

// Edit Membership Modal Functions
function editMembership(url, id) {
    $("#modal_edit_membership").modal("show");
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {
            setEditMembershipData(response);
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setEditMembershipData(response) {
    try {
        let data = response.data;
        let package = data.package;
        let packagebundles = data.packagebundles;
        let packageadvances = data.packageadvances;
        let packageservices = data.packageservices || [];
        let membership = data.membership;
        let paymentmodes = data.paymentmodes;
        let appointmentArray = data.appointmentArray || [];
        
        // Reset cash amount and payment mode fields
        $('#edit_membership_cash_amount').val(0).prop('disabled', true);
        $('#edit_membership_payment_mode_id').val('').trigger('change');
        
        // Set package ID
        $('#edit_package_id_membership').val(package.id);
        $('#edit_random_id_membership').val(package.random_id);
        
        // Set patient info
        $('#edit_patient_name_membership').text(package.user ? package.user.name : '-');
        $('#edit_patient_id_membership').val(package.patient_id);
        
        // Set membership info - membership is returned as a formatted string from API
        if (membership && typeof membership === 'string') {
            $('#edit_patient_membership_membership').text(membership);
        } else if (membership && membership.code) {
            // Fallback for object format
            let membershipText = membership.code;
            if (membership.end_date) {
                let endDate = new Date(membership.end_date);
                let now = new Date();
                let status = endDate < now ? 'Expired' : 'Active';
                membershipText += ' - ' + status + ' (Exp: ' + formatDate(membership.end_date, 'MMM DD, yyyy') + ')';
            }
            $('#edit_patient_membership_membership').text(membershipText);
        } else {
            $('#edit_patient_membership_membership').text('-');
        }
        
        // Set location info
        let locationName = package.location ? package.location.name : '-';
        $('#edit_location_name_membership').text(locationName);
        $('#edit_location_id_membership').val(package.location_id);
        
        // Populate appointment dropdown - appointmentArray is an object, not an array
        let appointmentOptions = '<option value="">Select Appointment</option>';
        if (appointmentArray && typeof appointmentArray === 'object') {
            Object.values(appointmentArray).forEach(function(apt) {
                // apt.id is like "369475.A", package.appointment_id is like 369475
                // Compare by extracting the numeric part
                let aptIdNumeric = apt.id ? apt.id.toString().split('.')[0] : '';
                let selected = aptIdNumeric == package.appointment_id ? 'selected' : '';
                appointmentOptions += '<option value="' + apt.id + '" ' + selected + '>' + apt.name + '</option>';
            });
        }
        $('#edit_membership_appointment_id').html(appointmentOptions);
        
        // Populate payment modes dropdown
        let paymentOptions = '<option value="">Select Payment Mode</option>';
        if (paymentmodes) {
            Object.entries(paymentmodes).forEach(function([id, name]) {
                paymentOptions += '<option value="' + id + '">' + name + '</option>';
            });
        }
        $('#edit_membership_payment_mode_id').html(paymentOptions);
        
        // Populate membership items table with Sold By and Action columns
        let serviceOptions = '';
        let totalAmount = 0;
        
        if (packagebundles && packagebundles.length) {
            packagebundles.forEach(function(bundle) {
                let serviceName = '-';
                if (bundle.membership_type && bundle.membership_type.name) {
                    serviceName = bundle.membership_type.name;
                } else if (bundle.bundle && bundle.bundle.name) {
                    serviceName = bundle.bundle.name;
                }
                
                // Get sold by names from package services
                let soldByNames = [];
                if (packageservices && packageservices.length) {
                    packageservices.forEach(function(ps) {
                        if (ps.package_bundle_id == bundle.id && ps.sold_by && ps.sold_by.name) {
                            if (!soldByNames.includes(ps.sold_by.name)) {
                                soldByNames.push(ps.sold_by.name);
                            }
                        }
                    });
                }
                let soldByText = soldByNames.length > 0 ? soldByNames.join(', ') : '-';
                
                serviceOptions += '<tr class="HR_' + bundle.id + '">';
                serviceOptions += '<td><span style="color: #3699FF;">' + serviceName + '</span></td>';
                serviceOptions += '<td>' + number_format(bundle.service_price, 2) + '</td>';
                serviceOptions += '<td>' + number_format(bundle.tax_exclusive_net_amount, 2) + '</td>';
                serviceOptions += '<td>' + number_format(bundle.tax_price, 2) + '</td>';
                serviceOptions += '<td>' + number_format(bundle.tax_including_price, 2) + '</td>';
                serviceOptions += '<td>' + soldByText + '</td>';
                serviceOptions += '</tr>';
                
                totalAmount += parseFloat(bundle.tax_including_price) || 0;
            });
        } else {
            serviceOptions = '<tr><td colspan="6" class="text-center">No record found</td></tr>';
        }
        $('#edit_membership_services').html(serviceOptions);
        
        // Set totals
        $('#edit_package_total_membership').val(number_format(totalAmount, 2));
        
        // Calculate cash received and balance
        let cashReceived = 0;
        let settledAmount = 0;
        
        if (packageadvances && packageadvances.length) {
            packageadvances.forEach(function(advance) {
                if (advance.cash_flow === 'in' && advance.is_cancel == 0) {
                    cashReceived += parseFloat(advance.cash_amount) || 0;
                }
                if (advance.cash_flow === 'out') {
                    settledAmount += parseFloat(advance.cash_amount) || 0;
                }
            });
        }
        
        let balance = totalAmount - cashReceived;
        $('#edit_membership_grand_total').val(number_format(balance, 2));
        
        // Store the initial balance for cash amount calculation
        $('#edit_membership_grand_total').data('initial-balance', balance);
        
        // Populate payment history with Edit/Delete actions
        let historyOptions = '';
        if (packageadvances && packageadvances.length) {
            packageadvances.forEach(function(advance) {
                if (advance.cash_amount != 0) {
                    historyOptions += '<tr id="history_cash_row_' + advance.id + '">';
                    
                    // Payment mode
                    if (advance.is_tax == 1 && advance.cash_flow === 'out') {
                        historyOptions += '<td>Tax</td>';
                    } else {
                        historyOptions += '<td>' + (advance.paymentmode ? advance.paymentmode.name : '-') + '</td>';
                    }
                    
                    // Cash flow
                    if (advance.is_refund == 1) {
                        historyOptions += '<td>out / refund</td>';
                    } else if (advance.is_setteled == 1) {
                        historyOptions += '<td>out / settled</td>';
                    } else {
                        historyOptions += '<td>' + advance.cash_flow + '</td>';
                    }
                    
                    historyOptions += '<td>' + number_format(advance.cash_amount, 2) + '</td>';
                    historyOptions += '<td>' + formatDate(advance.created_at, 'MMM, DD yyyy hh:mm A') + '</td>';
                    
                    // Action column - only for 'in' cash flow
                    historyOptions += '<td>';
                    if (advance.cash_flow === 'in') {
                        if (typeof permissions !== 'undefined' && permissions.plans_cash_edit) {
                            historyOptions += '<a onclick="planeEdit(' + advance.id + ', ' + package.id + ');" class="btn btn-sm btn-info" href="javascript:void(0);">Edit</a>&nbsp;';
                        }
                        if (typeof permissions !== 'undefined' && permissions.plans_cash_delete) {
                            historyOptions += '<button onclick="deletePlaneHistory(`' + route('admin.packages.delete_cash') + '`, ' + advance.id + ');" class="btn btn-sm btn-danger">Delete</button>';
                        }
                    }
                    historyOptions += '</td>';
                    
                    historyOptions += '</tr>';
                }
            });
        }
        
        if (!historyOptions) {
            historyOptions = '<tr><td colspan="5" class="text-center">No record found</td></tr>';
        }
        $('#edit_membership_payment_history').html(historyOptions);
        
        // Handle payment mode change to enable/disable cash amount
        $('#edit_membership_payment_mode_id').off('change').on('change', function() {
            if ($(this).val()) {
                $('#edit_membership_cash_amount').prop('disabled', false);
            } else {
                $('#edit_membership_cash_amount').val(0);
                $('#edit_membership_cash_amount').prop('disabled', true);
                // Reset remaining to initial balance when payment mode cleared
                var initialBalance = parseFloat($('#edit_membership_grand_total').data('initial-balance')) || 0;
                $('#edit_membership_grand_total').val(initialBalance.toFixed(2));
            }
        });
        
        // Handle cash amount change to update remaining balance
        $('#edit_membership_cash_amount').off('input').on('input', function() {
            var cashAmount = parseFloat($(this).val()) || 0;
            // Use the initial balance (current remaining), not the total price
            var initialBalance = parseFloat($('#edit_membership_grand_total').data('initial-balance')) || 0;
            var remaining = initialBalance - cashAmount;
            if (remaining < 0) {
                remaining = 0;
            }
            $('#edit_membership_grand_total').val(remaining.toFixed(2));
        });
        
        // Handle save button click
        $('#EditMembershipFinal').off('click').on('click', function() {
            var packageId = $('#edit_package_id_membership').val();
            var patientId = $('#edit_patient_id_membership').val();
            var locationId = $('#edit_location_id_membership').val();
            var appointmentId = $('#edit_membership_appointment_id').val();
            var paymentModeId = $('#edit_membership_payment_mode_id').val();
            var cashAmount = $('#edit_membership_cash_amount').val() || 0;
            var grandTotal = $('#edit_membership_grand_total').val() || 0;
            
            // Validate required fields
            if (!appointmentId) {
                toastr.error('Please select an appointment');
                return;
            }
            
            if (paymentModeId && (!cashAmount || cashAmount == 0)) {
                toastr.error('Please enter cash amount');
                return;
            }
            
            // Disable button and show spinner
            $(this).prop('disabled', true);
            
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.packages.update_membership_plan'),
                type: 'POST',
                data: {
                    package_id: packageId,
                    patient_id: patientId,
                    location_id: locationId,
                    appointment_id: appointmentId,
                    payment_mode_id: paymentModeId,
                    cash_amount: cashAmount,
                    grand_total: grandTotal
                },
                success: function(response) {
                    if (response.status) {
                        toastr.success(response.message || 'Membership updated successfully');
                        closeEditMembershipModal();
                        // Reload datatable if exists
                        if (typeof reInitTable === 'function') {
                            reInitTable();
                        }
                    } else {
                        toastr.error(response.message || 'Failed to update membership');
                    }
                    $('#EditMembershipFinal').prop('disabled', false);
                },
                error: function(xhr) {
                    console.error('Error updating membership:', xhr);
                    toastr.error('Failed to update membership');
                    $('#EditMembershipFinal').prop('disabled', false);
                }
            });
        });
        
    } catch (error) {
        console.error('Error setting edit membership data:', error);
    }
}

function closeEditMembershipModal() {
    $("#modal_edit_membership").modal("hide");
}

// Helper function for number formatting
function number_format(number, decimals) {
    if (typeof number_format_helper === 'function') {
        return number_format_helper(number, decimals);
    }
    if (isNaN(number)) return '0.00';
    return parseFloat(number).toFixed(decimals || 2);
}
