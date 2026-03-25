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
    
    // Reset student documents section
    $('#student_document_section').hide();
    isStudentMembership = false;
    resetStudentDocuments();
    
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

    // Auto-select location if user has only one location assigned
    if (locations) {
        // Filter out "All Cities-All Centres" option
        var validLocations = Object.entries(locations).filter(function(location) {
            return location[1] !== 'All Cities-All Centres';
        });
        if (validLocations.length === 1) {
            $("#add_membership_location_id").val(validLocations[0][0]).trigger('change');
        }
    }

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
        toggleStudentDocumentSection('');
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
                
                // Toggle student document section based on membership type name
                toggleStudentDocumentSection(response.data.membership_name || '');
            } else {
                $("#net_amount_membership").val('');
                toggleStudentDocumentSection('');
            }
            // Fetch sold by users
            if (location_id) {
                getSoldByMembership(location_id);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching membership info:', error);
            $("#net_amount_membership").val('');
            toggleStudentDocumentSection('');
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
        
        $("#add_appointment_id_membership").html(appointment_options);
        
        // Pre-select the latest consultation
        if (latestConsultationId) {
            $("#add_appointment_id_membership").val(latestConsultationId).trigger('change');
        } else if (appointmentKeys.length === 1) {
            // Fallback: Auto-select if only one appointment exists
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

    // Calculate remaining = Total - Cash Amount (negative means overpayment)
    var remaining = total - cash_amount;
    
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

        // No validation for student documents - they are optional
        
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

            // Create FormData for file upload support
            var submitData = new FormData();
            submitData.append('random_id', random_id);
            submitData.append('patient_id', patient_id);
            submitData.append('location_id', location_id);
            submitData.append('total', total);
            submitData.append('payment_mode_id', payment_mode_id || '');
            submitData.append('cash_amount', cash_amount || '0');
            submitData.append('grand_total', grand_total);
            submitData.append('is_exclusive', '');
            submitData.append('plan_type', 'membership');
            submitData.append('appointment_id', appointment_id);
            submitData.append('package_memberships', JSON.stringify(formData.package_memberships));

            // Add student documents if student membership
            if (isStudentMembership) {
                var documentCount = 0;
                $('.student-document-input').each(function() {
                    if (this.files && this.files[0]) {
                        submitData.append('student_documents[]', this.files[0]);
                        documentCount++;
                    }
                });
                submitData.append('has_student_documents', documentCount > 0 ? '1' : '0');
            }

            $.ajax({
                type: 'POST',
                url: route('admin.packages.savepackages'),
                data: submitData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
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

                        // Don't clear membership type field to keep document section visible
                        // Only clear other fields
                        $('#net_amount_membership').val('');
                        $('#add_sold_by_membership').val(null).trigger('change');
                        $('#add_membership_code').val(null).trigger('change');
                        
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
        
        // Check for student membership and show document section
        let membershipTypeId = null;
        let membershipTypeName = null;
        let existingDocuments = data.student_documents || [];
        
        console.log('Full data received:', data);
        console.log('Package ID being edited:', data.package ? data.package.id : 'unknown');
        console.log('student_documents from data:', data.student_documents);
        
        if (packagebundles && packagebundles.length) {
            let firstBundle = packagebundles[0];
            console.log('First bundle:', firstBundle);
            console.log('Membership type:', firstBundle.membership_type);
            if (firstBundle.membership_type) {
                membershipTypeId = firstBundle.membership_type_id;
                membershipTypeName = firstBundle.membership_type.name;
            }
        }
        
        let isMembershipConsumed = data.is_membership_consumed || false;
        
        console.log('Student membership check:', {
            membershipTypeId: membershipTypeId,
            membershipTypeName: membershipTypeName,
            existingDocuments: existingDocuments,
            existingDocumentsLength: existingDocuments ? existingDocuments.length : 0,
            isMembershipConsumed: isMembershipConsumed
        });
        
        // Toggle student document section
        toggleEditStudentDocumentSection(membershipTypeId, membershipTypeName, existingDocuments, isMembershipConsumed);
        
        // Populate membership items table with Sold By and Action columns
        let serviceOptions = '';
        let totalAmount = 0;
        let locationId = data.package ? data.package.location_id : null;
        
        // Check if user has plans_edit permission from hidden field
        let hasEditPermission = $('#edit_membership_has_edit_permission').val() === '1';
        // Check if user has plans_edit_sold_by permission for pencil icon
        let hasEditSoldByPermission = $('#edit_membership_has_edit_sold_by_permission').val() === '1';
        
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
                
                // Add Actions column with pencil icon for editing sold by (requires plans_edit_sold_by permission)
                if (hasEditPermission) {
                    serviceOptions += '<td class="text-center">';
                    if (hasEditSoldByPermission) {
                        serviceOptions += '<a href="javascript:void(0);" onclick="editMembershipSoldBy(' + bundle.id + ', ' + locationId + ');" class="btn btn-icon btn-light-primary btn-sm" title="Edit Sold By">';
                        serviceOptions += '<i class="la la-pencil"></i>';
                        serviceOptions += '</a>';
                    } else {
                        serviceOptions += '-';
                    }
                    serviceOptions += '</td>';
                }
                
                serviceOptions += '</tr>';
                
                totalAmount += parseFloat(bundle.tax_including_price) || 0;
            });
        } else {
            let colspan = hasEditPermission ? '7' : '6';
            serviceOptions = '<tr><td colspan="' + colspan + '" class="text-center">No record found</td></tr>';
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
            // Allow negative values to show overpayment
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
            var isStudentMembership = $('#edit_is_student_membership').val() === '1';
            var membershipTypeId = $('#edit_membership_type_id').val();
            
            console.log('Save clicked - values:', {
                isStudentMembership: isStudentMembership,
                hiddenFieldValue: $('#edit_is_student_membership').val(),
                membershipTypeId: membershipTypeId,
                paymentModeId: paymentModeId,
                cashAmount: cashAmount
            });
            
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
            
            // Use FormData for file upload support
            var formData = new FormData();
            formData.append('package_id', packageId);
            formData.append('patient_id', patientId);
            formData.append('location_id', locationId);
            formData.append('appointment_id', appointmentId);
            formData.append('payment_mode_id', paymentModeId || '');
            formData.append('cash_amount', cashAmount);
            formData.append('grand_total', grandTotal);
            formData.append('is_student_membership', isStudentMembership ? '1' : '0');
            formData.append('membership_type_id', membershipTypeId || '');
            
            // Add student documents if student membership
            if (isStudentMembership) {
                var documentCount = 0;
                $('.edit-student-document-input').each(function() {
                    if (this.files && this.files[0]) {
                        formData.append('student_documents[]', this.files[0]);
                        documentCount++;
                    }
                });
                formData.append('has_student_documents', documentCount > 0 ? '1' : '0');
                
                // Add documents to remove
                if (documentsToRemove && documentsToRemove.length > 0) {
                    formData.append('documents_to_remove', JSON.stringify(documentsToRemove));
                }
            }
            
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.packages.update_membership_plan'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
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

// ============================================
// Student Membership Document Upload Functions
// ============================================

var studentDocumentCount = 1;
var maxDocuments = 4;
var isStudentMembership = false;

// Show/hide student document section based on membership type
function toggleStudentDocumentSection(membershipTypeName) {
    if (membershipTypeName && membershipTypeName.toLowerCase().includes('student')) {
        $('#student_document_section').slideDown();
        isStudentMembership = true;
    } else {
        $('#student_document_section').slideUp();
        isStudentMembership = false;
        resetStudentDocuments();
    }
}

// Reset student documents
function resetStudentDocuments() {
    studentDocumentCount = 1;
    $('#document_upload_container').html(`
        <div class="document-upload-item mb-2" data-index="0">
            <div class="d-flex align-items-start gap-2">
                <div class="flex-grow-1">
                    <input type="file" 
                           name="student_documents[]" 
                           class="form-control form-control-sm student-document-input" 
                           accept="image/jpeg,image/png,image/jpg"
                           data-index="0">
                    <div class="mt-1 document-preview" id="preview_0" style="display: none;">
                        <img src="" class="img-thumbnail" style="max-height: 60px;">
                        <button type="button" class="btn btn-sm btn-light-danger ms-1 remove-preview" data-index="0">
                            <i class="la la-times"></i>
                        </button>
                    </div>
                    <small class="text-danger d-block"><b class="document-error" id="document_error_0"></b></small>
                </div>
                <button type="button" id="add_document_btn" class="btn btn-sm btn-icon btn-primary" title="Add Document" style="margin-left: 7px;margin-right:5px;">
                    <i class="la la-plus"></i>
                </button>
                <button type="button" 
                        class="btn btn-sm btn-icon btn-light-danger remove-document-btn" 
                        data-index="0"
                        style="display: none;">
                    <i class="la la-trash"></i>
                </button>
                <small class="text-muted" id="document_count_text" style="margin-left:3px;">1 of 4</small>
            </div>
        </div>
    `);
    updateDocumentCount();
    updateAddButtonState();
}

// Add new document upload field
$(document).on('click', '#add_document_btn', function() {
    if (studentDocumentCount >= maxDocuments) {
        toastr.warning('Maximum ' + maxDocuments + ' documents allowed');
        return;
    }

    var newIndex = studentDocumentCount;
    var newDocumentHtml = `
        <div class="document-upload-item mb-2" data-index="${newIndex}">
            <div class="d-flex align-items-start gap-2">
                <div class="flex-grow-1">
                    <input type="file" 
                           name="student_documents[]" 
                           class="form-control form-control-sm student-document-input" 
                           accept="image/jpeg,image/png,image/jpg"
                           data-index="${newIndex}">
                    <div class="mt-1 document-preview" id="preview_${newIndex}" style="display: none;">
                        <img src="" class="img-thumbnail" style="max-height: 60px;">
                        <button type="button" class="btn btn-sm btn-light-danger ms-1 remove-preview" data-index="${newIndex}">
                            <i class="la la-times"></i>
                        </button>
                    </div>
                    <small class="text-danger d-block"><b class="document-error" id="document_error_${newIndex}"></b></small>
                </div>
                <div style="width: 32px;"></div>
                <button type="button" 
                        class="btn btn-sm btn-icon btn-light-danger remove-document-btn" 
                        data-index="${newIndex}">
                    <i class="la la-trash"></i>
                </button>
                <div style="width: 40px;"></div>
            </div>
        </div>
    `;

    $('#document_upload_container').append(newDocumentHtml);
    studentDocumentCount++;
    updateDocumentCount();
    updateAddButtonState();
    updateRemoveButtonsVisibility();
});

// Remove document upload field
$(document).on('click', '.remove-document-btn', function() {
    var index = $(this).data('index');
    $(this).closest('.document-upload-item').remove();
    studentDocumentCount--;
    updateDocumentCount();
    updateAddButtonState();
    updateRemoveButtonsVisibility();
});

// Handle file input change - show preview
$(document).on('change', '.student-document-input', function() {
    var index = $(this).data('index');
    var file = this.files[0];
    
    // Clear previous error
    $('#document_error_' + index).text('');
    
    if (file) {
        // Validate file type
        var validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!validTypes.includes(file.type)) {
            $('#document_error_' + index).text('Only JPG, JPEG, and PNG files are allowed');
            $(this).val('');
            return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            $('#document_error_' + index).text('File size must be less than 5MB');
            $(this).val('');
            return;
        }
        
        // Show preview
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#preview_' + index).find('img').attr('src', e.target.result);
            $('#preview_' + index).show();
        };
        reader.readAsDataURL(file);
    }
});

