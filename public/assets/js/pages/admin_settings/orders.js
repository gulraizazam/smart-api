var table_url = route('admin.orders.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: '80',
        title: 'Order ID'
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
            return displayProducts(data.orders);
        }
    }, {
        field: 'orders.quantity',
        title: 'Quantity',
        sortable: false,
        width: 'auto',
        template: function (data) {
            return sumProductsQuantity(data.orders);
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
            return '<span class="badge badge-success">' + data.payment_mode + '</span>';
        }
    }, {
        field: 'status',
        title: 'Status',
        width: 80,
        template: function (data) {
            if (data.status == "pending") {
                return '<span class="badge badge-warning">' + data.status + '</span>';
            } else {
                return '<span class="badge badge-primary">' + data.status + '</span>';
            }
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
    }
];

function actions(data) {
    let id = data.id;
    let edit_url = route('admin.orders.edit', { id: id });
    let refund_url = route('admin.orders.refund.detail', { id: id });
    let delete_url = route('admin.orders.destroy', { id: id });

    let invoice_url = route('admin.orders.invoiceDisplay', { id: id });

    if (permissions.refund) {
        let actions = '<div class="dropdown dropdown-inline action-dots">'

        if (data.status == "pending") {
            actions += '<a title="Create Invoice" href="javascript:void(0);" onclick="createOrderInvoice(`' + invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-warning btn-sm">\
                            <span class="navi-icon"><i class="la la-file"></i></span>\
                        </a>';
        } else {
            actions += '<a title="View Invoice" href="javascript:void(0);" onclick="createOrderInvoice(`' + invoice_url + '`);" class="d-lg-inline-flex d-none btn btn-icon btn-info btn-sm">\
            <span class="navi-icon"><i class="la la-file-invoice-dollar"></i></span>\
                        </a>';
        }
        actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
        if (permissions.edit) {
            actions += '<li class="navi-item">\
                            <a href="javascript:void(0);" onclick="editRow(`'+ edit_url + '`);" class="navi-link">\
                                <span class="navi-icon"><i class="la la-pencil"></i></span>\
                                <span class="navi-text">Edit</span>\
                            </a>\
                        </li>';
        }
        if (permissions.refund) {
            actions += '<li class="navi-item">\
                            <a href="javascript:void(0);" onclick="refundOrder(`' + refund_url + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-plus"></i></span>\
                            <span class="navi-text">Refund Order</span>\
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
function displayProducts(orders) {
    let productHtml = '';
    if (orders != null) {
        for (let order = 0; order < orders.length; order++) {
            productHtml += '<span style="margin-bottom: 3px;" class="badge badge-info">' + orders[order].product.name + '</span><br/>';
        }
    }
    return productHtml;
}

function sumProductsQuantity(orders) {
    let quantitySum = 0;
    if (orders != null) {
        for (let order = 0; order < orders.length; order++) {
            quantitySum += orders[order].quantity;
        }
    }
    return quantitySum;
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
    let orderDetail = order.orders;
    let products = response.data.products;
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
    $('.edit_old_product').val(orderDetail[0].product_id);

    $('#edit_available_quantity').val(order.quantity);
    $('#edit_total_price').val(order.total_price);
    $('#edit_quantity').val(orderDetail[0].quantity);
    $("#edit_payment_mode").val(order.payment_mode).trigger('change');
}


function refundOrder(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_refund_order").modal("show");
            setRefundOrderData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setRefundOrderData(response) {
    let order = response.data;
    let orderDetail = order.orders;
    let action = route('admin.orders.refund', { id: order.id });
    $("#modal_order_refund_form").attr("action", action);

    /* Order */
    let location_option = order.location_id != null ? 'in_branch' : 'in_warehouse';

    if (location_option == 'in_branch') {
        $('.select_centre').show();
        $("#refund_order_centre").val(order.location_id).trigger('change');
    } else {
        $('.select_warehouse').show();
        $("#refund_order_warehouse").val(order.warehouse_id).trigger('change');
    }

    $("#refund_order_patient_search").val(order.patient_id).trigger('change');
    $("#refund_order_type_option").val(location_option).trigger('change');
    $('.refund_order_patient_search_id').val(order.patients.name).trigger('change');
    $('.refund_order_patient_search_id').prop('disabled', true);
    $('#refund_order_patient').val(order.patient_id).trigger('change');
    $('.edit_old_product').val(orderDetail[0].product_id);

    $('#refund_available_quantity').val(order.quantity);
    $('#refund_total_price').val(order.total_price);
    $('#refund_quantity').val(orderDetail[0].quantity);
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
        //$(".order_patient_search_id").val("")
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    let centres = filter_values.centres;
    let warehouses = filter_values.warehouse;
    let users = filter_values.users;
    let products = filter_values.products;

    let centre_options = '<option value="">Select Centre</option>';
    let warehouse_options = '<option value="">Select Warehouse</option>';
    let location = '<option value="">Select Product Location</option>';
    let product = '<option value="">Select Product</option>';
    let created_by = '<option value="">Select Created By</option>';
    let updated_by = '<option value="">Select Updated By</option>';

    /* Option Group */
    location += '<optgroup value="branch" label="Branches">';
    Object.entries(centres).forEach(function (value, index) {
        location += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
    });
    location += '</optgroup>';
    location += '<optgroup value="warehouse" label="Warehouse">';
    Object.entries(warehouses).forEach(function (value, index) {
        location += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
    });
    location += '</optgroup>';
    /* End Option Group */

    Object.entries(centres).forEach(function (value, index) {
        centre_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });
    Object.entries(warehouses).forEach(function (value, index) {
        warehouse_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
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
    $("#add_order_centre").html(centre_options);
    $("#add_order_warehouse").html(warehouse_options);

    /* Edit Option*/
    $("#edit_order_centre").html(centre_options);
    $("#edit_order_warehouse").html(warehouse_options);

    /* Refund Order values */
    $("#refund_order_centre").html(centre_options);
    $("#refund_order_warehouse").html(warehouse_options);
    $("#refund_order_product").html(product);

    /* List Filters values */
    $("#search_centre_id").html(centre_options);
    $("#search_warehouse_id").html(warehouse_options);

    /* Active Filters */
    $("#search_order_id").val(active_filters.order_id);
    $("#search_patient_id").val(active_filters.patient_id);
    $("#search_product_id").val(active_filters.product_id);
    $("#search_location").html(active_filters.location);
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
            let products = response.data.products;
            if (products.length) {

                products.forEach(function (product) {
                    $("#" + id + "_available_quantity").val(product.quantity);
                    $("#" + id + "_price").val(product.sale_price);
                    $("#" + id + "_total_price").val(product.sale_price);
                    $("#" + id + "_quantity").val(1);
                    $("#" + id + "_product_type").val(product.product_type);
                });

            }
        }
    });
}

function productSearch(from_id, from_key, id = null, type = null) {
    if (from_id != '') {
        $.ajax({
            type: "GET",
            url: route('admin.transfer_products.get_products'),
            dataType: 'json',
            data: {
                from_key: from_key,
                from_id: from_id,
                type: type
            },
            success: function (response) {
                let html = '';
                let products = response.data.products;

                if (products.length) {
                    html = '<option value="">Select Product</option>';
                    products.forEach(function (product) {
                        let oldProduct = $('.edit_old_product').val();

                        if (product.id == oldProduct) {
                            html += '<option value="' + product.id + '" selected>' + product.name + '</option>';
                            $('#edit_price').val(product.sale_price);
                            $('#refund_price').val(product.sale_price);
                            $("#refund_available_quantity").val(product.quantity);
                        } else {
                            html += '<option value="' + product.id + '">' + product.name + '</option>';
                        }
                    });
                } else {
                    html = '<option value="">No Product Found</option>';
                }
                $("#" + id + "_order_product").html(html);
            }
        });
    }
    return false;
}

function formRest() {
    $(".order_patient_search_id").val("");
    $("#modal_create_order_form").find('form').trigger('reset');
    $("#modal_create_order_form").find('.order_patient_search_id').empty();
    $("#modal_create_order_form").find('.order_patient_search_id').attr('disabled', false);
    $('.select_centre').hide();
    $('.select_warehouse').hide();
}

$(document).ready(function () {
    patientSearch('order_patient_search_id');
    $('#add_order_type_option').on('change', function () {
        if (this.value == 'in_warehouse') {
            $('.select_centre').hide();
            $('.select_warehouse').show();
        } else if (this.value == 'in_branch') {
            $('.select_centre').show();
            $('.select_warehouse').hide();
        } else {
            $('.select_centre').hide();
            $('.select_warehouse').hide();
        }
    });

    $('#edit_order_type_option').on('change', function () {
        if (this.value == 'in_warehouse') {
            $('.select_centre').hide();
            $('.select_warehouse').show();
        } else if (this.value == 'in_branch') {
            $('.select_centre').show();
            $('.select_warehouse').hide();
        } else {
            $('.select_centre').hide();
            $('.select_warehouse').hide();
        }
    });

    $("#add_quantity").on('keyup', function () {
        if ($("#add_quantity").val() > 0) {
            let total_price = $("#add_quantity").val() * $("#add_price").val();
            $("#add_total_price").val(total_price);

        } else {
            $("#add_total_price").val(0);
            $("#add_quantity").val();
        }
    });

    $("#edit_quantity").on('keyup', function () {
        if ($("#edit_quantity").val() > 0) {
            let total_price = $("#edit_quantity").val() * $("#edit_price").val();
            $("#edit_total_price").val(total_price);

        } else {
            $("#edit_total_price").val(0);
            $("#edit_quantity").val();
        }
    });

    $("#search_location").change(function () {
        var selected = $('select#search_location option:selected');
        let location = selected.closest('optgroup').attr('value');
        $('#search_location_type').val(location);
    });

});

function openInNewTab(url) {
    var win = window.open(url, '_blank');
    win.focus();
    $("#modal_display_invoice").modal("hide");
    reInitTable();
}
