
var table_url = route('admin.discounts.datatable');

var table_columns = [
    {
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 200,
    },{
        field: 'discount_type',
        title: 'Applicable On',
        sortable: false,
        width: 'auto',
    },{
        field: 'start',
        title: 'From',
        sortable: false,
        width: 'auto',
    },{
        field: 'end',
        title: 'To',
        sortable: false,
        width: 'auto',
    }, {
        field: 'status',
        title: 'status',
        width: 100,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.discounts.status');
            return statuses(data, status_url);
        }
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 120,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
    }];

function discountType(obj,sel){
    if(obj.value== 'Inventory'){
        $("#"+sel+"_amount_type").val('Percentage').trigger('change');
        $("#"+sel+"_amount_type").attr('disabled',true);        
    }else{
        $("#"+sel+"_amount_type").val('').trigger('change'); 
        $("#"+sel+"_amount_type").attr('disabled',false);
    }
}

function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let url = route('admin.discounts.edit', {id: id});
        let allocate_url = route('admin.discounts.location_manage', {id: id});
        let delete_url = route('admin.discounts.destroy', {id: id});

        if (permissions.edit || permissions.delete) {
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
                    <a href="javascript:void(0);" onclick="editRow(`' + url + '`);" class="navi-link">\
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

        let discount = response.data.discount;
        let locations = response.data.location;
        let discount_locations = response.data.discount_has_location;
        let isConfigurable = discount.type === 'Configurable';

        // Store discount type in hidden field
        $("#discount_type_hidden").val(discount.type);

        // Toggle UI based on discount type
        if (isConfigurable) {
            $(".configurable-info-row").show();
            $(".service-field-row").hide();
            $(".regular-allocation-row").hide();
            $(".configurable-add-btn").show();
        } else {
            $(".configurable-info-row").hide();
            $(".service-field-row").show();
            $(".regular-allocation-row").show();
            $(".configurable-add-btn").hide();
        }

        let location_options = '<option value="">Select Centre</option>';
        let location_services = '';
       
        Object.values(locations).forEach(function(value, index) {

            location_options += '<option value="">Select</option>\
            <optgroup label="'+value.name+'">';
            Object.values(value.children).forEach(function(child, index) {
                location_options += '<option value="'+child.id+'">'+child.name+'</option>';
            });

            location_options += '</optgroup>';
        });

        // Check if "All Centres" + "All Services" is already allocated
        let hasAllCentresAllServices = false;
        Object.values(discount_locations).forEach(function(value) {
            if (value.location && value.location.slug === 'all' && value.service && value.service.slug === 'all') {
                hasAllCentresAllServices = true;
            }
        });

        // Group services by location_id + type + amount + slug (same allocation settings)
        let grouped = {};
        Object.values(discount_locations).forEach(function(value, index) {
            let location_name = value.location.city.name + "-" + value.location.name;
            let display_type = value.type || '-';
            let display_amount = value.amount !== null ? value.amount : '-';
            let display_slug = value.slug || 'default';
            
            // Create a unique key for grouping: location_id + type + amount + slug
            let groupKey = value.location.id + '_' + display_type + '_' + display_amount + '_' + display_slug;
            
            if (!grouped[groupKey]) {
                grouped[groupKey] = {
                    ids: [],
                    location_name: location_name,
                    service_names: [],
                    type: display_type,
                    amount: display_amount,
                    slug: display_slug
                };
            }
            grouped[groupKey].ids.push(value.id);
            grouped[groupKey].service_names.push(value.service.name);
        });

        // Build table rows from grouped data
        Object.values(grouped).forEach(function(group) {
            let serviceNamesDisplay = group.service_names.join(', ');
            location_services += serviceLocationGrouped(group.ids, group.location_name, serviceNamesDisplay, group.type, group.amount, group.slug);
        });

        $('.HR_SERVICES').remove()
        $('#allocate_services').append(location_services)

        $("#discount_id").val(discount.id);
        $("#allocate_discount_name").text(discount.name);

        $("#locations").html(location_options);

        // Reset allocation form fields
        $("#allocation_type").val('').trigger('change');
        $("#allocation_amount").val('');
        $("#allocation_slug").val('default').trigger('change');

        // Disable/enable allocation form based on "All Centres" + "All Services" check
        if (hasAllCentresAllServices) {
            $("#locations").prop('disabled', true);
            $("#services").prop('disabled', true);
            $("#allocation_type").prop('disabled', true);
            $("#allocation_amount").prop('disabled', true);
            $("#allocation_slug").prop('disabled', true);
            $("#modal_allocate_discounts_form .spinner-button").prop('disabled', true);
        } else {
            enableAllocationForm();
        }

    } catch (error) {
        showException(error);
    }

}