// Remove preview
$(document).on('click', '.remove-preview', function() {
    var index = $(this).data('index');
    $('.student-document-input[data-index="' + index + '"]').val('');
    $('#preview_' + index).hide();
    $('#preview_' + index).find('img').attr('src', '');
});

// Update document count text
function updateDocumentCount() {
    $('#document_count_text').text(studentDocumentCount + ' of ' + maxDocuments);
}

// Update add button state
function updateAddButtonState() {
    if (studentDocumentCount >= maxDocuments) {
        $('#add_document_btn').prop('disabled', true).addClass('disabled');
    } else {
        $('#add_document_btn').prop('disabled', false).removeClass('disabled');
    }
}

// Update remove buttons visibility
function updateRemoveButtonsVisibility() {
    if (studentDocumentCount <= 1) {
        $('.remove-document-btn').hide();
    } else {
        $('.remove-document-btn').show();
    }
}

// Note: Document upload is optional for student membership
// The backend will handle consumption logic based on payment and document status

// Initialize on document ready
$(document).ready(function() {
    updateDocumentCount();
    updateAddButtonState();
    updateRemoveButtonsVisibility();
});

// ============================================
// Edit Membership - Student Document Functions
// ============================================

var editStudentDocumentCount = 1;
var editMaxDocuments = 4;
var editIsStudentMembership = false;

