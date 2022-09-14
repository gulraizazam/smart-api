
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
                                price:item.sale_price,
                                quantity:item.quantity
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
            $("#available_quantity").val(item.quantity);
            $('#add_disccount_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
            return item.text + " <button onclick='removeProducts()' class='croxcli' style='float: right;border: 0; background: none;padding: 0 0 0;'><i class='fa fa-times' aria-hidden='true'></i></button>";
        } else {
            return 'Select Product';
        }
    }

    $("#add_quantity").on('keyup',function(){
        if($("#add_price").val() != '' && $("#add_quantity").val() > 0){
            let total_price=$("#add_quantity").val()*$("#add_unit_price").val();
            $("#add_price").val(total_price);
            if($("#add_disccount_id").val() != ''){
                $("#add_disccount_id").val(null).trigger('change');
            }
            
        }
    })

    $("#add_disccount_id").on('change',function(){
        if($(this).find(':selected').attr('data-amount') == undefined){
            $("#add_quantity").trigger('keyup');
        }else{
            let discount = $(this).find(':selected').attr('data-amount');
            if($("#add_quantity").val() > 0){
                var discounted_price = ($("#add_unit_price").val()*discount/100)*$("#add_quantity").val();
            }else{
                var discounted_price = ($("#add_unit_price").val()*discount/100)*1;
            }
            $("#discount_price").val(discounted_price);
            $("#add_price").val(($("#add_unit_price").val()*$("#add_quantity").val())-discounted_price);
        }
        
    })
    /*save data for both predefined discounts and keyup trigger*/
    $("#add_order").click(function () {
        if(parseInt($("#add_quantity").val()) > parseInt($("#available_quantity").val())){
            toastr.error("Product quantity exceeded");
            return false;
        }
        $('#inputfieldMessage').hide();
        $('#inputExistMessage').hide();
        $('#inputEmptyMessage').hide();
        var patient_id=$("#add_patient_id").val();
        var product_id = $("#add_product_id").val();
        var product_name = $("#select2-add_product_id-container").attr("title");
        var quantity = $("#add_quantity").val();
        var discount_id= $("#add_disccount_id").val();
        var discount_price = $("#discount_price").val();
        var discount_name = $("#add_disccount_id").val() != '' ? $("#select2-add_disccount_id-container").attr("title") : '';
        var price = $("#add_price").val();
        if(patient_id == '' || product_id == '' || quantity == '' || price == ''){
            $('#inputfieldMessage').show();
            return false;
        }
        for (let order = 0; order < tempOrdersSaveArr.length; order++) {
            if(tempOrdersSaveArr[order]['product_id'] == product_id){
                $('#inputExistMessage').show();
                return false;
            }
        }
        tempEachOrdersSave = {};
        tempEachOrdersSave = {
            'patient_id':patient_id,
            'product_id':product_id,
            'product_name':product_name,
            'quantity':quantity,
            'discount_id':discount_id,
            'discount_price':discount_price,
            'discount_name':discount_name,
            'sale_price': discount_price != "" ? parseInt(price)+parseInt(discount_price) : parseInt(price),
            'sale_price_after_discount':price
        };
        tempOrdersSaveArr[tempOrdersSaveArr.length]= tempEachOrdersSave;
        tempEachOrdersSave = {};
        displayProductTableHTML();        
    });
    /*End*/
    patientSearch('search_patient',0);
    patientSearchOrder('search_patient_order',0);
    
});

