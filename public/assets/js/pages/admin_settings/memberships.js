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
    title: 'status',
    width: 60,
    template: function (data) {
     
        if(data.active ==1){
            return '<span class="text text-success">Active</span>';
        }else{
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

function addValidation(elem) {
    if (elem.val() == '') {
        elem.addClass("is-invalid");
        $(".lead_file_msg").removeClass("d-none");
    } else {
        elem.removeClass("is-invalid");
        $(".lead_file_msg").addClass("d-none");
    }
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
