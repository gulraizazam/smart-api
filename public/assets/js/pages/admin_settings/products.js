
var table_url = route('admin.products.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 'auto',
        title: 'Product ID'
    },
    {
        field: 'name',
        title: 'Name',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'brand_id',
        title: 'Brand',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'sale_price',
        title: 'Sale Price',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'quantity',
        title: 'Quantity',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'status',
        title: 'status',
        width: 80,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.products.status');
            return statusesProduct(data, status_url,true);
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
    let url = route('admin.products.edit', {id: id});
    let delete_url = route('admin.products.destroy', {id: id});
    let edit_sale_price_url = route('admin.products.edit-sale-price', {id: id});
    let stock_url = route('admin.products.stock', {id: id});
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
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="addProductStock(`' + id + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-plus"></i></span>\
                        <span class="navi-text">Add Stock</span>\
                        </a>\
                     </li>';
            actions += '<li class="navi-item">\
                     <a href="javascript:void(0);" onclick="editSalePrice(`' + edit_sale_price_url + '`);" class="navi-link">\
                     <span class="navi-icon"><i class="la la-pencil"></i></span>\
                     <span class="navi-text">Sale Price</span>\
                     </a>\
                  </li>';
        if (permissions.edit) {
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`'+url+'`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>';
        }
            actions += '<li class="navi-item">\
                    <a href="'+stock_url+'" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Stock</span>\
                    </a>\
                </li>';
        
    //    if (permissions.delete) {
            // actions += '<li class="navi-item">\
            //                 <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">\
            //                 <span class="navi-icon"><i class="la la-trash"></i></span>\
            //                 <span class="navi-text">Delete</span>\
            //                 </a>\
            //              </li>';
    //    }

        actions += '</ul>\
            </div>\
        </div>';

        return actions;
    }
    return '';
}

function stockDetail(stock_url){
    
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
    let action = route('admin.products.update', {id: product.id,detail:product_detail.id});
    $("#modal_edit_products_form").attr("action", action);
    $("#edit_name").val(product.name);
    $("#edit_products_brand").val(product.brand_id).trigger('change');
    $("#edit_sale_price").val(product.sale_price);
    $("#edit_purchase_price").val(product_detail.purchase_price);
    $("#edit_total_purchase_price").val(product_detail.total_purchase_price);
    $("#edit_quantity").val(product_detail.quantity);
}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            brand_id: $("#search_brand_id").val(),
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
            brand_id: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    let brands = filter_values.brands;

    let brands_options = '<option value="">Select Brand</option>';
    Object.entries(brands).forEach(function(value, index) {
        brands_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    $("#search_brand_id").html(brands_options);
    $("#add_products_brand").html(brands_options);
    $("#edit_products_brand").html(brands_options);
    $("#search_name").val(active_filters.name);
    $("#search_brand_id").val(active_filters.brand_id);
}

function editSalePrice(url){
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
    let action = route('admin.products.update-sale-price', {id: product.id});
    $("#modal_edit_products_sale_price_form").attr("action", action);
    $("#update_sale_price").val(product.sale_price);
}

function statusesProduct(data, status_url,is_column_name_change = false) {

    let id = data.id;

    let active = is_column_name_change == false ? data.active : data.status;
    let status = '';

    if (active) {
        if (permissions.active) {
            status += '<span class="switch switch-icon">\
            <label>\
                <input value="1" onchange="updateStatus(`'+status_url+'`, `'+id+'`, $(this));" type="checkbox" checked="checked" name="select">\
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

        status += '<span class="switch switch-icon">\
        <label>\
            <input value="1" onchange="updateStatus(`'+status_url+'`, `'+id+'`, $(this));" type="checkbox" name="select">\
            <span></span>\
        </label>\
        </span>';
    }

    return status;
}

function addProductStock(id){
    let action = route('admin.products.add-stock', {id: id});
    $("#modal_add_product_stock_form").attr("action", action);
    $("#modal_add_product_stock").modal("show");
}

function getTotalPurchase(type){
    if(type == 'add'){
        let purchase_price = $("#purchase_price").val();
        let quantity=$("#quantity").val();
        if(purchase_price != "" && quantity != ""){
            $("#total_purchase_price").val(purchase_price*quantity);
        }else{
            $("#total_purchase_price").val('');
        }
    }else if(type == 'edit'){
        let purchase_price = $("#edit_purchase_price").val();
        let quantity=$("#edit_quantity").val();
        if(purchase_price != "" && quantity != ""){
            $("#edit_total_purchase_price").val(purchase_price*quantity);
        }else{
            $("#edit_total_purchase_price").val('');
        } 
    }else{
        let purchase_price = $("#add_stock_purchase_price").val();
        let quantity=$("#add_stock_quantity").val();
        if(purchase_price != "" && quantity != ""){
            $("#add_stock_total_purchase_price").val(purchase_price*quantity);
        }else{
            $("#add_stock_total_purchase_price").val('');
        } 
    }
}

$("#purchase_price, #quantity").on('keyup',function(){
    getTotalPurchase('add');
});

$("#edit_purchase_price, edit_quantity").on('keyup',function(){
    getTotalPurchase('edit');
});

$("#add_stock_purchase_price, add_stock_quantity").on('keyup',function(){
    getTotalPurchase('new');
});