function getDesrvice($this) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/getDservice',
        type: "GET",
        data: {discount_id:  $("#discount_id").val(), id: $this.val()},
        cache: false,
        success: function (response) {

            setServicesData(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);0

            reInitValidation(EditValidation);
        }
    });
}

function setServicesData(response) {

    let services = response.data.services;
    let locaiton_id = response.data.locaiton_id_1;
    let service_child_value = '';
    let service_options = '';

    Object.values(services).forEach(function(value, index) {
        if (value.name == 'All Services') {
              service_options += '<option value="' + value.id + '">' + value.name + '</option>';
        } else {
            service_options += '<option value="' + value.id + '">' + value.name + '</option>';
            if(value.children){
                Object.values(value.children).forEach(function (child, index) {
                    service_child_value='\t&nbsp; \t&nbsp; \t&nbsp;'+child.name;
                    service_options += '<option value="' + child.id + '">' + service_child_value + '</option>';
                });
            }
            
        }
    });
    // Destroy existing select2 before repopulating to prevent duplicate "Select" tags
    if ($('#services').hasClass('select2-hidden-accessible')) {
        $('#services').select2('destroy');
    }
    $("#services").html(service_options);
}

function enableAllocationForm() {
    $("#locations").prop('disabled', false);
    $("#services").prop('disabled', false);
    $("#allocation_type").prop('disabled', false);
    $("#allocation_amount").prop('disabled', false);
    $("#allocation_slug").prop('disabled', false);
    $("#modal_allocate_discounts_form .spinner-button").prop('disabled', false);
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
                url: '/api/discounts/deleteDservice',
                data: {'id': id
                },
                success: function (response) {

                    $('.HR_' + response.data.id).remove();
                    enableAllocationForm();
                }
            });

        }
    });
}

function editRow(url) {

    $("#modal_edit_discounts").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {

            setEditData(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditValidation);
        }
    });

}

