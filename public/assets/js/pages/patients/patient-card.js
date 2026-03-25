jQuery(document).ready(function () {
    getPatient();
    getTabCounts();
    var result = get_query();
    if (typeof result.tab !== 'undefined') {
        $("." + result.tab+ '-tab').click();
    }
    activeFirstTab(result.tab);
    
    // Handle Create Consultation button
    $(document).on('click', '#create-consultation-btn', function() {
        createAppointmentFromPatient('consultancy');
    });
    
    // Handle Create Treatment button
    $(document).on('click', '#create-treatment-btn', function() {
        createAppointmentFromPatient('treatment');
    });
});

function getTabCounts() {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.patients.tabCounts', {id: patientCardID}),
        type: "GET",
        cache: false,
        success: function (response) {
            if (response.status && response.data) {
                var counts = response.data;
                $('#tab-count-appointments').text('(' + (counts.appointments || 0) + ')');
                $('#tab-count-consultations').text('(' + (counts.consultations || 0) + ')');
                $('#tab-count-treatments').text('(' + (counts.treatments || 0) + ')');
                $('#tab-count-vouchers').text('(' + (counts.vouchers || 0) + ')');
                $('#tab-count-documents').text('(' + (counts.documents || 0) + ')');
                $('#tab-count-plans').text('(' + (counts.plans || 0) + ')');
                $('#tab-count-invoices').text('(' + (counts.invoices || 0) + ')');
                $('#tab-count-refunds').text('(' + (counts.refunds || 0) + ')');
                $('#tab-count-activity').text('(' + (counts.activity_logs || 0) + ')');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.log('Error fetching tab counts:', thrownError);
        }
    });
}

function activeFirstTab(tab) {

    if (typeof tab === 'undefined' || tab === 'personal_info') {
        $(".personal-info").addClass("nav-bar-active");
    }
}

