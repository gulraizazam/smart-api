
var table_url = route('admin.doctors.datatable');

var table_columns = [
    {
        field: 'name',
        title: 'Name',
        width: 'auto',
    }, {
        field: 'email',
        title: 'Email',
        width: 160,
    }, {
        field: 'phone',
        title: 'Phone',
        width: 100,
    }, {
        field: 'gender',
        title: 'Gender',
        width: 60,
    }, {
        field: 'roles',
        title: 'roles',
        width: 120,
        sortable: false,
        template: function (data) {
            let roles = '';

            if (data.roles.length > 0) {

                for (let i = 0; i < data.roles.length; i++) {
                    roles += '<span><span class="label label-lg font-weight-bold label-light-info label-inline">'+data.roles[i]+'</span></span>&nbsp;';
                }

            }

            return roles;
        }
    }, {
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            return formatDate(data.created_at);
        }
    },{
        field: 'active',
        title: 'status',
        width: 'auto',
        template: function (data) {
            let status_url = route('admin.doctors.status');
            return statuses(data, status_url);
        }
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 180,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];

function actions(data) {
    let id = data.id;
    let url = route('admin.doctors.destroy', {id: id});
    let allocate_url = route('admin.doctors.location_manage', {id: id});
    let csrf = $('meta[name="csrf-token"]').attr('content');

    if (permissions.edit || permissions.delete || permissions.allocate || permissions.change_password) {
        let actions = '<div class="dropdown dropdown-inline action-dots">\
        <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';
        if (permissions.allocate) {
            actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="allocateRow(`' + allocate_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Allocate</span>\
                    </a>\
                </li>';
        }
        if (permissions.edit) {
            actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="editRow('+id+')" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
        }
        if (permissions.change_password) {
            actions += '<li class="navi-item">\
                <a href="javascript:void(0);"  onClick="changePassword('+id+');" class="navi-link">\
                    <span class="navi-icon"><i class="la la-key"></i></span>\
                    <span class="navi-text">Change Password</span>\
                </a>\
            </li>';
        }
        if (permissions.delete) {
            actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="deleteRow(`'+url+'`);" class="navi-link">\
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
    return '';
}


function editRow(id) {
    $("#modal_edit_user").modal("show");
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.doctors.edit', {id: id}),
        type: "GET",
        cache: false,
        success: function (response) {
            setEditData(response);
            reInitSelect2(".select2", "");
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(UserValidation);
        }
    });
}

function getDesrvice($this) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route("admin.doctors.get_service"),
        type: "GET",
        data: {doctor_id:  $("#doctor_id").val(), id: $this.val()},
        cache: false,
        success: function (response) {

            setServicesData(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditValidation);
        }
    });
}

function setServicesData(response) {

    let services = response.data.services;
    let locaiton_id = response.data.locaiton_id_1;
    let service_child_value = '';
    let service_options = '<option value="">Select</option>';

    Object.values(services).forEach(function(value, index) {
        if (value.name == 'All Services') {
              service_options += '<option value="' + value.id + '">' + value.name + '</option>';
        } else {
            service_options += '<option value="' + value.id + '">' + value.name + '</option>';
            Object.values(value.children).forEach(function (child, index) {
                service_child_value='\t&nbsp; \t&nbsp; \t&nbsp;'+child.name;
                service_options += '<option value="' + child.id + '">' + service_child_value + '</option>';
            });
        }
    });
    $("#services").html(service_options);
}

function allocateRow(url) {
    $("#modal_allocate_discounts").modal("show");
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setAllocateData(response);
            reInitSelect2(".select2", "");
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(EditValidation);
        }
    });
}