function setEditData(response) {
   
    $('#tes_container').empty();

    try {

        let discount = response.data.discount;

        if(discount.type=="Configurable"){
            $("#edit_amount_div").css('display','none');
            $("#buy_services_section").css('display','block');
            let services = response.data.services;
            let get_services = response.data.get_discount_services;
            let sessions_buy = response.data.base_discount_services.length;
            let base_service_id = response.data.base_discount_services[0].service_id;
            $('#sessions_buy').val(sessions_buy).change();
            let service_child_value = '';
            let service_options = '<option value="">Select</option>';
            Object.values(services).forEach(function(value, index) {
                if (value.name == 'All Services') {
                      service_options += '<option disabled value="' + value.id + '">' + value.name + '</option>';
                } else {
                    service_options += '<option disabled value="' + value.id + '">' + value.name + '</option>';
                    Object.values(value.children).forEach(function (child, index) {
                        service_child_value='\t&nbsp; \t&nbsp; \t&nbsp;'+child.name;
                        service_options += '<option value="' + child.id + '">' + service_child_value + '</option>';
                    });
                }
            });
            

            $("#edit_user_roles").html(roleOptions).trigger("change");
            $("#edit_get_services").html(service_options);

            Object.values(get_services).forEach(function(value, index) {
               
                populateSection(value,index);
                
            });
            $("#edit_base_service").html(service_options);
            $("#edit_base_service").select2();
            $("#edit_base_service").val(base_service_id).change();
        }

        $("#modal_edit_discounts_form").attr("action", route('admin.discounts.update', {id: discount.id}));
        if (discount.discount_type == 'Treatment') {
            $(".treatment").prop("checked", true);
        }
        if (discount.discount_type == 'Consultancy') {
            $(".consultancy").prop("checked", true);
        }

        if (discount.slug == 'default') {
            $(".default").prop("checked", true);
            $(".edit_birthday_range").addClass("d-none");
        }
        if (discount.slug == 'custom') {
            $(".custom").prop("checked", true);
            $(".edit_birthday_range").addClass("d-none");
        }
        

        $("#edit_name").val(discount.name);
        $("#edit_amount_types").val(discount.discount_type).trigger('change');
        $("#edit_start").val(discount.start);
        $("#edit_end").val(discount.end);

        // Populate customer types dropdown
        let customerTypes = response.data.customer_types;
        let allPatientsSelected = !discount.customer_type_id ? 'selected' : '';
        let customerTypeOptions = `<option value="" ${allPatientsSelected}>All Patients</option>`;
        if (customerTypes && Object.keys(customerTypes).length > 0) {
            Object.entries(customerTypes).forEach(([id, name]) => {
                let selected = discount.customer_type_id == id ? 'selected' : '';
                customerTypeOptions += `<option value="${id}" ${selected}>${name}</option>`;
            });
        }
        $("#edit_customer_type").html(customerTypeOptions);
        // Set the selected value
        if (discount.customer_type_id) {
            $("#edit_customer_type").val(discount.customer_type_id);
        } else {
            $("#edit_customer_type").val("");
        }

        $("#edit_active").prop("checked", discount.active);
        let roles = response.data.roles;
let selectedRoles = response.data.selected_roles || []; // handle null/undefined
let roleOptions = '';

// Always populate all roles
Object.entries(roles).forEach(([id, name]) => {
    let selected = selectedRoles.length > 0 && selectedRoles.includes(parseInt(id)) ? 'selected' : '';
    roleOptions += `<option value="${id}" ${selected}>${name}</option>`;
});

$("#edit_user_roles").html(roleOptions).trigger("change");
    } catch (error) {
        showException(error);
    }

}
function populateSection(data,index) {

    let newindex = index + 1;
    var templateSection = $("#get_services_section").clone().removeAttr("style");

    // Use a single modifiedHTML variable to accumulate changes
    let modifiedHTML = templateSection.html();
    modifiedHTML = modifiedHTML.replace(/edit_services_name\[\]/g, 'edit_services_name[' + index + ']');
    modifiedHTML = modifiedHTML.replace(/edit_sessions\[\]/g, 'edit_sessions[' + index + ']');
    modifiedHTML = modifiedHTML.replace(/edit_disc_type\[\]/g, 'edit_disc_type[' + index + ']');
    templateSection.html(modifiedHTML);

    templateSection.find('[name="edit_sessions['+ index + ']"]').val(data.sessions);
    templateSection.find('[name="edit_services_name['+ index + ']"]').val(data.service_id).change();

    if (data.discount_type == "complimentory") {
        templateSection.find('[name="edit_disc_type['+ index + ']"][value="complimentory"]').prop("checked", true);
    } else {
        templateSection.find('[name="edit_disc_type['+ index + ']"][value="custom"]').prop("checked", true);
        templateSection.append('<div class="fv-row col-md-5 mt-4 d-flex align-items-center pl-0" id="configurable_amount"><label class="required f-flex fw-bold fs-6 mb-2 pl-0 d-flex mr-4">Amount <span class="text text-danger ml-1">*</span></label><input type="number" min="0" max="99" id="add_configurable_amount" class="add_configurable_amount form-control" name="configurable_amount['+ index + ']" value="'+data.discount_amount+'"></div>');
    }

    $("#tes_container").append(templateSection);

}
function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            type: $("#search_type").val(),
            amount: $("#search_amount").val(),
            discount_type: $("#search_discount_type").val(),
            startdate: $("#search_start").val(),
            enddate: $("#search_end").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            status: $("#search_status").val(),
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
            type: '',
            amount: '',
            discount_type: '',
            startdate: '',
            enddate: '',
            created_from: '',
            created_to: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {
    try {

        let status = filter_values.status;

        let status_options = '<option value="">All</option>';

        Object.entries(status).forEach(function (value, index) {
            status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });


        $("#search_status").html(status_options);

        $("#search_name").val(active_filters.name);
        $("#search_type").val(active_filters.type);
        $("#search_amount").val(active_filters.amount);
        $("#search_discount_type").val(active_filters.discount_type);
        $("#search_start").val(active_filters.startdate);
        $("#search_end").val(active_filters.enddate);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);
        $("#search_status").val(active_filters.status);

        hideShowAdvanceFilters(active_filters);

    } catch (err) {

    }
}

