var ProductStock = [];
let refundProductId = [];
let refundProductPrice = [];

// Product search autocomplete function
function initProductSearch() {
    let debounceTimer;
    $(document).off("keyup", ".product_search_id");
    
    $(document).on("keyup", ".product_search_id", function () {
        $(this).parent().find(".product-suggestion-list").html('<li>Searching...</li>');
        $(this).parent().find(".product-suggesstion-box").show();
        if ($(this).val().length < 2) {
            $(this).parent().find(".product-suggesstion-box").hide();
            return false;
        }
        var that = $(this);
        if ($(this).val() != '') {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                $.ajax({
                    type: "GET",
                    url: route('admin.products.search'),
                    dataType: 'json',
                    data: { search: that.val() },
                    success: function (response) {
                        let html = '';
                        that.parent().find(".product-suggestion-list").html(html);
                        let products = response.data.products;
                        if (products.length) {
                            products.forEach(function (product) {
                                html += '<li onClick="selectProductFilter(`' + product.name + '`, `' + product.id + '`);">' + product.name + '</li>'
                            });
                            that.parent().find(".product-suggestion-list").html(html);
                            that.parent().find(".product-suggesstion-box").show();
                            that.parent().find(".product-croxcli").show();
                        } else {
                            that.parent().find(".product-suggesstion-box").hide();
                        }
                    }
                });
            }, 700);
        } else {
            $(this).parent().find(".product-suggesstion-box").hide();
            $(this).parent().find(".product-croxcli").hide();
        }
    });
    $(".product-croxcli").hide();
}

function selectProductFilter(name, id) {
    $(".product_search_id").val(name);
    $(".search_product_field").val(id).change();
    $(".product-suggesstion-box").hide();
    $(".product-croxcli").show();
}

function clearProductSearchFilter() {
    $(".product_search_id").val('');
    $(".search_product_field").val('').change();
    $(".product-suggesstion-box").hide();
    $(".product-croxcli").hide();
}

var table_url = route('admin.orders.datatable');

