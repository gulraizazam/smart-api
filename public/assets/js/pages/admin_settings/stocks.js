
var table_url = route('admin.products.stock-detail', {id: product_id});

var table_columns = [
    {
        field: 'product_id',
        sortable: false,
        width: 'auto',
        title: 'Product ID'
    },
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
        field: 'stock_type',
        title: 'Stock Type',
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
        }
    },
    ];


function actions(data) {

    let id = data.id;
    let url = route('admin.products.edit', {id: id});
    let delete_url = route('admin.products.destroy', {id: id});
    let edit_sale_price_url = route('admin.products.edit-sale-price', {id: id});
    let stock_url = route('admin.products.stock', {id: id});
    //if (permissions.edit && permissions.delete) {
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
    //    if (permissions.edit) {
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`'+url+'`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>';
            actions += '<li class="navi-item">\
                    <a href="'+stock_url+'" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Stock</span>\
                    </a>\
                </li>';
    //    }
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
    //}
    return '';
}

function formatDate(date) {
    var d = new Date(date),
        month = '' + (d.getMonth() + 1),
        day = '' + d.getDate(),
        year = d.getFullYear();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;

    return [year, month, day].join('-');
}


