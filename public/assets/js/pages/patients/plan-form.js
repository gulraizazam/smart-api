
// OPTIMIZED: Using new endpoint with 90% performance improvement (3-4 queries instead of 100+)
var table_url = route('admin.plans.optimized.datatable', { patient_id: patientCardID });

// Variables for edit plan functionality
var total_amountArray = [];
var edit_amountArray = [];
var ExistingTotal = 0;

var table_columns = [
    {
        field: 'package_id',
        title: 'Plan ID',
        width: 70,
    }, {
        field: 'location_id',
        title: 'Centres',
        width: 'auto',
        sortable: false,
    }, {
        field: 'total',
        title: 'Total',
        width: 80,
        sortable: false,
    }, {
        field: 'cash_receive',
        title: 'Cash In',
        width: 80,
        sortable: false,
    }, {
        field: 'settle_amount',
        title: 'Settled',
        width: 80,
        sortable: false,
    }, {
        field: 'refund',
        title: 'Refund',
        width: 'auto',
        sortable: false,
    }, {
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            return formatDate(data.date)
        }
    }, {
        field: 'status',
        title: 'Status',
        width: 'auto',
        template: function (data) {
            if (data.active == 1) {
                return '<span class="badge badge-success">Active</span>';
            } else {
                return '<span class="badge badge-danger">Inactive</span>';
            }
        }
    }, {
        field: 'actions',
        title: 'Actions',
        width: 100,
        sortable: false,
        template: function(data) {
            return actions(data);
        }
    }];


function actions(data) {

    if (typeof data.id !== 'undefined') {

        let id = data.id;

        let edit_url = route('admin.plans.edit', { id: id });
        let delete_url = route('admin.plans.destroy', { id: id });
        let display_url = route('admin.packages.display', { id: id });
        let log_url = route('admin.plans.log', { id: id, patient_id: patientCardID, type: 'web' });

        let actionsHtml = '<div class="dropdown dropdown-inline action-dots">\
            <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action:\
                    </li>\
                    <li class="navi-item">\
                        <a href="javascript:void(0);" onclick="viewPlan(\'' + display_url + '\');" class="navi-link">\
                            <span class="navi-icon"><i class="la la-eye"></i></span>\
                            <span class="navi-text">Display</span>\
                        </a>\
                    </li>\
                    <li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editPlanRow(\'' + edit_url + '\');" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>\
                    <li class="navi-item">\
                        <a href="javascript:void(0);" onclick="deletePlanAction(' + id + ');" class="navi-link">\
                            <span class="navi-icon"><i class="la la-trash"></i></span>\
                            <span class="navi-text">Delete</span>\
                        </a>\
                    </li>\
                </ul>\
            </div>\
        </div>';

        return actionsHtml;
    }
    return '';
}

// View plan display - copied from main plans module (create-plan.js)
function viewPlan($route) {
    $("#modal_display").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {
            displayData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            toastr.error('Failed to load plan data');
        }
    });
}

// Display plan data - copied from main plans module (create-plan.js)
function displayData(response) {

    try {

        let packageadvances = response.data.packageadvances;
        let package_data = response.data.package;
        let packagebundles = response.data.packagebundles;
        let packageservices = response.data.packageservices;
        let membership = response.data.membership;

        $("#modal_display #package_pdf").attr("href", route('admin.packages.package_pdf', package_data.id))

        let history_options = noRecordFoundTable(4);

        if (Object(packageadvances).length) {

            history_options = '';
            Object.values(packageadvances).forEach(function (packageadvance) {

                if (packageadvance.cash_amount != '0') {
                    history_options += '<tr>';
                    history_options += '<td>' + packageadvance.paymentmode.name + '</td>';
                    if (packageadvance.is_refund == 1) {
                        history_options += '<td>out / refund</td>';
                    } else if (packageadvance.is_setteled == 1) {
                        history_options += '<td>out / settled</td>';
                    }
                    else {
                        history_options += '<td>' + packageadvance.cash_flow + '</td>';
                    }
                    history_options += '<td>' + packageadvance.cash_amount + '</td>';
                    history_options += '<td>' + formatDate(packageadvance.created_at, 'MMM, DD yyyy hh:mm A') + '</td>';
                    history_options += '<tr>';
                }
            });
        }


        let service_options = noRecordFoundTable(10);

        if (packagebundles.length) {
            service_options = '';
            Object.values(packagebundles).forEach(function (packagebundle) {
                service_options += '<tr>';
                service_options += '<td><a href="javascript:void(0);" onclick="toggle(' + packagebundle.id + ')">' + packagebundle.bundle.name + '</a></td>';
                service_options += '<td>' + packagebundle.service_price.toFixed(2) + '</td>';
                service_options += '<td>';
                if (packagebundle.discount_id == null) {
                    service_options += '-';
                } else if (packagebundle.discount_name) {
                    service_options += packagebundle.discount_name;
                } else {
                    service_options += packagebundle.discount.name;
                }
                service_options += '</td>';

                service_options += '<td>';
                if (packagebundle.discount_type == null) {
                    service_options += '-';
                } else {
                    service_options += packagebundle.discount_type;
                }
                service_options += '</td>';

                service_options += '<td>';

                if (packagebundle.discount_price == null) {
                    service_options += '0.00';
                } else {
                    service_options += packagebundle.discount_price;
                }
                service_options += '</td>';

                service_options += '<td>' + packagebundle.tax_exclusive_net_amount + '</td>';

                service_options += '<td>' + packagebundle.tax_price + '</td>';
                service_options += '<td>' + packagebundle.tax_including_price + '</td>';

                // Get sold_by names for this bundle (for display view)
                let soldByNames = [];
                Object.values(packageservices).forEach(function (ps) {
                    if (ps.package_bundle_id == packagebundle.id && ps.sold_by && ps.sold_by.name) {
                        if (!soldByNames.includes(ps.sold_by.name)) {
                            soldByNames.push(ps.sold_by.name);
                        }
                    }
                });
                service_options += '<td>' + (soldByNames.length > 0 ? soldByNames.join(', ') : 'N/A') + '</td>';

                service_options += '</tr>';


                Object.values(packageservices).forEach(function (packageservice) {
                    let consume = 'No';
                    if (packageservice.package_bundle_id == packagebundle.id) {

                        if (packageservice.is_consumed == '0') {
                            consume = 'No';
                        } else {
                            consume = 'Yes';
                        }

                        service_options += '<tr class="' + packagebundle.id + '" style="display: none">';
                        service_options += '<td></td>';
                        service_options += '<td>' + packageservice.service.name + '</td>';
                        service_options += '<td>Amount : ' + packageservice.tax_exclusive_price + '</td>';

                        service_options += '<td>Tax Amt. : ' + packageservice.tax_price + '</td>';
                        service_options += '<td colspan="2">Is Consumed : ' + consume + '</td>';
                         service_options += '<td colspan="2">Consumed At: ' + (packageservice.consumed_at ?? 'N/A') + '</td>';

                        service_options += '</tr>';
                    }

                });
            });
        }

        $("#modal_display .display_plans").html(service_options);
        $("#modal_display #membership_name").text(membership);


        $("#modal_display .plan_history").html(history_options);
        var totalam = Math.round(response.data.grand_total);
        $("#modal_display .package_total_price").text(totalam);
        $("#modal_display #user_name").text(package_data.user.name)
        $("#modal_display #location_name").text(package_data.location.name)


    } catch (error) {
        showException(error);
    }

}

// Toggle function for expanding/collapsing service rows
function toggle(id) {
    $('.' + id).toggle();
}

// Edit plan row - copied from main plans module (create-plan.js)
function editPlanRow(url) {
    total_amountArray = [];
    edit_amountArray = [];
    ExistingTotal = 0;
    $('.error-msg').html('');
    $('#edit_service_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    $("#edit_discount_id").html('<option value="">Select Discount/Voucher</option>');
    $("#edit_discount_type").attr('disabled', true);
    $("#edit_discount_value_1").val('');
    $("#edit_discount_value_1").attr('disabled', true);
    hideMessages();
    if ($("#update_plane_form").length) {
        $("#update_plane_form")[0].reset();
    }
    $('#edit_cash_amount_1').val(0);
    $('#edit_cash_amount_1').prop('disabled', true);
    $('#edit_payment_mode_id').val('');

    $("#modal_edit_plan").modal("show");
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setEditData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            toastr.error('Failed to load plan data');
        }
    });
}

// Delete plan row
function deletePlanRow(url) {
    if (confirm('Are you sure you want to delete this plan?')) {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: url,
            type: 'DELETE',
            success: function(response) {
                if (response.status) {
                    toastr.success(response.message || 'Plan deleted successfully');
                    reInitTable();
                } else {
                    toastr.error(response.message || 'Failed to delete plan');
                }
            },
            error: function(xhr) {
                toastr.error('Failed to delete plan');
            }
        });
    }
}

/*actions*/

function createMembershipForPatient(id) {
    // Reset form fields
    $('#successMessageMembership').hide();
    hideSpinner("-save");
    hideSpinner("-add");
    
    $("#membership_services").html("");
    $("#modal_add_membership").modal("show");
    
    // Initialize Select2 on membership form dropdowns
    setTimeout(function() {
        $('#add_appointment_id_membership').select2({
            dropdownParent: $('#modal_add_membership')
        });
        $('#add_service_id_membership').select2({
            dropdownParent: $('#modal_add_membership')
        });
        $('#add_membership_code').select2({
            dropdownParent: $('#modal_add_membership')
        });
        $('#add_sold_by_membership').select2({
            dropdownParent: $('#modal_add_membership')
        });
        $('#payment_mode_id_membership').select2({
            dropdownParent: $('#modal_add_membership')
        });
    }, 100);

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.plans.createplan', { id: id }),
        type: "GET",
        cache: false,
        success: function (response) {
            if (response && response.data) {
                setMembershipDataForPatient(response, id);
            } else {
                console.error('Invalid response structure:', response);
                toastr.error('Failed to load membership data');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Create membership error:', xhr, thrownError);
            errorMessage(xhr);
        }
    });
}

function setMembershipDataForPatient(response, patient_id) {
    let paymentmodes = response?.data?.paymentmodes || {};
    let random_id = response?.data?.random_id || '';
    let patientName = response?.data?.patient_name || '';
    let lastConsultationLocationName = response?.data?.last_consultation_location_name || '';

    // Set payment mode options
    let payment_options = '<option value="">Select Payment Mode</option>';
    if (paymentmodes && Object.keys(paymentmodes).length > 0) {
        Object.entries(paymentmodes).forEach(function([id, name]) {
            payment_options += '<option value="' + id + '">' + name + '</option>';
        });
    }
    $("#payment_mode_id_membership").html(payment_options);

    // Set patient ID and random ID
    $("#parent_id_membership").val(patient_id);
    $("#add_patient_id_membership").val(patient_id);
    $("#random_id_membership").val(random_id);

    // Set patient name as text
    $("#add-patient-name-membership").text(patientName || 'Unknown');

    // Pre-select location and appointment if last consultation exists
    let lastConsultationLocationId = response?.data?.last_consultation_location_id;
    let lastConsultationId = response?.data?.last_consultation_id;
    let appointmentArray = response?.data?.appointmentArray || {};
    let locations = response?.data?.locations || {};

    // Populate locations dropdown
    let location_options = '<option value="">Select Centre</option>';
    if (locations && Object.keys(locations).length > 0) {
        Object.entries(locations).forEach(function([id, name]) {
            if (name !== 'All Cities-All Centres') {
                location_options += '<option value="' + id + '">' + name + '</option>';
            }
        });
    }
    $("#add_membership_location_id").html(location_options);

    if (lastConsultationLocationId) {
        // Pre-select the location
        $("#add_membership_location_id").val(lastConsultationLocationId);
        
        // Populate appointments dropdown
        let appointment_options = '<option value="">Select Appointment</option>';
        if (appointmentArray && Object.keys(appointmentArray).length > 0) {
            Object.entries(appointmentArray).forEach(function([id, appointmentData]) {
                let displayName = appointmentData.name || appointmentData;
                appointment_options += '<option value="' + id + '">' + displayName + '</option>';
            });
        }
        $("#add_appointment_id_membership").html(appointment_options);
        
        // Pre-select the last consultation
        if (lastConsultationId) {
            $("#add_appointment_id_membership").val(lastConsultationId);
        }
        
        // Load sold by for this location
        setTimeout(function() {
            loadSoldByForMembership(lastConsultationLocationId, patient_id);
        }, 200);
    }

    // Always load membership types (pass patient_id to check for renewals)
    setTimeout(function() {
        loadMembershipTypesForPatient(patient_id);
    }, 200);

    // Set membership info from API
    setTimeout(function() {
        if (lastConsultationLocationId && patient_id) {
            loadPatientMembershipInfo(lastConsultationLocationId, patient_id);
        }
    }, 300);
}