function setAllocateData(response) {
    try {
        let discount = response.data.doctor;
        let locations = response.data.location;
        let discount_locations = response.data.doctor_has_location;
        let location_options = '<option value="">Select Centre</option>';
        let location_services = '';
        Object.values(locations).forEach(function(value, index) {
            location_options += '<option value="">Select</option>';
            Object.values(value.children).forEach(function(child, index) {
                location_options += '<option value="'+child.id+'">'+child.name+'</option>';
            });
        });
        Object.values(discount_locations).forEach(function(value, index) {
            let location_name = value.location.city.name +"-"+ value.location.name;
            location_services += serviceLocation(value.id, location_name, value.service.name);
        });

        $('.HR_SERVICES').remove()
        $('#allocate_services').append(location_services)

        $("#doctor_id").val(discount.id);

        $("#locations").html(location_options);

        $("#edit_amount_type").val(discount.type);
        $("#edit_amount").val(discount.amount);
        $("#edit_pre_days").val(discount.pre_days);
        $("#edit_post_days").val(discount.post_days);
        $("#edit_start").val(discount.start);
        $("#edit_end").val(discount.end);

        $("#edit_active").prop("checked", discount.active);

    } catch (error) {
        showException(error);
    }
}


function serviceLocation(id, location_name, service_name) {
    return '<tr id="HR_" class="HR_SERVICES HR_'+id+'"><td>'+location_name+'</td><td>'+service_name+'</td><td>'+deleteIcon(id)+'</td></tr>';
}

function deleteIcon(id) {
    return '<a href="javascript:void(0);" onClick="deleteModel('+id+')" class="btn btn-icon btn-light btn-hover-danger btn-sm"> <span class="svg-icon svg-icon-md svg-icon-danger"> <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"> <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <rect x="0" y="0" width="24" height="24"></rect> <path d="M6,8 L6,20.5 C6,21.3284271 6.67157288,22 7.5,22 L16.5,22 C17.3284271,22 18,21.3284271 18,20.5 L18,8 L6,8 Z" fill="#000000" fill-rule="nonzero"></path> <path d="M14,4.5 L14,4 C14,3.44771525 13.5522847,3 13,3 L11,3 C10.4477153,3 10,3.44771525 10,4 L10,4.5 L5.5,4.5 C5.22385763,4.5 5,4.72385763 5,5 L5,5.5 C5,5.77614237 5.22385763,6 5.5,6 L18.5,6 C18.7761424,6 19,5.77614237 19,5.5 L19,5 C19,4.72385763 18.7761424,4.5 18.5,4.5 L14,4.5 Z" fill="#000000" opacity="0.3"></path> </g> </svg> </span> </a>';
}

function deleteModel(id) {
    swal.fire({
        title: 'Are you sure you want to remove?',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function(result) {
        if (result.value) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'post',
                url: route('admin.doctors.delete_service'),
                data: {'id': id
                },
                success: function (response) {
                    if (response.status == true) {
                        $('.HR_' + response.data.id).remove();
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                }
            });
        }
    });
}

function changePassword(id) {
    $("#change_modal").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.doctors.change_password', {id: id}),
        type: "GET",
        cache: false,
        success: function (response) {
               if(response.status == true){
                    $('#password_change_id').val(id);
               }
               else{
               
                   toastr.error(response.message);
               }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function createUsers($route) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setCreateData(response);

            reInitSelect2(".select2", "");
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(UserValidation);
        }
    });
}

function setCreateData(response) {
    let roles = response.data.roles;
    let roles_options = '<option value="">Select</option>';

    /*for (let i = 0; i< roles.length; i++) {
        roles_options += '<option value="'+roles[i].id+'">'+roles[i].name+'</option>';
    }*/
    Object.entries(roles).forEach( function (role) {
        roles_options += '<option value="'+role[0]+'">'+role[1]+'</option>';
    })
    $("#add_user_roles").html(roles_options);
    
    // Reset checkbox state when opening create modal
    $('#add_can_perform_consultation_row').hide();
    $('#add_doctor_can_perform_consultation').prop('checked', false);
}