var table_columns = [
    {
        field: 'patient_id',
        sortable: false,
        width: '80',
        title: 'Patient ID'
    }, {
        field: 'patients.name',
        title: 'Patient',
        sortable: false,
        width: 'auto',
    }, {
        field: 'orders',
        title: 'Products',
        sortable: false,
        width: 'auto',
        template: function (data) {
           
            return displayProducts(data.order_detail);
        }
    }, {
        field: 'orders.quantity',
        title: 'Quantity',
        sortable: false,
        width: 'auto',
        template: function (data) {
            return sumProductsQuantity(data.order_detail);
        }
    }, {
        field: 'order_have',
        title: 'Location',
        sortable: false,
        width: 'auto',
    }, {
        field: 'total_price',
        title: 'Total Price',
        sortable: false,
        width: 'auto',
    }, {
        field: 'payment_mode',
        title: 'Payment Status',
        width: 80,
        template: function (data) {
            if(data.payment_mode==1)
            {
                payment_mode_name = 'Cash';
            }else if(data.payment_mode==2)
            {
                payment_mode_name = 'Card';
            }else{
                payment_mode_name = 'Bank Transfer';
            }
            return '<span class="badge badge-success">' + payment_mode_name + '</span>';
        }
    }
    
    ,{
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
    let edit_url = route('admin.orders.edit', { id: id });
    let refund_url = route('admin.orders.refund.detail', { id: id });
    let delete_url = route('admin.orders.destroy', { id: id });

    let invoice_url = route('admin.orders.invoiceDisplay', { id: id });

    let actions = '<div class="dropdown dropdown-inline action-dots">'

    actions += '<a title="View Invoice" href="javascript:void(0);" onclick="createOrderInvoice(`' + invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-info btn-sm">\
            <span class="navi-icon"><i class="la la-file-invoice-dollar"></i></span>\
                        </a>';

    // actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
    //             <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
    //         </a>\
    //         <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
    //             <ul class="navi flex-column navi-hover py-2">\
    //                 <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
    //                     Choose an action: \
    //                     </li>';
    // if (permissions.refund) {
    //     actions += '<li class="navi-item">\
    //                         <a href="javascript:void(0);" onclick="refundOrder(`' + refund_url + '`);" class="navi-link">\
    //                         <span class="navi-icon"><i class="la la-plus"></i></span>\
    //                         <span class="navi-text">Refund Order</span>\
    //                         </a>\
    //                     </li>';
    // }
   
    actions += '</ul>\
            </div>\
        </div>';

    return actions;
}
function displayProducts(orders) {
    let productHtml = '';
    if (orders != null) {
        orders.forEach(function (value, index) {
            if (value.product != null) {
                productHtml += '<span style="margin-bottom: 3px;" class="badge badge-info">' + value.product.name + '</span><br/>';
            }
        });

    }
    return productHtml;
}

function sumProductsQuantity(orders) {
    let quantitySum = 0;
    if (orders != null) {
        orders.forEach(function (value, index) {
            quantitySum += value.quantity;
        });
    }
    return quantitySum;
}

function addRow() {
    if ($('#add_order_product').val() != '') {
        let product_id = $('#add_order_product').find(':selected').attr('data-id');
        let inventory_id = $('#add_order_product').find(':selected').attr('data-inventory_id');
        let product_name = $('#add_order_product').find(':selected').attr('data-name');
        let product_price = $('#add_order_product').find(':selected').attr('data-price');
        let location_id = $("#add_order_location").val();
        let patient_id = $("#create_order_patient_search").val();
        let employee_id = $("#add_employee_id").val();
        let quantity = 0;
        ProductStock.forEach(function (element) {
            if (element == inventory_id) {
                quantity++;
            }
        });

        $("#add_service_btn").attr('disabled', 'disabled');
        
        // Get available quantity from the selected option
        let available_quantity = parseInt($('#add_order_product').find(':selected').text().match(/(\d+) available/)?.[1] || 0);
        
        if (available_quantity - quantity == 0 || available_quantity - quantity < 0) {
            toastr.error("This Product is out of stock");
            $("#add_service_btn").removeAttr('disabled');
        } else {
            if (ProductStock.includes(inventory_id)) {
                toastr.error("This inventory item is already added to the list.");
                $("#add_service_btn").removeAttr('disabled');
            } else {
                $('#product_list').append(setProduct($("#product_list tr").length + 1, product_id, product_name, product_price, available_quantity, inventory_id));
                calculateTotal($(this));
                ProductStock.push(inventory_id);
                $("#add_service_btn").removeAttr('disabled');
            }
        }
    }
}

function calculateTotal(data) {
    let totalPrice = 0;
    var quantity = data.closest("tr").find('input[name="quantity[]"]').val();
    var price = data.closest("tr").find('.productPriceValue').val();
    var patient_id = $("#create_order_patient_search").val();
    var employee_id = $("#add_employee_id").val();

    data.closest("tr").find('.sub-total').empty();
    data.closest("tr").find('.sub-total').text(quantity * price);

    // Calculate total price by adding all subtotals
    $('tr .sub-total').each(function () {
        var subtotal = parseFloat($(this).text()); // Get the subtotal value and convert to a number
        totalPrice += subtotal; // Add the subtotal to the total
    });

    // Check if the patient has an active membership
    $.ajax({
        type: "GET",
        url: route('admin.orders.check_membership'), // Your route to check membership
        dataType: 'json',
        data: { patient_id: patient_id },
        success: function (response) {
            // Default discount is 0
            let discount = 0;
            
            // Apply a 10% discount if the patient has an active membership
            if (response.has_active_membership) {
                discount = 0.10; // 10% discount
            }

            // Apply a 20% discount if employee_id exists
            if (employee_id) {
                discount = 0.20; // 20% discount
            }

            // Calculate the discount amount and the final discounted price
            let discountAmount = totalPrice * discount;
            let discountedTotal = totalPrice - discountAmount;

            // Update the discount message and total price
            if (discount === 0.10) {
                $('#product_discount').text('10% Discount');
                $('#discount').val(10); // Update refund price
            } else if (discount === 0.20) {
                $('#product_discount').text('20% Employee Discount');
                $('#discount').val(20); // Update refund price
            } else {
                $('#product_discount').text('');
            }

            // Update the total price in the UI
            $('#total_product_price strong').text(discountedTotal.toFixed(2)); // Update total with discount
            $("#grand_total").val(discountedTotal.toFixed(2));
            $('#refund_total_product_price').text(discountedTotal.toFixed(2)); // Update refund price
           
        },
        error: function (error) {
            console.error("Error checking membership:", error);
        }
    });
}


function setProduct(id, product_id, product_name, price, stock, inventory_id) {
    let safePrice = parseFloat(price) || 0;
    return '<tr id="order_" class="order_product product_' + id + '"> <input type="hidden" name="product_id[]" value="' + product_id + '"> <input type="hidden" name="inventory_id[]" value="' + inventory_id + '"> <input type="hidden" name="stock[]" value="' + stock + '"><input type="hidden" name="product_price[]" value="' + safePrice + '"><input type="hidden" name="quantity[]" class="product_quantity_input" value="1"/> <input type="hidden" class="productPriceValue" value="' + safePrice + '"> <td>' + product_name + '</td><td>' + safePrice + '</td><td><div class="number"><span class="minus">-</span><input type="number" class="quantity_input" value="1"/><span class="plus">+</span></div></td><td></td><td class="sub-total">' + safePrice + '</td><td>' + deleteIcon(id) + '</td></tr>';
}


function deleteIcon(id) {
    return '<a href="javascript:void(0);" onClick="deleteModel(' + id + ')" class="btn btn-icon btn-light btn-hover-danger btn-sm"> <span class="svg-icon svg-icon-md svg-icon-danger"> <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"> <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <rect x="0" y="0" width="24" height="24"></rect> <path d="M6,8 L6,20.5 C6,21.3284271 6.67157288,22 7.5,22 L16.5,22 C17.3284271,22 18,21.3284271 18,20.5 L18,8 L6,8 Z" fill="#000000" fill-rule="nonzero"></path> <path d="M14,4.5 L14,4 C14,3.44771525 13.5522847,3 13,3 L11,3 C10.4477153,3 10,3.44771525 10,4 L10,4.5 L5.5,4.5 C5.22385763,4.5 5,4.72385763 5,5 L5,5.5 C5,5.77614237 5.22385763,6 5.5,6 L18.5,6 C18.7761424,6 19,5.77614237 19,5.5 L19,5 C19,4.72385763 18.7761424,4.5 18.5,4.5 L14,4.5 Z" fill="#000000" opacity="0.3"></path> </g> </svg> </span> </a>';
}

function deleteModel(id) {
    $('.product_' + id).remove();
    calculateTotal($(this));
    const valueToRemove = id;
    const indexToRemove = ProductStock.indexOf(valueToRemove);
    ProductStock.splice(indexToRemove, 1);
}

function orderSubmit() {
    ProductStock.length = 0;
    refundProductId.length = 0;
    refundProductPrice.length = 0;
}

function createOrderInvoice(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'GET',
        cache: false,
        success: function (response) {
            $("#display_invoice").html(response)

            $("#modal_display_invoice").modal("show");
        },
        error: function (xhr, ajaxOptions, thrownError) {
            toastr.error("Unable to process the request");
        }
    });

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
            $("#modal_edit_order").modal("show");
            setEditData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setEditData(response) {
    let order = response.data.response;
    let orderDetail = order.order_detail;
    let action = route('admin.orders.update', { id: order.id });
    $("#modal_edit_order_form").attr("action", action);

    /* Order */
    let location_option = order.location_id != null ? 'in_branch' : 'in_warehouse';

    if (location_option == 'in_branch') {
        $('.select_centre').show();
        $("#edit_order_centre").val(order.location_id).trigger('change');
    } else {
        $('.select_warehouse').show();
        $("#edit_order_warehouse").val(order.warehouse_id).trigger('change');
    }

    $("#edit_order_patient_search").val(order.patient_id).trigger('change');
    $("#edit_order_type_option").val(location_option).trigger('change');
    $('.edit_order_patient_search_id').val(order.patient_name).trigger('change');
    $('.edit_order_patient_search_id').prop('disabled', true);
    $('#edit_order_patient').val(order.patient_id).trigger('change');
    $('.edit_old_product').val(order.product_id);

    $('#edit_available_quantity').val(order.quantity);
    $('#edit_total_price').val(order.total_price);
    $('#edit_quantity').val(orderDetail.quantity);
    $("#edit_payment_mode").val(order.payment_mode).trigger('change');
}