function createDiscount($route) {
    $("#add_amount_type").val([]).trigger("change");
    // Clear previous validation state
    $("#modal_add_discounts_form .is-invalid").removeClass("is-invalid");
    $("#modal_add_discounts_form .select2-is-invalid").removeClass("select2-is-invalid");
    $("#modal_add_discounts_form .select2-selection").removeClass("select2-is-invalid");
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {
            let roles = response.data.roles;
            let roleOptions = '';
           Object.entries(roles).forEach(([id, name]) => {
                roleOptions += `<option value="${id}">${name}</option>`;
            });

            $("#add_user_roles").html(roleOptions).trigger("change");
            
            // Populate customer types dropdown
            let customerTypes = response.data.customer_types;
            let customerTypeOptions = '<option value="" selected>All Patients</option>';
            if (customerTypes) {
                Object.entries(customerTypes).forEach(([id, name]) => {
                    customerTypeOptions += `<option value="${id}">${name}</option>`;
                });
            }
            $("#add_customer_type").html(customerTypeOptions).trigger("change");
            
            let locations = response.data.locations;
            let location_options = '<option value="">Select Centre</option>';
            Object.values(locations).forEach(function(value, index) {
                location_options += '<optgroup label="'+value.name+'">';
                Object.values(value.children).forEach(function(child, index) {
                    location_options += '<option value="'+child.id+'">'+child.name+'</option>';
                });
                location_options += '</optgroup>';
            });

            $("#locations").html(location_options);

            //setDiscountData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(AddValidation);
        }
    });
}

function setDiscountData(response) {

    try {
    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
        || (typeof active_filters.startdate !== 'undefined' && active_filters.startdate != '')
        || (typeof active_filters.enddate !== 'undefined' && active_filters.enddate != '')
        || (typeof active_filters.status !== 'undefined' && active_filters.status != '')) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}

function serviceLocation(id, location_name, service_name) {
    return '<tr id="HR_" class="HR_SERVICES HR_'+id+'"><td>'+location_name+'</td><td>'+service_name+'</td><td>-</td><td>-</td><td>-</td><td>'+deleteIcon(id)+'</td></tr>';
}

function serviceLocationWithTypeAmount(id, location_name, service_name, type, amount) {
    return '<tr id="HR_" class="HR_SERVICES HR_'+id+'"><td>'+location_name+'</td><td>'+service_name+'</td><td>'+type+'</td><td>'+amount+'</td><td>-</td><td>'+deleteIcon(id)+'</td></tr>';
}

function serviceLocationWithAllFields(id, location_name, service_name, type, amount, slug) {
    let slugDisplay = slug === 'custom' ? 'Custom' : 'Fixed';
    return '<tr id="HR_" class="HR_SERVICES HR_'+id+'"><td>'+location_name+'</td><td>'+service_name+'</td><td>'+type+'</td><td>'+amount+'</td><td>'+slugDisplay+'</td><td>'+deleteIcon(id)+'</td></tr>';
}

function serviceLocationGrouped(ids, location_name, service_names, type, amount, slug) {
    let slugDisplay = slug === 'custom' ? 'Custom' : 'Fixed';
    let idsArray = ids.join(',');
    let classNames = ids.map(id => 'HR_' + id).join(' ');
    return '<tr class="HR_SERVICES ' + classNames + '" data-ids="' + idsArray + '"><td>' + location_name + '</td><td>' + service_names + '</td><td>' + type + '</td><td>' + amount + '</td><td>' + slugDisplay + '</td><td>' + deleteIconGroup(idsArray) + '</td></tr>';
}

function deleteIconGroup(ids) {
    return '<a href="javascript:void(0);" onClick="deleteModelGroup(\'' + ids + '\')" class="btn btn-icon btn-light btn-hover-danger btn-sm"> <span class="svg-icon svg-icon-md svg-icon-danger"> <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"> <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <rect x="0" y="0" width="24" height="24"></rect> <path d="M6,8 L6,20.5 C6,21.3284271 6.67157288,22 7.5,22 L16.5,22 C17.3284271,22 18,21.3284271 18,20.5 L18,8 L6,8 Z" fill="#000000" fill-rule="nonzero"></path> <path d="M14,4.5 L14,4 C14,3.44771525 13.5522847,3 13,3 L11,3 C10.4477153,3 10,3.44771525 10,4 L10,4.5 L5.5,4.5 C5.22385763,4.5 5,4.72385763 5,5 L5,5.5 C5,5.77614237 5.22385763,6 5.5,6 L18.5,6 C18.7761424,6 19,5.77614237 19,5.5 L19,5 C19,4.72385763 18.7761424,4.5 18.5,4.5 L14,4.5 Z" fill="#000000" opacity="0.3"></path> </g> </svg> </span> </a>';
}