function getDiscounts(){
    $("#inputEmptyMessage").hide();
    $("#inputExistMessage").hide();
    $("#inputfieldMessage").hide();
    var url = route('admin.orders.getdiscounts');
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_create_order").modal("show");
            let discounts = response.data.discounts;
            let discount_options = '<option value="">Select Discount</option>';

            if (discounts) {
                Object.entries(discounts).forEach( function(discount) {
                    discount_options += '<option data-amount="'+discount[1].amount+'" value="'+discount[1].id+'">'+discount[1].name+'</option>';
                });
            }

            $("#add_disccount_id").html(discount_options);
            tempOrdersSaveArr=[];
            $('.patient_id').val(null).trigger('change');
            $('.product_id').val(null).trigger('change');
            $('#add_disccount_id').val(null).trigger('change');
            $("#add_price").val('');
            $("#add_quantity").val('');
            $('#add_product_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
            $('#add_disccount_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
            $(".plan_services").html('<tr class="text-center"><td colspan="8">No record found</td></tr>');

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function displayProductTableHTML(){
    lists = '';
    if(tempOrdersSaveArr.length > 0){
        for (let order = 0; order < tempOrdersSaveArr.length; order++) {
            lists+='<tr class="text-center" id="tr_'+order+'"><td>'+tempOrdersSaveArr[order].product_name+'</td><td>'+tempOrdersSaveArr[order].discount_name+'</td><td>'+tempOrdersSaveArr[order].quantity+'</td><td>'+tempOrdersSaveArr[order].sale_price_after_discount+'</td><td><button type="button" class="btn btn-danger" onclick="removeProductFromTable('+order+')">Delete</button></td></tr>';
        }
    }else{
        lists+='<tr class="text-center"><td colspan="8">No record found</td></tr>';
    }
    $(".plan_services").html(lists);
}

function removeProductFromTable(index){
    $("#tr_"+index).hide();
    tempOrdersSaveArr.splice(index, 1);
    displayProductTableHTML();
}

function saveOrder(){
    url = route('admin.orders.store');
    if(tempOrdersSaveArr.length == 0){
        $('#inputEmptyMessage').show();
        return false;
    }
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "POST",
        data:{data:tempOrdersSaveArr},
        success: function (response) {
            closePopup('modal_add_order_form');
            toastr.success(response.message);
            reInitTable();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });


}

function refundOrder(url){

     if(url == ''){
        toastr.error("Try Again");
        return false;
    }
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "POST",
        success: function (response) {
            closePopup('modal_order_refund_form');
            toastr.success(response.message);
            //reInitTable();
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

var table_url = route('admin.orders.datatable');

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
    let id = data.id;
    let refund_url = route('admin.orders.refund', {id: id});
    if (permissions.refund) {
        let actions = '<div class="dropdown dropdown-inline action-dots">\
            <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
            if (permissions.refund) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="refundOrder(`' + refund_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-plus"></i></span>\
                        <span class="navi-text">Refund Order</span>\
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
function removeProducts() {
    $('.product_id').val(null).trigger('change');
    $("#add_price").val('');
    $("#add_quantity").val('');
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

function sumProductsQuantity(orders){
    let quantitySum = 0;
    if(orders != null){
        for(let order=0; order<orders.length;order++){
            quantitySum+= orders[order].quantity;
        }
    }
    return quantitySum;
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

function patientSearchOrder(search_id = 'patient_id',flag=1) {

    $("." + search_id).keyup(function() {
        $(".suggestion-list-order").html('<li>Searching...</li>');
        $(".suggesstion-box-order").show();

        if ($(this).val().length < 2) {
            $(".suggesstion-box-order").hide();
            return false;
        }

        if ($(this).val() != '') {

            let form_type = $(this).parents("form").find('.form_type').val();

            $.ajax({
                type: "GET",
                url: route('admin.users.getpatient.id'),
                dataType: 'json',
                delay: 250,
                data: {search: $(this).val()},

                success: function (response) {

                    let html = '';
                    let patients = response.data.patients;

                    if (patients.length) {
                        Object.values(patients).forEach(function (patient) {
                            html += '<li onClick="selectUserOrder(`' + patient.name + '`, `' + patient.id + '`, `'+ search_id+'`, `'+ flag+'`);">' + patient.name +' - '+ makePatientId(patient.id) +'</li>'
                        });

                        $(".suggestion-list-order").html(html);

                        $(".suggesstion-box-order").show();
                    } else {
                        $(".suggesstion-box-order").hide();
                    }

                }
            });

        } else {
            $(".suggesstion-box-order").hide();
        }
    });

    return false;
}

function selectUserOrder(name, user_id,  search_id,flag=1) {


    $("." + search_id).parent('div').find('.search_field').val(user_id).change();
    $("#add_patient_id").val(user_id);
   // $(".search_field").val(user_id).change();
    $("." + search_id).val(name);
    $(".suggesstion-box-order").hide();
    $("." + search_id).focus();
}

function addUsers(){
    $(".filter-field").val('');
}


function resetFilterOrder(){
    addUsers();
    removeProducts();
}