function loadMembershipTypesForPatient(patientId) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.membershiptypes.getactivetypes'),
        type: "GET",
        data: {
            patient_id: patientId
        },
        cache: false,
        success: function (response) {
            if (response && response.data && response.data.membership_types) {
                let membershipTypes = response.data.membership_types;
                let options = '<option value="">Select Membership Type</option>';
                
                membershipTypes.forEach(function(type) {
                    options += '<option value="' + type.id + '" data-price="' + type.amount + '">' + type.name + ' - Rs. ' + parseFloat(type.amount).toLocaleString() + '</option>';
                });
                
                $("#add_service_id_membership").html(options);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Error loading membership types:', thrownError);
        }
    });
}

function loadSoldByForMembership(locationId, patientId) {
    let url = route('admin.packages.getappointmentinfo', {
        _query: {
            location_id: locationId,
            patient_id: patientId
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
            if (response && response.data) {
                // Populate sold by dropdown
                if (response.data.users) {
                    let users = response.data.users;
                    let soldByOptions = '<option value="">Select</option>';
                    
                    Object.entries(users).forEach(function([id, name]) {
                        soldByOptions += '<option value="' + id + '">' + name + '</option>';
                    });
                    
                    $("#add_sold_by_membership").html(soldByOptions);
                    
                    // Pre-select the doctor if available
                    if (response.data.selected_doctor_id) {
                        $("#add_sold_by_membership").val(response.data.selected_doctor_id);
                    }
                }
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Error loading sold by data for membership:', thrownError);
        }
    });
}

function loadPatientMembershipInfo(locationId, patientId) {
    let url = route('admin.packages.getappointmentinfo', {
        _query: {
            location_id: locationId,
            patient_id: patientId
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
            if (response && response.data && response.data.membership) {
                $("#patient_membership_membership").text(response.data.membership);
            } else {
                $("#patient_membership_membership").text('No Membership');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Error loading patient membership info:', thrownError);
        }
    });
}

function createBundleForPatient(id) {
    // Reset form fields
    $('#successMessageBundle').hide();
    hideSpinner("-save");
    hideSpinner("-add");
    
    $("#bundle_services").html("");
    $("#modal_add_bundle_form").modal("show");
    
    // Initialize Select2 on bundle form dropdowns (location is now hidden, not a dropdown)
    setTimeout(function() {
        $('#add_appointment_id_bundle').select2({
            dropdownParent: $('#modal_add_bundle_form')
        });
        $('#add_service_id_bundle').select2({
            dropdownParent: $('#modal_add_bundle_form')
        });
        $('#add_sold_by_bundle').select2({
            dropdownParent: $('#modal_add_bundle_form')
        });
        $('#payment_mode_id_bundle').select2({
            dropdownParent: $('#modal_add_bundle_form')
        });
    }, 100);

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.plans.createplan', { id: id }),
        type: "GET",
        cache: false,
        success: function (response) {
            if (response && response.data) {
                setBundleDataForPatient(response, id);
            } else {
                console.error('Invalid response structure:', response);
                toastr.error('Failed to load bundle data');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Create bundle error:', xhr, thrownError);
            errorMessage(xhr);
        }
    });
}

function setBundleDataForPatient(response, patient_id) {
    let discounts = response?.data?.discounts || {};
    let paymentmodes = response?.data?.paymentmodes || {};
    let random_id = response?.data?.random_id || '';
    let patientName = response?.data?.patient_name || '';
    let lastConsultationLocationName = response?.data?.last_consultation_location_name || '';

    // Set discount options
    let discount_options = '<option value="">Select Discount</option>';
    if (discounts && Object.keys(discounts).length > 0) {
        Object.values(discounts).forEach(function (discount) {
            discount_options += '<option value="' + discount.id + '">' + discount.name + '</option>';
        });
    }
    $("#add_discount_id_bundle").html(discount_options);

    // Set payment mode options
    let payment_options = '<option value="">Select Payment Mode</option>';
    if (paymentmodes && Object.keys(paymentmodes).length > 0) {
        Object.entries(paymentmodes).forEach(function([id, name]) {
            payment_options += '<option value="' + id + '">' + name + '</option>';
        });
    }
    $("#payment_mode_id_bundle").html(payment_options);

    // Set patient ID and random ID
    $("#parent_id_bundle").val(patient_id);
    $("#add_patient_id_bundle").val(patient_id);
    $("#random_id_bundle").val(random_id);

    // Set patient name as text
    $(".bundlePatientName").text(patientName || 'Unknown');

    // Pre-select location and appointment if last consultation exists
    let lastConsultationLocationId = response?.data?.last_consultation_location_id;
    let lastConsultationId = response?.data?.last_consultation_id;
    let appointmentArray = response?.data?.appointmentArray || {};

    if (lastConsultationLocationId) {
        // Set location ID in hidden field and display name
        $("#add_bundle_location_id").val(lastConsultationLocationId);
        $(".bundleLocationName").text(lastConsultationLocationName || 'Unknown Location');
        
        // Populate appointments dropdown
        let appointment_options = '<option value="">Select Appointment</option>';
        if (appointmentArray && Object.keys(appointmentArray).length > 0) {
            Object.entries(appointmentArray).forEach(function([id, appointmentData]) {
                let displayName = appointmentData.name || appointmentData;
                appointment_options += '<option value="' + id + '">' + displayName + '</option>';
            });
        }
        $("#add_appointment_id_bundle").html(appointment_options);
        
        // Pre-select the last consultation
        if (lastConsultationId) {
            $("#add_appointment_id_bundle").val(lastConsultationId);
        }
        
        // Load services for this location
        setTimeout(function() {
            loadServicesBundleForPatient(lastConsultationLocationId);
            loadSoldByForBundle(lastConsultationLocationId, patient_id);
        }, 200);
    } else {
        $(".bundleLocationName").text('No Location');
    }

    // Set membership info from API
    setTimeout(function() {
        if (lastConsultationLocationId && patient_id) {
            loadMembershipForBundle(lastConsultationLocationId, patient_id);
        }
    }, 300);
}

function loadSoldByForBundle(locationId, patientId) {
    let url = route('admin.packages.getappointmentinfo', {
        _query: {
            location_id: locationId,
            patient_id: patientId
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
            if (response && response.data) {
                // Populate sold by dropdown
                if (response.data.users) {
                    let users = response.data.users;
                    let soldByOptions = '<option value="">Select</option>';
                    
                    Object.entries(users).forEach(function([id, name]) {
                        soldByOptions += '<option value="' + id + '">' + name + '</option>';
                    });
                    
                    $("#add_sold_by_bundle").html(soldByOptions);
                    
                    // Pre-select the doctor if available
                    if (response.data.selected_doctor_id) {
                        $("#add_sold_by_bundle").val(response.data.selected_doctor_id);
                    }
                }
                
                // Set membership info
                if (response.data.membership) {
                    $("#patient_membership_bundle").val(response.data.membership);
                }
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Error loading sold by data for bundle:', thrownError);
        }
    });
}

function getServiceDiscountBundle(element) {
    var bundle_id = element.val();
    var patient_id = $('#add_patient_id_bundle').val() || $('#parent_id_bundle').val() || patientCardID;
    var location_id = $('#add_bundle_location_id').val();

    // Clear price if no bundle selected
    if (!bundle_id) {
        $("#net_amount_bundle").val('');
        $("#package_total_bundle").val('0');
        $("#grand_total_bundle").val('0');
        return;
    }

    if (bundle_id && location_id) {
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
            },
            error: function(xhr, status, error) {
                console.error('Error fetching bundle info:', error);
                $("#net_amount_bundle").val('');
            }
        });
    }
}

