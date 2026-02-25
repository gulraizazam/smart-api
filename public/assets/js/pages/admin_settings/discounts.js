
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
        let configurableServices = response.data.configurable_services || null;

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

        // Build configurable services display string
        let configurableServicesDisplay = '';
        if (isConfigurable && configurableServices) {
            let buyParts = [];
            let getParts = [];
            
            if (configurableServices.base_services && configurableServices.base_services.length > 0) {
                configurableServices.base_services.forEach(function(svc) {
                    let label = svc.is_category ? '[Category] ' + svc.name : svc.name;
                    buyParts.push('Buy ' + svc.sessions + ' ' + label);
                });
            }
            
            if (configurableServices.get_services && configurableServices.get_services.length > 0) {
                configurableServices.get_services.forEach(function(svc) {
                    let discountText = svc.discount_type === 'complimentory' ? 'Free' : svc.discount_amount + '% Off';
                    let serviceName = svc.same_service ? 'Same Service' : svc.name;
                    getParts.push('Get ' + svc.sessions + ' ' + serviceName + ' (' + discountText + ')');
                });
            }
            
            configurableServicesDisplay = buyParts.join(', ') + ' → ' + getParts.join(', ');
        }

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
            
            // For configurable discounts, use the configurable services display
            if (isConfigurable && configurableServicesDisplay) {
                grouped[groupKey].service_names = [configurableServicesDisplay];
            } else {
                grouped[groupKey].service_names.push(value.service.name);
            }
        });

        // Build table rows from grouped data
        Object.values(grouped).forEach(function(group) {
            let serviceNamesDisplay = isConfigurable && configurableServicesDisplay 
                ? configurableServicesDisplay 
                : group.service_names.join(', ');
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
    // Skip loading services for configurable discounts - services are already defined in the discount
    let discountType = $("#discount_type_hidden").val();
    if (discountType === 'Configurable') {
        return;
    }

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
    $('#edit_get_services_container').empty();
    editGetServiceRowIndex = 0;

    try {

        let discount = response.data.discount;
        
        // Set discount type display and hidden field
        let discountTypeDisplay = discount.type === 'Configurable' ? 'Configurable (Buy X Get Y)' : 'Simple Discount';
        $('#edit_discount_type_display').val(discountTypeDisplay);
        $('#edit_discount_type_hidden').val(discount.type || 'Simple');

        if(discount.type=="Configurable"){
            // Show configurable fields
            $('.edit-configurable-discount-fields').show();
            
            let services = response.data.services || {};
            let get_services = response.data.get_discount_services || [];
            let base_discount_services = response.data.base_discount_services || [];
            let sessions_buy = base_discount_services.length > 0 ? (base_discount_services[0].sessions || base_discount_services.length) : 0;
            let base_service_id = base_discount_services.length > 0 ? base_discount_services[0].service_id : '';
            let isCategory = base_discount_services.length > 0 && base_discount_services[0].is_category == 1;
            
            // Build service options and category options
            let service_options = '<option value="">Select Service</option>';
            let category_options = '';
            let servicesArray = Array.isArray(services) ? services : Object.values(services);
            
            servicesArray.forEach(function(value) {
                if (!value || value.name === 'All Services' || value.slug === 'all') {
                    return;
                }
                service_options += '<option disabled value="' + value.id + '">' + value.name + '</option>';
                category_options += '<option value="' + value.id + '">' + value.name + '</option>';
                if (value.children && value.children.length > 0) {
                    value.children.forEach(function (child) {
                        if (child && child.id) {
                            service_options += '<option value="' + child.id + '">&nbsp;&nbsp;&nbsp;' + child.name + '</option>';
                        }
                    });
                }
            });
            
            confServicesOptions = service_options;
            confCategoryOptions = category_options;
            
            // Set BUY section - determine mode
            $('#edit_sessions_buy').val(sessions_buy);
            
            if (isCategory) {
                // Category mode
                $('#edit_buy_mode_category').prop('checked', true).trigger('change');
                $("#edit_base_category").html(category_options);
                // Get unique category IDs from base_discount_services
                let categoryIds = [...new Set(base_discount_services.map(s => s.service_id))];
                $("#edit_base_category").val(categoryIds);
                // Hide service, show category
                $('.edit-buy-service-wrap').hide();
                $('.edit-buy-category-wrap').show();
                $('#edit_base_service').prop('disabled', true);
                $('#edit_base_category').prop('disabled', false);
            } else {
                // Service mode
                $('#edit_buy_mode_service').prop('checked', true);
                $("#edit_base_service").html(service_options);
                $("#edit_base_service").val(base_service_id);
                $('.edit-buy-service-wrap').show();
                $('.edit-buy-category-wrap').hide();
                $('#edit_base_service').prop('disabled', false);
                $('#edit_base_category').prop('disabled', true);
            }
            
            // Also populate the other dropdown for potential mode switch
            $("#edit_base_service").html(service_options);
            $("#edit_base_category").html(category_options);
            if (isCategory) {
                let categoryIds = [...new Set(base_discount_services.map(s => s.service_id))];
                $("#edit_base_category").val(categoryIds);
            } else {
                $("#edit_base_service").val(base_service_id);
            }
            
            // Group GET services by service_id, discount_type, and same_service
            let groupedServices = {};
            if (get_services && get_services.length > 0) {
                get_services.forEach(function(service) {
                    let sameFlag = service.same_service || 0;
                    let key = service.service_id + '_' + service.discount_type + '_' + sameFlag;
                    if (!groupedServices[key]) {
                        groupedServices[key] = {
                            service_id: service.service_id,
                            discount_type: service.discount_type,
                            discount_amount: service.discount_amount,
                            same_service: sameFlag,
                            sessions: 0
                        };
                    }
                    groupedServices[key].sessions++;
                });
                
                Object.values(groupedServices).forEach(function(service, index) {
                    addEditGetServiceRow(index, service);
                    editGetServiceRowIndex++;
                });
            } else {
                addEditGetServiceRow(0);
                editGetServiceRowIndex = 1;
            }
        } else {
            // Hide configurable fields for simple discounts
            $('.edit-configurable-discount-fields').hide();
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
        
        // Populate roles dropdown
        let roles = response.data.roles || {};
        let selectedRoles = response.data.selected_roles || [];
        let roleOptions = '';

        // Always populate all roles
        if (roles && Object.keys(roles).length > 0) {
            Object.entries(roles).forEach(([id, name]) => {
                let selected = selectedRoles.length > 0 && selectedRoles.includes(parseInt(id)) ? 'selected' : '';
                roleOptions += `<option value="${id}" ${selected}>${name}</option>`;
            });
        }

        $("#edit_user_roles").html(roleOptions);
        
        // Re-initialize select2 to show selected values
        if ($("#edit_user_roles").hasClass("select2-hidden-accessible")) {
            $("#edit_user_roles").select2('destroy');
        }
        $("#edit_user_roles").select2();
        
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
    
    // Reset form
    $("#modal_add_discounts_form")[0].reset();
    
    // Reset discount type to Simple and hide configurable fields
    $("#add_discount_type").val('Simple');
    $('.configurable-discount-fields').hide();
    $('#add_get_services_container').html('');
    confGetServiceRowIndex = 1;
    
    // Reset buy mode to Service
    $('#add_buy_mode_service').prop('checked', true);
    $('.add-buy-service-wrap').show();
    $('.add-buy-category-wrap').hide();
    $('#add_base_service').prop('disabled', false);
    $('#add_base_category').prop('disabled', true).val([]);
    
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
            
            initDatepickers();
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
var editGetServiceRowIndex = 0;
var confServicesOptions = '';
var confCategoryOptions = '';

// Toggle discount type fields in Add Discount modal
function toggleDiscountTypeFields() {
    let discountType = $('#add_discount_type').val();
    
    if (discountType === 'Configurable') {
        // Show configurable fields (BUY/GET sections)
        $('.configurable-discount-fields').show();
        
        // Load services if not already loaded
        if ($('#add_base_service option').length <= 1) {
            loadServicesForConfigurable();
        }
        
        // Add first GET row if container is empty
        if ($('#add_get_services_container').children().length === 0) {
            addGetServiceRow(0);
        }
    } else {
        // Hide configurable fields (BUY/GET sections)
        $('.configurable-discount-fields').hide();
    }
}

// Load services for configurable discount
function loadServicesForConfigurable() {
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
                return;
            }
            
            let services = response.data.services;
            let service_options = '<option value="">Select Service</option>';
            let category_options = '';
            
            let servicesArray = Array.isArray(services) ? services : Object.values(services);
            
            servicesArray.forEach(function(value) {
                if (!value || value.name === 'All Services' || value.slug === 'all') {
                    return;
                }
                // Parent = category (disabled in service mode, selectable in category mode)
                service_options += '<option disabled value="' + value.id + '">' + value.name + '</option>';
                category_options += '<option value="' + value.id + '">' + value.name + '</option>';
                if (value.children && value.children.length > 0) {
                    value.children.forEach(function (child) {
                        if (child && child.id) {
                            service_options += '<option value="' + child.id + '">&nbsp;&nbsp;&nbsp;' + child.name + '</option>';
                        }
                    });
                }
            });
            
            confServicesOptions = service_options;
            confCategoryOptions = category_options;
            $("#add_base_service").html(service_options);
            $("#add_base_category").html(category_options);
            
            // Update existing GET service dropdowns
            $('#add_get_services_container .add-get-service').each(function() {
                $(this).html(service_options);
            });
            
            reInitSelect2(".select2", "");
        },
        error: function (xhr) {
            errorMessage(xhr);
        }
    });
}

