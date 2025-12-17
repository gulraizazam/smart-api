var table_url = route('admin.transfer_products.datatable');

var table_columns = [
    {
        field: 'transfer_index',
        sortable: false,
        width: '40',
        title: '#',
    }, {
        field: 'from',
        title: 'Transfer From',
        width: 'auto',
        sortable: false,
    }, {
        field: 'to',
        title: 'Transfer To',
        width: 'auto',
        sortable: false,
    }, {
        field: 'name',
        title: 'Name',
        width: 'auto',
        sortable: false,
    }, {
        field: 'quantity',
        title: 'Quantity',
        width: 'auto',
        sortable: false,
    }, {
        field: 'transfer_date',
        title: 'Transfer Date',
        width: 'auto',
        sortable: false,
    }];


function actions(data) {
    let id = data.id;
    let url = route('admin.transfer_product.edit', { id: id });
    let delete_url = route('admin.transfer_product.destroy', { id: id });
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
        if (permissions.edit) {
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`'+ url + '`);" class="navi-link">\
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
            $("#modal_edit_transfer_products").modal("show");
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setEditData(response) {
    let transferProduct = response.data.product;
    let product_detail = response.data.product_details;
    let action = route('admin.transfer_product.update', { id: transferProduct.id, detail: transferProduct.product_detail_id });
    $("#modal_edit_transfer_products_form").attr("action", action);

    $("#edit_product_type_option_from").attr("disabled", true);
    $("#edit_product_type_option_to").attr("disabled", true);
    $("#edit_product_centre_from").attr("disabled", true);
    $("#edit_product_warehouse_from").attr("disabled", true);
    $("#edit_product_centre_to").attr("disabled", true);
    $("#edit_product_warehouse_to").attr("disabled", true);


    let location_from_option = transferProduct.from_location_id != null ? 'in_branch' : 'in_warehouse';
    let location_to_option = transferProduct.to_location_id != null ? 'in_branch' : 'in_warehouse';
    $("#edit_product_id").val(transferProduct.product_id);

    $("#edit_product_type_option_from").val(location_from_option).trigger('change');
    $("#edit_product_type_option_to").val(location_to_option).trigger('change');
    $('#edit_transfer_product').select2().val(transferProduct.product_id).trigger('change');
    if (location_from_option == 'in_branch') {
        $('.select_centre_from').show();
        $("#edit_product_centre_from").val(transferProduct.from_location_id).trigger('change');
    } else {
        $('.select_warehouse_from').show();
        $("#edit_product_warehouse_from").val(transferProduct.from_warehouse_id).trigger('change');
    }
    if (location_to_option == 'in_branch') {
        $('.select_centre_to').show();
        $("#edit_product_centre_to").val(transferProduct.to_location_id).trigger('change');
    } else {
        $('.select_warehouse_to').show();
        $("#edit_product_warehouse_to").val(transferProduct.to_warehouse_id).trigger('change');
    }

    productSelect(transferProduct.product_id, 'edit');
    $('#edit_product_quantity').val(transferProduct.quantity);
    $('#edit_transfer_date').val(transferProduct.transfer_date);
    $('#edit_child_product_id').val(transferProduct.child_product_id);
    $('#edit_product_detail_id').val(transferProduct.product_detail_id);
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            name: $("#search_name").val(),
            location_from: $('#search_location_from').val(),
            location_to: $('#search_location_to').val(),
            transfer_from: $("#search_transfer_from").val(),
            transfer_to: $("#search_transfer_to").val(),
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
            location_from: '',
            location_to: '',
            transfer_from: '',
            transfer_to: '',
            created_at: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    let centres = filter_values.centres;
    let warehouses = filter_values.warehouse;
    let centre_options = '<option value="">Select Centre</option>';
    let warehouse_options = '<option value="">Select Warehouse</option>';
    let transferFrom = '<option value="">Select Transfer From</option>';
    let transferTo = '<option value="">Select Transfer To</option>';

    Object.entries(centres).forEach(function (value, index) {
        centre_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });
    Object.entries(warehouses).forEach(function (value, index) {
        warehouse_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    /* Option Group */
    transferFrom += '<optgroup value="branch" label="Branches">';
    Object.entries(centres).forEach(function (value, index) {
        transferFrom += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
    });
    transferFrom += '</optgroup>';
    transferFrom += '<optgroup value="warehouse" label="Warehouse">';
    Object.entries(warehouses).forEach(function (value, index) {
        transferFrom += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
    });
    transferFrom += '</optgroup>';
    /* End Option Group */

    /* Option Group */
    transferTo += '<optgroup value="branch" label="Branches">';
    Object.entries(centres).forEach(function (value, index) {
        transferTo += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
    });
    transferTo += '</optgroup>';
    transferTo += '<optgroup value="warehouse" label="Warehouse">';
    Object.entries(warehouses).forEach(function (value, index) {
        transferTo += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
    });
    transferTo += '</optgroup>';
    /* End Option Group */

    /* List Filters values */
    $("#search_transfer_from").html(transferFrom);
    $("#search_transfer_to").html(transferTo);

    $("#search_centre_id").html(centre_options);
    $("#search_warehouse_id").html(warehouse_options);

    $('#edit_product_centre_from').html(centre_options);
    $('#edit_product_warehouse_from').html(warehouse_options);

    $('#edit_product_centre_to').html(centre_options);
    $('#edit_product_warehouse_to').html(warehouse_options);

    /* Add product values */
    $("#add_product_centre_from").html(centre_options);
    $("#add_product_warehouse_from").html(warehouse_options);
    $("#add_product_centre_to").html(centre_options);
    $("#add_product_warehouse_to").html(warehouse_options);

    /* Edit Product values */
    $("#edit_product_centre").html(centre_options);
    $("#edit_product_warehouse").html(warehouse_options);

    /* Active Filters */
    $("#search_name").val(active_filters.name);
    $("#search_location_from").val(active_filters.location_from);
    $("#search_location_to").val(active_filters.location_to);
    $("#search_transfer_from").val(active_filters.transfer_from);
    $("#search_transfer_to").val(active_filters.transfer_to);
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
            
            let products = response.data.products;
            if (products.length) {

                products.forEach(function (product) {
                    $("#" + id + "_total_stock").val(product.quantity);
                });

            }
        }
    });
}
function productSelectTransfer(product_id, id = null) {
  var warehouse_id;
  var location_id;
    if($("#add_product_type_option_from").val() == 'in_warehouse'){
        warehouse_id = $("#add_product_warehouse_from").val();
      
        
    }else{
        location_id = $("#add_product_centre_from").val();
       
    }

    $.ajax({
        type: "GET",
        url: route('admin.transfer_products.get_products'),
        dataType: 'json',
        data: {
            product_id: product_id,
            location_id:location_id,
            warehouse_id:warehouse_id,
        },
        success: function (response) {
        
            let products = response.data.products;
            
            $("#add_total_stock").val(products.quantity);
            
            let warehouse_options = '<option value="">Select Warehouse</option>';
            var warehousesArray = response.data.warehouses;
           
            for(var i = 0; i < warehousesArray.length; i++){
                warehouse_options += '<option value="' + warehousesArray[i].id + '">' + warehousesArray[i].name + '</option>';
            }
            
            $("#add_product_warehouse_to").html(warehouse_options).select2();
            
        }
    });
}
function formRest() {
    $('#modal_create_order_form')[0].reset();
    $('.select2').val(null).trigger('change');
    $("#add_product_type_option_from").val("");
    $("#add_product_type_option_to").val("");
    $("#add_transfer_product").val("").trigger("change");
    $('.select_centre_from').hide();
    $('.select_warehouse_from').hide();
    $('.select_centre_to').hide();
    $('.select_warehouse_to').hide();
   
}