function setEditData(response) {
    let user = response.data.user;
    let user_roles = response.data.user_roles;
    $("#modal_edit_user_form").attr("action", route('admin.doctors.update', {id: user.id}));

    let roles = response.data.roles;
    let roles_options = '<option value="">Select</option>';

    Object.entries(roles).forEach(function(role, index) {
        roles_options += '<option value="'+role[0]+'">'+role[1]+'</option>';
    });

    $("#edit_user_roles").html(roles_options);

    $("#edit_user_name").val(user.name);
    $("#edit_user_email").val(user.email);
    $("#edit_user_gender").val(user.gender);
    $("#edit_user_commission").val(user.commission);
    $('#edit_user_roles').val(user_roles).change();
    
    // Toggle checkbox visibility based on role, then set checkbox value
    toggleConsultationCheckbox('edit');
    $('#edit_doctor_can_perform_consultation').prop('checked', user.can_perform_consultation == 1);

    $("#edit_old_user_phone").val(user.phone);

    if (permissions.contact) {
        $("#edit_user_phone").val(user.phone);
    } else {
        $("#edit_user_phone").val("***********").attr("readonly", true);
    }
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function() {
        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            email: $("#search_email").val(),
            phone: $("#search_phone").val(),
            role_id: $("#search_role").val(),
            gender: $("#search_gender").val(),
            status: $("#search_status").val(),
            created_at: $("#date_range").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            name: '',
            email: '',
            phone: '',
            role_id: '',
            gender: '',
            status: '',
            created_at: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    let genders = filter_values.gender_array;
    let roles = filter_values.roles;
    let status = filter_values.status;

    let location_options = '<option value="">Select</option>';
    let role_options = '<option value="">Select</option>';
    let status_options = '<option value="">All</option>';
    let gender_options = '<option value="">All</option>';

    Object.entries(status).forEach(function(value, index) {
        status_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    Object.entries(genders).forEach(function(value, index) {
        gender_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    Object.entries(roles).forEach(function(value, index) {

        role_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });
    // edit_user_gender
    $("#edit_user_gender").html(gender_options);

    $("#search_role").html(role_options);
    $("#search_center").html(location_options);
    $("#search_status").html(status_options);

    $("#search_name").val(active_filters.name);
    $("#search_phone").val(active_filters.phone);
    $("#search_commission").val(active_filters.commission);
    $("#search_email").val(active_filters.email);
    $("#date_range").val(active_filters.created_at);

    $("#search_role").val(active_filters.role_id);
    $("#search_status").val(active_filters.status);
    $("#search_gender").val(active_filters.gender).change();

    hideShowAdvanceFilters(active_filters);
}

function hideShowAdvanceFilters(active_filters) {
    if ((typeof active_filters.gender !== 'undefined' && active_filters.gender != '')
        || (typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')) {

        $(".advance-filters").show();
        $(".advance-arrow").addClass("fa fa-caret-down");
    }
}

jQuery(document).ready( function () {
    $("#date_range").val("");

    // Show/hide Can Perform Consultation checkbox based on role selection
    $(document).on('change', '#add_user_roles', function() {
        toggleConsultationCheckbox('add');
    });

    $(document).on('change', '#edit_user_roles', function() {
        toggleConsultationCheckbox('edit');
    });
});

// Toggle Can Perform Consultation checkbox visibility based on role
function toggleConsultationCheckbox(prefix) {
    var roleSelect = $('#' + prefix + '_user_roles');
    var checkboxRow = $('#' + prefix + '_can_perform_consultation_row');
    
    // For select2 multiple, check all selected options
    var hasAestheticDoctor = false;
    roleSelect.find('option:selected').each(function() {
        if ($(this).text().toLowerCase().includes('aesthetic doctor')) {
            hasAestheticDoctor = true;
            return false; // break loop
        }
    });
    
    if (hasAestheticDoctor) {
        checkboxRow.show();
    } else {
        checkboxRow.hide();
        // Uncheck the checkbox when hiding
        $('#' + prefix + '_doctor_can_perform_consultation').prop('checked', false);
    }
}