function getPatient() {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.patients.getPatient', {id: patientCardID}),
        type: "GET",
        cache: false,
        success: function (response) {
            setPatientData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setPatientData(response) {
   let permission = response.data.permissions
    if (response?.data?.patient) {
        let patient = response.data.patient;
        $("#profile_patient_name").text(patient.name);
        $("#profile_patient_id").text(makePatientId(patient.id));
        $("#patient_id").text(makePatientId(patient.id));
        $("#patient_name").text(patient.name);
        
        // Load patient notes after patient data is loaded
        if (typeof loadPatientNotes === 'function') {
            loadPatientNotes();
        }
        $("#patient_email").text(patient.email);
        if (permission.contact) {
            $("#patient_phone").text(patient.phone);
        } else {
            $("#patient_phone").text("***********");
        }
        $("#patient_gender").text(getGender(patient.gender));
        
        // Set membership info on profile personal info section
        if (response?.data?.membership) {
            let membership = response.data.membership;
            $("#patient_membership").text(membership.type || 'N/A');
            $("#patient_membership_expiry").text(membership.end_date ? formatDate(membership.end_date, 'MMM D, YYYY') : '-');
            $("#membership_type_row").show();
            $("#membership_expiry_row").show();
        } else {
            $("#membership_type_row").hide();
            $("#membership_expiry_row").hide();
        }
        
        // Set membership info on left card
        if (response?.data?.membership) {
            let membership = response.data.membership;
            let membershipLabel = $("#profile_membership");
            let membershipContainer = $("#profile_membership_container");
            
            // Set color based on membership type
            membershipLabel.removeClass('label-warning label-primary label-secondary');
            if (membership.type.toLowerCase().includes('gold')) {
                membershipLabel.addClass('label-warning'); // Gold color
            } else if (membership.type.toLowerCase().includes('student')) {
                membershipLabel.addClass('label-primary'); // Blue color
            } else {
                membershipLabel.addClass('label-secondary'); // Default gray
            }
            
            // Build membership text with code and status
            let membershipText = membership.type + ' (' + membership.code + ')';
            membershipLabel.text(membershipText);
            
            // Show active/expired status
            let statusBadge = membership.is_active 
                ? '<span class="label label-light-success label-inline font-weight-bold label-sm ml-2">Active</span>'
                : '<span class="label label-light-danger label-inline font-weight-bold label-sm ml-2">Expired</span>';
            membershipContainer.html('<span class="label label-inline font-weight-bold label-lg ' + 
                (membership.type.toLowerCase().includes('gold') ? 'label-warning' : 
                 membership.type.toLowerCase().includes('student') ? 'label-primary' : 'label-secondary') + 
                '" id="profile_membership">' + membershipText + '</span>' + statusBadge);
            membershipContainer.show();
        } else {
            $("#profile_membership_container").hide();
        }
        
        $(".statuses").addClass("d-none");
        if (patient.active == 1) {
            $("#profile-active").removeClass("d-none");
            $("#active-icon").removeClass("d-none");
        } else {
            $("#profile-inactive").removeClass("d-none");
            $("#inactive-icon").removeClass("d-none");
        }
        if (patient.image_src) {
            let image = asset_url + 'storage/patient_image/' + patient.image_src;
            $("#profile_patient_avatar").css('background-image', "url(" + image + ")");
            $(".patient_profile_image").css('background-image', "url(" + image + ")");
        }
    }
}

function changeProfilePage($this, page_id) {

    let loadScript = true;

    $("#kt_profile_aside").removeClass("d-none");
    $(".main-patient-section").attr("style", "margin-left: 2rem !important");
    $("#page_name").text($this.text());
    $(".change-tab").removeClass("nav-bar-active");
    $this.addClass("nav-bar-active");
    $(".submit-btn").addClass("d-none");
    $(".toolbar-" + page_id).removeClass("d-none");
    $(".content-section").addClass("d-none");
    $("#" + page_id).removeClass("d-none");
    $(".profile-buttons").addClass("d-none");
    $(".toolbar-" + page_id).removeClass("d-none");
    
    // Show/hide Edit Patient button based on active tab (only visible on profile tabs)
    if (page_id === 'personal_info' || page_id === 'change_profile_picture') {
        $(".profile-edit-btn").removeClass('d-none');
    } else {
        $(".profile-edit-btn").addClass('d-none');
    }
    if (page_id != 'personal_info' && page_id != 'change_profile_picture') {
        $("#kt_profile_aside").addClass("d-none");
        $(".main-patient-section").attr("style", "margin-left: 0px !important");
    }
    if (page_id == 'personal_info') {
        $(".personal-info").addClass("nav-bar-active");
        $(".change_profile_pic").removeClass("active");
        $(".persnl_info").removeClass("nav-bar-active");
        $(".personal-info").addClass("active");
        $(".persnl_info").addClass("active");
        $(".profile-buttons").removeClass("d-none");
        $(".submit-btn").addClass("d-none");
        loadScript = false;
    }
    if (page_id == 'change_profile_picture') {
        $(".personal-info").addClass("nav-bar-active");
        $(".change_profile_pic").removeClass("nav-bar-active");
        $(".change_profile_pic").addClass("active");
        $(".persnl_info").removeClass("active");
        $(".personal-info").addClass("active");
        $(".profile-buttons").removeClass("d-none");
        $(".submit-btn").addClass("d-none");
        loadScript = false;
    }
    setQueryStringParameter('tab', page_id);
    loadDataTable(page_id, loadScript);
}

function loadDataTable(page_id, loadScript = true) {
    if (loadScript) {
        /*load script on change tab and then init datatable*/
        let url;
        
        if (page_id === 'plan-form') {
            // Load patient-specific table_url first, then shared create-plan.js
            window.isPatientCardContext = true;
            window.patientCardPatientId = patientCardID;
            
            // Check if script already loaded to prevent duplicate initialization
            if (!window.planScriptLoaded) {
                window.planScriptLoaded = true;
                
                // Load patient-specific plan-form.js to set table_url and table_columns
                let patientUrl = asset_url + "assets/js/pages/patients/plan-form.js?v=" + Date.now();
                $.getScript(patientUrl, function() {
                    // Then load shared create-plan.js for actions/functions
                    let sharedUrl = asset_url + "assets/js/pages/admin_settings/create-plan.js?v=" + Date.now();
                    $.getScript(sharedUrl, function() {
                        // Also load bundle JS files
                        $.getScript(asset_url + "assets/js/pages/admin_settings/create-bundle.js");
                        $.getScript(asset_url + "assets/js/pages/admin_settings/edit-bundle.js");
                        // Also load membership JS file
                        $.getScript(asset_url + "assets/js/pages/admin_settings/create-membership.js");
                        
                        setTimeout(function () {
                            let className = "." + page_id;
                            let datatableExist = $("#" + page_id).find(className).html();
                            if (typeof table_url !== 'undefined' && typeof datatableExist !== 'undefined') {
                                if (datatableExist.length === 0) {
                                    console.log('Initializing plans datatable with class selector');
                                    KTPatientDatatable.init("." + page_id);
                                } else {
                                    if (typeof patientDatatable !== 'undefined' && typeof patientDatatable[className] !== 'undefined') patientDatatable[className].search({datatable_reload: 'reload'}, 'search');
                                }
                            }
                        }, 1000);
                    });
                });
            } else {
                // Script already loaded, just reload datatable
                setTimeout(function () {
                    let className = "." + page_id;
                    let datatableExist = $("#" + page_id).find(className).html();
                    if (typeof table_url !== 'undefined' && typeof datatableExist !== 'undefined') {
                        if (datatableExist.length === 0) {
                            KTPatientDatatable.init("." + page_id);
                        } else {
                            if (typeof patientDatatable !== 'undefined' && typeof patientDatatable[className] !== 'undefined') patientDatatable[className].search({datatable_reload: 'reload'}, 'search');
                        }
                    }
                }, 1000);
            }
        } else if (page_id === 'consultation-form') {
            // Load consultations scripts
            window.isPatientCardContext = true;
            window.patientCardPatientId = patientCardID;
            
            let className = "." + page_id;
            
            // Load consultation scripts only once
            if (!window.consultationScriptLoaded) {
                window.consultationScriptLoaded = true;
                
                // First load the shared consultation-common.js
                let consultationCommonUrl = asset_url + "assets/js/pages/appointment/consultation-common.js?v=" + Date.now();
                $.getScript(consultationCommonUrl, function() {
                    // Then load the patient-specific consultation-form.js
                    let consultationDatatableUrl = asset_url + "assets/js/pages/patients/consultation-form.js?v=" + Date.now();
                    $.getScript(consultationDatatableUrl, function() {
                        // Store the consultation-specific variables
                        window.consultation_table_url = table_url;
                        window.consultation_table_columns = table_columns;
                        
                        setTimeout(function () {
                            if (typeof patientDatatable !== 'undefined' && typeof patientDatatable[className] !== 'undefined') {
                                patientDatatable[className].destroy();
                                $(className).empty();
                            }
                            KTPatientDatatable.init(className);
                        }, 300);
                    });
                });
                
                // Load other required scripts only once
                let invoiceUrl = asset_url + "assets/js/pages/appointment/invoice.js?v=" + Date.now();
                $.getScript(invoiceUrl);
            } else {
                // Script already loaded, restore consultation-specific variables
                table_url = window.consultation_table_url;
                table_columns = window.consultation_table_columns;
                
                setTimeout(function () {
                    if (typeof patientDatatable !== 'undefined' && typeof patientDatatable[className] !== 'undefined') {
                        patientDatatable[className].destroy();
                        $(className).empty();
                    }
                    KTPatientDatatable.init(className);
                }, 300);
            }
        } else if (page_id === 'treatment-form') {
            // Load treatments scripts
            window.isPatientCardContext = true;
            window.patientCardPatientId = patientCardID;
            
            let className = "." + page_id;
            
            // Load treatment-form.js only once
            if (!window.treatmentScriptLoaded) {
                window.treatmentScriptLoaded = true;
                
                let treatmentDatatableUrl = asset_url + "assets/js/pages/patients/treatment-form.js?v=" + Date.now();
                $.getScript(treatmentDatatableUrl, function() {
                    // Store the treatment-specific variables
                    window.treatment_table_url = table_url;
                    window.treatment_table_columns = table_columns;
                    
                    setTimeout(function () {
                        if (typeof patientDatatable !== 'undefined' && typeof patientDatatable[className] !== 'undefined') {
                            patientDatatable[className].destroy();
                            $(className).empty();
                        }
                        KTPatientDatatable.init(className);
                    }, 300);
                });
                
                // Load other required scripts only once (if not already loaded by consultations)
                if (!window.consultationScriptLoaded) {
                    let invoiceUrl = asset_url + "assets/js/pages/appointment/invoice.js?v=" + Date.now();
                    let commonUrl = asset_url + "assets/js/pages/appointment/common.js?v=" + Date.now();
                    $.getScript(invoiceUrl);
                    $.getScript(commonUrl);
                }
            } else {
                // Script already loaded, restore treatment-specific variables
                table_url = window.treatment_table_url;
                table_columns = window.treatment_table_columns;
                
                setTimeout(function () {
                    if (typeof patientDatatable !== 'undefined' && typeof patientDatatable[className] !== 'undefined') {
                        patientDatatable[className].destroy();
                        $(className).empty();
                    }
                    KTPatientDatatable.init(className);
                }, 300);
            }
        } else if (page_id === 'voucher-form') {
            // Load voucher-form.js for patient card vouchers
            window.isPatientCardContext = true;
            window.patientCardPatientId = patientCardID;
            
            let className = "." + page_id;
            
            // Set table_url directly here before loading script
            table_url = '/api/patients/' + patientCardID + '/vouchers-datatable';
            
            // Load voucher-form.js only once (for columns and functions)
            if (!window.voucherScriptLoaded) {
                window.voucherScriptLoaded = true;
                
                let voucherUrl = asset_url + "assets/js/pages/patients/voucher-form.js?v=" + Date.now();
                $.getScript(voucherUrl, function() {
                    setTimeout(function () {
                        if (typeof patientDatatable !== 'undefined' && typeof patientDatatable[className] !== 'undefined') {
                            patientDatatable[className].destroy();
                            $(className).empty();
                        }
                        KTPatientDatatable.init(className);
                    }, 300);
                });
            } else {
                // Script already loaded, just reinitialize datatable
                setTimeout(function () {
                    if (typeof patientDatatable !== 'undefined' && typeof patientDatatable[className] !== 'undefined') {
                        patientDatatable[className].destroy();
                        $(className).empty();
                    }
                    KTPatientDatatable.init(className);
                }, 300);
            }
        } else {
            url = asset_url + "assets/js/pages/patients/" + page_id + ".js?v=" + Date.now();
            $.getScript(url);
            setTimeout(function () {
                let className = "." + page_id;
                let datatableExist = $("#" + page_id).find(className).html();
                if (typeof table_url !== 'undefined' && typeof datatableExist !== 'undefined') {
                    if (datatableExist.length === 0) {
                        KTPatientDatatable.init("." + page_id);
                    } else {
                        if (typeof patientDatatable !== 'undefined' && typeof patientDatatable[className] !== 'undefined') patientDatatable[className].search({datatable_reload: 'reload'}, 'search');
                    }
                }
            }, 1000);
        }
        
        $(".change-tab").attr("style", "pointer-events: none !important;color: black");
        $(".horizontal-nav-bar-li").attr("style", "color: white !important");
        setTimeout(function () {
            $(".change-tab").attr("style", "pointer-events: all !important");
        }, 1500);
    }
}

function savePatientImage() {
    let form_id = 'save_profile_image';
    let form = document.getElementById(form_id);
    submitFileForm($(form).attr('action'), $(form).attr('method'), form_id, function (response) {
        if (response.status) {
            $("#profile_patient_avatar").css('background-image', "url("+response.data.image+")");
            toastr.success(response.message);
        } else {
            toastr.error(response.message);
        }
    }, true);
}

/**
 * Assign voucher to patient from patient detail page
 * Opens modal and pre-fills patient ID
 */
function assignVoucherToPatient() {
    // Set form action with patient ID
    $("#modal_edit_vouchers_form").attr("action", route('admin.patients.assignvoucher', {id: patientCardID}));
    
    // Clear amount field
    $('#edit_amount').val('');
    
    // Show modal first
    $("#modal_assign_voucher_patient").modal("show");
    
    // Load voucher types - using new ID: patient_voucher_select
    setTimeout(function() {
        console.log('Loading vouchers into patient_voucher_select');
        loadVoucherTypesForPatient(function() {
            console.log('Vouchers loaded successfully');
        });
    }, 300);
    
    // Setup form submission handler
    setupVoucherFormSubmission();
}

function setupVoucherFormSubmission() {
    // Remove any existing handlers to avoid duplicates
    $(document).off('submit', '#modal_edit_vouchers_form');
    
    // Add submit handler using event delegation
    $(document).on('submit', '#modal_edit_vouchers_form', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $form = $(this);
        var voucherId = $('#patient_voucher_select').val();
        var amount = $('#edit_amount').val();
        
        // Simple validation
        if (!voucherId) {
            toastr.error('Please select a voucher type');
            return false;
        }
        
        if (!amount || amount <= 0) {
            toastr.error('Please enter a valid amount');
            return false;
        }
        
        // Show loading
        var $submitBtn = $form.find('button[type="submit"]');
        var originalHtml = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');
        
        // Submit via API
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '/api/patients/assignvoucher',
            type: 'POST',
            data: {
                patient_id: patientCardID,
                voucher_id: voucherId,
                amount: amount
            },
            success: function(response) {
                if (response.status) {
                    toastr.success(response.message || 'Voucher assigned successfully');
                    $("#modal_assign_voucher_patient").modal("hide");
                    
                    // Reset form
                    $('#patient_voucher_select').val('');
                    $('#edit_amount').val('');
                    
                    // Refresh voucher datatable
                    if (typeof patientDatatable !== 'undefined' && patientDatatable['.voucher-form']) {
                        patientDatatable['.voucher-form'].search({datatable_reload: 'reload'}, 'search');
                    }
                } else {
                    toastr.error(response.message || 'Failed to assign voucher');
                }
            },
            error: function(xhr) {
                var errorMsg = 'An error occurred';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                toastr.error(errorMsg);
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalHtml);
            }
        });
        
        return false;
    });
}

