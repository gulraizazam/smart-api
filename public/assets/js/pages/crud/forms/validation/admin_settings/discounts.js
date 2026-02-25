
var AddValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_add_discounts_form';
        let form = document.getElementById(modal_id);
        
        // Use manual validation to handle both simple and configurable discounts
        $(form).off('submit').on('submit', function(e) {
            e.preventDefault();
            
            let discountType = $('#add_discount_type').val();
            let errors = [];
            
            // Common validations
            let name = $('#add_name').val();
            let start = $('#add_start').val();
            let end = $('#add_end').val();
            
            if (!name) errors.push('Discount name is required');
            if (!start) errors.push('Valid From date is required');
            if (!end) errors.push('Valid To date is required');
            
            // Common field validations (for both types)
            let discountApplicable = $('#add_amount_types').val();
            if (!discountApplicable) errors.push('Discount Applicable On is required');
            
            if (discountType === 'Configurable') {
                // Configurable discount validations
                let sessionsBuy = $('#add_sessions_buy').val();
                let buyMode = $('input[name="buy_mode"]:checked').val() || 'service';
                
                if (!sessionsBuy) errors.push('BUY sessions count is required');
                
                if (buyMode === 'category') {
                    let categories = $('#add_base_category').val();
                    if (!categories || categories.length === 0) errors.push('At least one BUY category is required');
                } else {
                    let baseService = $('#add_base_service').val();
                    if (!baseService) errors.push('BUY service is required');
                }
                
                // Validate GET services
                let getRows = $('#add_get_services_container .get-service-row');
                if (getRows.length === 0) {
                    errors.push('At least one GET service is required');
                } else {
                    getRows.each(function(index) {
                        let sessions = $(this).find('[name^="sessions["]').val();
                        let isSameService = $(this).find('.same-service-check').is(':checked');
                        let service = $(this).find('[name^="services_name["]').val();
                        let discType = $(this).find('[name^="disc_type["]:checked').val();
                        
                        if (!sessions) errors.push('GET row ' + (index + 1) + ': Sessions is required');
                        if (!isSameService && !service) errors.push('GET row ' + (index + 1) + ': Service is required');
                        if (!discType) errors.push('GET row ' + (index + 1) + ': Discount type is required');
                        
                        if (discType === 'custom') {
                            let amount = $(this).find('[name^="configurable_amount["]').val();
                            if (!amount || amount <= 0 || amount > 99) {
                                errors.push('GET row ' + (index + 1) + ': Percentage must be between 1 and 99');
                            }
                        }
                    });
                }
            }
            
            if (errors.length > 0) {
                toastr.error(errors[0]);
                select2Validation();
                return;
            }
            
            // Submit form
            submitForm($(form).attr('action'), 'POST', $(form).serialize(), function (response) {
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
        let modal_id = 'modal_edit_discounts_form';
        let form = document.getElementById(modal_id);
        
        // Use manual validation to handle both simple and configurable discounts
        $(form).off('submit').on('submit', function(e) {
            e.preventDefault();
            
            let discountType = $('#edit_discount_type_hidden').val();
            let errors = [];
            
            // Common validations
            let name = $('#edit_name').val();
            let start = $('#edit_start').val();
            let end = $('#edit_end').val();
            
            if (!name) errors.push('Discount name is required');
            if (!start) errors.push('Valid From date is required');
            if (!end) errors.push('Valid To date is required');
            
            // Common field validations (for both types)
            let discountApplicable = $('#edit_amount_types').val();
            if (!discountApplicable) errors.push('Discount Applicable On is required');
            
            if (discountType === 'Configurable') {
                // Configurable discount validations
                let sessionsBuy = $('#edit_sessions_buy').val();
                let editBuyMode = $('input[name="edit_buy_mode"]:checked').val() || 'service';
                
                if (!sessionsBuy) errors.push('BUY sessions count is required');
                
                if (editBuyMode === 'category') {
                    let categories = $('#edit_base_category').val();
                    if (!categories || categories.length === 0) errors.push('At least one BUY category is required');
                } else {
                    let baseService = $('#edit_base_service').val();
                    if (!baseService) errors.push('BUY service is required');
                }
                
                // Validate GET services
                let getRows = $('#edit_get_services_container .get-service-row');
                if (getRows.length === 0) {
                    errors.push('At least one GET service is required');
                } else {
                    getRows.each(function(index) {
                        let sessions = $(this).find('[name^="edit_sessions["]').val();
                        let isSameService = $(this).find('.same-service-check').is(':checked');
                        let service = $(this).find('[name^="edit_services_name["]').val();
                        let discType = $(this).find('[name^="edit_disc_type["]:checked').val();
                        
                        if (!sessions) errors.push('GET row ' + (index + 1) + ': Sessions is required');
                        if (!isSameService && !service) errors.push('GET row ' + (index + 1) + ': Service is required');
                        if (!discType) errors.push('GET row ' + (index + 1) + ': Discount type is required');
                        
                        if (discType === 'custom') {
                            let amount = $(this).find('[name^="configurable_amount["]').val();
                            if (!amount || amount <= 0 || amount > 99) {
                                errors.push('GET row ' + (index + 1) + ': Percentage must be between 1 and 99');
                            }
                        }
                    });
                }
            }
            
            if (errors.length > 0) {
                toastr.error(errors[0]);
                select2Validation();
                return;
            }
            
            // Submit form
            submitForm($(form).attr('action'), 'POST', $(form).serialize(), function (response) {
                if (response.status == true) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable('discount');
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

var AllocateValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_allocate_discounts_form';
        let form = document.getElementById(modal_id);

        // Handle submit manually to avoid submitButton plugin blocking re-submission
        $(form).off('submit').on('submit', function(e) {
            e.preventDefault();

            // Check if this is a configurable discount
            let discountType = $("#discount_type_hidden").val();
            let isConfigurable = discountType === 'Configurable';

            // Manual validation
            let location_id = $("#locations").val();
            let errors = [];
            
            if (!location_id) errors.push('The centre field is required');
            
            // For regular discounts, validate services and allocation fields
            if (!isConfigurable) {
                let service_ids = $("#services").val();
                let allocation_type = $("#allocation_type").val();
                let allocation_amount = $("#allocation_amount").val();
                
                if (!service_ids || service_ids.length === 0) errors.push('The service field is required');
                if (!allocation_type) errors.push('The type field is required');
                if (!allocation_amount) errors.push('The amount field is required');
            }

            if (errors.length > 0) {
                toastr.error(errors[0]);
                select2Validation();
                return;
            }

            // Use different submit function based on discount type
            if (isConfigurable) {
                submitConfigurableAllocation(function (response) {
                    if (response.status == true) {
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                });
            } else {
                submitData(function (response) {
                    if (response.status == true) {
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                });
            }
        });
    }

    return {
        init: function() {
            validation();
        }
    };
}();

// Configurable Discount Add Validation
var ConfigurableAddValidation = function () {
    var validation = function () {
        let modal_id = 'modal_add_configurable_discount_form';
        let form = document.getElementById(modal_id);
        
        if (!form) return;

        $(form).off('submit').on('submit', function(e) {
            e.preventDefault();
            
            // Manual validation
            let errors = [];
            let name = $('#conf_discount_name').val();
            let sessions_buy = $('[name="sessions_buy"]').val();
            let base_service = $('#conf_base_service').val();
            let start = $('#conf_start').val();
            let end = $('#conf_end').val();
            
            if (!name) errors.push('Discount name is required');
            if (!sessions_buy) errors.push('BUY sessions count is required');
            if (!base_service) errors.push('BUY service is required');
            if (!start) errors.push('Valid From date is required');
            if (!end) errors.push('Valid To date is required');
            
            // Validate GET services
            let getRows = $('#conf_get_services_container .get-service-row');
            if (getRows.length === 0) {
                errors.push('At least one GET service is required');
            } else {
                getRows.each(function(index) {
                    let sessions = $(this).find('[name^="sessions["]').val();
                    let service = $(this).find('[name^="services_name["]').val();
                    let discType = $(this).find('[name^="disc_type["]:checked').val();
                    
                    if (!sessions) errors.push('GET row ' + (index + 1) + ': Sessions is required');
                    if (!service) errors.push('GET row ' + (index + 1) + ': Service is required');
                    if (!discType) errors.push('GET row ' + (index + 1) + ': Discount type is required');
                    
                    if (discType === 'custom') {
                        let amount = $(this).find('[name^="configurable_amount["]').val();
                        if (!amount || amount <= 0 || amount > 99) {
                            errors.push('GET row ' + (index + 1) + ': Percentage must be between 1 and 99');
                        }
                    }
                });
            }
            
            if (errors.length > 0) {
                toastr.error(errors[0]);
                return;
            }
            
            // Submit form
            submitForm($(form).attr('action'), 'POST', $(form).serialize(), function (response) {
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

// Configurable Discount Edit Validation
var ConfigurableEditValidation = function () {
    var validation = function () {
        let modal_id = 'modal_edit_configurable_discount_form';
        let form = document.getElementById(modal_id);
        
        if (!form) return;

        $(form).off('submit').on('submit', function(e) {
            e.preventDefault();
            
            // Manual validation
            let errors = [];
            let name = $('#edit_conf_discount_name').val();
            let sessions_buy = $('#edit_conf_sessions_buy').val();
            let base_service = $('#edit_conf_base_service').val();
            let start = $('#edit_conf_start').val();
            let end = $('#edit_conf_end').val();
            
            if (!name) errors.push('Discount name is required');
            if (!sessions_buy) errors.push('BUY sessions count is required');
            if (!base_service) errors.push('BUY service is required');
            if (!start) errors.push('Valid From date is required');
            if (!end) errors.push('Valid To date is required');
            
            // Validate GET services
            let getRows = $('#edit_conf_get_services_container .get-service-row');
            if (getRows.length === 0) {
                errors.push('At least one GET service is required');
            } else {
                getRows.each(function(index) {
                    let sessions = $(this).find('[name^="edit_sessions["]').val();
                    let service = $(this).find('[name^="edit_services_name["]').val();
                    let discType = $(this).find('[name^="edit_disc_type["]:checked').val();
                    
                    if (!sessions) errors.push('GET row ' + (index + 1) + ': Sessions is required');
                    if (!service) errors.push('GET row ' + (index + 1) + ': Service is required');
                    if (!discType) errors.push('GET row ' + (index + 1) + ': Discount type is required');
                    
                    if (discType === 'custom') {
                        let amount = $(this).find('[name^="configurable_amount["]').val();
                        if (!amount || amount <= 0 || amount > 99) {
                            errors.push('GET row ' + (index + 1) + ': Percentage must be between 1 and 99');
                        }
                    }
                });
            }
            
            if (errors.length > 0) {
                toastr.error(errors[0]);
                return;
            }
            
            // Submit form
            submitForm($(form).attr('action'), 'POST', $(form).serialize(), function (response) {
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

jQuery(document).ready(function() {
    AddValidation.init();
    EditValidation.init();
    AllocateValidation.init();
    ConfigurableAddValidation.init();
    ConfigurableEditValidation.init();
});

// Submit allocation for configurable discounts (centre only)
function submitConfigurableAllocation(callback) {
    let location_id = $("#locations").val();
    let discount_id = $("#discount_id").val();

    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/discounts/allocate-configurable',
        type: "POST",
        data: {
            discount_id: discount_id,
            location_id: location_id
        },
        cache: false,
        timeout: 30000,
        success: function (response) {
            if (response.status == true) {
                var data = response.data;
                
                // Add row to table showing the allocation
                if (data.record) {
                    $('#allocate_services').append(serviceLocationGrouped(
                        [data.record.id], 
                        data.record.location_name, 
                        data.record.service_name,
                        '-',
                        '-',
                        'configurable'
                    ));
                }
                
                // Reset location dropdown
                $("#locations").val('').trigger('change');
                
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm();
            } else {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm();
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            let errorMsg = 'Unknown error';
            if (xhr.status == '401') {
                errorMsg = 'You are not authorized to access this resource';
            } else if (xhr.status == 500) {
                errorMsg = 'Server error (500). Check server logs.';
            } else {
                errorMsg = 'Error ' + xhr.status + ': ' + thrownError;
            }

            callback({
                'status': 0,
                'message': errorMsg,
            });
            hideSpinnerRestForm();
        }
    });
}

function submitData(callback) {

    let ids = [];
    let location_id = $("#locations").val();
    let service_ids = $("#services").val(); // Now returns array for multiselect

    // Get allocation-level fields (required in new approach)
    let allocation_type = $("#allocation_type").val();
    let allocation_amount = $("#allocation_amount").val();
    let allocation_slug = $("#allocation_slug").val();

    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/discounts/saveDervice',
        type: "POST",
        data: {
            voucher_id: $("#discount_id").val(), 
            discount_id: $("#discount_id").val(),
            location_id: location_id,
            service_ids: service_ids,
            allocation_type: allocation_type,
            allocation_amount: allocation_amount,
            allocation_slug: allocation_slug
        },
        cache: false,
        timeout: 30000,
        beforeSend: function() {
            console.log('Request sending...');
        },
        success: function (response) {
           
            if (response.status == true) {
                var data = response.data;
                
                // Remove any individual services that were replaced by "All Services"
                if (data.removed_ids && data.removed_ids.length > 0) {
                    data.removed_ids.forEach(function(id) {
                        $('.HR_' + id).remove();
                    });
                }
                
                // Handle multiple records (multiselect services) - display as grouped row
                if (data.records && data.records.length > 0) {
                    let ids = data.records.map(r => r.id);
                    let serviceNames = data.records.map(r => r.service_name).join(', ');
                    let firstRecord = data.records[0];
                    $('#allocate_services').append(serviceLocationGrouped(
                        ids, 
                        firstRecord.location_name, 
                        serviceNames,
                        firstRecord.type || '-',
                        firstRecord.amount || '-',
                        firstRecord.slug || 'default'
                    ));
                }
                
                // Reset allocation form fields after successful add
                $("#services").val(null).trigger('change');
                $("#allocation_type").val('').trigger('change');
                $("#allocation_amount").val('');
                $("#allocation_slug").val('default').trigger('change');
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm();
            } else {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm();
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('=== AJAX ERROR DETAILS ===');
            console.error('Status:', xhr.status);
            console.error('Status Text:', xhr.statusText);
            console.error('Thrown Error:', thrownError);
            console.error('Response Text:', xhr.responseText);
            console.error('Ready State:', xhr.readyState);
            console.error('Full XHR:', xhr);

            let errorMsg = 'Unknown error';
            if (xhr.status == '401') {
                errorMsg = 'You are not authorized to access this resource';
            } else if (thrownError === 'timeout') {
                errorMsg = 'Request timeout. Please check your connection and try again.';
            } else if (xhr.status == 0) {
                errorMsg = 'Network error. Please check your internet connection or CORS settings.';
            } else if (xhr.status == 500) {
                errorMsg = 'Server error (500). Check server logs.';
            } else if (xhr.status == 404) {
                errorMsg = 'Route not found (404). URL: /api/saveDervice';
            } else {
                errorMsg = 'Error ' + xhr.status + ': ' + thrownError;
            }

            callback({
                'status': 0,
                'message': errorMsg,
            });
            hideSpinnerRestForm();
        },
        complete: function() {
            console.log('Request completed');
        }
    });
}
