var table_url = route('admin.bundles.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 30,
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    },
    {
        field: 'name',
        title: 'Name',
        width: 190,
    },
    {
        field: 'price',
        title: 'Price',
        width: 90,
    },
    {
        field: 'total_services',
        title: 'Total Services',
        width: 100,
    },

    {
        field: 'apply_discount',
        title: 'Apply Discount',
        width: 100,
    },
    {
        field: 'start',
        title: 'Valid From',
        width: 'auto',
    },
    {
        field: 'end',
        title: 'Valid To',
        width: 'auto',
    },{
        field: 'status',
        title: 'status',
        width: 130,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.bundles.status');
            return statuses(data, status_url);
        }
    },{
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 80,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }, {
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
    }];


function actions(data) {

    let id = data.id;

    let csrf = $('meta[name="csrf-token"]').attr('content');
    let url;
    if(data.bundle_type=="configurable"){
         url = route('admin.bundles.editconf', {id: id});
    }else{
         url = route('admin.bundles.edit', {id: id});
    }
   
    let delete_url = route('admin.bundles.destroy', {id: id});

    if (permissions.details || permissions.edit || permissions.delete) {
        let actions = '<div class="dropdown dropdown-inline action-dots">\
            <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
        if (permissions.details) {
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="detailRow(`' + url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Detail</span>\
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
    return '';
}

function editRow(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            
            if(response.data.bundle.bundle_type=="configurable"){
                $("#modal_edit_conf_bundles").modal("show");
                setConfEditData(response);
            }else{
                $("#modal_bundles").modal("show");
                setEditData(response);
            }
          

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(TownValidation);
        }
    });
}

function detailRow(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_details_bundles").modal("show");
            setDetailData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(TownValidation);
        }
    });
}

function setDetailData(response) {
    let bundle = response.data.bundle;
    let bundle_services =response.data.bundle_services;
    let relationships =response.data.relationships;
    $('#detail_name').html(bundle.name);
    $('#detail_price').html(bundle.price);
    $('#detail_total_services').html(bundle.total_services);
    $('#detail_services_price').html(bundle.services_price);
    $('.DETAIL_SERVICES').remove();
    Object.entries(relationships).forEach(function (value, index) {
        $("#detail-service-body").append(setDetailService(bundle_services[value[1].service_id].name, bundle_services[value[1].service_id].price));
    });
}