// Initialize voucher form submission handler on document ready
$(document).ready(function() {
    // Use click handler on submit button instead of form submit
    $(document).on('click', '#modal_edit_vouchers_form button[type="submit"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var voucherId = $('#patient_voucher_select').val();
        var amount = $('#edit_amount').val();
        
        // Simple validation
        if (!voucherId) {
            toastr.error('Please select a voucher type');
            return false;
        }
        
        if (!amount || amount <= 0) {
            toastr.error('Please enter a valid amount');
            return false;
        }
        
        // Show loading
        var $submitBtn = $(this);
        var originalHtml = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');
        
        // Submit via API
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '/api/patients/assignvoucher',
            type: 'POST',
            data: {
                patient_id: patientCardID,
                voucher_id: voucherId,
                amount: amount
            },
            success: function(response) {
                if (response.status) {
                    toastr.success(response.message || 'Voucher assigned successfully');
                    $("#modal_assign_voucher_patient").modal("hide");
                    
                    // Reset form
                    $('#patient_voucher_select').val('');
                    $('#edit_amount').val('');
                    
                    // Refresh voucher datatable
                    if (typeof patientDatatable !== 'undefined' && patientDatatable['.voucher-form']) {
                        patientDatatable['.voucher-form'].search({datatable_reload: 'reload'}, 'search');
                    }
                } else {
                    toastr.error(response.message || 'Failed to assign voucher');
                }
            },
            error: function(xhr) {
                var errorMsg = 'An error occurred';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                toastr.error(errorMsg);
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalHtml);
            }
        });
        
        return false;
    });
});