// Toggle BUY mode between Service and Category (Add modal)
$(document).on('change', '.buy-mode-radio', function() {
    let mode = $(this).val();
    if (mode === 'category') {
        $('.add-buy-service-wrap').hide();
        $('.add-buy-category-wrap').show();
        // Disable single select so it doesn't submit
        $('#add_base_service').prop('disabled', true);
        $('#add_base_category').prop('disabled', false);
    } else {
        $('.add-buy-service-wrap').show();
        $('.add-buy-category-wrap').hide();
        $('#add_base_service').prop('disabled', false);
        $('#add_base_category').prop('disabled', true);
    }
    reInitSelect2(".select2", "");
});

// Toggle BUY mode between Service and Category (Edit modal)
$(document).on('change', '.edit-buy-mode-radio', function() {
    let mode = $(this).val();
    if (mode === 'category') {
        $('.edit-buy-service-wrap').hide();
        $('.edit-buy-category-wrap').show();
        $('#edit_base_service').prop('disabled', true);
        $('#edit_base_category').prop('disabled', false);
    } else {
        $('.edit-buy-service-wrap').show();
        $('.edit-buy-category-wrap').hide();
        $('#edit_base_service').prop('disabled', false);
        $('#edit_base_category').prop('disabled', true);
    }
    reInitSelect2(".select2", "");
});