$("#add_product_p").on("click", function(){
    $("input").val('');
    $("select").val('');
    $('#add_transfer_date').val(moment().format('YYYY-MM-DD'));
});

$(document).ready(function () {
    $('#add_product_type_option_from').on('change', function () {
        if (this.value == 'in_warehouse') {
            $('.select_centre_from').hide();
            $('.select_warehouse_from').show();
            $('#add_product_centre_from').val('');
            $("#to_branch").show();
        } else if (this.value == 'in_branch') {
            $('.select_centre_from').show();
            $('.select_warehouse_from').hide();
            $('#add_product_warehouse_from').val('');
            $("#to_branch").hide();
        } else {
            $('.select_centre_from').hide();
            $('.select_warehouse_from').hide();
            $('#add_product_warehouse_from').val('');
            $('#add_product_centre_from').val('');
        }
    });
    $('#add_product_type_option_to').on('change', function () {
        if (this.value == 'in_warehouse') {
            $('.select_centre_to').hide();
            $('.select_warehouse_to').show();
            $('#add_product_centre_to').val('');
        } else if (this.value == 'in_branch') {
            $('.select_warehouse_to').hide();
            $('.select_centre_to').show();
            $('#add_product_warehouse_to').val('');
        } else {
            $('.select_centre_to').hide();
            $('.select_warehouse_to').hide();
            $('#add_product_warehouse_to').val('');
            $('#add_product_centre_to').val('');
        }
    });

    $('#edit_product_type_option_from').on('change', function () {
        if (this.value == 'in_warehouse') {
            $('.select_centre_from').hide();
            $('.select_warehouse_from').show();
        } else if (this.value == 'in_branch') {
            $('.select_centre_from').show();
            $('.select_warehouse_from').hide();
        } else {
            $('.select_centre_from').hide();
            $('.select_warehouse_from').hide();
        }
    });
    $('#edit_product_type_option_to').on('change', function () {
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


    $("#search_transfer_from").change(function () {
        var selected = $('select#search_transfer_from option:selected');
        let location = selected.closest('optgroup').attr('value');
        $('#search_location_from').val(location);
    
    });

    $("#search_transfer_to").change(function () {
        let selected = $('select#search_transfer_to option:selected');
        let location = selected.closest('optgroup').attr('value');
        $('#search_location_to').val(location);

    });

})