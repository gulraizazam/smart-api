
var table_url = route('admin.products.inventories', {id: inventory_id});

var table_columns = [
   
    {
        field: 'product.name',
        title: 'Name',
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
        field: 'sale_price',
        title: 'Sale Price',
        width: 'auto',
        sortable: false,
        template: function (data) {
            return data.sale_price ? 'Rs. ' + parseFloat(data.sale_price).toFixed(2) : 'N/A';
        },
    },
    {
        field: 'centre.name',
        title: 'Centre',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        sortable: false,
        template: function (data) {
            return formatDate(data.created_at)
        },
    },
    // {
    //     field: 'actions',
    //     title: 'Actions',
    //     sortable: false,
    //     width: 80,
    //     overflow: 'visible',
    //     autoHide: false,
    //     template: function (data) {
    //         return actions(data);
    //     }
    // }
    
    // }, 
    // }
];
function actions(data) {
    let id = data.id;
    let edit_url = route('admin.inventory.edit', { id: id });
    let stock_url = route('admin.products.stock', { id: id });
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
                                            <a href="javascript:void(0);" onclick="addProductStock(`' + id + '`,`'+id+'`);" class="navi-link">\
                                            <span class="navi-icon"><i class="la la-plus"></i></span>\
                                            <span class="navi-text">Add Stock</span>\
                                            </a>\
                                        </li>';
                    
                        
                            actions += '<li class="navi-item">\
                                            <a href="javascript:void(0);" onclick="editRow(`' + edit_url + '`);" class="navi-link">\
                                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                                            <span class="navi-text">Edit</span>\
                                            </a>\
                                        </li>';
                        
                        
                        
                       

                        actions += '</ul>\
                                </div>\
                            </div>';

                        return actions;
}
function addProductStock(id,inventory_id) {
    
    let action = route('admin.products.add-stock', { id: id });
    $("#modal_add_product_stock_form").attr("action", action);
    $("#modal_add_product_stock").modal("show");
    $("#inventory_id").val(inventory_id);
}
function editRow(url) {

    $("#modal_edit_inventory").modal("show");

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
    

}