function deleteModelGroup(ids) {
    Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, delete it!"
    }).then(function(result) {
        if (result.value) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: "POST",
                url: '/api/discounts/deleteDserviceGroup',
                data: {'ids': ids},
                success: function (response) {
                    if (response.status) {
                        // Remove the row containing all these IDs
                        let idsArray = ids.split(',');
                        idsArray.forEach(function(id) {
                            $('.HR_' + id).remove();
                        });
                        enableAllocationForm();
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                }
            });
        }
    });
}

function deleteIcon(id) {
    return '<a href="javascript:void(0);" onClick="deleteModel('+id+')" class="btn btn-icon btn-light btn-hover-danger btn-sm"> <span class="svg-icon svg-icon-md svg-icon-danger"> <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"> <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <rect x="0" y="0" width="24" height="24"></rect> <path d="M6,8 L6,20.5 C6,21.3284271 6.67157288,22 7.5,22 L16.5,22 C17.3284271,22 18,21.3284271 18,20.5 L18,8 L6,8 Z" fill="#000000" fill-rule="nonzero"></path> <path d="M14,4.5 L14,4 C14,3.44771525 13.5522847,3 13,3 L11,3 C10.4477153,3 10,3.44771525 10,4 L10,4.5 L5.5,4.5 C5.22385763,4.5 5,4.72385763 5,5 L5,5.5 C5,5.77614237 5.22385763,6 5.5,6 L18.5,6 C18.7761424,6 19,5.77614237 19,5.5 L19,5 C19,4.72385763 18.7761424,4.5 18.5,4.5 L14,4.5 Z" fill="#000000" opacity="0.3"></path> </g> </svg> </span> </a>';
}
function SetFields()
{
    if($("#add_amount_type").val()=="Configurable"){
        $("#custom").css('display','none');
        $("#amount").css('display','none');
        $("#configurable_fields").css('display','block');
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '/api/getDiscountServices',
            type: "GET",
            data: {},
            cache: false,
            success: function (response) {
    
                let services = response.data.services;
                let service_child_value = '';
                let service_options = '<option value="">Select</option>';
            
                Object.values(services).forEach(function(value, index) {
                    
                    if (value.name == 'All Services') {
                          service_options += '<option disabled value="' + value.id + '">' + value.name + '</option>';
                    } else {
                        service_options += '<option disabled value="' + value.id + '">' + value.name + '</option>';
                        Object.values(value.children).forEach(function (child, index) {
                            service_child_value='\t&nbsp; \t&nbsp; \t&nbsp;'+child.name;
                            service_options += '<option value="' + child.id + '">' + service_child_value + '</option>';
                        });
                    }
                });
                $("#base_service").html(service_options);
                $("#services_sessions").html(service_options);
                $('#base_service').select2();
               
                reInitSelect2(".select2", "");
    
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
    
                reInitValidation(EditValidation);
            }
        });
    }else{
        $("#custom").css('display','block');
        $("#amount").css('display','block');
        $("#configurable_fields").css('display','none');
    }
}
function getCentreServices()
{
    var location = $("#locations").val();
    $.ajax({
        
        url: route('admin.locations.getservices'),
        type: "GET",
        data: {id: location},
        cache: false,
        success: function (response) {
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
        },
        error: function (xhr, ajaxOptions, thrownError) {
           
        }
    });
}


var cloneCounter = 1;

$('.discount_type_wrap.get_discount_type .add_new_discount_field').on('click', function(){

    var cloneElements = $(this).parent().parent('.get_discount_type').children().html();
    
    // Replace names of input fields with unique names
    cloneElements = cloneElements.replace('sessions[]', 'sessions[' + cloneCounter + ']');
    cloneElements = cloneElements.replace('services_name[]', 'services_name[' + cloneCounter + ']');
    cloneElements = cloneElements.replaceAll('disc_type[]', 'disc_type[' + cloneCounter + ']');
    cloneElements = cloneElements.replace('configurable_amount[]', 'configurable_amount[' + cloneCounter + ']');
    cloneElements = cloneElements.replace('add_new_discount_field', 'remove_discount');
    cloneElements = cloneElements.replace('btn-primary', 'btn-danger');
    cloneElements = cloneElements.replace('la-plus', 'la-minus');

    $('.discount_wrap').append('<div class="fv-row col-12 discount_type_wrap get_discount_type mt-3"><div class="d-flex">'+cloneElements+'</div></div>');
    
    // Increment the counter for the next clone
    cloneCounter++;
});

