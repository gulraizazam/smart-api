var table_url = route('admin.bundles.datatable');

var table_columns = [
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
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            if (data.created_at) {
                var date = new Date(data.created_at);
                return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' }) + ' ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            }
            return '';
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
    }
];


function actions(data) {

    let id = data.id;

    let csrf = $('meta[name="csrf-token"]').attr('content');
    let url = route('admin.bundles.edit', {id: id});
   
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
            $("#modal_bundles").modal("show");
            setEditData(response);
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
    
    $('#model-title').html('Edit Bundle');
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

function addRow() {
    if ($('#services').val() != '') {

        let service_id = $('#services').find(':selected').attr('data-id');
        let service_name = $('#services').find(':selected').attr('data-name');
        let service_price = $('#services').find(':selected').attr('data-price');
  
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
    $('#model-title').html('Add Bundle');
    $('.HR_SERVICES').remove();
    calculateServicesTotal();
});

function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            name: $("#search_name").val(),
            price: $("#search_price").val(),
            total_services: $("#search_total_services").val(),
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
    let taxs = filter_values.tax_treatment_types;
    let tax_options = '';

    let services = filter_values.services;
    let service_options = '';

    Object.entries(status).forEach(function (value, index) {
        status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
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
    $("#bundles_tax").html(tax_options);
    $("#services").html(service_options);


    $("#search_price").val(active_filters.price);
    $("#search_created_from").val(active_filters.created_from);
    $("#search_created_to").val(active_filters.created_to);
    $("#search_startdate").val(active_filters.start);
    $("#search_enddate").val(active_filters.end);
    $("#search_name").val(active_filters.name);
    $("#search_total_services").val(active_filters.total_services);
    $("#search_status").val(active_filters.status);

}