// Add GET service row in Add Discount modal
function addGetServiceRow(index) {
    let rowHtml = `
        <div class="get-service-row mb-3" data-index="${index}">
            <div class="row align-items-end">
                <div class="col-md-1 d-flex align-items-center justify-content-center" style="padding-bottom: 8px;">
                    <button type="button" class="btn btn-sm btn-primary add-get-row-btn" title="Add More">
                        <i class="la la-plus p-0 m-0"></i>
                    </button>
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
                <div class="col-md-1 text-center d-flex align-items-center justify-content-center" style="padding-bottom: 8px;">
                    <span class="fw-bold">of</span>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-2">
                        <label class="fw-bold fs-6 mb-0 mr-3">Service</label>
                        <div class="form-check form-check-sm">
                            <input class="form-check-input same-service-check" type="checkbox" name="same_service[${index}]" value="1" id="add_same_service_${index}">
                            <label class="form-check-label fs-7 text-muted" for="add_same_service_${index}">Same as BUY</label>
                        </div>
                    </div>
                    <select class="form-control form-control-solid add-get-service select2" name="services_name[${index}]">
                        ${confServicesOptions || '<option value="">Select Service</option>'}
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="fw-bold fs-6 mb-2">Discount Type <span class="text text-danger">*</span></label>
                    <div class="d-flex align-items-center" style="height: 38px;">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input disc-type-radio" type="radio" name="disc_type[${index}]" value="complimentory" id="add_complimentory_${index}">
                            <label class="form-check-label" for="add_complimentory_${index}">Free</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input disc-type-radio" type="radio" name="disc_type[${index}]" value="custom" id="add_custom_${index}">
                            <label class="form-check-label" for="add_custom_${index}">% Off</label>
                        </div>
                        <input type="number" class="form-control form-control-sm percentage-input d-none" name="configurable_amount[${index}]" placeholder="%" min="1" max="99" style="width: 70px;" disabled>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#add_get_services_container').append(rowHtml);
    
    // Initialize select2 on the newly added service dropdown
    $(`#add_get_services_container .get-service-row[data-index="${index}"] .add-get-service`).select2({
        placeholder: "Select Service",
        allowClear: true
    });
    
    confGetServiceRowIndex++;
}