$(document).on('click', '.discount_type_wrap.get_discount_type .remove_discount', function(){
    $(this).parent().parent('.get_discount_type').remove();
});

$(document).on('change', '.discount_type_wrap.get_discount_type .radio-inline .group_slug', function(){
    var Elementindex = $(this).parents('.discount_type_wrap.get_discount_type').index();
    if(!$('#modal_edit_discounts.show').length){
        Elementindex = (parseInt(Elementindex)-1);
    }
    if($(this).is(':checked') && $(this).val() == "custom"){
        $(this).parents('.discount_type_wrap.get_discount_type').append('<div class="fv-row col-md-5 mt-4 d-flex align-items-center pl-0" id="configurable_amount"><label class="required f-flex fw-bold fs-6 mb-2 pl-0 d-flex mr-4">Amount <span class="text text-danger ml-1">*</span></label><input type="number" min="0" max="99" id="add_configurable_amount" class="add_configurable_amount form-control"  name="configurable_amount['+Elementindex+']"></div>');
    } else{
        $(this).parents('.discount_type_wrap.get_discount_type').find('#configurable_amount').remove();
    }
});
$(document).on("keyup", ".add_configurable_amount", function () {

    
        var val = parseInt(this.value);
        if (val > 100 || val < 0) {
            this.value = '';
            toastr.error("Amount is not allowed greater than 100");
        }
    

});

// ============================================
// CONFIGURABLE DISCOUNT FUNCTIONALITY
// ============================================

var confGetServiceRowIndex = 1;
var confServicesOptions = '';

// Create Configurable Discount - Load services
function createConfigurableDiscount() {
    // Reset form
    $('#modal_add_configurable_discount_form')[0].reset();
    $('#conf_get_services_container').html('');
    confGetServiceRowIndex = 1;
    
    // Load services
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/discounts/services-for-configurable',
        type: "GET",
        cache: false,
        success: function (response) {
            if (!response.status || !response.data || !response.data.services) {
                toastr.error('Failed to load services');
                console.error('Services response:', response);
                return;
            }
            
            let services = response.data.services;
            let service_options = '<option value="">Select Service</option>';
            
            // Handle both array and object formats
            let servicesArray = Array.isArray(services) ? services : Object.values(services);
            
            servicesArray.forEach(function(value) {
                // Skip null values and "All Services"
                if (!value || value.name === 'All Services' || value.slug === 'all') {
                    return;
                }
                
                // Parent service (disabled, acts as category header)
                service_options += '<option disabled value="' + value.id + '">' + value.name + '</option>';
                
                // Child services (selectable)
                if (value.children && value.children.length > 0) {
                    value.children.forEach(function (child) {
                        if (child && child.id) {
                            service_options += '<option value="' + child.id + '">&nbsp;&nbsp;&nbsp;' + child.name + '</option>';
                        }
                    });
                }
            });
            
            confServicesOptions = service_options;
            $("#conf_base_service").html(service_options);
            
            // Add first GET row
            addConfGetServiceRow(0);
            
            reInitSelect2(".select2", "");
            initDatepickers();
        },
        error: function (xhr) {
            errorMessage(xhr);
        }
    });
}