// Show/hide student document section in edit modal
function toggleEditStudentDocumentSection(membershipTypeId, membershipTypeName, existingDocuments, isMembershipConsumed) {
    console.log('toggleEditStudentDocumentSection called:', {
        membershipTypeId: membershipTypeId,
        membershipTypeName: membershipTypeName,
        isStudent: membershipTypeName && membershipTypeName.toLowerCase().includes('student'),
        isMembershipConsumed: isMembershipConsumed
    });
    
    if (membershipTypeName && membershipTypeName.toLowerCase().includes('student')) {
        $('#edit_student_document_section').slideDown();
        $('#edit_membership_type_id').val(membershipTypeId);
        $('#edit_is_student_membership').val('1');
        editIsStudentMembership = true;
        
        console.log('Student membership detected - showing document section');
        
        // Show existing documents if any
        if (existingDocuments && existingDocuments.length > 0) {
            displayExistingDocuments(existingDocuments, isMembershipConsumed);
        } else {
            $('#edit_existing_documents').hide();
            $('#edit_existing_documents_list').html('');
        }
        
        // Hide upload section if membership is consumed
        if (isMembershipConsumed) {
            $('#edit_document_upload_container').hide();
            $('#edit_add_document_btn').hide();
            $('#edit_document_count').hide();
        } else {
            $('#edit_document_upload_container').show();
            $('#edit_add_document_btn').show();
            $('#edit_document_count').show();
        }
    } else {
        $('#edit_student_document_section').slideUp();
        $('#edit_is_student_membership').val('0');
        editIsStudentMembership = false;
        resetEditStudentDocuments();
        console.log('Non-student membership - hiding document section');
    }
}