// Toggle GET service dropdown when "Same as BUY" checkbox changes
$(document).on('change', '.same-service-check', function() {
    let row = $(this).closest('.get-service-row');
    let serviceSelect = row.find('select[name^="services_name"], select[name^="edit_services_name"]');
    if ($(this).is(':checked')) {
        serviceSelect.prop('disabled', true).val('').trigger('change');
        serviceSelect.closest('.col-md-4').find('.select2-container').css('opacity', '0.5');
    } else {
        serviceSelect.prop('disabled', false);
        serviceSelect.closest('.col-md-4').find('.select2-container').css('opacity', '1');
    }
});

// Event delegation for add/remove GET rows
$(document).on('click', '#add_get_services_container .add-get-row-btn', function() {
    // Change current button to remove
    $(this).removeClass('btn-primary add-get-row-btn').addClass('btn-danger remove-get-row-btn');
    $(this).find('i').removeClass('la-plus').addClass('la-minus');
    $(this).attr('title', 'Remove');
    
    // Add new row
    addGetServiceRow(confGetServiceRowIndex);
});

$(document).on('click', '#add_get_services_container .remove-get-row-btn', function() {
    $(this).closest('.get-service-row').remove();
});

// Toggle percentage input visibility
$(document).on('change', '#add_get_services_container .disc-type-radio', function() {
    let row = $(this).closest('.get-service-row');
    let percentageInput = row.find('.percentage-input');
    
    if ($(this).val() === 'custom') {
        percentageInput.removeClass('d-none').prop('disabled', false);
    } else {
        percentageInput.addClass('d-none').val('').prop('disabled', true);
    }
});

// Create Configurable Discount - Load services (legacy function - kept for compatibility)
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


// Event: Add GET service row (Create modal)
$(document).on('click', '.add-get-service-row', function() {
    addConfGetServiceRow(confGetServiceRowIndex);
    confGetServiceRowIndex++;
});

// Event: Remove GET service row (Create modal)
$(document).on('click', '.remove-get-service-row', function() {
    $(this).closest('.get-service-row').remove();
});