// Add GET service row
function addConfGetServiceRow(index) {
    let rowHtml = `
        <div class="get-service-row mb-3" data-index="${index}">
            <div class="row align-items-center">
                <div class="col-md-1">
                    ${index === 0 ? 
                        '<button type="button" class="btn btn-sm btn-primary add-get-service-row" title="Add More"><i class="la la-plus p-0 m-0"></i></button>' : 
                        '<button type="button" class="btn btn-sm btn-danger remove-get-service-row" title="Remove"><i class="la la-minus p-0 m-0"></i></button>'
                    }
                </div>
                <div class="col-md-2">
                    <label class="fw-bold fs-6 mb-2">Sessions <span class="text text-danger">*</span></label>
                    <select class="form-control form-control-solid" name="sessions[${index}]">
                        <option value="">Select</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div class="col-md-1 text-center pt-8">
                    <span class="fw-bold">of</span>
                </div>
                <div class="col-md-4">
                    <label class="fw-bold fs-6 mb-2">Service <span class="text text-danger">*</span></label>
                    <select class="form-control form-control-solid conf-get-service" name="services_name[${index}]">
                        ${confServicesOptions}
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="fw-bold fs-6 mb-2">Discount Type <span class="text text-danger">*</span></label>
                    <div class="d-flex align-items-center mt-2">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input disc-type-radio" type="radio" name="disc_type[${index}]" value="complimentory" id="complimentory_${index}">
                            <label class="form-check-label" for="complimentory_${index}">Free</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input disc-type-radio" type="radio" name="disc_type[${index}]" value="custom" id="custom_${index}">
                            <label class="form-check-label" for="custom_${index}">% Off</label>
                        </div>
                        <input type="number" class="form-control form-control-sm conf-percentage-input d-none" name="configurable_amount[${index}]" placeholder="%" min="1" max="99" style="width: 70px;">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#conf_get_services_container').append(rowHtml);
}

// Add GET service row for edit modal
function addEditConfGetServiceRow(index, data = null) {
    let rowHtml = `
        <div class="get-service-row mb-3" data-index="${index}">
            <div class="row align-items-center">
                <div class="col-md-1">
                    ${index === 0 ? 
                        '<button type="button" class="btn btn-sm btn-primary add-edit-get-service-row" title="Add More"><i class="la la-plus p-0 m-0"></i></button>' : 
                        '<button type="button" class="btn btn-sm btn-danger remove-edit-get-service-row" title="Remove"><i class="la la-minus p-0 m-0"></i></button>'
                    }
                </div>
                <div class="col-md-2">
                    <label class="fw-bold fs-6 mb-2">Sessions <span class="text text-danger">*</span></label>
                    <select class="form-control form-control-solid" name="edit_sessions[${index}]">
                        <option value="">Select</option>
                        <option value="1" ${data && data.sessions == 1 ? 'selected' : ''}>1</option>
                        <option value="2" ${data && data.sessions == 2 ? 'selected' : ''}>2</option>
                        <option value="3" ${data && data.sessions == 3 ? 'selected' : ''}>3</option>
                        <option value="4" ${data && data.sessions == 4 ? 'selected' : ''}>4</option>
                        <option value="5" ${data && data.sessions == 5 ? 'selected' : ''}>5</option>
                    </select>
                </div>
                <div class="col-md-1 text-center pt-8">
                    <span class="fw-bold">of</span>
                </div>
                <div class="col-md-4">
                    <label class="fw-bold fs-6 mb-2">Service <span class="text text-danger">*</span></label>
                    <select class="form-control form-control-solid edit-conf-get-service" name="edit_services_name[${index}]">
                        ${confServicesOptions}
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="fw-bold fs-6 mb-2">Discount Type <span class="text text-danger">*</span></label>
                    <div class="d-flex align-items-center mt-2">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input edit-disc-type-radio" type="radio" name="edit_disc_type[${index}]" value="complimentory" id="edit_complimentory_${index}" ${data && data.discount_type == 'complimentory' ? 'checked' : ''}>
                            <label class="form-check-label" for="edit_complimentory_${index}">Free</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input edit-disc-type-radio" type="radio" name="edit_disc_type[${index}]" value="custom" id="edit_custom_${index}" ${data && data.discount_type == 'custom' ? 'checked' : ''}>
                            <label class="form-check-label" for="edit_custom_${index}">% Off</label>
                        </div>
                        <input type="number" class="form-control form-control-sm edit-conf-percentage-input ${data && data.discount_type == 'custom' ? '' : 'd-none'}" name="configurable_amount[${index}]" placeholder="%" min="1" max="99" style="width: 70px;" value="${data && data.discount_amount ? data.discount_amount : ''}">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#edit_conf_get_services_container').append(rowHtml);
    
    // Set service value after appending
    if (data && data.service_id) {
        $(`#edit_conf_get_services_container .get-service-row[data-index="${index}"] select[name="edit_services_name[${index}]"]`).val(data.service_id);
    }
}

// Event: Add GET service row (Create modal)
$(document).on('click', '.add-get-service-row', function() {
    addConfGetServiceRow(confGetServiceRowIndex);
    confGetServiceRowIndex++;
});

// Event: Remove GET service row (Create modal)
$(document).on('click', '.remove-get-service-row', function() {
    $(this).closest('.get-service-row').remove();
});

// Event: Add GET service row (Edit modal)
$(document).on('click', '.add-edit-get-service-row', function() {
    addEditConfGetServiceRow(confGetServiceRowIndex);
    confGetServiceRowIndex++;
});