// Store documents to be removed
var documentsToRemove = [];

// Display existing uploaded documents
function displayExistingDocuments(documents, isMembershipConsumed) {
    documentsToRemove = []; // Reset on load
    var html = '';
    documents.forEach(function(doc, index) {
        var docUrl = '/storage/app/public/' + doc;
        var deleteButton = isMembershipConsumed ? '' : `
            <button type="button" class="btn btn-light-danger btn-sm py-0 px-1" onclick="markDocumentForRemoval(this, '${doc}')" title="Remove">
                <i class="la la-trash"></i>
            </button>
        `;
        html += `
            <div class="existing-doc-item position-relative me-2 mb-2" data-doc-path="${doc}" style="display: inline-block;">
                <div class="card" style="width: 150px;margin-right:10px">
                    <img src="${docUrl}" class="card-img-top" style="height: 80px; object-fit: cover; cursor: pointer;" 
                         onclick="viewDocument('${docUrl}')" title="Click to view">
                    <div class="card-body p-1 text-center">
                        <small class="text-muted">Doc ${index + 1}</small>
                        <div class="btn-group btn-group-sm mt-1" role="group">
                            <button type="button" class="btn btn-light-primary btn-sm py-0 px-1" onclick="viewDocument('${docUrl}')" title="View">
                                <i class="la la-eye"></i>
                            </button>
                            ${deleteButton}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    $('#edit_existing_documents_list').html(html);
    $('#edit_existing_documents').show();
}

// View document in new tab/modal
function viewDocument(url) {
    window.open(url, '_blank');
}

// Mark document for removal (will be removed on save)
function markDocumentForRemoval(btn, docPath) {
    var docItem = $(btn).closest('.existing-doc-item');
    
    if (docItem.hasClass('marked-for-removal')) {
        // Unmark for removal
        docItem.removeClass('marked-for-removal');
        docItem.find('.card').css('opacity', '1');
        docItem.find('.card-body small').text('Doc ' + (docItem.index() + 1));
        documentsToRemove = documentsToRemove.filter(function(d) { return d !== docPath; });
        $(btn).removeClass('btn-success').addClass('btn-light-danger');
        $(btn).find('i').removeClass('la-undo').addClass('la-trash');
        $(btn).attr('title', 'Remove');
    } else {
        // Mark for removal
        docItem.addClass('marked-for-removal');
        docItem.find('.card').css('opacity', '0.5');
        docItem.find('.card-body small').text('Will be removed');
        documentsToRemove.push(docPath);
        $(btn).removeClass('btn-light-danger').addClass('btn-success');
        $(btn).find('i').removeClass('la-trash').addClass('la-undo');
        $(btn).attr('title', 'Undo removal');
    }
    
    console.log('Documents to remove:', documentsToRemove);
}

// Reset edit student documents
function resetEditStudentDocuments() {
    editStudentDocumentCount = 1;
    $('#edit_document_upload_container').html(`
        <div class="edit-document-upload-item mb-2" data-index="0">
            <div class="d-flex align-items-start gap-2">
                <div class="flex-grow-1">
                    <input type="file" 
                           name="edit_student_documents[]" 
                           class="form-control form-control-sm edit-student-document-input" 
                           accept="image/jpeg,image/png,image/jpg"
                           data-index="0"
                           onchange="previewEditDocument(this, 0)">
                    <div class="mt-1 edit-document-preview" id="edit_document_preview_0" style="display: none;">
                        <img src="" class="img-thumbnail" style="max-height: 60px;">
                    </div>
                </div>
                <button type="button" id="edit_add_document_btn" class="btn btn-sm btn-icon btn-primary" onclick="addEditDocumentField()" title="Add Document" style="margin-left: 7px; margin-right: 5px;">
                    <i class="la la-plus"></i>
                </button>
                <button type="button" 
                        class="btn btn-sm btn-icon btn-light-danger edit-remove-document-btn" 
                        data-index="0"
                        onclick="removeEditDocumentField(0)"
                        style="display: none;">
                    <i class="la la-trash"></i>
                </button>
                <small class="text-muted" id="edit_document_count" style="margin-left: 3px;">1 of 4</small>
            </div>
        </div>
    `);
    updateEditDocumentCount();
    updateEditAddButtonState();
}

// Add new document field in edit modal
function addEditDocumentField() {
    if (editStudentDocumentCount >= editMaxDocuments) {
        toastr.warning('Maximum ' + editMaxDocuments + ' documents allowed');
        return;
    }

    var newIndex = editStudentDocumentCount;
    var newHtml = `
        <div class="edit-document-upload-item mb-2" data-index="${newIndex}">
            <div class="d-flex align-items-start gap-2">
                <div class="flex-grow-1">
                    <input type="file" 
                           name="edit_student_documents[]" 
                           class="form-control form-control-sm edit-student-document-input" 
                           accept="image/jpeg,image/png,image/jpg"
                           data-index="${newIndex}"
                           onchange="previewEditDocument(this, ${newIndex})">
                    <div class="mt-1 edit-document-preview" id="edit_document_preview_${newIndex}" style="display: none;">
                        <img src="" class="img-thumbnail" style="max-height: 60px;">
                    </div>
                </div>
                <div style="width: 32px;"></div>
                <button type="button" 
                        class="btn btn-sm btn-icon btn-light-danger edit-remove-document-btn" 
                        data-index="${newIndex}"
                        onclick="removeEditDocumentField(${newIndex})">
                    <i class="la la-trash"></i>
                </button>
                <div style="width: 40px;"></div>
            </div>
        </div>
    `;
    $('#edit_document_upload_container').append(newHtml);
    editStudentDocumentCount++;
    updateEditDocumentCount();
    updateEditAddButtonState();
    updateEditRemoveButtonsVisibility();
}

// Remove document field in edit modal
function removeEditDocumentField(index) {
    if (editStudentDocumentCount <= 1) {
        return;
    }
    $(`.edit-document-upload-item[data-index="${index}"]`).remove();
    editStudentDocumentCount--;
    updateEditDocumentCount();
    updateEditAddButtonState();
    updateEditRemoveButtonsVisibility();
}

// Preview document in edit modal
function previewEditDocument(input, index) {
    var previewContainer = $('#edit_document_preview_' + index);
    
    if (input.files && input.files[0]) {
        var file = input.files[0];
        
        // Validate file type
        var validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!validTypes.includes(file.type)) {
            toastr.error('Please upload only JPG or PNG images');
            $(input).val('');
            previewContainer.hide();
            return;
        }
        
        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            toastr.error('File size must be less than 5MB');
            $(input).val('');
            previewContainer.hide();
            return;
        }
        
        var reader = new FileReader();
        reader.onload = function(e) {
            previewContainer.find('img').attr('src', e.target.result);
            previewContainer.show();
        };
        reader.readAsDataURL(file);
    } else {
        previewContainer.hide();
    }
}

// Update document count display in edit modal
function updateEditDocumentCount() {
    $('#edit_document_count').text(editStudentDocumentCount + ' of 4');
}

// Update add button state in edit modal
function updateEditAddButtonState() {
    if (editStudentDocumentCount >= editMaxDocuments) {
        $('#edit_add_document_btn').prop('disabled', true);
    } else {
        $('#edit_add_document_btn').prop('disabled', false);
    }
}

// Update remove buttons visibility in edit modal
function updateEditRemoveButtonsVisibility() {
    if (editStudentDocumentCount <= 1) {
        $('.edit-remove-document-btn').hide();
    } else {
        $('.edit-remove-document-btn').show();
    }
}

/*
 * Function to edit sold_by for membership bundle
 * Uses the same modal and functionality as plan type
 */
var membershipSoldByContext = null;

function editMembershipSoldBy(packageBundleId, locationId) {
    // Store context for callback
    membershipSoldByContext = {
        packageBundleId: packageBundleId,
        locationId: locationId,
        packageId: $('#edit_package_id_membership').val()
    };
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.packages.getsoldbydata'),
        type: 'GET',
        data: {
            package_bundle_id: packageBundleId,
            location_id: locationId
        },
        success: function(response) {
            if (response.status) {
                // Store all package service IDs
                let serviceIds = response.data.package_services.map(service => service.id);
                $('#package_service_id').val(serviceIds[0] || '');
                $('#package_service_id').data('service-ids', serviceIds);
                
                // Mark that this is from membership edit modal
                $('#package_service_id').data('from-membership', true);
                $('#package_service_id').data('membership-package-id', membershipSoldByContext.packageId);

                // Populate dropdown with users
                let userOptions = '<option value="">Select</option>';
                Object.entries(response.data.users).forEach(function([id, name]) {
                    let selected = (parseInt(id) === parseInt(response.data.current_sold_by)) ? 'selected' : '';
                    userOptions += '<option value="' + id + '" ' + selected + '>' + name + '</option>';
                });
                $('#sold_by_dropdown').html(userOptions);

                // Show modal with proper z-index handling
                $('#modal_edit_sold_by').modal({
                    backdrop: 'static',
                    keyboard: true
                });

                // Fix z-index for nested modal (membership edit modal is parent)
                $('#modal_edit_sold_by').on('shown.bs.modal', function () {
                    $(this).css('z-index', parseInt($('.modal-backdrop').css('z-index')) + 10);
                });

            } else {
                toastr.error(response.message || 'Failed to load sold by data');
            }
        },
        error: function(xhr) {
            toastr.error('Failed to load sold by data');
        }
    });
}

/*
 * Handle sold by update callback for membership modal
 * This overrides the default page reload behavior
 * NOTE: This handler must run BEFORE the one in create-plan.js
 */
$(document).on('click.membership', '#update_sold_by_btn', function(e) {
    // Check if this is from membership edit modal
    let fromMembership = $('#package_service_id').data('from-membership');
    let membershipPackageId = $('#package_service_id').data('membership-package-id');
    
    if (fromMembership && membershipPackageId) {
        e.preventDefault();
        
        let packageServiceIds = $('#package_service_id').data('service-ids');
        let soldBy = $('#sold_by_dropdown').val();

        // Validation
        if (!soldBy) {
            $('#sold_by_error').html('Please select sold by');
            return false;
        }

        $('#sold_by_error').html('');
        $(this).attr('disabled', true);

        // Prepare data for update
        let updateData = {
            sold_by: soldBy
        };

        // If multiple services, send as array
        if (packageServiceIds && Array.isArray(packageServiceIds)) {
            updateData.package_services = packageServiceIds;
        } else {
            updateData.package_service_id = $('#package_service_id').val();
        }

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.packages.updatesoldby'),
            type: 'POST',
            data: updateData,
            success: function(response) {
                $('#update_sold_by_btn').attr('disabled', false);
                if (response.status) {
                    // Close sold by modal
                    $('#modal_edit_sold_by').modal('hide');
                    $('#sold_by_error').html('');
                    $('#package_service_id').val('');
                    $('#package_service_id').removeData('service-ids');
                    $('#package_service_id').removeData('from-membership');
                    $('#package_service_id').removeData('membership-package-id');

                    // Show success message and reload page
                    toastr.success(response.message || 'Sold by updated successfully');

                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    toastr.error(response.message || 'Failed to update sold by');
                }
            },
            error: function(xhr) {
                $('#update_sold_by_btn').attr('disabled', false);
                toastr.error('Failed to update sold by');
            }
        });
        
        return false;
    }
});
