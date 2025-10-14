
var table_url = route('admin.voucherTypes.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 30,
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    }, {
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 200,
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
            let status_url = route('admin.voucherTypes.status');
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



function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let url = route('admin.voucherTypes.edit', {id: id});
        let allocate_url = route('admin.voucherTypes.location_manage', {id: id});
        let delete_url = route('admin.voucherTypes.destroy', {id: id});

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
            if (permissions.assign) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="assignToPatient(`' + id + '`, `' + data.name + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-user"></i></span>\
                        <span class="navi-text">Assign To Patient</span>\
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

    $("#modal_allocate_voucher").modal("show");

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

    Object.values(discount_locations).forEach(function(value, index) {
        
        let location_name = value.location.city.name +"-"+ value.location.name;
        location_services += serviceLocation(value.id, location_name, value.service.name);
        
       
    });

        

        $('.HR_SERVICES').remove()
        $('#allocate_services').append(location_services)

        $("#voucher_id").val(discount.id);

        $("#locations").html(location_options);

       
        $("#edit_start").val(discount.start);
        $("#edit_end").val(discount.end);

        $("#edit_active").prop("checked", discount.active);

    } catch (error) {
        showException(error);
    }

}

function getDesrvice($this) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route("admin.voucherTypes.get_Dservice"),
        type: "GET",
        data: {voucher_id:  $("#voucher_id").val(), id: $this.val()},
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
    let service_options = '<option value="">Select</option>';

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
    $("#services").html(service_options);
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
                url: route('admin.voucherTypes.delete_service'),
                data: {'id': id
                },
                success: function (response) {

                    $('.HR_' + response.data.id).remove();
                }
            });

        }
    });
}

function editRow(url) {

    $("#modal_edit_voucher").modal("show");

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

        let voucher = response.data.voucher;
        let isAssigned = response.data.is_assigned;



        $("#modal_edit_vouchers_form").attr("action", route('admin.voucherTypes.update', {id: voucher.id}));


        $("#edit_name").val(voucher.name);
        $("#edit_start").val(voucher.start);
        $("#edit_end").val(voucher.end);

        $("#edit_active").prop("checked", voucher.active);

        // Disable name field if voucher is assigned to a patient
        if (isAssigned) {
            $("#edit_name").prop("readonly", true);
            $("#edit_name").addClass("bg-light-secondary");
        } else {
            $("#edit_name").prop("readonly", false);
            $("#edit_name").removeClass("bg-light-secondary");
        }

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
        $("#search_start").val(active_filters.startdate);
        $("#search_end").val(active_filters.enddate);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);
        $("#search_status").val(active_filters.status);

        hideShowAdvanceFilters(active_filters);

    } catch (err) {

    }
}

function createVoucher($route) {
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {
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

function setVoucherData(response) {

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
    return '<tr id="HR_" class="HR_SERVICES HR_'+id+'"><td>'+location_name+'</td><td>'+service_name+'</td><td>'+deleteIcon(id)+'</td></tr>';
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


})

function assignToPatient(voucherId, voucherName) {
    $('#assign_voucher_type_name').val(voucherName);
    $('#assign_voucher_id').val(voucherId);
    $('#assign_patient_search').val('');
    $('#assign_patient_id').val('');
    $('#assign_amount').val('');
    $('.suggesstion-box-voucher').hide();

    $("#modal_assign_voucher_to_patient").modal("show");
}

function selectUserVoucher(name, user_id) {
    $('#assign_patient_search').val(name);
    $('#assign_patient_id').val(user_id);
    $(".suggesstion-box-voucher").hide();
}

$(document).ready(function() {
    // Patient search for voucher assignment
    $(document).on("keyup", "#assign_patient_search", function () {
        $(".suggesstion-box-voucher .suggestion-list").html('<li>Searching...</li>');
        $(".suggesstion-box-voucher").show();

        if ($(this).val().length < 2) {
            $(".suggesstion-box-voucher").hide();
            return false;
        }

        var that = $(this);
        if ($(this).val() != '') {
            setTimeout(function () {
                $.ajax({
                    type: "GET",
                    url: route('admin.users.getpatient.id'),
                    dataType: 'json',
                    data: { search: that.val() },
                    success: function (response) {
                        let html = '';
                        $(".suggesstion-box-voucher .suggestion-list").html(html);
                        let patients = response.data.patients;
                        if (patients.length) {
                            patients.forEach(function (patient) {
                                html += '<li onClick="selectUserVoucher(\'' + patient.name + '\', \'' + patient.id + '\');">' + patient.name + ' - ' + patient.id + '</li>';
                            });
                            $(".suggesstion-box-voucher .suggestion-list").html(html);
                            $(".suggesstion-box-voucher").show();
                        } else {
                            $(".suggesstion-box-voucher").hide();
                        }
                    }
                });
            }, 1000);
        } else {
            $(".suggesstion-box-voucher").hide();
        }
    });

    $(document).on("click", ".clear-patient-voucher", function () {
        $('#assign_patient_search').val('');
        $('#assign_patient_id').val('');
        $(".suggesstion-box-voucher").hide();
    });
});