// Event: Remove GET service row (Edit modal)
$(document).on('click', '.remove-edit-get-service-row', function() {
    $(this).closest('.get-service-row').remove();
});

// Event: Toggle percentage input visibility (Create modal)
$(document).on('change', '.disc-type-radio', function() {
    let row = $(this).closest('.get-service-row');
    let percentageInput = row.find('.conf-percentage-input');
    
    if ($(this).val() === 'custom') {
        percentageInput.removeClass('d-none').prop('required', true);
    } else {
        percentageInput.addClass('d-none').prop('required', false).val('');
    }
});

// Event: Toggle percentage input visibility (Edit modal)
$(document).on('change', '.edit-disc-type-radio', function() {
    let row = $(this).closest('.get-service-row');
    let percentageInput = row.find('.edit-conf-percentage-input');
    
    if ($(this).val() === 'custom') {
        percentageInput.removeClass('d-none').prop('required', true);
    } else {
        percentageInput.addClass('d-none').prop('required', false).val('');
    }
});

// Edit Configurable Discount
function editConfigurableDiscount(url) {
    $("#modal_edit_configurable_discount").modal("show");
    $('#edit_conf_get_services_container').html('');
    confGetServiceRowIndex = 0;
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            let discount = response.data.discount;
            let services = response.data.services || {};
            let base_discount_services = response.data.base_discount_services;
            let get_discount_services = response.data.get_discount_services;
            
            // Build service options
            let service_options = '<option value="">Select Service</option>';
            let servicesArray = Array.isArray(services) ? services : Object.values(services);
            
            servicesArray.forEach(function(value) {
                // Skip null values and "All Services"
                if (!value || value.name === 'All Services' || value.slug === 'all') {
                    return;
                }
                
                // Parent service (disabled, acts as category header)
                service_options += '<option disabled value="' + value.id + '">' + value.name + '</option>';
                
                // Child services (selectable)
                if (value.children && value.children.length > 0) {
                    value.children.forEach(function (child) {
                        if (child && child.id) {
                            service_options += '<option value="' + child.id + '">&nbsp;&nbsp;&nbsp;' + child.name + '</option>';
                        }
                    });
                }
            });
            confServicesOptions = service_options;
            
            // Set form action
            $("#modal_edit_configurable_discount_form").attr("action", route('admin.discounts.update', {id: discount.id}));
            
            // Set basic fields
            $("#edit_conf_discount_name").val(discount.name);
            $("#edit_conf_start").val(discount.start);
            $("#edit_conf_end").val(discount.end);
            $("#edit_conf_active").prop("checked", discount.active == 1);
            
            // Set BUY section
            if (base_discount_services && base_discount_services.length > 0) {
                $("#edit_conf_sessions_buy").val(base_discount_services.length);
                $("#edit_conf_base_service").html(service_options);
                $("#edit_conf_base_service").val(base_discount_services[0].service_id);
            }
            
            // Set GET section - Group by service_id and discount_type
            let groupedServices = {};
            if (get_discount_services && get_discount_services.length > 0) {
                get_discount_services.forEach(function(service) {
                    let key = service.service_id + '_' + service.discount_type;
                    if (!groupedServices[key]) {
                        groupedServices[key] = {
                            service_id: service.service_id,
                            discount_type: service.discount_type,
                            discount_amount: service.discount_amount,
                            sessions: 0
                        };
                    }
                    groupedServices[key].sessions++;
                });
                
                Object.values(groupedServices).forEach(function(service, index) {
                    addEditConfGetServiceRow(index, service);
                    confGetServiceRowIndex++;
                });
            } else {
                addEditConfGetServiceRow(0);
                confGetServiceRowIndex = 1;
            }
            
            reInitSelect2(".select2", "");
            initDatepickers();
        },
        error: function (xhr) {
            errorMessage(xhr);
        }
    });
}

// Initialize datepickers
function initDatepickers() {
    $('.current-datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });
}

// Override editRow to handle configurable discounts
var originalEditRow = typeof editRow === 'function' ? editRow : null;
function editRow(url) {
    // First fetch to check if it's configurable
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            let discount = response.data.discount;
            
            if (discount.type === 'Configurable') {
                // Use configurable edit modal
                editConfigurableDiscount(url);
            } else {
                // Use regular edit modal
                $("#modal_edit_discounts").modal("show");
                setEditData(response);
                reInitSelect2(".select2", "");
            }
        },
        error: function (xhr) {
            errorMessage(xhr);
        }
    });
}

