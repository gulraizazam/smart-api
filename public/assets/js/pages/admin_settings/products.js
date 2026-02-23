var table_url = route('admin.products.datatable');

var table_columns = [
    {
        field: 'sku',
        title: 'SKU',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'name',
        title: 'Name',
        width: 'auto',
        sortable: false,
    }, {
        field: 'brand_id',
        title: 'Brand',
        width: 'auto',
        sortable: false,
    },  {
        field: 'sale_price',
        title: 'Sale Price',
        width: 'auto',
        sortable: false,
    },  {
        field: 'status',
        title: 'status',
        width: 80,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.products.status');
            return statusesProduct(data, status_url, true);
        }
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 80,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];


function actions(data) {
    let id = data.id;
    let inventory_id = data.inventory_id;
    let edit_sale_price_url = route('admin.products.edit-sale-price', { id: id });
    let url = route('admin.products.edit', { id: id });
   
    let inventories_url = route('admin.products.inventory', { id: id });
    let allocate_url = route('admin.products.location_manage', {id: id});
    //let transfer_product_url = route('admin.products.transfer_product.get', { id: inventory_id });
    let log_url = route('admin.products.logs', { id: id });

    let actions = '<div class="dropdown dropdown-inline action-dots">\
            <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
                        actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="allocateRow(`' + allocate_url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Add Inventory</span>\
                        </a>\
                    </li>';
                         if (permissions.add_stock) {
                            // actions += '<li class="navi-item">\
                            //                     <a href="'+ inventories_url + '" class="navi-link">\
                            //                     <span class="navi-icon"><i class="la la-archway"></i></span>\
                            //                     <span class="navi-text">Inventories</span>\
                            //                 </a>\
                            //              </li>';
                        }
                        
                        // if (permissions.sale_price) {
                        //     actions += '<li class="navi-item">\
                        //                 <a href="javascript:void(0);" onclick="editSalePrice(`' + edit_sale_price_url + '`);" class="navi-link">\
                        //                 <span class="navi-icon"><i class="la la-money-bill-wave"></i></span>\
                        //                 <span class="navi-text">Sale Price</span>\
                        //                 </a>\
                        //             </li>';
                        // }
                        
                        // if (permissions.transfer_product) {
                        //     actions += '<li class="navi-item">\
                        //                     <a href="javascript:void(0);" onclick="transferProductRow(`' + transfer_product_url + '`);" class="navi-link">\
                        //                     <span class="navi-icon"><i class="la la-exchange-alt"></i></span>\
                        //                     <span class="navi-text">Transfer Product</span>\
                        //                     </a>\
                        //                 </li>';
                        // }
                        // if (permissions.stock_detail) {
                        //     actions += '<li class="navi-item">\
                        //                 <a href="'+ stock_url + '" class="navi-link">\
                        //                     <span class="navi-icon"><i class="la la-archway"></i></span>\
                        //                     <span class="navi-text">Stock Logs</span>\
                        //                 </a>\
                        //             </li>';
                        // }
                        if (permissions.edit) {
                            actions += '<li class="navi-item">\
                                            <a href="javascript:void(0);" onclick="editRow(`'+ url + '`);" class="navi-link">\
                                                <span class="navi-icon"><i class="la la-pencil"></i></span>\
                                                <span class="navi-text">Edit</span>\
                                            </a>\
                                        </li>';
                        }
                        

                        


                        actions += '</ul>\
                                </div>\
                            </div>';

                        return actions;
}
function allocateRow(url) {
    $("#modal_allocate_products").modal("show");
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
        let product = response.data.product;
        let locations = response.data.location;
      
        let location_options = '<option value="">Select Centre</option>';
        let location_services = '';
        Object.values(locations).forEach(function(value, index) {
            location_options += '<option value="">Select</option>';
            Object.values(value.children).forEach(function(child, index) {
                location_options += '<option value="'+child.id+'">'+child.name+'</option>';
            });
        });
      

       

        $("#product_id").val(product.id);

        $("#locations").html(location_options);

       

    } catch (error) {
        showException(error);
    }
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
            $("#modal_edit_products").modal("show");
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setEditData(response) {
    let product = response.data.product;
    let product_detail = response.data.product_detail;
    let quantity = response.data.quantity;
   
    let action = route('admin.products.update', { id: product.id, detail: product_detail.id });
    $("#modal_edit_products_form").attr("action", action);

    /* Products */
    $("#edit_name").val(product.name);
    $("#product_sku").val(product.sku);
    $("#edit_products_brand").val(product.brand_id).trigger('change');
    $("#edit_sale_price").val(product.sale_price);
    $("#edit_product_centre").val(product.location_id).trigger('change');
    $("#edit_product_warehouse").val(product.warehouse_id).trigger('change');
    $("#edit_product_type").val(product.product_type).trigger('change');
    $('#edit_select_option').show();
    if (product.product_type == 'in_house_use') {
        $('#edit_sale_price_section').hide();
    } else if (product.product_type == 'for_sale') {
        $('#edit_sale_price_section').show();
    }
    // if (product.warehouse_id != null) {
    //     $('#edit_product_type_option').val('in_warehouse').trigger('change');
    //     $('#edit_select_warehouse').show();
    //     $('#edit_select_centre').hide();
    // }
    // if (product.location_id != null) {
    //     $('#edit_product_type_option').val('in_branch').trigger('change');
    //     $('#edit_select_centre').show();
    //     $('#edit_select_warehouse').hide();
    // }

    /* Product Details */
    $("#edit_purchase_price").val(product_detail.purchase_price);
    $("#edit_total_purchase_price").val(product_detail.total_purchase_price);
    $("#edit_quantity").val(quantity.stock_quantity);
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {

        let filters = {
            delete: '',
            name: $("#search_name").val(),
           
            status: $("#search_status").val(),
            brand_id: $("#search_brand_id").val(),
            centre_id: $("#search_centre_id").val(),
            warehouse_id: $("#search_warehouse_id").val(),
            created_at: $("#date_range").val(),
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
            brand_id: '',
            centre_id: '',
            warehouse_id: '',
            product_type: '',
            status: '',
            created_at: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    let brands = filter_values.brands;
    let skus = filter_values.sku;
    let centres = filter_values.centres;
    let warehouses = filter_values.warehouse;
    let status = filter_values.status;

    let brands_options = '<option value="">Select Brand</option>';
    let sku_options = '<option value="">Select SKU</option>';
    let centre_options = '<option value="">Select Centre</option>';
    let warehouse_options = '<option value="">Select Warehouse</option>';
    let status_options = '<option value="">Select Status</option>';

    Object.entries(brands).forEach(function (value, index) {
        brands_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });
    Object.entries(skus).forEach(function (value, index) {
        sku_options += '<option value="' + value[1] + '">' + value[1] + '</option>';
    });
    Object.entries(centres).forEach(function (value, index) {
        centre_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });
    Object.entries(warehouses).forEach(function (value, index) {
        warehouse_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    Object.entries(status).forEach(function (value, index) {
        status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    /* List Filters values */
    $("#search_brand_id").html(brands_options);
    $("#search_centre_id").html(centre_options);
    $("#search_warehouse_id").html(warehouse_options);
    $("#search_status").html(status_options);

    /* Add product values */
    $("#add_products_brand").html(brands_options);
    $("#sku").html(sku_options);
    $('#sku').select2({
        tags: true, // Allows custom entries
        placeholder: "Select or enter SKU",
        allowClear: true,
        width: '100%' // Adjust width to match form-control styling
    });
    $("#add_product_centre").html(centre_options);
    $("#add_product_warehouse").html(warehouse_options);

    /* Edit Product values */
    $("#edit_products_brand").html(brands_options);
    $("#edit_product_centre").html(centre_options);
    $("#edit_product_warehouse").html(warehouse_options);

    /* Transfer Product values */
    $("#transfer_product_centre_from").html(centre_options);
    $("#transfer_product_warehouse_from").html(warehouse_options);
    $("#transfer_product_centre_to").html(centre_options);
    $("#transfer_product_warehouse_to").html(warehouse_options);

    /* Active Filters */
    $("#search_name").val(active_filters.name);
   
    $("#search_status").val(active_filters.status);
    $("#search_brand_id").val(active_filters.brand_id);
    $("#search_centre_id").val(active_filters.centre_id);
    $("#search_warehouse_id").val(active_filters.warehouse_id);
    $("#date_range").val(active_filters.created_at);
}

function editSalePrice(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_products_sale_price").modal("show");
            setEditSalePriceData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setEditSalePriceData(response) {
    let product = response.data;
    let action = route('admin.products.update-sale-price', { id: product.id });
    $("#modal_edit_products_sale_price_form").attr("action", action);
    $("#update_sale_price").val(product.sale_price);
}

function statusesProduct(data, status_url, is_column_name_change = false) {

    let id = data.id;

    let active = is_column_name_change == false ? data.active : data.status;
    let status = '';

    if (active) {
        if (permissions.active) {
            status += '<span class="switch switch-icon">\
            <label>\
                <input value="1" onchange="updateStatus(`'+ status_url + '`, `' + id + '`, $(this));" type="checkbox" checked="checked" name="select">\
                <span></span>\
            </label>\
            </span>';
        } else {
            status += '<span class="switch switch-icon">\
            <label>\
                <input disabled type="checkbox" checked="checked" name="select">\
                <span></span>\
            </label>\
            </span>';
        }

    } else {
        if (permissions.active) {
            status += '<span class="switch switch-icon">\
            <label>\
                <input value="1" onchange="updateStatus(`'+ status_url + '`, `' + id + '`, $(this));" type="checkbox" name="select">\
                <span></span>\
            </label>\
            </span>';
         }else{
            status += '<span class="switch switch-icon">\
            <label>\
                <input disabled type="checkbox"  name="select">\
                <span></span>\
            </label>\
            </span>';
        }
    }

    return status;
}

function addProductStock(id,inventory_id) {
  
    let action = route('admin.products.add-stock', { id: id });
    $("#modal_add_product_stock_form").attr("action", action);
    $("#modal_add_product_stock").modal("show");
    $("#inventory_id").val(inventory_id);
}

function getTotalPurchase(type) {
    if (type == 'add') {
        let purchase_price = $("#purchase_price").val();
        let quantity = $("#quantity").val();
        if (purchase_price != "" && quantity != "") {
            $("#total_purchase_price").val(purchase_price * quantity);
        } else {
            $("#total_purchase_price").val('');
        }
    } else if (type == 'edit') {
        let purchase_price = $("#edit_purchase_price").val();
        let quantity = $("#edit_quantity").val();
        if (purchase_price != "" && quantity != "") {
            $("#edit_total_purchase_price").val(purchase_price * quantity);
        } else {
            $("#edit_total_purchase_price").val('');
        }
    } else {
        let purchase_price = $("#add_stock_purchase_price").val();
        let quantity = $("#add_stock_quantity").val();
        if (purchase_price != "" && quantity != "") {
            $("#add_stock_total_purchase_price").val(purchase_price * quantity);
        } else {
            $("#add_stock_total_purchase_price").val('');
        }
    }
}

function transferProductRow(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_transfer_products_form").modal("show");
            transferProductSetData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}
/* Transfer Product Set Data */
function transferProductSetData(response) {
    
    let transferProduct = response.data.product;
    
    $("#transfer_product_id").val(transferProduct.id);
   // $("#transfer_transfer_product").attr('disabled', 'true');
    $("#transfer_location_id_from").val(transferProduct.location_id);
    $("#transfer_warehouse_id_from").val(transferProduct.warehouse_id);

    let location_from_option = transferProduct.location_id != null ? 'in_branch' : 'in_warehouse';

    $("#transfer_product_type_option_from").val(location_from_option).trigger('change');
    //$("#transfer_product_type_option_from").attr('disabled', 'true');
    
    $('#transfer_transfer_product').val(transferProduct.name);
    $('#transfer_total_stock').val(transferProduct.quantity);
    if (location_from_option == 'in_branch') {
        $('.select_centre_from').show();
        $('.select_warehouse_from').hide();
        $("#transfer_product_centre_from").val(transferProduct.location_id).trigger('change');
        $("#transfer_product_centre_from").attr('disabled', 'true');
    } else if (location_from_option == 'in_warehouse'){
        $('.select_warehouse_from').show();
        $('.select_centre_from').hide();
        $("#transfer_product_warehouse_from").val(transferProduct.warehouse_id).trigger('change');
        // $("#transfer_product_warehouse_from").attr('disabled', 'true');
    } else {
        $('.select_warehouse_from').hide();
        $('.select_centre_from').hide();
    }

    $('#transfer_product_quantity').val(transferProduct.quantity);
    $('#transfer_transfer_date').val(moment().format('YYYY-MM-DD'));
}



$("#purchase_price, #quantity").on('keyup', function () {
    getTotalPurchase('add');
});

$("#edit_purchase_price, #edit_quantity").on('keyup', function () {
    getTotalPurchase('edit');
});

$("#add_stock_purchase_price, add_stock_quantity").on('keyup', function () {
    getTotalPurchase('new');
});

$('#add_products_m').on('click', function () {
    $('input').val('');
    $('select').val('');
    $("#select_option").hide();
    $("#select_centre").hide();
    $("#select_warehouse").hide();
});

$(document).ready(function () {
    // $('.sale_price_message').hide();
    // $('#add_product_type').on('change', function () {
    //     $('#select_option').show();
    //     if (this.value == 'in_house_use') {
    //         $('#sale_price_section').hide();
    //         $('#sale_price').val('');
    //     } else {
    //         $('#sale_price_section').show();
    //     }
    // });
    // $('#add_product_type_option').on('change', function () {
    //     if (this.value == 'in_warehouse') {
    //         $('#select_centre').hide();
    //         $('#select_warehouse').show();
    //     } else if (this.value == 'in_branch') {
    //         $('#select_centre').show();
    //         $('#select_warehouse').hide();
    //     } else {
    //         $('#select_centre').hide();
    //         $('#select_warehouse').hide();
    //     }
    // });

    // $('#edit_product_type').on('change', function () {
    //     if (this.value == 'in_house_use') {
    //         $('#edit_sale_price_section').hide();
    //         $('#edit_sale_price').val('');
    //     } else {
    //         $('#edit_sale_price_section').show();
    //     }
    // });

    // $('#edit_product_type_option').on('change', function () {
    //     if (this.value == 'in_warehouse') {
    //         $('#edit_select_centre').hide();
    //         $('#edit_select_warehouse').show();
    //         $('#edit_product_centre').val('');
    //     } else if (this.value == 'in_branch') {
    //         $('#edit_select_centre').show();
    //         $('#edit_select_warehouse').hide();
    //         $('#edit_product_warehouse').val('');
    //     } else {
    //         $('#edit_select_centre').hide();
    //         $('#edit_select_warehouse').hide();
    //         $('#edit_product_centre').val('');
    //         $('#edit_product_warehouse').val('');
    //     }
    // });

    $('#transfer_product_type_option_to').on('change', function () {
        if (this.value == 'in_warehouse') {
            $('.select_centre_to').hide();
            $('.select_warehouse_to').show();
        } else if (this.value == 'in_branch') {
            $('.select_centre_to').show();
            $('.select_warehouse_to').hide();
        } else {
            $('.select_centre_to').hide();
            $('.select_warehouse_to').hide();
        }
    });

})
