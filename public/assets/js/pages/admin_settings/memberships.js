var table_url = route('admin.memberships.datatable');

var table_columns = [{
    field: 'patient_id',
    title: 'Patient ID',
    sortable: false,
    width: 80,
    template: function (data) {
        if (data.patient_unique_id) {
            return 'C-' + data.patient_unique_id;
        } else if (data.patient_id && data.patient_id !== 'N/A') {
            return 'C-' + data.patient_id;
        } else {
            return '-';
        }
    }
}, {
    field: 'patient',
    title: 'Patient',
    sortable: false,
    width: 110,
    template: function (data) {
        if (data.patient && data.patient !== 'N/A') {
            return data.patient;
        } else {
            return '-';
        }
    }
}, {
    field: 'code',
    title: 'Membership Code',
    sortable: false,
    width: 110,
}, {
    field: 'membership_type_id',
    title: 'Membership Type',
    sortable: false,
    width: 110,
}, {
    field: 'start_date',
    title: 'Start Date',
    sortable: false,
    width: 110,
    template: function (data) {
        
        if(data.start_date  !=undefined){
            return data.start_date;
        }else{
            return '<span class="text"></span>';
        }
    }
},{
    field: 'end_date',
    title: 'End date',
    sortable: false,
    width: 110,
    template: function (data) {
        if(data.end_date !=undefined){
            return data.end_date;
        }else{
            return '<span class="text"></span>';
        }
    }
},{
    field: 'status',
    title: 'Status',
    width: 80,
    template: function (data) {
        // Show "Not Assigned" if patient is not assigned
        if (!data.patient || data.patient === 'N/A') {
            return '<span class="label label-lg label-light-warning label-inline">Not Assigned</span>';
        }
        if (data.active == 1) {
            return '<span class="text text-success">Active</span>';
        } else {
            return '<span class="text text-danger">Expired</span>';
        }
    }
},{
    field: 'actions',
    title: 'Actions',
    sortable: false,
    width: 70,
    overflow: 'visible',
    autoHide: false,
    template: function(data) {
     
        return actions(data);
    }
}];


function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;
        let edit_url = route('admin.memberships.edit', { id: id });
      
        let delete_url = route('admin.memberships.destroy', { id: id });
      
        if (permissions.create || permissions.edit || permissions.view_details) {
            let actions = '<div class="dropdown dropdown-inline action-dots">';
           
        actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';
            
            // Add view option for all memberships with assigned patients (requires view_details permission)
            if (permissions.view_details && data.patient && data.patient !== 'N/A') {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="viewMembershipDetails(' + id + ', ' + data.is_student_membership + ');" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye text-primary"></i></span>\
                        <span class="navi-text">View Details</span>\
                    </a>\
                </li>';
            }
           
            if (permissions.edit) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="editRow(`' + edit_url + '`, '+id+');" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
            }
            if (permissions.delete) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-trash"></i></span>\
                        <span class="navi-text">Delete</span>\
                        </a>\
                     </li>';
            }
            actions += '</ul>\
        </div>\
    </div>';
            return actions;
        }
    }
    return '';
}

function createMembership(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {
          
            setMembershipCreateData(response);
          
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(Validation);
        }
    });

}
function setMembershipCreateData(response) {
    try {
        let membershipType = response.data.membershipType;
        
        let membershiptype_options = '<option value="">Select</option>';
       
        if (membershipType) {
            Object.entries(membershipType).forEach(function (membershipType) {
                membershiptype_options += '<option value="' + membershipType[0] + '">' + membershipType[1] + '</option>';
            });
        }
       
        $("#add_membership_type_id").html(membershiptype_options);
       
        
    } catch (error) {
        showException(error);
    }
}