// Add GET service row for unified edit modal
function addEditGetServiceRow(index, data = null) {
    let isCustom = data && data.discount_type == 'custom';
    let isSameService = data && data.same_service == 1;
    let rowHtml = `
        <div class="get-service-row mb-3" data-index="${index}">
            <div class="row align-items-end">
                <div class="col-md-1 d-flex align-items-center justify-content-center" style="padding-bottom: 8px;">
                    ${index === 0 ? 
                        '<button type="button" class="btn btn-sm btn-primary add-edit-get-row-btn" title="Add More"><i class="la la-plus p-0 m-0"></i></button>' : 
                        '<button type="button" class="btn btn-sm btn-danger remove-edit-get-row-btn" title="Remove"><i class="la la-minus p-0 m-0"></i></button>'
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
                <div class="col-md-1 text-center d-flex align-items-center justify-content-center" style="padding-bottom: 8px;">
                    <span class="fw-bold">of</span>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center mb-2">
                        <label class="fw-bold fs-6 mb-0 mr-3">Service</label>
                        <div class="form-check form-check-sm">
                            <input class="form-check-input same-service-check" type="checkbox" name="edit_same_service[${index}]" value="1" id="edit_same_service_${index}" ${isSameService ? 'checked' : ''}>
                            <label class="form-check-label fs-7 text-muted" for="edit_same_service_${index}">Same as BUY</label>
                        </div>
                    </div>
                    <select class="form-control form-control-solid edit-get-service select2" name="edit_services_name[${index}]" ${isSameService ? 'disabled' : ''}>
                        ${confServicesOptions || '<option value="">Select Service</option>'}
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="fw-bold fs-6 mb-2">Discount Type <span class="text text-danger">*</span></label>
                    <div class="d-flex align-items-center" style="height: 38px;">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input edit-get-disc-type-radio" type="radio" name="edit_disc_type[${index}]" value="complimentory" id="edit_get_complimentory_${index}" ${data && data.discount_type == 'complimentory' ? 'checked' : ''}>
                            <label class="form-check-label" for="edit_get_complimentory_${index}">Free</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input edit-get-disc-type-radio" type="radio" name="edit_disc_type[${index}]" value="custom" id="edit_get_custom_${index}" ${isCustom ? 'checked' : ''}>
                            <label class="form-check-label" for="edit_get_custom_${index}">% Off</label>
                        </div>
                        <input type="number" class="form-control form-control-sm edit-get-percentage-input ${isCustom ? '' : 'd-none'}" name="configurable_amount[${index}]" placeholder="%" min="1" max="99" style="width: 70px;" value="${data && data.discount_amount ? data.discount_amount : ''}" ${isCustom ? '' : 'disabled'}>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#edit_get_services_container').append(rowHtml);
    
    // Set service value after appending (only if not same_service)
    if (data && data.service_id && !isSameService) {
        $(`#edit_get_services_container .get-service-row[data-index="${index}"] select[name="edit_services_name[${index}]"]`).val(data.service_id);
    }
    
    // Initialize select2 on the service dropdown
    let $select = $(`#edit_get_services_container .get-service-row[data-index="${index}"] .edit-get-service`);
    $select.select2({
        placeholder: "Select Service",
        allowClear: true
    });
    
    // Apply visual disabled state for same_service
    if (isSameService) {
        $select.closest('.col-md-4').find('.select2-container').css('opacity', '0.5');
    }
}

// Event: Add GET service row (Edit modal - unified)
$(document).on('click', '#edit_get_services_container .add-edit-get-row-btn', function() {
    // Change current button to remove
    $(this).removeClass('btn-primary add-edit-get-row-btn').addClass('btn-danger remove-edit-get-row-btn');
    $(this).find('i').removeClass('la-plus').addClass('la-minus');
    $(this).attr('title', 'Remove');
    
    // Add new row
    addEditGetServiceRow(editGetServiceRowIndex);
    editGetServiceRowIndex++;
});

// Event: Remove GET service row (Edit modal - unified)
$(document).on('click', '#edit_get_services_container .remove-edit-get-row-btn', function() {
    $(this).closest('.get-service-row').remove();
});

// Event: Toggle percentage input visibility (Edit modal - unified)
$(document).on('change', '#edit_get_services_container .edit-get-disc-type-radio', function() {
    let row = $(this).closest('.get-service-row');
    let percentageInput = row.find('.edit-get-percentage-input');
    
    if ($(this).val() === 'custom') {
        percentageInput.removeClass('d-none').prop('disabled', false);
    } else {
        percentageInput.addClass('d-none').prop('disabled', true).val('');
    }
});


// Initialize datepickers
function initDatepickers() {
    $('.current-datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });
}

// Override editRow to handle both simple and configurable discounts in same modal
function editRow(url) {
    // Reset edit modal state
    $('.edit-configurable-discount-fields').hide();
    $('#edit_get_services_container').html('');
    editGetServiceRowIndex = 0;
    
    // Reset buy mode to service (will be overridden by setEditData if category)
    $('#edit_buy_mode_service').prop('checked', true);
    $('.edit-buy-service-wrap').show();
    $('.edit-buy-category-wrap').hide();
    $('#edit_base_service').prop('disabled', false);
    $('#edit_base_category').prop('disabled', true).val([]);
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_discounts").modal("show");
            setEditData(response);
            reInitSelect2(".select2", "");
            initDatepickers();
        },
        error: function (xhr) {
            errorMessage(xhr);
        }
    });
}