function loadServicesBundleForPatient(locationId) {
    if (!locationId || locationId == '') {
        $("#add_service_id_bundle").html('<option value="">Select Service</option>');
        return;
    }

    let url = route('admin.packages.getbundles', {
        _query: {
            location_id: locationId
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
            if (response.status && response.data && response.data.bundles) {
                let bundles = response.data.bundles;
                let bundle_options = '<option value="">Select Bundle</option>';

                // Handle both array and object formats
                if (Array.isArray(bundles)) {
                    bundles.forEach(function (bundle) {
                        bundle_options += '<option value="' + bundle.id + '">' + bundle.name + ' - Rs. ' + bundle.price + '</option>';
                    });
                } else {
                    Object.values(bundles).forEach(function (bundle) {
                        bundle_options += '<option value="' + bundle.id + '">' + bundle.name + ' - Rs. ' + bundle.price + '</option>';
                    });
                }

                $("#add_service_id_bundle").html(bundle_options);
            } else {
                console.log('No bundles found in response:', response);
                $("#add_service_id_bundle").html('<option value="">Select Service</option>');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Error loading services for bundle:', thrownError);
            $("#add_service_id_bundle").html('<option value="">Select Service</option>');
        }
    });
}

// Handle Add button click for patient-specific Add Bundle modal
$(document).on('click', '#AddPackageBundle', function () {
    // Check if we're in the patient-specific modal (has bundlePatientName class)
    if ($('.bundlePatientName').length === 0) {
        return; // Let the original handler in create-bundle.js handle it
    }

    $('.create-bundle-error').html('');

    // Check if a service already exists in the table
    if ($('#bundle_services tr').length > 0) {
        toastr.error('A plan can have only one bundle at a time. Please remove the current bundle to add a new one.');
        return false;
    }

    // Validation
    if (!$('#add_bundle_location_id').val()) {
        toastr.error('Location is required');
        return false;
    }

    if (!$('#add_patient_id_bundle').val()) {
        toastr.error('Patient is required');
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
        $('#add_sold_by_bundle_error').html('Please select sold by');
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
                    $('#bundle_services').append(
                        "<tr id='table_bundle' class='HR_" + random_id + " HR_" + bundlesData.id + "'>" +
                        "<td><a href='javascript:void(0)' onClick='toggle(" + bundlesData.id + ")'>" + servicesData.service_name + "</a></td>" +
                        "<td>" + servicesData.service_price.toLocaleString() + "</td>" +
                        "<td>" + bundlesData.tax_exclusive_net_amount.toLocaleString() + "</td>" +
                        "<td>" + bundlesData.tax_price + "</td>" +
                        "<td>" + grandTotal + "</td>" +
                        "<td>" +
                        "<input type='hidden' class='package_bundles_bundle' name='package_bundles[]' value='" + bundlesData.package_bundle_id + "' />" +
                        "<input type='hidden' class='original_bundle_id' value='" + bundlesData.id + "' />" +
                        "<input type='hidden' class='package_bundles_sold_by_bundle' name='sold_by[]' value='" + servicesData.sold_by + "' />" +
                        "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deleteBundleRowTemPatient(" + bundlesData.package_bundle_id + ")'>" + trashBtn() + "</button>" +
                        "</td>" +
                        "</tr>"
                    );

                    // Add child services
                    jQuery.each(packageServicesData, function (i, packageService) {
                        let consume = packageService.is_consumed == '0' ? 'No' : 'Yes';
                        $('#bundle_services').append(
                            "<tr class='inner_records_hr HR_" + bundlesData.id + " " + bundlesData.id + "'>" +
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
                    toggle(bundlesData.id);

                    // Clear form fields
                    $('#add_service_id_bundle').val(null).trigger('change');
                    $('#net_amount_bundle').val('');
                    $('#add_sold_by_bundle').val(null).trigger('change');
                    
                    // Disable Add button after service added (only 1 service allowed)
                    $("#AddPackageBundle").prop("disabled", true);
                    $("#add_service_id_bundle").prop("disabled", true);
                    $("#add_sold_by_bundle").prop("disabled", true);

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

// Handle Save button click for patient-specific Add Bundle modal
$(document).on('click', '#AddPackageFinalBundle', function () {
    // Check if we're in the patient-specific modal (has bundlePatientName class)
    if ($('.bundlePatientName').length === 0) {
        return; // Let the original handler in create-bundle.js handle it
    }

    $('.create-bundle-error').html('');
    
    // Validate payment mode and cash amount
    if ($('#payment_mode_id_bundle').val()) {
        if (!$('#cash_amount_bundle').val()) {
            $('#cash_amount_bundle_error').html('Please enter cash amount');
            return false;
        }
    }

    var random_id = $('#random_id_bundle').val();
    var patient_id = $('#add_patient_id_bundle').val();
    var total = $('#package_total_bundle').val();
    var payment_mode_id = $('#payment_mode_id_bundle').val();
    var cash_amount = $('#cash_amount_bundle').val() || '0';
    var grand_total = $('#grand_total_bundle').val();
    if (!grand_total || grand_total === '') {
        grand_total = total;
        $('#grand_total_bundle').val(grand_total);
    }
    var location_id = $('#add_bundle_location_id').val();
    var appointment_id = $('#add_appointment_id_bundle').val();

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
    $('#bundle_services').find('tr:not(.inner_records_hr)').each(function () {
        var $row = $(this);
        var bundleData = {
            serviceName: $row.find('td:nth-child(1) a').text().trim(),
            RegularPrice: $row.find('td:nth-child(2)').text().trim(),
            DiscountName: '-',
            Type: '-',
            DiscountValue: '0.00',
            Amount: $row.find('td:nth-child(3)').text().trim(),
            Tax: $row.find('td:nth-child(4)').text().trim(),
            Total: $row.find('td:nth-child(5)').text().trim(),
            bundleId: $row.find('td:nth-child(6) input.original_bundle_id').val(),
            sold_by: $row.find('td:nth-child(6) input.package_bundles_sold_by_bundle').val()
        };
        
        formData['package_bundles'].push(bundleData);
    });
    
    // Validate that we have bundle data
    if (formData['package_bundles'].length === 0) {
        toastr.error('No bundle found in table. Please add a bundle first.');
        $('#inputfieldMessageBundle').show();
        return false;
    }

    if (payment_mode_id == '' && cash_amount > 0) {
        toastr.error("Please select the payment mode");
        return false;
    }

    // Validate required fields
    if (random_id && patient_id && total && location_id) {
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
                    
                    // Close modal and reload datatable
                    $('#modal_add_bundle_form').modal('hide');
                    reInitTable();
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
    }
});

// Delete bundle row for patient-specific modal
function deleteBundleRowTemPatient(id) {
    $.ajax({
        type: 'get',
        url: route('admin.packages.deleteplanrowtem'),
        data: {
            'id': id
        },
        success: function (response) {
            if (response.status) {
                // Remove rows from table
                $(".HR_" + id).remove();
                
                // Reset total
                $("#package_total_bundle").val('0');
                
                // Re-enable all fields and Add button
                $("#AddPackageBundle").prop("disabled", false);
                $("#add_service_id_bundle").prop("disabled", false);
                $("#add_sold_by_bundle").prop("disabled", false);
            }
        }
    });
}

function loadMembershipForBundle(locationId, patientId) {
    let url = route('admin.packages.getappointmentinfo', {
        _query: {
            location_id: locationId,
            patient_id: patientId
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
            if (response && response.data && response.data.membership) {
                $(".bundleMembershipInfo").text(response.data.membership);
            } else {
                $(".bundleMembershipInfo").text('No Membership');
            }
        }
    });
}

function createPlan(id) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.plans.createplan', { id: id }),
        type: "GET",
        cache: false,
        success: function (response) {
            // Check if response has data before proceeding
            if (response && response.data) {
                $("#modal_edit_regions").modal("show");
                setPlanData(response, id);
            } else {
                console.error('Invalid response structure:', response);
                toastr.error('Failed to load plan data');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Create plan error:', xhr, thrownError);
            errorMessage(xhr);
            reInitValidation(Validation);
        }
    });

}

function setPlanData(response, patient_id) {

    let locations = response?.data?.locations || {};
    let discounts = response?.data?.discounts || {};
    let discount_types = response?.data?.discount_type || {};
    let paymentmodes = response?.data?.paymentmodes || {};
    let random_id = response?.data?.random_id || '';

    let discount_options = '<option value="">Select Discount</option>';

    if (discounts && Object.keys(discounts).length > 0) {
        Object.values(discounts).forEach(function (discount) {
            discount_options += '<option value="' + discount.id + '">' + discount.name + '</option>';
        });
    }

    $("#add_discount_id").html(discount_options);

    let discount_type_options = '<option value="">Select Discount Type</option>';

    if (discount_types && Object.keys(discount_types).length > 0) {
        Object.entries(discount_types).forEach(function (discount_type) {
            discount_type_options += '<option value="' + discount_type[0] + '">' + discount_type[1] + '</option>';
        });
    }

    $("#add_discount_type").html(discount_type_options);


    let payment_mode_options = '<option value="">Select Payment Mode</option>';

    if (paymentmodes && Object.keys(paymentmodes).length > 0) {
        Object.entries(paymentmodes).forEach(function (paymentmode) {
            payment_mode_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
        });
    }

    $("#add_payment_mode_id").html(payment_mode_options);

    $("#add_patient_id").val(patient_id);

    $("#random_id_1").val(random_id);

    // Set membership info if available
    let patientMembership = response?.data?.patient_membership || 'No Membership';
    $(".membershipInfo").text(patientMembership);

    // Pre-select location and appointment if last consultation exists
    let lastConsultationLocationId = response?.data?.last_consultation_location_id;
    let lastConsultationId = response?.data?.last_consultation_id;
    let lastConsultationLocationName = response?.data?.last_consultation_location_name;
    let appointmentArray = response?.data?.appointmentArray || {};

    if (lastConsultationLocationId) {
        // Set location ID in hidden field
        $("#add_location_id").val(lastConsultationLocationId);
        
        // Display location name in heading from backend response
        let locationName = lastConsultationLocationName || 'Unknown Location';
        $(".locationName").text(locationName);
        
        // Populate appointments dropdown from API response
        let appointment_options = '<option value="">Select Appointment</option>';
        if (appointmentArray && Object.keys(appointmentArray).length > 0) {
            Object.entries(appointmentArray).forEach(function([id, appointmentData]) {
                // appointmentData is an object with id, name, doctor_id
                let displayName = appointmentData.name || appointmentData;
                let appointmentId = appointmentData.id || id;
                appointment_options += '<option value="' + id + '">' + displayName + '</option>';
            });
        }
        $("#add_appointment_id").html(appointment_options);
        
        // Pre-select the last consultation
        if (lastConsultationId) {
            $("#add_appointment_id").val(lastConsultationId);
        }
        
        // Load services and sold by data with location_id and patient_id - wait for DOM to update
        setTimeout(function() {
            let locationId = $("#add_location_id").val();
            if (locationId && patient_id) {
                loadServicesForPatient(locationId, patient_id);
                loadSoldByForPatient(locationId, patient_id);
            }
        }, 200);
    } else {
        // No last consultation, clear appointments
        $("#add_appointment_id").html('<option value="">Select Appointment</option>');
        $(".locationName").text('No Location');
    }

}

function loadServicesForPatient(locationId, patientId) {
    let url = route('admin.packages.getservice', {
        _query: {
            location_id: locationId,
            patient_id: patientId
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
            if (response && response.data && response.data.service) {
                let services = response.data.service;
                let service_options = '<option value="">Select Service</option>';

                Object.values(services).forEach(function (value) {
                    service_options += '<option value="' + value.id + '">' + value.name + '</option>';
                });

                $("#add_service_id").html(service_options);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Error loading services:', thrownError);
            $("#add_service_id").html('<option value="">Select Service</option>');
        }
    });
}

function getServices(type = 'add', patient_id) {

    let location = $("#" + type + "_location_id").val();

    let queryParams = {};
    if (location != '') {
        queryParams.location_id = location;
    }
    if (patient_id) {
        queryParams.patient_id = patient_id;
    }

    let url = route('admin.packages.getservice', {
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
            $("#modal_edit_regions").modal("show");

            setServices(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

    getAppointments(patient_id);
}

function setServices(response) {

    try {

        let services = response.data.service;
        let service_options = '<option value=""> Select Service </option>';

        Object.values(services).forEach(function (value) {
            service_options += '<option value="' + value.id + '"> ' + value.name + ' </option>';
        });

        $("#add_service_id").html(service_options);

    } catch (error) {
        showException(error);
    }
}

function getAppointments(patient_id) {

    let location = $("#add_location_id").val();

    if (patient_id != '' && patient_id != '') {

        let url = route('admin.packages.getappointmentinfo', {
            _query: {
                location_id: location,
                patient_id: patient_id,
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
                $("#modal_edit_regions").modal("show");

                setAppointments(response);

            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
                //reInitValidation(AddValidation);
            }
        });

    }

}

function setAppointments(response) {

    try {

        let appointments = response.data.appointments;
        let latestConsultationId = response.data.latest_consultation_id;
        let appointment_options = '<option value=""> Select Appointment </option>';

        if (Object.keys(appointments).length) {

            Object.entries(appointments).forEach(function ([id, value]) {
                appointment_options += '<option value="' + id + '"> ' + value.name + ' </option>';
            });

            $("#add_appointment_id").html(appointment_options);
            
            // Pre-select the latest consultation
            if (latestConsultationId) {
                $("#add_appointment_id").val(latestConsultationId);
            }

        }

    } catch (error) {
        showException(error);
    }
}

function editRow(url) {

    $("#modal_edit_plan").modal("show");
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_regions").modal("show");
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            // reInitValidation(Validation);
        }
    });


}

function setEditData(response) {

    try {

        let appointmentArray = response?.data?.appointmentArray || [];
        let end_previous_date = response?.data?.end_previous_date || '';
        let grand_total = response?.data?.grand_total || 0;
        let locationhasservice = response?.data?.locationhasservice || [];
        let locations = response?.data?.locations || {};
        let package = response?.data?.package || {};
        let packageadvances = response?.data?.packageadvances || [];
        let packagebundles = response?.data?.packagebundles || [];
        let packageservices = response?.data?.packageservices || [];
        let paymentmodes = response?.data?.paymentmodes || {};
        let users = response?.data?.users || {};
        let range = response?.data?.range || {};
        let total_price = response?.data?.total_price || 0;
        let patient = package?.user;
        let location = package?.location;
        let membership = response?.data?.membership || '';
        let selected_user_id = response?.data?.selectedUserId || '';
        let selectedAppointmentId = response?.data?.selectedAppointmentId || '';

        // Populate patient name, membership, location (using patient card edit modal selectors)
        $(".patientName").text(patient?.name || '');
        $(".membershipInfo").text(membership || 'No Membership');
        $(".locationName").text(location?.name || '');
        
        // Set hidden fields
        $("#edit_location_id").val(location?.id);
        $("#edit_parent_id").val(package?.patient_id);
        $("#edit_random_id").val(package?.random_id);
        $("#edit_random_id_1").val(package?.random_id);
        $("#edit_package_total_1").val(total_price ? total_price.toFixed(2) : '0.00');
        $("#edit_grand_total_1").val(grand_total);
        $('#edit_cash_amount_1').val(0);
        $('#edit_cash_amount_1').prop('disabled', true);
        $('#edit_payment_mode_id').val('');

        let history_options = noRecordFoundTable(5);

        if (packageadvances.length) {

            history_options = '';
            Object.values(packageadvances).forEach(function (packageadvance) {

                if (packageadvance.cash_amount != '0') {

                    let selector = 'history_cash_row_' + packageadvance.id;
                    history_options += '<tr id="' + selector + '">';

                    if (packageadvance.is_tax == 1 && packageadvance.cash_flow == 'out') {
                        history_options += '<td>Tax</td>';
                    } else {
                        history_options += '<td>' + packageadvance?.paymentmode?.name + '</td>';
                    }

                    history_options += '<td>' + packageadvance.cash_flow + '</td>';
                    history_options += '<td>' + packageadvance.cash_amount + '</td>';
                    history_options += '<td>' + formatDate(packageadvance.created_at, 'MMM, DD yyyy hh:mm A') + '</td>';


                    history_options += '<td>';

                    if (end_previous_date <= packageadvance?.created_at && packageadvance?.cash_flow == 'in') {
                        if (typeof permissions !== 'undefined' && permissions.patients_plan_cash_edit) {
                            history_options += '<a onclick="planeEdit(' + packageadvance.id + ', ' + package.id + ');" class="btn btn-sm btn-info" href="javascript:void(0);">Edit</a>&nbsp;';
                        }
                        if (typeof permissions !== 'undefined' && permissions.patients_plan_cash_delete) {
                            history_options += '<button onclick="deletePlaneHistory(`' + route('admin.packages.delete_cash') + '`, ' + packageadvance.id + ');" class="btn btn-sm btn-danger">Delete</button>';
                        }
                    }

                    history_options += '</td>';

                    history_options += '<tr>';

                }
            });
        }

        $(".edit_plan_history").html(history_options);

        // Appointment dropdown with pre-selection
        let appointment_options = '<option value="">Select Appointment</option>';
        if (appointmentArray && Object.keys(appointmentArray).length > 0) {
            Object.values(appointmentArray).forEach(function (appointment) {
                let selected = (appointment.id === selectedAppointmentId) ? 'selected' : '';
                appointment_options += '<option value="' + appointment.id + '" ' + selected + '>' + appointment.name + '</option>';
            });
        }
        $("#edit_appointment_id").html(appointment_options);

        // Services dropdown
        let serviceOptions = '<option value="">Select Service</option>';
        if (locationhasservice && locationhasservice.length) {
            Object.values(locationhasservice).forEach(function (service) {
                serviceOptions += '<option value="' + service?.id + '">' + service?.name + '</option>';
            });
        }
        $("#edit_service_id").html(serviceOptions);

        // Sold By dropdown with pre-selection
        let userOptions = '<option value="">Select</option>';
        if (users) {
            Object.entries(users).forEach(function ([id, name]) {
                let selected = (parseInt(id) === parseInt(selected_user_id)) ? 'selected' : '';
                userOptions += '<option value="' + id + '" ' + selected + '>' + name + '</option>';
            });
        }
        $("#edit_sold_by").html(userOptions);

        // Payment mode dropdown
        let payment_options = '<option value="">Select Payment Mode</option>';
        if (paymentmodes) {
            Object.entries(paymentmodes).forEach(function (paymentmode) {
                payment_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
            });
        }
        $("#edit_payment_mode_id").html(payment_options);

        // Detect out-of-order config group consumption (GET consumed before BUY)
        window.editPlanLocked = false;
        var configGroupConsumption = {};
        var consumedBundleIds = {};
        var consumedConfigGroupBundleIds = {};
        if (packageservices && Object.keys(packageservices).length) {
            Object.values(packageservices).forEach(function (ps) {
                if (ps.is_consumed == '1') {
                    consumedBundleIds[ps.package_bundle_id] = true;
                }
                if (ps.package_bundle_id) {
                    var pb = null;
                    Object.values(packagebundles).forEach(function (b) {
                        if (b.id == ps.package_bundle_id) pb = b;
                    });
                    if (pb && pb.config_group_id) {
                        if (!configGroupConsumption[pb.config_group_id]) {
                            configGroupConsumption[pb.config_group_id] = [];
                        }
                        configGroupConsumption[pb.config_group_id].push({
                            consumption_order: parseInt(ps.consumption_order) || 0,
                            is_consumed: ps.is_consumed == '1'
                        });
                    }
                }
            });
            // Check for out-of-order consumption
            Object.keys(configGroupConsumption).forEach(function (groupId) {
                var services = configGroupConsumption[groupId];
                var maxConsumedOrder = -1;
                var minUnconsumedOrder = Infinity;
                services.forEach(function (s) {
                    if (s.is_consumed && s.consumption_order > maxConsumedOrder) maxConsumedOrder = s.consumption_order;
                    if (!s.is_consumed && s.consumption_order < minUnconsumedOrder) minUnconsumedOrder = s.consumption_order;
                });
                if (maxConsumedOrder > minUnconsumedOrder) window.editPlanLocked = true;
            });
            // Build consumed config group bundle IDs
            var consumedConfigGroups = {};
            Object.values(packageservices).forEach(function (ps) {
                if (ps.is_consumed == '1' && ps.package_bundle_id) {
                    var pb = null;
                    Object.values(packagebundles).forEach(function (b) { if (b.id == ps.package_bundle_id) pb = b; });
                    if (pb && pb.config_group_id) consumedConfigGroups[pb.config_group_id] = true;
                }
            });
            Object.values(packagebundles).forEach(function (pb) {
                if (pb.config_group_id && consumedConfigGroups[pb.config_group_id]) {
                    consumedConfigGroupBundleIds[pb.id] = true;
                }
            });
        }

        // Services table
        let service_options = noRecordFoundTable(10);

        if (packagebundles.length) {
            service_options = '';
            Object.values(packagebundles).forEach(function (packagebundle) {
                // Per-row delete: hide if consumed or belongs to consumed config group
                var hideDelete = consumedBundleIds[packagebundle.id] || consumedConfigGroupBundleIds[packagebundle.id];
                let del_icon = hideDelete ? "<td></td>" : "<td><button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRow(" + packagebundle.id + ", `edit_`)'>" + trashBtn() + "</button></td>";

                service_options += '<tr class="HR_' + packagebundle.id + '">';
                service_options += '<td><a href="javascript:void(0);" onclick="toggle(' + packagebundle.id + ')">' + packagebundle.bundle.name + '</a></td>';
                service_options += '<td>' + packagebundle.service_price.toFixed(2) + '</td>';
                service_options += '<td>';
                if (packagebundle.discount_id == null) {
                    service_options += '-';
                } else if (packagebundle.discount_name) {
                    service_options += packagebundle.discount_name;
                } else {
                    service_options += packagebundle.discount.name;
                }
                service_options += '</td>';

                service_options += '<td>';
                if (packagebundle.discount_type == null) {
                    service_options += '-';
                } else {
                    service_options += packagebundle.discount_type;
                }
                service_options += '</td>';

                service_options += '<td>';

                if (packagebundle.discount_price == null) {
                    service_options += '0.00';
                } else {
                    service_options += packagebundle.discount_price;
                }
                service_options += '</td>';

                service_options += '<td>' + packagebundle.tax_exclusive_net_amount + '</td>';
                service_options += '<td>' + packagebundle.tax_price + '</td>';
                service_options += '<td>' + packagebundle.tax_including_price + '</td>';
                
                // Get sold_by names for this bundle
                let soldByNames = [];
                Object.values(packageservices).forEach(function (ps) {
                    if (ps.package_bundle_id == packagebundle.id && ps.sold_by && ps.sold_by.name) {
                        if (!soldByNames.includes(ps.sold_by.name)) {
                            soldByNames.push(ps.sold_by.name);
                        }
                    }
                });
                service_options += '<td>' + (soldByNames.length > 0 ? soldByNames.join(', ') : 'N/A') + '</td>';
                
                service_options += del_icon;

                service_options += '</tr>';


                Object.values(packageservices).forEach(function (packageservice) {
                    let consume = 'No';
                    if (packageservice.package_bundle_id == packagebundle.id) {
                        if (packageservice.is_consumed == '0') {
                            consume = 'No';
                        } else {
                            consume = 'Yes';
                        }

                        service_options += '<tr class="' + packagebundle.id + '" style="display: none">';
                        service_options += '<td></td>';
                        service_options += '<td>' + packageservice.service.name + '</td>';
                        service_options += '<td>Amount : ' + packageservice.tax_exclusive_price + '</td>';
                        service_options += '<td>Tax:' + packageservice.tax_price + '</td>';
                        service_options += '<td>Total Amount:' + packageservice.tax_including_price + '</td>';
                        service_options += '<td colspan="2">Is Consumed:' + consume + '</td>';
                        service_options += '<td colspan="2">Consumed At: ' + (packageservice.consumed_at ?? 'N/A') + '</td>';
                        service_options += '</tr>';
                    }

                });
            });
        }

        $("#edit_plan_services").html(service_options);


        $(".package_total_price").text(package.total_price);
        $("#user_name").text(package?.user?.name);
        $("#location_name").text(package?.location?.name);



        let discounts = response.data.discounts;
        let discount_types = response.data.discount_type;

        let discount_options = '<option value="">Select Discount</option>';

        if (discounts) {
            Object.values(discounts).forEach(function (discount) {
                discount_options += '<option value="' + discount.id + '">' + discount.name + '</option>';
            });
        }

        $("#edit_discount_id").html(discount_options);

        let discount_type_options = '<option value="">Select Discount Type</option>';

  
        if (discount_types) {
            Object.entries(discount_types).forEach(function (discount_type) {
                discount_type_options += '<option value="' + discount_type[0] + '">' + discount_type[1] + '</option>';
            });
        }

        $("#edit_discount_type").html(discount_type_options);


        let payment_mode_options = '<option value="">Select Payment Mode</option>';

        if (paymentmodes) {
            Object.entries(paymentmodes).forEach(function (paymentmode) {
                payment_mode_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
            });
        }

        $("#edit_payment_mode_id").html(payment_mode_options);



        let location_options = '<option value="">Select Centers</option>';
        if (locations) {
            Object.values(locations).forEach(function (location) {

                location_options += '<option value="' + location.id + '">' + location.name + '</option>';
            });
        }

        $("#edit_location_id").html(location_options);

        // Lock Add button only if a config group has out-of-order consumption
        if (window.editPlanLocked) {
            $('#EditPackage').attr('disabled', true).css('opacity', '0.5');
            $('#edit_service_id').prop('disabled', true);
            $('#edit_discount_id').prop('disabled', true);
            $('#edit_discount_type').prop('disabled', true);
            $('#edit_discount_value_1').prop('disabled', true);
            $('#edit_net_amount_1').prop('disabled', true);
            $('#edit_sold_by').prop('disabled', true);
            toastr.info('This plan has a configurable discount with out-of-order consumption. Please consume the BUY services first or create a new plan to add services.');
        } else {
            $('#EditPackage').attr('disabled', false).css('opacity', '1');
            $('#edit_service_id').prop('disabled', false);
        }

    } catch (error) {
        showException(error);
    }

}

function planeEdit(id, package_id) {

    $("#plan_edit_cash").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.packages.edit_cash', { id: id, package_id: package_id }),
        type: "GET",
        cache: false,
        success: function (response) {
            setPlaneEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setPlaneEditData(response) {

    let paymentmodes = response.data.paymentmodes;
    let pack_adv_info = response.data.pack_adv_info;
    let package_id = response.data.package_id;

    let payment_options = '<option value="">Select Payment Mode</option>';

    if (paymentmodes) {
        Object.values(paymentmodes).forEach(function (paymentmode) {
            payment_options += '<option value="' + paymentmode.id + '">' + paymentmode.name + '</option>';
        });
    }

    if (permissions.plans_cash_edit_payment_mode) {
        $("#plane_cash_payment_mode").html(payment_options).val(pack_adv_info.payment_mode_id);
    } else {
        $("#plane_cash_payment_mode").remove();

        let input = '<input type="hidden" id="payment_mode_id" name="payment_mode_id" value="' + pack_adv_info?.payment_mode_id + '">';
        $(".append_payment_mode").append(input);
    }

    if (permissions.plans_cash_edit_amount) {
        $("#plane_cash_amount").val(pack_adv_info.cash_amount);
    } else {
        $("#plane_cash_amount").remove();

        let input = '<input type="hidden" id="cash_amount" name="cash_amount" value="' + pack_adv_info?.cash_amount + '">';
        $(".append_cash_amount").append(input);
    }

    if (permissions.plans_cash_edit_date) {
        $("#plane_cash_date").val(formatDate(pack_adv_info.created_at, 'YYYY-MM-DD'));
    } else {
        $("#plane_cash_date").remove();

        let input = '<input type="hidden" id="created_at" name="created_at" value="' + formatDate(pack_adv_info.created_at, 'YYYY-MM-DD') + '">';
        $(".append_cash_date").append(input);
    }

    $("#edit_package_advances_id").val(pack_adv_info.id);
    $("#edit_package_id").val(package_id);




}

function deletePlaneHistory(url, package_advance_id) {

    swal.fire({
        title: 'Are you sure you want to delete?',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function (result) {
        if (result.value) {

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: url,
                type: "POST",
                data: {
                    package_advance_id: package_advance_id,
                    cash_receveive_remain: $("#edit_total_price").val()
                },
                cache: false,
                success: function (response) {
                    if (response.status) {
                        toastr.success(response.message);
                        let cash_remain = response.data.cash_receveive_remain;
                        $("#edit_total_price").val(cash_remain);
                        $("#history_cash_row_" + package_advance_id).remove()
                    } else {
                        toastr.error(response.message);
                    }

                },
                error: function (xhr, ajaxOptions, thrownError) {
                    errorMessage(xhr);
                }
            });

        }
    });

}

function addServiceDiscount($this, type = 'add_') {

    var service_id = $this.val();
    var location_id = $('#add_location_id').val();
    var patient_id = $('#add_patient_id').val();

    $("#" + type + "discount_id").val('').trigger('change');
    $('#configurable_preview').remove();
    $('#net_amount_1').val('');

    if (service_id && patient_id) {

        $("#net_amount_1").prop("disabled", true);
        $("#add_discount_value").val(0).change();
        $("#add_discount_type").val('').change();

        $.ajax({
            type: 'get',
            url: route('admin.plans.getserviceinfo_for_plan'),
            data: {
                'service_id': service_id,
                'location_id': location_id,
                'patient_id': patient_id
            },
            success: function (response) {

                if (response.data && response.data.discounts) {
                    let discounts = response.data.discounts;
                    let options = '<option value="">Select Discount</option>';
                    jQuery.each(discounts, function (i, discount) {
                        options += '<option value="' + discount.id + '">' + discount.name + '</option>';
                    });
                    $("#add_discount_id").html(options);
                } else {
                    $("#add_discount_id").html('<option value="">Select Discount</option>');
                }

                var net = (response.data && response.data.net_amount) ? response.data.net_amount : '';
                $("#net_amount_1").val(net);
                $("#net_amount_1").prop("disabled", true);
                $("#add_discount_value").val(0).change();
                $("#add_discount_type").val('').change();
            },
        });
    }

}

function editServiceDiscount($this, type = '') {

    hideMessages();

    var service_id = $this.val();
    var location_id = $('#edit_location_id').val();
    var patient_id = $('#edit_patient_id').val();

    $("#edit_discount_id").val('0').trigger('change');
    $("#edit_net_amount_1").prop("disabled", true);
    $("#edit_discount_value").val(0).change();
    $("#edit_discount_type").val('').change();

    if (service_id && patient_id) {
        $.ajax({
            type: 'get',
            url: route('admin.packages.getserviceinfo'),
            data: {
                'bundle_id': service_id, //Basically it is bundle id
                'location_id': location_id,
                'patient_id': patient_id
            },
            success: function (resposne) {

                if (resposne.status) {

                    let discounts = resposne.data.discounts;

                    let options = '<option value="" >Select Discount</option>';

                    jQuery.each(discounts, function (i, discount) {
                        options += '<option value="' + discount.id + '">' + discount.name + '</option>';
                    });

                    $("#edit_discount_id").html(options);

                    $("#edit_net_amount_1").val(resposne.data.net_amount);
                    $("#edit_net_amount_1").prop("disabled", true);

                } else {

                    let options = '<option value="" >Select Discount</option>';

                    $("#edit_discount_id").html(options);

                    $("#edit_net_amount_1").val(resposne.data.net_amount);
                    $("#edit_net_amount_1").prop("disabled", true);
                    $("#edit_discount_value").val(0).change();
                    $("#edit_discount_type").val('').change();

                }
            },
        });
    }

}

function getDiscountValue($this) {

    inputSpinner(true, 'AddPackage')
    hideMessages();

    var service_id = $('#add_service_id').val();//Basicailly it is bundle id
    var discount_id = $('#add_discount_id').val();
    var discount_type = $('#add_discount_type').val();
    var discount_value = $this.val();
    var patient_id = $('#add_patients_id').val();
    if (discount_type == 'Percentage') {
        if (discount_value > 100) {
            $('#percentageMessage').show();
            $("#net_amount_1").val('')
            inputSpinner(false, 'AddPackage')
            return false;
        } else {
            $('#percentageMessage').hide();
            inputSpinner(false, 'AddPackage')
        }
    }

    if (service_id && discount_id && discount_value && discount_type) {

        $.ajax({
            type: 'get',
            url: route('admin.packages.getdiscountinfo_custom'),
            data: {
                'service_id': service_id,//Basicailly it is bundle id
                'discount_id': discount_id,
                'discount_value': discount_value,
                'discount_type': discount_type, 
                'patient_id': patient_id
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#net_amount_1").val(resposne.data.net_amount);
                    $("#net_amount_1").prop("disabled", true);
                    inputSpinner(false, 'AddPackage')
                } else {
                    $('#DiscountRange').show();
                    // $("#net_amount_1").prop("disabled", false);
                    $("#net_amount_1").val('')
                    inputSpinner(false, 'AddPackage')
                }
            },
            error: function () {
                $("#net_amount_1").val('')
                inputSpinner(false, 'AddPackage');
                $("#net_amount_1").prop("disabled", false);
            }
        });
    }

}

function editDiscountValue($this) {
    inputSpinner(true, 'EditPackage')
    hideMessages();

    var service_id = $('#edit_service_id').val();//Basicailly it is bundle id
    var discount_id = $('#edit_discount_id').val();
    var discount_type = $('#edit_discount_type').val();
    var discount_value = $this.val();
    var patient_id = $('#edit_patients_id').val();
    if (discount_type == 'Percentage') {
        if (discount_value > 100) {
            $('#edit_percentageMessage').show();
            $("#edit_net_amount_1").val('')
            inputSpinner(false, 'EditPackage')
            return false;
        } else {
            $('#edit_percentageMessage').hide();
            inputSpinner(false, 'EditPackage')
        }
    }

    if (service_id && discount_id && discount_value && discount_type) {

        $.ajax({
            type: 'get',
            url: route('admin.packages.getdiscountinfo_custom'),
            data: {
                'service_id': service_id,//Basicailly it is bundle id
                'discount_id': discount_id,
                'discount_value': discount_value,
                'discount_type': discount_type,
                'patient_id': patient_id
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#edit_net_amount_1").val(resposne.data.net_amount);
                    $("#edit_net_amount_1").prop("disabled", true);
                    inputSpinner(false, 'EditPackage')
                } else {
                    $('#edit_DiscountRange').show();
                    $("#edit_net_amount_1").val('')
                    inputSpinner(false, 'EditPackage')
                }
            },
            error: function () {
                $("#edit_net_amount_1").val('')
                inputSpinner(false, 'EditPackage');
                $("#edit_net_amount_1").prop("disabled", false);
            }
        });
    }

}

function viewSmsLogs($route) {

    $("#modal_sms_logs").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setSmsLogs(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            //reInitValidation(Validation);
        }
    });

}

function setSmsLogs(response) {

    try {

        let SMSLogs = response.data.SMSLogs;
        let rows = '<tr><td colspan="5" class="text-center">No SMS log found.</td></tr>';
        if (SMSLogs.length) {
            let rows = '<tr>';
            Object.entries(SMSLogs).forEach(function (value, index) {
                rows += '<td></td>';
            });
            rows += '</tr>';
        }

        $("#sms_log_rows").html(rows);

    } catch (error) {
        showException(error);
    }

}

function displayDataForEdit(response) {

    try {

        let packageadvances = response.data.packageadvances;
        let package = response.data.package;
        let packagebundles = response.data.packagebundles;
        let packageservices = response.data.packageservices;

        $(".editPatientName").text(package?.user?.name);

        let history_options = noRecordFoundTable(4);

        if (packageadvances.length) {

            history_options = noRecordFoundTable(4);
            Object.values(packageadvances).forEach(function (packageadvance) {

                if (packageadvance.cash_amount != '0' && packageadvance.is_tax == 0) {
                    history_options += '<tr>';
                    history_options += '<td>' + packageadvance.paymentmode.name + '</td>';
                    history_options += '<td>' + packageadvance.cash_flow + '</td>';
                    history_options += '<td>' + packageadvance.package_refund_price + '</td>';
                    history_options += '<td>' + packageadvance.created_at_formated + '</td>';
                    history_options += '<tr>';
                }
            });
        }


        let service_options = noRecordFoundTable(9);

        if (packagebundles.length) {
            service_options = noRecordFoundTable(9);
            Object.values(packagebundles).forEach(function (packagebundle) {
                service_options += '<tr>';
                service_options += '<td><a href="javascript:void(0);" onclick="toggle(' + packagebundle.id + ')">' + packagebundle.bundle.name + '</a></td>';
                service_options += '<td>' + packagebundle.service_price.toFixed(2) + '</td>';
                service_options += '<td>';
                if (packagebundle.discount_id == null) {
                    service_options += '-';
                } else if (packagebundle.discount_name) {
                    service_options += packagebundle.discount_name;
                } else {
                    service_options += packagebundle.discount.name;
                }
                service_options += '</td>';

                service_options += '<td>';
                if (packagebundle.discount_type == null) {
                    service_options += '-';
                } else {
                    service_options += packagebundle.discount_type;
                }
                service_options += '</td>';

                service_options += '<td>';

                if (packagebundle.discount_price == null) {
                    service_options += '0.00';
                } else {
                    service_options += packagebundle.discount_price;
                }
                service_options += '</td>';

                service_options += '<td>' + packagebundle.tax_exclusive_net_amount + '</td>';
                service_options += '<td>' + packagebundle.tax_percenatage + '</td>';
                service_options += '<td>' + packagebundle.tax_price + '</td>';
                service_options += '<td>' + packagebundle.tax_including_price + '</td>';

                service_options += '</tr>';


                Object.values(packageservices).forEach(function (packageservice) {

                    if (packageservice.package_bundle_id == packagebundle.id) {
                        let consume = packageservice.is_consumed == '0' ? 'NO' : 'YES';

                        service_options += '<tr class="' + packagebundle.id + '" style="display: none">';
                        service_options += '<td></td>';
                        service_options += '<td>' + packageservice.service.name + '</td>';
                        service_options += '<td>Amount : ' + packageservice.tax_exclusive_price + '</td>';
                        service_options += '<td>Tax % : ' + packageservice.tax_percenatage + '</td>';
                        service_options += '<td>Tax Amt. : ' + packageservice.tax_including_price + '</td>';
                        service_options += '<td colspan="4">Is Consumed : ' + consume + '</td>';
                        service_options += '</tr>';
                    }

                });
            });
        }

        $(".display_plans").html(service_options);

        $(".plan_history").html(history_options);

        $(".package_total_price").text(package.total_price);
        $("#user_name").text(package.user.name);
        $(".patient_print").attr("href", route('admin.packages.package_pdf', { id: package?.id }))
        $("#location_name").text(package.location.name);


    } catch (error) {
        showException(error);
    }

}

/*Actions*/

function applyFilters(datatable) {

    $('#plan-search').on('click', function () {

        let filters = {
            delete: '',
            location_id: $("#search_plan_location_id").val(),
            status_id: $("#search_status").val(),
            plan_id: $("#search_plan_id").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $(".page-plan-form").find('#reset-filters').on('click', function () {

        let filters = {
            delete: '',
            name: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        let locations = filter_values?.locations || {};
        let statuses = filter_values?.status || {};

        let location_options = '<option value="">All</option>';

        if (locations && Object.keys(locations).length > 0) {
            Object.entries(locations).forEach(function (location) {
                location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
            });
        }

        let status_options = '<option value="">All</option>';

        if (statuses && Object.keys(statuses).length > 0) {
            Object.entries(statuses).forEach(function (status) {
                status_options += '<option value="' + status[0] + '">' + status[1] + '</option>';
            });
        }

        let plan_options = '<option value="">All</option>';

        $("#search_plan_location_id").html(location_options);
        $("#search_status").html(status_options);
        $("#search_plan_id").html(plan_options);

    } catch (error) {
        showException(error);
    }
}

function hideMessages() {

    $('#wrongMessage').hide();
    $('#inputfieldMessage').hide();
    $('#percentageMessage').hide();
    $('#AlreadyExitMessage').hide();
    $('#DiscountRange').hide();
    $('#datanotexist').hide();

    $('#edit_wrongMessage').hide();
    $('#edit_inputfieldMessage').hide();
    $('#edit_percentageMessage').hide();
    $('#edit_AlreadyExitMessage').hide();
    $('#edit_DiscountRange').hide();
    $('#edit_datanotexist').hide();
}

function keyfunction_grandtotal() {

    hideMessages();

    var cash_amount = $('#add_cash_amount').val();
    var total = $('#add_package_total').val();

    if (cash_amount && total) {
        $.ajax({
            type: 'GET',
            url: route('admin.packages.getgrandtotal'),
            data: {
                'cash_amount': cash_amount,
                'total': total,
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#add_total_price").val(resposne?.data?.grand_total ?? 0);
                } else {
                    $('#wrongMessage').show();
                }
            },
        });
    } else {
        $("#add_total_price").val(total);
        $('#inputfieldMessage').show();
    }
}

function edit_keyfunction_grandtotal() {

    hideMessages();

    var cash_amount = $('#edit_cash_amount_1').val();
    var total = $('#edit_package_total_1').val();
    var random_id = $('#edit_random_id').val();

    if (total) {
        $.ajax({
            type: 'GET',
            url: route('admin.packages.getgrandtotal_update'),
            data: {
                'cash_amount': cash_amount ?? 0,
                'total': total,
                'random_id': random_id
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#edit_grand_total_1").val(resposne?.data?.grand_total ?? 0);
                } else {
                    $('#edit_wrongMessage').show();
                }
            },
        });
    }
}

function toggle(id) {
    $("." + id).toggle();
}

// Delete plan action - calls API endpoint DELETE /api/packages/{id}
function deletePlanAction(id) {
    swal.fire({
        title: 'Are you sure you want to delete?',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function (result) {
        if (result.value) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '/api/packages/' + id,
                type: 'DELETE',
                success: function(response) {
                    if (response.status) {
                        toastr.success(response.message || 'Plan deleted successfully');
                        reInitTable();
                    } else {
                        toastr.error(response.message || 'Failed to delete plan');
                    }
                },
                error: function(xhr) {
                    toastr.error('Failed to delete plan');
                }
            });
        }
    });
}

// Delete all rows in a configurable discount group
function deleteConfigurablePlanRows(btn) {
    var configRowIds = JSON.parse($(btn).attr('data-config-rows'));
    
    swal.fire({
        title: 'Are you sure you want to delete?',
        text: 'This will delete all services in this configurable discount group.',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function (result) {
        if (result.value) {
            var package_total = $('#add_package_total').val();
            // Delete each row via AJAX
            configRowIds.forEach(function(id) {
                $.ajax({
                    type: 'post',
                    url: route('admin.packages.deletepackages_service'),
                    data: {
                        '_token': $('input[name=_token]').val(),
                        'id': id,
                        'package_total': package_total
                    },
                    success: function (response) {},
                    error: function (response) {}
                });
            });
            
            // Remove all rows from the table
            var firstId = configRowIds[0];
            $('.configurable-group-' + firstId).remove();
            
            // Check if any rows remain
            var remainingRows = $('#plan_services tbody tr[id="table_1"]').length;
            var total = 0;
            
            if (remainingRows > 0) {
                // Recalculate totals from remaining rows
                $('.package_bundles').each(function() {
                    var row = $(this).closest('tr');
                    var rowTotal = parseFloat(row.find('td:eq(7)').text().replace(/,/g, '')) || 0;
                    total += rowTotal;
                });
            }
            
            // Set total (will be 0 if no rows remain)
            $('#add_package_total').val(total.toFixed(2));
            
            // Update grand total
            if (remainingRows === 0) {
                // No rows remain - reset everything to 0
                $("#add_total_price").val('0');
                $("#add_cash_amount").val('');
                $("#add_location_id").prop("disabled", false);
            } else {
                var cash_amount = $('#add_cash_amount').val() || 0;
                $.ajax({
                    type: 'get',
                    url: route('admin.packages.getgrandtotal'),
                    data: { 'cash_amount': cash_amount, 'total': total },
                    success: function (grandTotalResponse) {
                        if (grandTotalResponse.status && grandTotalResponse.data) {
                            $("#add_total_price").val(grandTotalResponse.data.grand_total);
                        }
                    }
                });
            }
        }
    });
}

/*Delete The record*/
function deletePlanRow(id = '') {

    hideMessages();

    swal.fire({
        title: 'Are you sure you want to delete?',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function (result) {
        if (result.value) {
            deletePlan(id);
        }
    });
}

function deletePlan(id) {

    var package_total = $('#add_package_total').val();

    $.ajax({
        type: 'post',
        url: route('admin.packages.deletepackages_service'),
        data: {
            '_token': $('input[name=_token]').val(),
            'id': id,
            'package_total': package_total
        },
        success: function (resposne) {

            if (resposne.status) {

                $('.HR_' + resposne.data.id).remove();
                $("#add_package_total").val(resposne?.data?.total ?? 0);
                $("#add_total_price").val(resposne?.data?.total ?? 0);

                keyfunction_grandtotal();

                var rows = $('#plan_services tbody tr.HR_' + $('#random_id_1').val()).length;
                if (rows <= 1) {
                    $("#add_location_id").prop("disabled", false);
                }

            } else {
                $('#wrongMessage').show();
            }
        }
    });

}

function deleteEditPlanRow(id, type = '') {

    hideMessages();

    swal.fire({
        title: 'Are you sure you want to delete?',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function (result) {
        if (result.value) {
            deleteEditPlan(id, type);
        }
    });
}

function deleteEditPlan(id) {

    var package_total = $('#edit_package_total').val();

    $.ajax({
        type: 'post',
        url: route('admin.packages.deletepackages_service'),
        data: {
            '_token': $('input[name=_token]').val(),
            'id': id,
            'package_total': package_total,
            'update_status': 1
        },
        success: function (resposne) {

            if (resposne.status) {

                $('.edit_HR_' + resposne.data.id).remove();
                $("#edit_package_total").val(resposne?.data?.total ?? 0);
                $("#edit_total_price").val(resposne?.data?.total ?? 0);

                edit_keyfunction_grandtotal();

                var rows = $('#edit_plan_services tbody tr.HR_' + $('#edit_random_id').val()).length;
                if (rows <= 1) {
                    $("#edit_location_id").prop("disabled", false);
                }

            } else {
                toastr.error(resposne.message);
                $('#edit_consumeservice').show();
            }
        }
    });

}

var planeEditValidation = function () {
    // Private functions
    var planeValidation = function () {
        let modal_id = 'plane_edit_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    payment_mode_id: {
                        validators: {
                            notEmpty: {
                                message: 'The payment mode field is required'
                            }
                        }
                    },
                    cash_amount: {
                        validators: {
                            notEmpty: {
                                message: 'The cash amount field is required'
                            }
                        }
                    },
                    created_at: {
                        validators: {
                            notEmpty: {
                                message: 'The date field is required'
                            }
                        }
                    },
                    consultancy_type_id: {
                        validators: {
                            notEmpty: {
                                message: 'The consultancy type field is required'
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
        validate.on('core.form.valid', function (event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {

                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    closePopup('plane_edit_form');
                    closePopup('modal_edit_plan_form');
                    reloadTable('.plan-form');
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        // public functions
        init: function () {
            planeValidation();
        }
    };
}();


jQuery(document).ready(function () {

    planeEditValidation.init();

    $("#add_cash_amount").keyup(function () {
        keyfunction_grandtotal();
    });

    $("#add_cash_amount").blur(function () {
        keyfunction_grandtotal();
    });

    // Handle discount selection change - fetch discount info and show configurable preview
    $(document).on('change', '#add_discount_id', function () {
        var discount_id = $(this).val();
        var service_id  = $('#add_service_id').val();
        var location_id = $('#add_location_id').val();
        var patient_id  = $('#add_patient_id').val();

        $('#configurable_preview').remove();

        if (!discount_id || !service_id) {
            return;
        }

        $.ajax({
            type: 'get',
            url: route('admin.plans.getdiscountinfo_for_plan'),
            data: {
                'discount_id': discount_id,
                'service_id':  service_id,
                'location_id': location_id,
                'patient_id':  patient_id
            },
            success: function (response) {
                if (!response.status || !response.data) return;

                if (response.data.is_configurable) {
                    // Show configurable preview table
                    var rows = response.data.preview_rows;
                    var html = '<div id="configurable_preview" class="mt-3 alert alert-info" style="color: white;">';
                    html += '<strong>Configurable Discount Preview:</strong>';
                    html += '<table class="table table-sm table-bordered mt-2 mb-0" style="color: white;">';
                    html += '<thead><tr style="color: white;"><th style="color: white;">Service</th><th style="color: white;">Regular Price</th><th style="color: white;">Discount</th><th style="color: white;">Net Amount</th></tr></thead><tbody>';
                    jQuery.each(rows, function (i, row) {
                        var badge = row.row_type === 'buy'
                            ? '<span class="badge badge-primary">BUY</span>'
                            : '<span class="badge badge-success">GET</span>';
                        html += '<tr>';
                        html += '<td style="color: white !important;">' + badge + ' ' + row.service_name + '</td>';
                        html += '<td style="color: white !important;">' + parseFloat(row.service_price).toLocaleString() + '</td>';
                        html += '<td style="color: white !important;">' + row.discount_type + '</td>';
                        html += '<td style="color: white !important;">' + parseFloat(row.net_amount).toLocaleString() + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                    $('#net_amount_1').closest('.fv-row').after(html);
                    // Set net_amount to total of all rows for grand total calculation
                    $('#net_amount_1').val(response.data.total_net_amount);
                } else {
                    $('#net_amount_1').val(response.data.net_amount);
                }
            }
        });
    });

    /*save data for both predefined discounts and keyup trigger*/
    $("#AddPackage").click(function () {

        hideMessages();

        $(this).attr("disabled", true);
        var random_id = $('#random_id_1').val();
        var service_id = $('#add_service_id').val();
        var discount_id = $('#add_discount_id').val();
        var net_amount = $('#net_amount_1').val();
        var discount_type = $('#add_discount_type').val();
        var discount_price = $('#add_discount_value').val();
        var discount_slug = $("#slug_1").val();
        var package_total = $('#add_package_total').val();

        var is_exclusive = $('#is_exclusive').val();
        var location_id = $('#add_location_id').val();
        var user_id = $('#add_patient_id').val();
        var sold_by = $('#add_sold_by').val();

        // Check if a configurable discount is selected
        var selected_discount_option = $('#add_discount_id option:selected');
        var is_configurable = ($('#configurable_preview').length > 0);

        // For configurable discounts, net_amount validation is different (can be 0 for complimentary)
        var has_valid_fields = service_id && location_id && user_id && sold_by && (net_amount !== '' || is_configurable);

        if (has_valid_fields) {

            showSpinner("-add");

            if (!is_configurable && discount_slug == 'custom') {
                if (discount_price == '') {
                    hideSpinner("-add");
                    $('#inputfieldMessage').show();
                    $(this).attr("disabled", false);
                    return false;
                }
                if (discount_type == 'Percentage') {
                    if (discount_price > 100) {
                        $('#percentageMessage').show();
                        hideSpinner("-add");
                        $(this).attr("disabled", false);
                        return false;
                    }
                }
            }

            var formData = {
                'random_id':     random_id,
                'service_id':    service_id,
                'discount_id':   discount_id,
                'net_amount':    net_amount,
                'discount_type': discount_type,
                'discount_price':discount_price,
                'package_total': package_total,
                'is_exclusive':  is_exclusive,
                'location_id':   location_id,
                'user_id':       user_id,
                'sold_by':       sold_by,
                'package_bundles[]': []
            };

            $(".package_bundles").each(function () {
                formData['package_bundles[]'].push($(this).val());
            });

            $.ajax({
                type: 'get',
                url: route('admin.plans.savepackages_service_for_plan'),
                data: formData,
                success: function (resposne) {

                    if (resposne.status) {
                        $('.not_found').remove();

                        if (resposne.data.is_configurable) {
                            // Multiple rows for configurable discount
                            var rows = resposne.data.rows;
                            var grand_total = resposne.data.grand_total;
                            // Store all row IDs for grouped deletion
                            var configRowIds = rows.map(function(r) { return r.record.id; });

                            jQuery.each(rows, function (i, row) {
                                var consume = 'NO';
                                // Only show delete button on first row (base service)
                                var deleteBtn = '';
                                if (i === 0) {
                                    deleteBtn = "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' data-config-rows='" + JSON.stringify(configRowIds) + "' onClick='deleteConfigurablePlanRows(this)'>" + trashBtn() + "</button>";
                                }
                                $('#plan_services').append(
                                    "<tr id='table_1' class='HR_" + random_id + " HR_" + row.record.id + " configurable-group-" + configRowIds[0] + "'>" +
                                    "<td><a href='javascript:void(0)' onClick='toggle(" + row.record.id + ")'>" + row.service_name + "</a></td>" +
                                    "<td>" + parseFloat(row.service_price).toLocaleString() + "</td>" +
                                    "<td>" + row.discount_name + "</td>" +
                                    "<td>" + row.discount_type + "</td>" +
                                    "<td>" + row.discount_price + "</td>" +
                                    "<td>" + parseFloat(row.record.tax_exclusive_net_amount).toLocaleString() + "</td>" +
                                    "<td>" + row.record.tax_percenatage + "</td>" +
                                    "<td>" + parseFloat(row.record.tax_including_price).toLocaleString() + "</td>" +
                                    "<td>" +
                                    "<input type='hidden' class='package_bundles' name='package_bundles[]' value='" + row.record.id + "' />" +
                                    deleteBtn +
                                    "</td>" +
                                    "</tr>"
                                );
                                jQuery.each(row.record_detail, function (j, rd) {
                                    consume = rd.is_consumed == '0' ? 'NO' : 'YES';
                                    $('#plan_services').append(
                                        "<tr class='inner_records_hr HR_" + row.record.id + " " + row.record.id + " configurable-group-" + configRowIds[0] + "'>" +
                                        "<td></td><td>" + rd.name + "</td>" +
                                        "<td>Amount : " + parseFloat(rd.tax_exclusive_price).toLocaleString() + "</td>" +
                                        "<td>Tax % : " + rd.tax_percenatage + "</td>" +
                                        "<td>Tax Amt. : " + parseFloat(rd.tax_including_price).toLocaleString() + "</td>" +
                                        "<td colspan='4'>Is Consume : " + consume + "</td></tr>"
                                    );
                                });
                            });

                            $("#add_package_total").val(grand_total);

                        } else {
                            // Single row for simple discount
                            var myarray = resposne.data.myarray;
                            var consume = 'NO';

                            $("#add_package_total").val(myarray.total ?? 0);

                            $('#plan_services').append(
                                "<tr id='table_1' class='HR_" + random_id + " HR_" + myarray.record.id + "'>" +
                                "<td><a href='javascript:void(0)' onClick='toggle(" + myarray.record.id + ")'>" + myarray.service_name + "</a></td>" +
                                "<td>" + parseFloat(myarray.service_price).toLocaleString() + "</td>" +
                                "<td>" + myarray.discount_name + "</td>" +
                                "<td>" + myarray.discount_type + "</td>" +
                                "<td>" + myarray.discount_price + "</td>" +
                                "<td>" + parseFloat(myarray.record.tax_exclusive_net_amount).toLocaleString() + "</td>" +
                                "<td>" + myarray.record.tax_percenatage + "</td>" +
                                "<td>" + parseFloat(myarray.record.tax_including_price).toLocaleString() + "</td>" +
                                "<td>" +
                                "<input type='hidden' class='package_bundles' name='package_bundles[]' value='" + myarray.record.id + "' />" +
                                "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRow(" + myarray.record.id + ")'>" + trashBtn() + "</button>" +
                                "</td>" +
                                "</tr>"
                            );

                            jQuery.each(myarray.record_detail, function (i, record_detail) {
                                consume = record_detail.is_consumed == '0' ? 'NO' : 'YES';
                                $('#plan_services').append(
                                    "<tr class='inner_records_hr HR_" + myarray.record.id + " " + myarray.record.id + "'>" +
                                    "<td></td><td>" + record_detail.name + "</td>" +
                                    "<td>Amount : " + parseFloat(record_detail.tax_exclusive_price).toLocaleString() + "</td>" +
                                    "<td>Tax % : " + record_detail.tax_percenatage + "</td>" +
                                    "<td>Tax Amt. : " + parseFloat(record_detail.tax_including_price).toLocaleString() + "</td>" +
                                    "<td colspan='4'>Is Consume : " + consume + "</td></tr>"
                                );
                            });
                        }

                        // Update grand total
                        var cash_amount = $('#add_cash_amount').val() || 0;
                        var total = $("#add_package_total").val() || 0;
                        $.ajax({
                            type: 'get',
                            url: route('admin.packages.getgrandtotal'),
                            data: { 'cash_amount': cash_amount, 'total': total },
                            success: function (grandTotalResponse) {
                                if (grandTotalResponse.status && grandTotalResponse.data) {
                                    $("#add_total_price").val(grandTotalResponse.data.grand_total);
                                }
                            }
                        });

                        var rows = $('#plan_services tbody tr').length;
                        if (rows >= 3) {
                            $("#add_location_id").prop("disabled", true);
                        }

                        // Reset form fields
                        $('#add_service_id').val(null).trigger('change');
                        $('#add_discount_id').val(null).trigger('change');
                        $('#add_discount_type').val(null).trigger('change');
                        $('#add_discount_value').val('');
                        $('#net_amount_1').val('');
                        $('#add_sold_by').val(null).trigger('change');
                        $('#configurable_preview').remove();

                    } else {
                        toastr.error(resposne.message || 'Failed to add service.');
                    }

                    $("#AddPackage").attr("disabled", false);
                    hideSpinner("-add");
                },
                error: function () {
                    hideSpinner("-add");
                    $("#AddPackage").attr("disabled", false);
                }
            });
        } else {
            $('#inputfieldMessage').show();
            $(this).attr("disabled", false);
            hideSpinner("-add");
        }
    });
    /*End*/

    /*function for final package information save*/
    $("#AddPackageFinal").click(function () {

        hideMessages();

        var random_id = $('#random_id_1').val();
        var patient_id = $('#add_patient_id').val();
        var total = $('#add_package_total').val();
        var payment_mode_id = $('#add_payment_mode_id').val();
        var cash_amount = $('#add_cash_amount').val();
        var grand_total = $('#add_total_price').val();
        var location_id = $('#add_location_id').val();
        var is_exclusive = $('#is_exclusive').val();
        var appointment_id = $('#add_appointment_id').val();

        var formData = {
            'random_id': random_id,
            'patient_id': patient_id,
            'location_id': location_id,
            'total': total,
            'payment_mode_id': payment_mode_id,
            'cash_amount': cash_amount,
            'grand_total': grand_total,
            'is_exclusive': is_exclusive,
            'appointment_id': appointment_id,
            'package_bundles[]': []
        };

        $(".package_bundles").each(function () {
            formData['package_bundles[]'].push($(this).val());
        });
        var status = 0;
        if (cash_amount > 0) {
            var status = 1;
        }

        if (random_id && (patient_id > 0) && total && status == 1 ? payment_mode_id : true && cash_amount >= 0 && grand_total && location_id) {

            showSpinner("-save");

            $.ajax({
                type: 'get',
                url: route('admin.packages.savepackages'),
                data: formData,
                success: function (resposne) {

                    if (resposne.status) {
                        $('#successMessage').show();
                        toastr.success(" Plan successfully created")
                        /*closePopup('add_patient_plane');
                        reloadTable('.plan-form')*/
                        setTimeout(function () {
                            window.location.reload();
                        }, 200);
                    } else {
                        $('#wrongMessage').show();
                    }

                    hideSpinner("-save");
                },
                error: function () {
                    hideSpinner("-save");
                }
            });
        } else {
            $('#inputfieldMessage').show();
            $(this).attr("disabled", false);
            hideSpinner("-save");
        }
    });
    /*End*/


    /*save data for both predefined discounts and keyup trigger*/
    $("#EditPackage").click(function () {

        // Safety check: block adding if config group has out-of-order consumption
        if (window.editPlanLocked) {
            toastr.error('Cannot add new services. A configurable discount group has out-of-order consumption. Please consume the BUY services first or create a new plan.');
            return false;
        }

        hideMessages();

        $(this).attr("disabled", true);
        var random_id = $('#edit_random_id').val();
        var service_id = $('#edit_service_id').val(); //Basicailly it is bundle id
        var discount_id = $('#edit_discount_id').val();
        var net_amount = $('#edit_net_amount_1').val();
        var discount_type = $('#edit_discount_type').val();
        var discount_price = $('#edit_discount_value').val();
        var discount_slug = $("#edit_slug").val();
        var package_total = $('#edit_package_total').val();

        var is_exclusive = $('#edit_is_exclusive').val();
        var location_id = $('#edit_location_id').val();

        if (service_id && net_amount && location_id) {

            showSpinner("-edit-add");

            if (discount_slug == 'custom') {
                if (discount_price == '') {
                    hideSpinner("-edit-add");
                    $('#edit_inputfieldMessage').show();
                    return false;
                }
                if (discount_type == 'Percentage') {
                    if (discount_price > 100) {
                        $('#edit_percentageMessage').show();
                        hideSpinner("-edit-add");
                        return false;
                    }
                }
            }

            var formData = {
                'random_id': random_id,
                'bundle_id': service_id, //Basicailly it is bundle id
                'discount_id': discount_id,
                'net_amount': net_amount,
                'discount_type': discount_type,
                'discount_price': discount_price,
                'package_total': package_total,
                'is_exclusive': is_exclusive,
                'location_id': location_id,
                'package_bundles[]': []
            };

            $(".package_bundles").each(function () {
                formData['package_bundles[]'].push($(this).val());
            });

            $.ajax({
                type: 'get',
                url: route('admin.packages.savepackages_service'),
                data: formData,
                success: function (resposne) {
                    let consume = 'NO';
                    if (resposne.status) {

                        $("#edit_package_total").val(resposne?.data?.myarray?.total ?? 0);

                        $(".not_found").remove();

                        $('#edit_plan_services').append("" +
                            "<tr id='table_1' class='HR_" + random_id + " edit_HR_" + resposne.data.myarray.record.id + "'>" +
                            "<td><a href='javascript:void(0)' onClick='toggle(" + resposne.data.myarray.record.id + ")'>" + resposne.data.myarray.service_name + "</a></td>" +
                            "<td>" + resposne.data.myarray.service_price.toLocaleString() + "</td>" +
                            "<td>" + resposne.data.myarray.discount_name + "</td>" +
                            "<td>" + resposne.data.myarray.discount_type + "</td>" +
                            "<td>" + resposne.data.myarray.discount_price + "</td>" +
                            "<td>" + resposne.data.myarray.record.tax_exclusive_net_amount.toLocaleString() + "</td>" +
                            "<td>" + resposne.data.myarray.record.tax_percenatage + "</td>" +
                            "<td>" + resposne.data.myarray.record.tax_including_price.toLocaleString() + "</td>" +
                            "<td>" +
                            "<input type='hidden' class='package_bundles' name='package_bundles[]' value='" + resposne.data.myarray.record.id + "' />" +
                            "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deleteEditPlanRow(" + resposne.data.myarray.record.id + ")'>" + trashBtn() + "</button>" +
                            "</td>" +
                            "</tr>");

                        jQuery.each(resposne.data.myarray.record_detail, function (i, record_detail) {
                            if (record_detail.is_consumed == '0') {
                                consume = 'NO';
                            } else {
                                consume = 'YES';
                            }
                            $('#edit_plan_services').append("<tr class='inner_records_hr HR_" + resposne.data.myarray.record.id + " " + resposne.data.myarray.record.id + "'><td></td><td>" + record_detail.name + "</td><td>Amount : " + record_detail.tax_exclusive_price.toLocaleString() + "</td><td>Tax % : " + record_detail.tax_percenatage + "</td><td>Tax Amt. : " + record_detail.tax_including_price.toLocaleString() + "</td><td colspan='4'>Is Consume : " + consume + "</td></tr>");
                        });

                        edit_keyfunction_grandtotal();

                    } else {
                        $('#edit_AlreadyExitMessage').show();
                    }

                    hideSpinner("-edit-add");
                },
                error: function () {
                    hideSpinner("-edit-add");
                }
            });
        } else {
            $('#edit_inputfieldMessage').show();
            $(this).attr("disabled", false);
            hideSpinner("-edit-add");
        }
    });
    /*End*/

    /*function for final package information save - copied from main plans module*/
    $("#EditPackageFinal").click(function () {
        $('.error-msg').html('');
        hideMessages();
        
        var random_id = $('#edit_random_id').val();
        var patient_id = patientCardID;
        var total = $('#edit_package_total_1').val();
        var payment_mode_id = $('#edit_payment_mode_id').val();
        var cash_amount = $('#edit_cash_amount_1').val();
        var grand_total = $('#edit_grand_total_1').val();
        var location_id = $('#edit_location_id').val();
        var is_exclusive = $('#edit_is_exclusive').val();
        var appointment_id = $('#edit_appointment_id').val();
        
        var formData = {
            'random_id': random_id,
            'patient_id': patient_id,
            'location_id': location_id,
            'total': total,
            'payment_mode_id': payment_mode_id,
            'cash_amount': cash_amount,
            'grand_total': grand_total,
            'is_exclusive': is_exclusive,
            'appointment_id': appointment_id,
            package_bundles: []
        };

        // Only collect NEWLY ADDED rows (not existing DB rows)
        $('#edit_plan_services').find('tr[id="table_1"]:not(.inner_records_hr)').each(function () {
            // Skip existing DB rows — they are already in the database
            if ($(this).data('existing')) return true;
            formData['package_bundles'].push({
                serviceName: $(this).find('td:first-child a').text(),
                RegularPrice: $(this).find('td:nth-child(2)').text(),
                DiscountName: $(this).find('td:nth-child(3)').text(),
                Type: $(this).find('td:nth-child(4)').text(),
                DiscountValue: $(this).find('td:nth-child(5)').text(),
                Amount: $(this).find('td:nth-child(6)').text(),
                Tax: $(this).find('td:nth-child(7)').text(),
                Total: $(this).find('td:nth-child(8)').text(),
                bundleId: $(this).find('td:nth-child(10)').find("input[name='bundle_id']").val(),
                DiscountId: $(this).find('td:nth-child(10)').find("input[name='discount_id']").val(),
                sold_by: $(this).find('td:nth-child(11)').find("input[name='sold_by[]']").val()
            });
        });

        var status = 0;
        if (cash_amount > 0) {
            var status = 1;
        }

        if (payment_mode_id == '' && cash_amount > 0) {
            $('#payment_mode_id').html('Please select payment mode');
            return false;
        }

        if (payment_mode_id && cash_amount == '') {
            $('#cash_amount_error').html('Please enter cash amount');
            return false;
        }

        // Check if there are any services (not total amount, as services can have 0 amount with 100% discount)
        if (formData.package_bundles.length <= 0) {
            toastr.error("Please add atleast one service");
            return false;
        }

        if (random_id && (patient_id > 0) && total !== '' && status == 1 ? payment_mode_id : true && cash_amount >= 0 && grand_total !== '' && location_id) {
            showSpinner("-edit-save");
            $.ajax({
                type: 'get',
                url: route('admin.packages.updatepackages'),
                data: formData,
                success: function (resposne) {
                    if (resposne.status) {
                        toastr.success(resposne.message || 'Plan updated successfully');
                        $("#modal_edit_plan").modal("hide");
                        reInitTable();
                    } else {
                        if (resposne.data?.setteled == 1) {
                            $('#casesetteledamount').show();
                        } else {
                            $('#edit_wrongMessage').show();
                            toastr.error(resposne.message)
                        }
                    }

                    hideSpinner("-edit-save");
                },
                error: function (response) {
                    errors = response?.responseJSON?.errors;
                    if (errors) {
                        errors.appointment_id ? $('#appointment_id').html(errors.appointment_id) : $('#appointment_id').html('');
                    }
                    hideSpinner("-edit-save");
                }
            });
        } else {
            $('#edit_inputfieldMessage').show();
            $(this).attr("disabled", false);
            toastr.error("Kindly enter required fields or you enter wrong value.")
            hideSpinner("-edit-save");
        }
    });
    /*End*/

    // Service selection handler - fetch service price and available discounts
    $(document).on('change', '#add_service_id', function() {
        getServiceDiscountForPatient($(this));
    });

    // Discount selection handler - calculate final price with discount
    $(document).on('change', '#add_discount_id', function() {
        getDiscountInfoForPatient($(this));
    });

});

// Load sold by data from getappointmentinfo API
function loadSoldByForPatient(locationId, patientId) {
    let url = route('admin.packages.getappointmentinfo', {
        _query: {
            location_id: locationId,
            patient_id: patientId
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
            if (response && response.data) {
                // Populate sold by dropdown
                if (response.data.users) {
                    let users = response.data.users;
                    let soldByOptions = '<option value="">Select</option>';
                    
                    Object.entries(users).forEach(function([id, name]) {
                        soldByOptions += '<option value="' + id + '">' + name + '</option>';
                    });
                    
                    $("#add_sold_by").html(soldByOptions);
                    
                    // Pre-select the doctor if available
                    if (response.data.selected_doctor_id) {
                        $("#add_sold_by").val(response.data.selected_doctor_id);
                    }
                }
                
                // Set membership info from API response
                if (response.data.membership) {
                    $(".membershipInfo").text(response.data.membership);
                }
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Error loading sold by data:', thrownError);
            $("#add_sold_by").html('<option value="">Select</option>');
        }
    });
}

// Get service price and available discounts when service is selected
function getServiceDiscountForPatient($this) {
    var service_id = $this.val();
    var patient_id = $('#add_patient_id').val();
    var location_id = $('#add_location_id').val();
    
    if (!service_id) {
        $("#add_discount_id").html('<option value="">Select Discount</option>');
        $("#add_discount_type").val('').trigger('change');
        $("#add_discount_type").prop("disabled", true);
        $("#add_discount_value").val('');
        $("#add_discount_value").prop("disabled", true);
        $("#net_amount_1").val('');
        $("#net_amount_1").prop("disabled", false);
        return;
    }
    
    if (service_id && patient_id && location_id) {
        $.ajax({
            type: 'get',
            url: route('admin.packages.getserviceinfo_for_plan'),
            data: {
                'service_id': service_id,
                'location_id': location_id,
                'patient_id': patient_id
            },
            success: function (response) {
                if (response.status) {
                    let discounts = response.data.discounts;
                    let options = '<option value="">Select Discount</option>';
                    
                    if (discounts) {
                        jQuery.each(discounts, function (i, discount) {
                            options += '<option value="' + discount.id + '">' + discount.name + '</option>';
                        });
                    }
                    
                    $("#add_discount_id").html(options);
                    $("#net_amount_1").val((response.data.net_amount).toFixed(2));
                    $("#net_amount_1").prop("disabled", true);
                    
                    // Reset discount fields
                    $("#add_discount_type").val('').trigger('change');
                    $("#add_discount_type").prop("disabled", true);
                    $("#add_discount_value").val('');
                    $("#add_discount_value").prop("disabled", true);
                } else {
                    $("#add_discount_id").html('<option value="">Select Discount</option>');
                    $("#net_amount_1").val((response.data.net_amount).toFixed(2));
                    $("#net_amount_1").prop("disabled", true);
                }
            },
            error: function() {
                toastr.error('Failed to load service information');
            }
        });
    }
}

// Calculate final price when discount is selected
function getDiscountInfoForPatient($this) {
    var service_id = $('#add_service_id').val();
    var discount_id = $this.val();
    var patient_id = $('#add_patient_id').val();
    
    if (!discount_id || discount_id == '') {
        // No discount selected - reset to service price
        if (service_id) {
            getServiceDiscountForPatient($('#add_service_id'));
        }
        return;
    }
    
    if (discount_id == '0') {
        // No discount option selected
        $.ajax({
            type: 'get',
            url: route('admin.packages.getserviceinfo_discount_zero'),
            data: {
                'bundle_id': service_id
            },
            success: function (response) {
                if (response.status) {
                    $("#add_discount_type").prop("disabled", true);
                    $("#add_discount_type").val('').trigger('change');
                    $("#add_discount_value").prop("disabled", true);
                    $("#add_discount_value").val('');
                    $("#net_amount_1").val((response.data.net_amount).toFixed(2));
                    $("#net_amount_1").prop("disabled", true);
                }
            }
        });
    } else {
        // Discount selected - get discount details and calculate price
        $.ajax({
            type: 'get',
            url: route('admin.packages.getdiscountinfo_for_plan'),
            data: {
                'service_id': service_id,
                'discount_id': discount_id,
                'patient_id': patient_id
            },
            success: function (response) {
                if (response.status) {
                    if (response.data.custom_checked == 0) {
                        $("#add_discount_type").val(response.data.discount_type).trigger('change');
                        $("#add_discount_type").prop("disabled", true);
                        $("#add_discount_value").val(response.data.discount_price);
                        $("#add_discount_value").prop("disabled", true);
                        $("#net_amount_1").val((response.data.net_amount).toFixed(2));
                        $("#net_amount_1").prop("disabled", true);
                    } else {
                        // Custom discount - allow user to enter values
                        $("#add_discount_type").prop("disabled", false);
                        $("#add_discount_value").prop("disabled", false);
                        $("#net_amount_1").prop("disabled", false);
                    }
                }
            },
            error: function() {
                toastr.error('Failed to load discount information');
            }
        });
    }
}

function checkpaymentMode() {
    if ($('#edit_payment_mode_id').val()) {
        $('#edit_cash_amount_1').prop('disabled', false);
    } else {
        $('#edit_cash_amount_1').val(0);
        $('#edit_cash_amount_1').prop('disabled', true);
        if (typeof edit_keyfunction_grandtotal === 'function') {
            edit_keyfunction_grandtotal();
        }
    }
}

// Bind input event for cash amount field to update grand total (matching create-plan.js behavior)
// Using event delegation since modal content is loaded dynamically
$(document).on('input', '#edit_cash_amount_1', function () {
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

    // Call your function if value is valid
    edit_keyfunction_grandtotal();
});