function editRow(url, id) {
    $("#modal_edit_memberships").modal("show");
    $("#modal_edit_memberships_form").attr("action", route('admin.memberships.update', {id: id}));
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {
            setEditData(response);
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setEditData(response) {
    try {
        let membership = response.data.membership;
        let membershipType = response.data.membershipType;
        let membershipType_options = '<option value="">Select </option>';
        if (membershipType) {
            Object.entries(membershipType).forEach(function(membership) {
                membershipType_options += '<option value="' + membership[0] + '">' + membership[1] + '</option>';
            });
        }
       
        $("#edit_membership_type_id").html(membershipType_options);
        $("#edit_membership_type_id").val(membership.membership_type_id);
        $("#edit_code_name").val(membership.code);
       
    } catch (error) {
        showException(error);
    }
}
function importMembership() {
    let form_id = 'modal_import_memberships_form';
    let form = document.getElementById(form_id);
    if ($(".memberships_file").val() == '') {
        addValidation($(".memberships_file"))
        return false;
    }
    submitFileForm($(form).attr('action'), $(form).attr('method'), form_id, function (response) {
        if (response.status) {
            toastr.success(response.message);
            closePopup("modal_import_memberships_form");
            reInitTable();
        } else {
            toastr.error(response.message);
        }
    });
}

$("#export-memberships").on("click",function(){
    let code= $("#search_code_name").val();
    let membership_type_id = $("#search_membership_type").val();
    let assigned = $("#search_assigned_status").val();
    let status = $("#search_membership_status").val();
    let url = $(this).data('href');
    window.location.href =  url+'?&code='+code+'&membership_type_id='+membership_type_id+'&assigned='+assigned+'&status='+status+'&ext=xlsx';
});
$("#export-memberships-leads").on("click",function(){
    let code= $("#search_code_name").val();
    let membership_type_id = $("#search_membership_type").val();
    let assigned = $("#search_assigned_status").val();
    let status = $("#search_membership_status").val();
    let url = $(this).data('href');
    window.location.href =  url+'?&code='+code+'&membership_type_id='+membership_type_id+'&assigned='+assigned+'&status='+status;
});
function applyFilters(datatable) {
    $('#apply-filters').on('click', function() {
        let filters = {
            patient_id: $("#search_patient_id").val(),
            code: $("#search_code_name").val(),
            membership_type_id: $("#search_membership_type").val(),
            status: $("#search_membership_status").val(),
            location_id: $("#search_location_id").val(),
            sold_by: $("#search_sold_by").val(),
            assigned_at: $("#search_assigned_at").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function() {
        let filters = {
            patient_id: '',
            code: '',
            membership_type_id: '',
            created_by: '',
            status: '',
            location_id: '',
            sold_by: '',
            assigned_at: '',
            filter: 'filter_cancel',
        }
        // Clear patient search fields
        $('#membership_patient_search_input').val('');
        $('#search_patient_id').val('');
        $('#membership_patient_suggestions').hide();
        // Clear date range picker
        $('#search_assigned_at').val('');
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
   
    try {
       
        let membershipTypes = filter_values.membershipType;
        let users = filter_values.users;
        let locations = filter_values.locations;
        let soldByUsers = filter_values.soldByUsers;
        
        let user_options = '<option value="">All</option>';
        if (users) {
            Object.entries(users).forEach(function(user) {
                user_options += '<option value="' + user[0] + '">' + user[1] + '</option>';
            });
        }
        
        let location_options = '<option value="">All</option>';
        if (locations) {
            Object.entries(locations).forEach(function(location) {
                location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
            });
        }
        
        let sold_by_options = '<option value="">All</option>';
        if (soldByUsers) {
            Object.entries(soldByUsers).forEach(function(user) {
                sold_by_options += '<option value="' + user[0] + '">' + user[1] + '</option>';
            });
        }
      
        $("#search_created_by").html(user_options);
        $("#search_location_id").html(location_options);
        $("#search_sold_by").html(sold_by_options);
        
        // When location changes, update sold_by dropdown (use select2:select event)
        $("#search_location_id").off('select2:select select2:clear').on('select2:select select2:clear', function(e) {
            let locationId = $(this).val() || '';
            // Fetch sold by users for this location
            $.ajax({
                url: '/api/memberships/getsoldbyusers',
                type: 'GET',
                data: { location_id: locationId },
                success: function(response) {
                    let options = '<option value="">All</option>';
                    if (response.success && response.data && response.data.users) {
                        Object.entries(response.data.users).forEach(function(user) {
                            options += '<option value="' + user[0] + '">' + user[1] + '</option>';
                        });
                    }
                    $("#search_sold_by").html(options).val('').trigger('change.select2');
                },
                error: function() {
                    $("#search_sold_by").html('<option value="">All</option>').val('').trigger('change.select2');
                }
            });
        });
        
        // Initialize daterangepicker for assigned_at filter
        $('#search_assigned_at').daterangepicker({
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear',
                format: 'YYYY-MM-DD'
            },
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        });
        
        $('#search_assigned_at').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
        });
        
        $('#search_assigned_at').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
        });
        
        $("#search_patient_id").val(active_filters.patient_id);
        $("#search_code_name").val(active_filters.code);
        $("#search_assigned_at").val(active_filters.assigned_at);
        $("#search_membership_type").val(active_filters.membership_type_id).trigger('change.select2');
        $("#search_membership_status").val(active_filters.status).trigger('change.select2');
        $("#search_location_id").val(active_filters.location_id).trigger('change.select2');
        $("#search_created_by").val(active_filters.created_by).trigger('change.select2');
        
        // If location filter is active, fetch sold_by users for that location then set the value
        if (active_filters.location_id) {
            $.ajax({
                url: '/api/memberships/getsoldbyusers',
                type: 'GET',
                data: { location_id: active_filters.location_id },
                success: function(response) {
                    let options = '<option value="">All</option>';
                    if (response.success && response.data && response.data.users) {
                        Object.entries(response.data.users).forEach(function(user) {
                            options += '<option value="' + user[0] + '">' + user[1] + '</option>';
                        });
                    }
                    $("#search_sold_by").html(options).val(active_filters.sold_by).trigger('change.select2');
                },
                error: function() {
                    $("#search_sold_by").val(active_filters.sold_by).trigger('change.select2');
                }
            });
        } else {
            $("#search_sold_by").val(active_filters.sold_by).trigger('change.select2');
        }
        
        hideShowAdvanceFilters(active_filters);

    } catch (error) {
        showException(error);
    }
}

// Simple patient search with custom autocomplete (no Select2)
$(document).ready(function() {
    let debounceTimer;
    
    $('#membership_patient_search_input').on('input', function() {
        let searchVal = $(this).val();
        let $suggestions = $('#membership_patient_suggestions');
        
        // Clear hidden field when typing
        $('#search_patient_id').val('');
        
        if (searchVal.length < 2) {
            $suggestions.hide();
            return;
        }
        
        $suggestions.html('<div style="padding: 8px 12px; color: #666;">Searching...</div>').show();
        
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            $.ajax({
                type: "GET",
                url: route('admin.users.getpatient.optimized'),
                dataType: 'json',
                data: { search: searchVal },
                success: function(response) {
                    let patients = response.data.patients || [];
                    let html = '';
                    
                    if (patients.length) {
                        patients.forEach(function(patient) {
                            html += '<div class="patient-suggestion-item" data-id="' + patient.id + '" data-name="' + patient.name + '" data-phone="' + patient.phone + '" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">' + patient.name + ' - ' + patient.phone + '</div>';
                        });
                    } else {
                        html = '<div style="padding: 8px 12px; color: #999;">No patients found</div>';
                    }
                    
                    $suggestions.html(html);
                }
            });
        }, 300);
    });
    
    // Handle patient selection
    $(document).on('click', '.patient-suggestion-item', function() {
        let id = $(this).data('id');
        let name = $(this).data('name');
        let phone = $(this).data('phone');
        
        $('#membership_patient_search_input').val(name + ' - ' + phone);
        $('#search_patient_id').val(id);
        $('#membership_patient_suggestions').hide();
    });
    
    // Hide suggestions when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#membership_patient_search_input, #membership_patient_suggestions').length) {
            $('#membership_patient_suggestions').hide();
        }
    });
    
    // Hover effect
    $(document).on('mouseenter', '.patient-suggestion-item', function() {
        $(this).css('background-color', '#f5f5f5');
    }).on('mouseleave', '.patient-suggestion-item', function() {
        $(this).css('background-color', '#fff');
    });
});

