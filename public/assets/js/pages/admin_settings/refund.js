
var tempOrdersSaveArr=[];
$(document).ready( function () {

    $(".product_id").select2({
        width: '100%',
        placeholder: 'Select Product',
        ajax: {
            url: route('admin.orders.getproducts'),
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term, // search term
                    page: params.page
                };
            },
            processResults: function (response, params) {

                try {
                    let data = response.data.products;

                    params.page = params.page || 1;
                    return {
                        results: $.map(data, function (item) {

                            return {
                                text: item.name,
                                id: item.id,
                                price:item.sale_price
                            }
                        }),
                    };

                } catch (error) {
                    showException(error);
                }
            },
            cache: true
        },
        escapeMarkup: function (markup) {
            return markup;
        },
        minimumInputLength: 3,
        templateResult: formatRepo,
        templateSelection: formatRepoSelection
    });

    function formatRepo(item) {
        if (item.loading) {
            return item.text;
        }
        markup = item.text;
        return markup;
    }

    function formatRepoSelection(item) {
        $("#discount_price").val('');
        $('#add_disccount_id').val(null).trigger('change');
        if (item.id) {
            $("#add_price").val(item.price);
            $("#add_quantity").val(1); 
            $("#add_unit_price").val(item.price);
            return item.text + " <button onclick='removeProducts()' class='croxcli' style='float: right;border: 0; background: none;padding: 0 0 0;'><i class='fa fa-times' aria-hidden='true'></i></button>";
        } else {
            return 'Select Product';
        }
    }
    /*End*/

});


var table_url = route('admin.orders.refund.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 'auto',
        title: 'Order ID'
    },{
        field: 'patients.name',
        title: 'Patient',
        sortable: false,
        width: 'auto',
    },{
        field: 'orders',
        title: 'Products',
        sortable: false,
        width: 'auto',
        template: function (data) {
            return displayProducts(data.orders);
        }
    },{
        field: 'orders.quantity',
        title: 'Quantity',
        sortable: false,
        width: 'auto',
        template: function (data) {
            return sumProductsQuantity(data.orders);
        }
    },{
        field: 'total_price',
        title: 'Total Price',
        sortable: false,
        width: 'auto',
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
}];


function actions(data) {

    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let detail = route('admin.orders.refund.detail', {id: id});

    //    if (permissions.create && permissions.log && permissions.sms_log && permissions.edit) {
            let actions = '<div class="dropdown dropdown-inline action-dots">\
        <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';

        //    if (permissions.delete) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="getRefundOrderDetail(`' + detail + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-trash"></i></span>\
                        <span class="navi-text">Detail</span>\
                        </a>\
                     </li>';
        //    }

            actions += '</ul>\
        </div>\
    </div>';

            return actions;
        //}
    }
    return '';
}

function getRefundOrderDetail(url){
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            displayRefundOrderDetail(response.data);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function displayRefundOrderDetail(data){
   if(data == null){
    toastr.error("Try Again");
    return false;
   }
   $(".customer-name").html(data.patients.name);
   orders = data.orders;
   lists = '';
   if(orders.length > 0){
    for (let order = 0; order < orders.length; order++) {
        lists+='<tr class="text-center" id="tr_'+order+'"><td>'+orders[order].product.name+'</td><td>'+orders[order].discount.name+'</td><td>'+orders[order].quantity+'</td><td>'+orders[order].sale_price_after_discount+'</td></tr>';
    }
    }else{
        lists+='<tr class="text-center"><td colspan="8">No record found</td></tr>';
    }
    $(".refund_orders").html(lists);
    $("#modal_display_refund_order").modal('show');
}

function sumProductsQuantity(orders){
    let quantitySum = 0;
    if(orders != null){
        for(let order=0; order<orders.length;order++){
            quantitySum=quantitySum+orders[order].quantity;
        }
    }
    return quantitySum;
}

function displayProducts(orders){
    let productHtml = '';
    if(orders != null){
        for(let order=0; order<orders.length;order++){
            productHtml+='<span style="margin-bottom: 3px;" class="badge badge-info">'+orders[order].product.name+'</span><br/>';
        }
    }
    return productHtml;
}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            patient_id: $("#search_patient_id").val(),
            product_id: $("#search_product_id").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            patient_id: '',
            product_id: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}