function loadVoucherTypesForPatient(callback) {
    console.log('Loading voucher types...');
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.vouchersTypes.getListing'),
        type: "GET",
        cache: false,
        success: function(response) {
            console.log('Voucher types response:', response);
            if (response.data) {
                // Build options HTML
                var optionsHtml = '<option value="">Select Voucher Type</option>';
                $.each(response.data, function(id, name) {
                    optionsHtml += '<option value="' + id + '">' + name + '</option>';
                });
                
                // Set options using native DOM - using new ID: patient_voucher_select
                var selectElement = document.getElementById('patient_voucher_select');
                if (selectElement) {
                    selectElement.innerHTML = optionsHtml;
                    console.log('Options set. Options count:', selectElement.options.length);
                } else {
                    console.error('Select element patient_voucher_select not found!');
                }
                
                // Call callback after options are loaded
                if (typeof callback === 'function') {
                    callback();
                }
            } else {
                console.error('No data in response');
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            console.error('Error loading voucher types:', thrownError);
            toastr.error('Failed to load voucher types');
            
            if (typeof callback === 'function') {
                callback();
            }
        }
    });
}

/**
 * Create appointment from patient detail page
 * Gets last appointment location and redirects to calendar
 */
function createAppointmentFromPatient(appointmentType) {
    // Show loading state
    var btnId = appointmentType === 'consultancy' ? '#create-consultation-btn' : '#create-treatment-btn';
    var $btn = $(btnId);
    var originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
    
    // Get last appointment location for this patient
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.patients.getLastAppointmentLocation', {id: patientCardID}),
        type: "GET",
        data: {
            appointment_type: appointmentType
        },
        cache: false,
        success: function (response) {
            var locationId = null;
            
            if (response.status && response.data && response.data.location_id) {
                locationId = response.data.location_id;
            }
            
            // Build calendar URL with location filter and tab parameter
            var calendarRoute = appointmentType === 'consultancy' 
                ? route('admin.consultancy.index') 
                : route('admin.treatment.index');
            
            // Add location filter and tab parameter
            var params = [];
            if (locationId) {
                params.push('location_id=' + locationId);
            }
            params.push('tab=' + appointmentType);
            
            calendarRoute += '?' + params.join('&');
            
            // Redirect to calendar
            window.location.href = calendarRoute;
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Error getting last appointment location:', thrownError);
            
            // Redirect to calendar anyway without location filter but with tab parameter
            var calendarRoute = appointmentType === 'consultancy' 
                ? route('admin.consultancy.index') 
                : route('admin.treatment.index');
            
            calendarRoute += '?tab=' + appointmentType;
            
            window.location.href = calendarRoute;
        },
        complete: function() {
            // Reset button state
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}