function refundOrder(url) {
    $("#product_list").empty();
    $("input").val('');
    $(".select2").val('').trigger("change");
    $("#refund_products").val('');
    $("#refund_products_price").val('');
    $("#product_list").empty();
    $("#refund_product_list").empty();
    $('#refund_total_product_price strong').text(0);
    refundProductId.length = 0;
    refundProductPrice.length = 0;

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
          
            if(response.status==false){
                toastr.error(response.message);
            }else{
                $("#modal_refund_order").modal("show");
                setRefundOrderData(response);
                
            }
            
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setRefundOrderData(response) {
    
    let order = response.data;
    
    let orderDetail = order.order_detail;
    let action = route('admin.orders.refund', { id: order.id });
    $("#modal_order_refund_form").attr("action", action);

    /* Order */
    let location_option = order.location_id != null ? 'in_branch' : 'in_warehouse';

    if (location_option == 'in_branch') {
        $('#refund_order_location').val(order.location_id).trigger('change');
        $('#refund_location_id').val(order.location_id);
    } else {
        $("#refund_order_location").val(order.warehouse_id).trigger('change');
    }
    $("#refund_order_location").prop('disabled', true);

    $("#refund_order_patient_search").val(order.patient_id).trigger('change');
    $('.refund_order_patient_search_id').val(order.patients.name).trigger('change');
    $('.refund_order_patient_search_id').prop('disabled', true);

    let productList = "";
    let loop = 0;

    orderDetail.forEach(function (value, index) {
    
        let product = value.product;
        loop++;
        if(value.quantity > 0){
            productList += setProductRefund(loop, product.id, product.name, product.sale_price, value.quantity);
       
        }
        
    });

    $('#refund_product_list').append(productList);
    $('#refund_total_product_price').text(order.total_price);
    $('#refund_payment_mode').val(order.payment_mode).trigger("change");
    $('#refund_payment_mode').prop("disabled", true);
}


function setProductRefund(id, product_id, product_name, price, stock) {
    return '<tr id="order_" class="order_product product_' + id + '"> <input type="hidden" name="product_id[]" value="' + product_id + '"> <input type="hidden" name="stock[]" value="' + stock + '"><input type="hidden" name="product_price[]" value="' + price + '"><input type="hidden" name="quantity[]" class="product_quantity_input" value="0" /> <input type="hidden" class="productPriceValue" value="' + price + '"> <td>' + product_name + '</td><td>' + price + '</td><td>' + stock + '</td><td><div class="number"><span class="minus">-</span><input type="number" class="quantity_input" value="0"/><span class="plus">+</span></div></td><td class="sub-total">0</td></tr>';
}
$('body').on('keyup', ".quantity_input", function () {
   
    var $input = $(this).parent().find('.quantity_input');
    var input2 = $(this).closest("tr").find('.product_quantity_input');
    var stock = $(this).closest("tr").find('input[name="stock[]"]').val();
    var count = parseInt($input.val()); 
    if (stock >= count) {
        $input.val(count);
        $input.change();
        input2.val(count).change();
        calculateTotal($(this));
       
    } else {
        toastr.error("This Product is out of stock");
    }
    return false;
});



function deleteIconRefund(id) {
    return '<a href="javascript:void(0);" onClick="deleteModelRefund(' + id + ')" class="btn btn-icon btn-light btn-hover-danger btn-sm"> <span class="svg-icon svg-icon-md svg-icon-danger"> <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"> <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <rect x="0" y="0" width="24" height="24"></rect> <path d="M6,8 L6,20.5 C6,21.3284271 6.67157288,22 7.5,22 L16.5,22 C17.3284271,22 18,21.3284271 18,20.5 L18,8 L6,8 Z" fill="#000000" fill-rule="nonzero"></path> <path d="M14,4.5 L14,4 C14,3.44771525 13.5522847,3 13,3 L11,3 C10.4477153,3 10,3.44771525 10,4 L10,4.5 L5.5,4.5 C5.22385763,4.5 5,4.72385763 5,5 L5,5.5 C5,5.77614237 5.22385763,6 5.5,6 L18.5,6 C18.7761424,6 19,5.77614237 19,5.5 L19,5 C19,4.72385763 18.7761424,4.5 18.5,4.5 L14,4.5 Z" fill="#000000" opacity="0.3"></path> </g> </svg> </span> </a>';
}

function deleteModelRefund(id) {
    let totalPrice = $('#refund_total_product_price').text();
    var price = $('.product_' + id).find('.productPriceValue').val();
    var productId = $('.product_' + id).find('.productId').val();
    refundProductId.push(productId);
    refundProductPrice.push(price);
 

    $('#refund_products').val(refundProductId);
    $('#refund_products_price').val(refundProductPrice);

    $('.product_' + id).remove();
    $('#refund_total_product_price').text(totalPrice - price);
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            order_id: $('#search_order_id').val(),
            patient_name: $(".order_patient_search_id").val(),
            patient_id: $("#order_patient_search").val(),
            product_id: $('#search_product_id').val(),
            location: $("#search_location").val(),
            location_type: $('#search_location_type').val(),
            created_by: $("#search_created_by").val(),
            updated_by: $("#search_updated_by").val(),
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
            order_id: '',
            patient_name: '',
            patient_id: '',
            location: '',
            location_type: '',
            product_id: '',
            created_by: '',
            updated_by: '',
            created_at: '',
            filter: 'filter_cancel',
        }
        clearProductSearchFilter();
        datatable.search(filters, 'search');
    });
}
function SelectEmployee(){
    const productList = $("#product_list").children();
   
    // Check if there are products in the list
    if (productList.length > 0) {
        // Prevent switching and reset the selection
        $("#sold_to").val($("#sold_to").data("previous"));
        toastr.error("You cannot change 'Sold To' after adding products. Please remove the products first.");
        return;
    }
   if($("#sold_to").val()=="employee")
   {
    $("#walkinDiv").hide();  
    $("#product_discount").text("");
  
    $("#discount").val("");
   
    $("#prescribedBy").hide();
    let url = route('admin.get-employees');
    let location_id = $("#add_order_location").val();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "POST",
        cache: false,
        data: {location_id: location_id},
        success: function (response) {
          
            if(response.status==false){
                toastr.error(response.message);
            }else{
                $("#patientDropDown").hide();
                $("#employeeDropDown").show();               
                 let employees = response.users;
                let emp_options = '<option value="">Select Employee</option>';
                Object.entries(employees).forEach(function (value, index) {
                    emp_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
                });
                $("#add_employee_id").html(emp_options);
            }
            
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
   }else if($("#sold_to").val()=="walkin"){
        $("#prescribedBy").hide();
        $("#patientDropDown").hide();
        $("#employeeDropDown").hide();  
        $("#walkinDiv").show();  
   }else{
    
    $("#product_discount").text("");
    $("#discount").val("");
    $("#walkinDiv").hide();  
   
   
        $("#prescribedBy").show();
        $("#patientDropDown").show();
        $("#employeeDropDown").hide();  
   }
}

function setFilters(filter_values, active_filters) {
    let centres = filter_values.centres;
    let users = filter_values.users;
    let products = filter_values.products;

    let centre_options = '<option value="">Select Centre</option>';
    let location = '<option value="">Select Product Location</option>';
    let product = '<option value="">Select Product</option>';
    let created_by = '<option value="">Select Created By</option>';
    let updated_by = '<option value="">Select Updated By</option>';
    let centres_selected, warehouse_selected;
    let FDM = '';

    if (Object.keys(centres).length == 1) {
        centres_selected = "selected"
        FDM = "fdm_select";
    
    } else {
        centres_selected = "";
        FDM = "";
    }
    /* Option Group */
    if (Object.keys(centres).length > 0) {
        location += '<optgroup value="branch" label="Branches">';
        Object.entries(centres).forEach(function (value, index) {
            if (active_filters.location_type == 'branch' && active_filters.location == value[0] || centres_selected == "selected") {
                location += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
            } else {
                location += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
                defaultValue = value[0];
            }
        });
        location += '</optgroup>';
    }

   

    Object.entries(centres).forEach(function (value, index) {
        centre_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });
   
    Object.entries(products).forEach(function (value, index) {
        product += '<option value="' + value[0] + '">' + value[1].name + '</option>';
    });

    Object.entries(users).forEach(function (value, index) {
        created_by += '<option value="' + value[0] + '">' + value[1].name + '</option>';
        updated_by += '<option value="' + value[0] + '">' + value[1].name + '</option>';
    });

    $("#search_location").html(location);
    $("#search_product_id").html(product);
    $("#search_created_by").html(created_by);
    $("#search_updated_by").html(updated_by);
    /* End Option Group */

    $("#add_order_location").html(location);
    $("#add_order_location").attr('role', FDM);

    /* Refund Order values */
    $("#refund_order_location").html(location);
    $("#refund_order_location").attr('role', FDM);
    $("#refund_order_product").html(product);

    /* List Filters values */
    $("#search_centre_id").html(centre_options);
    

    /* Active Filters */
    $("#search_order_id").val(active_filters.order_id);
    $("#search_patient_id").val(active_filters.patient_id);
    $("#search_product_id").val(active_filters.product_id);
    $("#search_created_by").val(active_filters.created_by);
    $("#search_updated_by").val(active_filters.updated_by);
    $("#date_range").val(active_filters.created_at);
}

function productSelect(product_id, id = null) {
    $.ajax({
        type: "GET",
        url: route('admin.transfer_products.get_products'),
        dataType: 'json',
        data: {
            product_id: product_id,
        },
        success: function (response) {
            let user_options = '<option value="">Select Doctor</option>';
            let product = response.data.products;
            let users = response.data.users;
           
            Object.entries(users).forEach(function (value, index) {
                user_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
            });
            $("#add_doctor_id").html(user_options);
            //if (products.length) {

                //products.forEach(function (product) {
                    $("#" + id + "_available_quantity").val(product.quantity);
                    $("#" + id + "_price").val(product.sale_price);
                    $("#" + id + "_total_price").val(product.sale_price);
                    $("#" + id + "_quantity").val(1);
                    $("#" + id + "_product_type").val(product.product_type);
                //});

            //}
        }
    });
}

function productSearch(from_id, id = null, type = null) {
    let from_key = $('#add_order_location_type').val();
    let html = '';
    if (from_id != '') {
        $.ajax({
            type: "GET",
            url: route('admin.transfer_products.fetch_products'),
            dataType: 'json',
            data: {
                from_key: from_key,
                from_id: from_id,
                type: type
            },
            success: function (response) {
                let products = response.data.products;
                let doctors = response.data.doctors;
               
                if (products.length) {
                    html = '<option value="">Select Product</option>';
                    products.forEach(function (product) {
                        let price = product.sale_price || 0;
                        let priceDisplay = price > 0 ? ' @ Rs.' + price : '';
                        html += '<option value="' + product.inventory_id + '" data-name="' + product.name + '" data-price="' + price + '" data-id="' + product.id + '" data-inventory_id="' + product.inventory_id + '" data-product_type="' + product.product_type + '">' + product.name + priceDisplay + ' - ' + product.available_quantity + ' available' + '</option>';
                    });
                } else {
                    html = '<option value="">No Product Found</option>';
                }
                let user_options = '';
                if (Object.keys(doctors).length) {
                    user_options = '<option value="">Select Doctor</option>';
                    Object.entries(doctors).forEach(function ([id, name]) {
                        user_options += '<option value="' + id + '">' + name + '</option>';
                    });
                } else {
                    user_options = '<option value="">No Doctor Found</option>';
                }

                $("#" + id + "_order_product").html(html);
                $("#add_doctor_ids").html(user_options);
            }
        });
    } else {
        html = '<option value="">No Product Found</option>';
        $("#" + id + "_order_product").html(html);
    }
    return false;
}

$("#reset-filters").on('click', function (e) {
    e.preventDefault();
    $("input").val("");
    $("select").val("");
});

$(document).ready(function () {
    patientSearch('order_patient_search_id');
    initProductSearch();

    $("#search_location").change(function () {
        var selected = $('select#search_location option:selected');
        let location = selected.closest('optgroup').attr('value');
        $('#search_location_type').val(location);
    });

    $("#add_order_location").change(function () {
        var selected = $('select#add_order_location option:selected');
        let location = selected.closest('optgroup').attr('value');
        let locationType = location == "branch" ? "location_id" : (location == "warehouse" ? "warehouse_id" : null);
        $('#add_order_location_type').val(locationType);
    });
    $(document).on("click", ".minus", function () {
        var $input = $(this).parent().find('.quantity_input');
        var input2 = $(this).closest("tr").find('.product_quantity_input');
        var count = parseInt($input.val()) - 1;
        count = count < 0 ? 0 : count;
        $input.val(count);
        $input.change();
        input2.val(count).change();
        calculateTotal($(this));
        return false;
    });
    $(document).on('click', '.plus', function () {
        var $input = $(this).parent().find('.quantity_input');
        var input2 = $(this).closest("tr").find('.product_quantity_input');
        var stock = $(this).closest("tr").find('input[name="stock[]"]').val();
        var count = parseInt($input.val()) + 1; 
        if (stock >= count) {
            $input.val(count);
            $input.change();
            input2.val(count).change();
            calculateTotal($(this));
       
        } else {
            toastr.error("This Product is out of stock");
        }
        return false;
    });

});

$("#add_new_order").on("click", function () {
    $("input").val('');
    $(".select2").val('').trigger("change");
    $("#product_list").empty();
    $("#refund_product_list").empty();
    $("#employeeDropDown").hide();
    $("#patientDropDown").show();
    $("#product_discount").text('');
    $('#sold_to').val('patient').trigger('change');
    var FDMVal = $('#add_order_location[role="fdm_select"] optgroup option:first-child').val();
    setTimeout(function () {
        $('#add_order_location[role="fdm_select"]').val(FDMVal).trigger('change');
    }, 600);
    $('#total_product_price strong').text(0);
});

function openInNewTab(id) {
    let url = route('admin.orders.invoice_pdf', { id: id, download: 'download' });
    var win = window.open(url, '_blank');
    win.focus();
    $("#modal_display_invoice").modal("hide");
    reInitTable();
}

function openNewTab(url) {
    var win = window.open(url, '_blank');
    win.focus();
    $("#modal_display_invoice").modal("hide");
    reInitTable();
}
