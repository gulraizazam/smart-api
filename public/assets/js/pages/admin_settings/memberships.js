var table_url = route('admin.memberships.datatable');

var table_columns = [{
    field: 'code',
    title: 'Membership Code',
    sortable: false,
    width: 110,
}, {
    field: 'membership_type_id',
    title: 'Membership Type',
    sortable: false,
    width: 110,
},
{
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
    field: 'patient_id',
    title: 'Patient ID',
    sortable: false,
    width: 80,
    template: function (data) {
        if (data.patient_id && data.patient_id !== 'N/A') {
            return data.patient_id;
        } else {
            return '-';
        }
    }
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
      
        if (permissions.create || permissions.edit) {
            let actions = '<div class="dropdown dropdown-inline action-dots">';
           
        actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';
            
            // Add view option for all memberships with assigned patients
            if (data.patient && data.patient !== 'N/A') {
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
           
            code: $("#search_code_name").val(),
            membership_type_id: $("#membershiptype_id").val(),
           
            membership_type_id: $("#search_membership_type").val(),
            assigned:$("#search_assigned_status").val(),
            status:$("#search_membership_status").val(),
            created_at: $("#date_range").val(),
         
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function() {
        let filters = {
            
            
            code: '',
            membership_type_id: '',
            created_by: '',
            created_at: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
   
    try {
       
        let membershipTypes = filter_values.membershipType;
        let users = filter_values.users;
        let user_options = '<option value="">All</option>';
       
        if (users) {
            Object.entries(users).forEach(function(user) {

                user_options += '<option value="' + user[0] + '">' + user[1] + '</option>';
            });
        }
      
        $("#search_created_by").html(user_options);
        $("#search_code_name").val(active_filters.code);
        $("#date_range").val(active_filters.created_at);
        $("#search_membership_status").val(active_filters.status);
        $("#search_created_by").val(active_filters.created_by);
        hideShowAdvanceFilters(active_filters);

    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {
    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
        || (typeof active_filters.lead_status_id !== 'undefined' && active_filters.lead_status_id != '')
        || (typeof active_filters.region_id !== 'undefined' && active_filters.region_id != '')
        || (typeof active_filters.service_id !== 'undefined' && active_filters.service_id != '')
        || (typeof active_filters.created_by !== 'undefined' && active_filters.created_by != '')
    ) {
        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
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
            <div class="card card-custom gutter-b">
                <div class="card-header py-3">
                    <div class="card-title">
                        <h3 class="card-label"><i class="la la-list-alt text-primary"></i> Membership Usage</h3>
                    </div>
                    <div class="card-toolbar">
                        <span class="label label-lg label-light-info label-inline">${serviceUsage.total_services} Service(s)</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th width="20%">Service Name</th>
                                    <th class="text-right" width="14%">Original Price</th>
                                    <th class="text-right" width="12%">Discount</th>
                                    <th class="text-right" width="14%">Discounted Price</th>
                                    <th width="12%">Date</th>
                                    <th width="28%">Consumed</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${servicesTableRows}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    } else {
        serviceUsageSection = `
            <!-- Service Usage -->
            <div class="card card-custom gutter-b">
                <div class="card-header py-3">
                    <div class="card-title">
                        <h3 class="card-label"><i class="la la-list-alt text-primary"></i> Membership Usage</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-light-info mb-0">
                        <i class="la la-info-circle"></i> This membership has not been used on any services yet.
                    </div>
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
                    <div class="col-md-6 col-sm-6 mb-3">
                        <div class="card">
                            <a href="${docUrl}" target="_blank">
                                <img src="${docUrl}" class="card-img-top" style="height: 200px; object-fit: cover;" alt="Document ${index + 1}">
                            </a>
                            <div class="card-body p-2 text-center">
                                <small class="text-muted">Document ${index + 1}</small>
                                <br>
                                <a href="${docUrl}" target="_blank" class="btn btn-sm btn-light-primary mt-1">
                                    <i class="la la-eye"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            });
            documentsHtml += '</div>';
        } else {
            documentsHtml = '<div class="alert alert-light-warning">No documents uploaded</div>';
        }
        
        documentsSection = `
            <!-- Verification Documents -->
            <div class="card card-custom gutter-b">
                <div class="card-header py-3">
                    <div class="card-title">
                        <h3 class="card-label"><i class="la la-file-image text-primary"></i> Verification Documents</h3>
                    </div>
                </div>
                <div class="card-body">
                    ${documentsHtml}
                    ${verification && verification.submitted_at ? '<small class="text-muted">Submitted: ' + verification.submitted_at + '</small>' : ''}
                </div>
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
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="la ${modalIcon} text-primary"></i>
                            ${modalTitle}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <i aria-hidden="true" class="ki ki-close"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <!-- Patient Info -->
                            <div class="col-md-6">
                                <div class="card card-custom card-stretch gutter-b">
                                    <div class="card-header py-3">
                                        <div class="card-title">
                                            <h3 class="card-label"><i class="la la-user text-primary"></i> Patient Information</h3>
                                        </div>
                                    </div>
                                    <div class="card-body py-3">
                                        <table class="table table-borderless mb-0">
                                            <tr>
                                                <td class="text-muted" width="40%">Name</td>
                                                <td><strong>${patient.name}</strong></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Patient ID</td>
                                                <td>${patient.unique_id || '-'}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Email</td>
                                                <td>${patient.email || '-'}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Phone</td>
                                                <td>${patient.phone || '-'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Membership Info -->
                            <div class="col-md-6">
                                <div class="card card-custom card-stretch gutter-b">
                                    <div class="card-header py-3">
                                        <div class="card-title">
                                            <h3 class="card-label"><i class="la la-id-card text-primary"></i> Membership Information</h3>
                                        </div>
                                    </div>
                                    <div class="card-body py-3">
                                        <table class="table table-borderless mb-0">
                                            <tr>
                                                <td class="text-muted" width="40%">Code</td>
                                                <td><strong>${membership.code}</strong></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Type</td>
                                                <td>${membership.type}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Status</td>
                                                <td>${membershipStatusBadge}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Period</td>
                                                <td>${membership.start_date || '-'} to ${membership.end_date || '-'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        ${serviceUsageSection}
                        
                        ${documentsSection}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-primary font-weight-bold" data-dismiss="modal">Close</button>
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