function hideShowAdvanceFilters(active_filters) {
    // Show advance filters if assigned_at filter is active
    if ((typeof active_filters.assigned_at !== 'undefined' && active_filters.assigned_at != '')) {
        $(".membership-advance-filters").show();
        $(".membership-advance-arrow").removeClass("fa-caret-right").addClass("fa-caret-down");
    }
}

// Number format helper function
function number_format(number, decimals = 2, dec_point = '.', thousands_sep = ',') {
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
        s = '',
        toFixedFix = function(n, prec) {
            var k = Math.pow(10, prec);
            return '' + (Math.round(n * k) / k).toFixed(prec);
        };
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    return s.join(dec);
}

function addValidation(elem) {
    if (elem.val() == '') {
        elem.addClass("is-invalid");
        $(".lead_file_msg").removeClass("d-none");
    } else {
        elem.removeClass("is-invalid");
        $(".lead_file_msg").addClass("d-none");
    }
}

// View membership details (for both student and non-student memberships)
function viewMembershipDetails(membershipId, isStudentMembership) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.memberships.student_verification', { id: membershipId }),
        type: "GET",
        cache: false,
        beforeSend: function() {
            // Show loading
            Swal.fire({
                title: 'Loading...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        },
        success: function(response) {
            Swal.close();
            if (response.status) {
                showMembershipDetailsModal(response.data, isStudentMembership);
            } else {
                toastr.error(response.message || 'Failed to load details');
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            Swal.close();
            toastr.error('Failed to load membership details');
        }
    });
}

function showMembershipDetailsModal(data, isStudentMembership) {
    let membership = data.membership;
    let patient = data.patient;
    let verification = data.verification;
    let documents = data.documents || [];
    let serviceUsage = data.service_usage || { total_services: 0, total_discount_saved: 0, services: [] };
    
    // Build service usage section
    let serviceUsageSection = '';
    if (serviceUsage.services && serviceUsage.services.length > 0) {
        let servicesTableRows = '';
        serviceUsage.services.forEach(function(service, index) {
            let consumedText = service.consumed_at 
                ? '<span class="label label-success label-inline">Yes</span> <span class="text-dark" style="font-size: 13px;"> at ' + service.consumed_at + '</span>'
                : '<span class="label label-warning label-inline">No</span>';
            servicesTableRows += `
                <tr>
                    <td>${service.service_name}</td>
                    <td class="text-right">${number_format(service.service_price, 2)}</td>
                    <td class="text-right">${service.discount_type === 'Percentage' ? service.discount_amount + '%' : number_format(service.discount_amount, 2)}</td>
                    <td class="text-right">${number_format(service.net_amount, 2)}</td>
                    <td>${service.plan_date || '-'}</td>
                    <td>${consumedText}</td>
                </tr>
            `;
        });
        
        serviceUsageSection = `
            <!-- Service Usage -->
            <div class="mt-3">
                <h6 class="mb-2"><i class="la la-list-alt text-primary"></i> Membership Usage <span class="label label-sm label-light-info label-inline ml-2">${serviceUsage.total_services} Service(s)</span></h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center">Service</th>
                                <th class="text-center">Regular Price</th>
                                <th class="text-center">Discount</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Date</th>
                                <th class="text-center">Consumed</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${servicesTableRows}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    } else {
        serviceUsageSection = `
            <!-- Service Usage -->
            <div class="mt-3">
                <h6 class="mb-2"><i class="la la-list-alt text-primary"></i> Membership Usage</h6>
                <div class="alert alert-light-info py-2 mb-0">
                    <i class="la la-info-circle"></i> Not used on any services yet.
                </div>
            </div>
        `;
    }
    
    // Build documents HTML (only for student memberships)
    let documentsSection = '';
    if (isStudentMembership) {
        let documentsHtml = '';
        if (documents.length > 0) {
            documentsHtml = '<div class="row">';
            documents.forEach(function(doc, index) {
                let docUrl = '/storage/app/public/' + doc;
                documentsHtml += `
                    <div class="col-md-3 col-sm-4 col-6 mb-2">
                        <a href="${docUrl}" target="_blank" class="d-block border rounded p-1 text-center">
                            <img src="${docUrl}" style="height: 80px; width: 100%; object-fit: cover; border-radius: 4px;" alt="Doc ${index + 1}">
                            <small class="d-block text-muted mt-1">Doc ${index + 1}</small>
                        </a>
                    </div>
                `;
            });
            documentsHtml += '</div>';
        } else {
            documentsHtml = '<div class="alert alert-light-warning">No documents uploaded</div>';
        }
        
        documentsSection = `
            <!-- Verification Documents -->
            <div class="mt-2">
                <h6 class="mb-2"><i class="la la-file-image text-primary"></i> Verification Documents</h6>
                ${documentsHtml}
                ${verification && verification.submitted_at ? '<small class="text-muted">Submitted: ' + verification.submitted_at + '</small>' : ''}
            </div>
        `;
    }
    
    // Build membership status badge
    let membershipStatusClass = membership.status === 'Active' ? 'success' : (membership.status === 'Inactive' ? 'warning' : 'danger');
    let membershipStatusBadge = `<span class="label label-lg label-light-${membershipStatusClass} label-inline">${membership.status}</span>`;
    
    // Modal title based on membership type
    let modalTitle = isStudentMembership ? 'Student Membership Details' : 'Membership Details';
    let modalIcon = isStudentMembership ? 'la-user-graduate' : 'la-id-card';
    
    let modalContent = `
        <div class="modal fade" id="modal_membership_details" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header py-3">
                        <h5 class="modal-title">
                            <i class="la ${modalIcon} text-primary"></i>
                            ${modalTitle}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <i aria-hidden="true" class="ki ki-close"></i>
                        </button>
                    </div>
                    <div class="modal-body py-3">
                        <!-- Combined Patient & Membership Info -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-3">
                                <tbody>
                                    <tr>
                                        <td class="text-dark bg-light" width="20%">Patient</td>
                                        <td width="30%"><strong>${patient.name}</strong> (${patient.unique_id || '-'})</td>
                                        <td class="text-dark bg-light" width="20%">Code</td>
                                        <td width="30%"><strong>${membership.code}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-dark bg-light">Phone</td>
                                        <td>${patient.phone || '-'}</td>
                                        <td class="text-dark bg-light">Type</td>
                                        <td>${membership.type}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-dark bg-light">Email</td>
                                        <td>${patient.email || '-'}</td>
                                        <td class="text-dark bg-light">Status</td>
                                        <td>${membershipStatusBadge}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-dark bg-light">Period</td>
                                        <td colspan="3">${membership.start_date || '-'} to ${membership.end_date || '-'}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        ${serviceUsageSection}
                        
                        ${documentsSection}
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-light-primary font-weight-bold" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    $('#modal_membership_details').remove();
    
    // Append and show modal
    $('body').append(modalContent);
    $('#modal_membership_details').modal('show');
    
    // Clean up on close
    $('#modal_membership_details').on('hidden.bs.modal', function () {
        $(this).remove();
    });
}

jQuery(document).ready( function () {
    $(".memberships_file").change( function () {
        addValidation($(this))
    });
   
    $(document).on( "click", ".popup-close", function () {
        $(this).parents(".modal").modal("toggle");
    });
    $("#date_range").val("");
});