function setEditData(response) {
    
    $('#model-title').html('Edit Package');
    let bundle = response.data.bundle;
    let bundle_services =response.data.bundle_services;
    let relationships =response.data.relationships;
    let action = route('admin.bundles.update', {id: bundle.id});
    $("#modal_bundles_form").attr("action", action);
    $('#put_input').html('<input type="hidden" name="_method" value="put">');
    $('#bundles_name').val(bundle.name);
    $('#bundles_price').val(bundle.price);
    $('#start').val(bundle.start);
    $('#end').val(bundle.end);
    $('input[name="tax_treatment_type_id"][value="'+bundle.tax_treatment_type_id+'"]').prop('checked',true);
    if(bundle.apply_discount){
        $('input[name="apply_discount"]').prop('checked',true);
    }

    $('.HR_SERVICES').remove();

    Object.entries(relationships).forEach(function (value, index) {

        $('#service_body').append(setService(index+1,value[1].service_id,bundle_services[value[1].service_id].name, bundle_services[value[1].service_id].price));
    });

    calculateServicesTotal();
}
function setConfEditData(response) {
    $('#tes_container').empty();
    let bundle = response.data.bundle;
    let base_service_id = response.data.base_service[0].service_id;
    console.log(base_service_id);
    let action = route('admin.bundles.update', {id: bundle.id});
    $("#modal_edit_conf_bundles_form").attr("action", action);
    $('#editput_input').html('<input type="hidden" name="_method" value="put">');
    $('#edit_bundles_name').val(bundle.name);
    $('#sessions_buy').val(bundle.base_service_session).change();
    $('#start').val(bundle.start);
    $('#end').val(bundle.end);
    let services = response.data.services;
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
    $("#edit_get_services").html(service_options);
    Object.values(response.data.get_services).forEach(function(value, index) {
               
        populateSection(value,index);
        
    });
    $("#edit_base_service").html(service_options);

    $("#edit_base_service").val(base_service_id).change();

   
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

    templateSection.find('[name="edit_sessions['+ index + ']"]').val(1);
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
    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            name: $("#search_name").val(),
            price: $("#search_price").val(),
            total_services: $("#search_total_services").val(),
            apply_discount: $("#search_apply_discount").val(),
            startdate: $("#search_startdate").val(),
            enddate: $("#search_enddate").val(),
            created_from: $("#created_from").val(),
            created_to: $("#created_to").val(),
            status: $("#search_status").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function () {
        let filters = {
            delete: '',
            name: '',
            price: '',
            total_services: '',
            apply_discount: '',
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
    let status = filter_values.status;
    let status_options = '<option value="">All</option>';
    let discounts = filter_values.discounts;
    let discount_options = '<option value="">All</option>';

    let taxs = filter_values.tax_treatment_types;
    let tax_options = '';

    let services = filter_values.services;
    let service_options = '';

    Object.entries(status).forEach(function (value, index) {
        status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });
    Object.entries(discounts).forEach(function (value, index) {
        discount_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    Object.entries(taxs).forEach(function (value, i, index) {
        if (i === 0) {
            tax_options += '<label class="radio"><input checked name="tax_treatment_type_id" value="' + value[1].id + '" type="radio"/><span></span>' + value[1].name + '</label>';
        } else {
            tax_options += '<label class="radio"><input name="tax_treatment_type_id" value="' + value[1].id + '" type="radio"/><span></span>' + value[1].name + '</label>';
        }
    });

    Object.entries(services).forEach(function (value, index) {
        service_options += '<option value="' + value[1].id + '" data-name = "' + value[1].name + '" data-price = "' + value[1].price + '" data-id = "' + value[1].id + '">' + value[1].name + '</option>';
    });


    $("#search_status").html(status_options);
    $("#search_apply_discount").html(discount_options);
    $("#bundles_tax").html(tax_options);
    $("#services").html(service_options);


    $("#search_apply_discount").val(active_filters.apply_discount);
    $("#search_price").val(active_filters.price);
    $("#search_created_from").val(active_filters.created_from);
    $("#search_created_to").val(active_filters.created_to);
    $("#search_startdate").val(active_filters.start);
    $("#search_enddate").val(active_filters.end);
    $("#search_name").val(active_filters.name);
    $("#search_total_services").val(active_filters.total_services);
    $("#search_status").val(active_filters.status);

}


function addRow() {
    if ($('#services').val() != '') {

        let service_id = $('#services').find(':selected').attr('data-id');
        let service_name = $('#services').find(':selected').attr('data-name');
        let service_price = $('#services').find(':selected').attr('data-price');
        console.log("1st "+service_id);
        $('#service_body').append(setService($("#service_body tr").length+1,service_id, service_name, service_price));
        calculateServicesTotal();
    }
}

function calculateServicesTotal() {
    let totalPrice = 0;
    let total_services = 0;
    $('.servicePriceValue').each(function (index, value) {
        totalPrice = totalPrice + parseFloat($(this).val());
        total_services++;
    });
    $('#service_price').val(totalPrice);
    $('#total_services').val(total_services);
}

function setDetailService(service_name, price) {
    return '<tr class="DETAIL_SERVICES">  <td>' + service_name + '</td><td>' + price + '</td></tr>';
}

function setService(id, service_id,service_name, price) {
    console.log("2nd "+id);
    return '<tr id="HR_" class="HR_SERVICES HR_' + id + '"> <input type="hidden" name="service_id[]" value="' + service_id + '"> <input type="hidden" name="service_price[]" value="' + price + '"> <input type="hidden" class="servicePriceValue" value="' + price + '"> <td>' + service_name + '</td><td>' + price + '</td><td>' + deleteIcon(id) + '</td></tr>';
}

function deleteIcon(id) {
    return '<a href="javascript:void(0);" onClick="deleteModel(' + id + ')" class="btn btn-icon btn-light btn-hover-danger btn-sm"> <span class="svg-icon svg-icon-md svg-icon-danger"> <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"> <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <rect x="0" y="0" width="24" height="24"></rect> <path d="M6,8 L6,20.5 C6,21.3284271 6.67157288,22 7.5,22 L16.5,22 C17.3284271,22 18,21.3284271 18,20.5 L18,8 L6,8 Z" fill="#000000" fill-rule="nonzero"></path> <path d="M14,4.5 L14,4 C14,3.44771525 13.5522847,3 13,3 L11,3 C10.4477153,3 10,3.44771525 10,4 L10,4.5 L5.5,4.5 C5.22385763,4.5 5,4.72385763 5,5 L5,5.5 C5,5.77614237 5.22385763,6 5.5,6 L18.5,6 C18.7761424,6 19,5.77614237 19,5.5 L19,5 C19,4.72385763 18.7761424,4.5 18.5,4.5 L14,4.5 Z" fill="#000000" opacity="0.3"></path> </g> </svg> </span> </a>';
}

function deleteModel(id) {
    $('.HR_' + id).remove();
    calculateServicesTotal();
}

$('#create-btn').click(function () {
    let action = route('admin.bundles.store');
    $("#modal_bundles_form").attr("action", action);
    $('#put_input').html('');
    $('#model-title').html('Add Package');
    $('.HR_SERVICES').remove();
    calculateServicesTotal();
});

function notParent(val) {
    let form = ($(val).parents('form'))[0];
    let value = $(val).val();
    if (value == '' || value == null) {
        $('.not-have-parent').show();
    } else {
        $(form).find("input[name='is_default'][value='1']").prop('checked', false);
        $(form).find("input[name='is_default'][value='0']").prop('checked', true);

        $(form).find("input[name='is_arrived'][value='1']").prop('checked', false);
        $(form).find("input[name='is_arrived'][value='0']").prop('checked', true);

        $(form).find("input[name='is_cancelled'][value='1']").prop('checked', false);
        $(form).find("input[name='is_cancelled'][value='0']").prop('checked', true);

        $(form).find("input[name='is_unscheduled'][value='1']").prop('checked', false);
        $(form).find("input[name='is_unscheduled'][value='0']").prop('checked', true);

        $(form).find("input[name='allow_message']").prop('checked', false);

        $('.not-have-parent').hide();
    }
}
function SetFields()
{
   
        
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route("admin.discounts.getDiscountServices"),
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
                $("#add_base_service").html(service_options);
                $("#services_sessions").html(service_options);
                $('#add_base_service').select2();
               
                reInitSelect2(".select2", "");
    
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
    
                reInitValidation(EditValidation);
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


})