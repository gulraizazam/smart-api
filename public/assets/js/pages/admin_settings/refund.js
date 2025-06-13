var table_url = route('admin.orders.refund.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: '80',
        title: 'ID'
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
           
            return displayProducts(data.orderrefunddetails);
        }
    }, {
        field: 'orders.quantity',
        title: 'Quantity',
        sortable: false,
        width: 'auto',
        template: function (data) {
            return data.quantity;
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
    }
];


function actions(data) {

    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let detele = route('admin.orders.destroy', { id: id });

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
                        <a href="javascript:void(0);" onclick="deleteRow(`' + detele + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-trash"></i></span>\
                        <span class="navi-text">Delete</span>\
                        </a>\
                     </li>';
        actions += '</ul>\
        </div>\
    </div>';

        return actions;
    }
    return '';
}

$("#reset-filters").on("click", function () {
    $("input").val('');
});

function sumProductsQuantity(orders) {
    let quantitySum = 0;
    if (orders != null) {
        orders.forEach(function (value, index) {
            quantitySum += value.quantity;
        });
    }
    return quantitySum;
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
function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            order_id: $('#search_order_id').val(),
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
            patient_id: '',
            location: '',
            location_type: '',
            product_id: '',
            created_by: '',
            updated_by: '',
            created_at: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    let centres = filter_values.centres;
    let warehouses = filter_values.warehouse;
    let users = filter_values.users;
    let products = filter_values.products;

    let location = '<option value="">Select Product Location</option>';
    let product = '<option value="">Select Product</option>';
    let created_by = '<option value="">Select Created By</option>';
    let updated_by = '<option value="">Select Updated By</option>';

    /* Option Group */
    location += '<optgroup value="branch" label="Branches">';
    Object.entries(centres).forEach(function (value, index) {
        if (active_filters.location_type == 'branch' && active_filters.location == value[0]) {
            location += '<option value="' + value[0] + '" selected>&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
        } else {
            location += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
        }

    });
    location += '</optgroup>';
    location += '<optgroup value="warehouse" label="Warehouse">';
    Object.entries(warehouses).forEach(function (value, index) {
        if (active_filters.location_type == 'Warehouse' && active_filters.location == value[0]) {
            location += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
        } else {
            location += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1] + '</option>';
        }
    });
    location += '</optgroup>';
    /* End Option Group */

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

    /* Active Filters */
    $("#search_order_id").html(active_filters.order_id);
    $("#search_patient_id").val(active_filters.patient_id);
    $("#search_product_id").val(active_filters.product_id);
    //$("#search_location").html(active_filters.location);
    $("#search_created_by").val(active_filters.created_by);
    $("#search_updated_by").val(active_filters.updated_by);
    $("#date_range").val(active_filters.created_at);
}


$(document).ready(function () {
    patientSearch('order_patient_search_id');
    $("#search_location").change(function () {
        var selected = $('select#search_location option:selected');
        let location = selected.closest('optgroup').attr('value');
        $('#search_location_type').val(location);
    });

});

