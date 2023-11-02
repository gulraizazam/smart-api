function filter() {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.inventory_report_result'),
        type: "POST",
        cache: false,
        success: function (response) {
            setFilterData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setFilterData(response) {
    let warehouses = response.data.warehouse;
    let centres = response.data.centres;

    let location = '<option value="">Select Transfer From</option>';
    /* Option Group */
    location += '<optgroup value="branch" label="Branches">';
    Object.entries(centres).forEach(function (value, index) {
        location += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1].name + '</option>';
    });
    location += '</optgroup>';
    location += '<optgroup value="warehouse" label="Warehouse">';
    Object.entries(warehouses).forEach(function (value, index) {
        location += '<option value="' + value[0] + '">&nbsp;&nbsp;&nbsp; ' + value[1].name + '</option>';
    });
    location += '</optgroup>';
    $("#search_location").html(location);

}

function submitFilter() {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.reports.inventory_report_stock'),
        type: "POST",
        cache: false,
        data: $("#search_inventory_report_form").serialize(),
        success: function (response) {
            stockReport(response);
        },
        error: function (response, xhr, ajaxOptions, thrownError) {
            toastr.error(response.responseJSON.message);
        }
    });
}

function stockReport(response) {
    let products = response.data.products;
    let row;
    let totalRow;
    $("#datatable_stock_report").show();
    $('#stock_table_body').empty();
    $('#stock_table_total').empty();

    if (products.length === 0) {
        row = '<tr>' +
            '<td colspan="8">Data Not Found</td>' +
            '</tr>';
        $('#stock_table_body').append(row);
    } else {
        let purchaseTotal = 0;
        let saleTotal = 0;
        $.each(products, function (index, item) {
            let locationArea = item.location_id != null ? 'Branch' : 'Warehouse';
            purchaseTotal += item.product_detail_sum_total_purchase_price;
            saleTotal += item.order_sale_price;
            row = '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + item.name + '</td>' +
                '<td>' + item.location + ', <strong>(' + locationArea + ')</strong></td>' +
                '<td>' + item.product_detail_sum_quantity + '</td>' +
                '<td>' + item.order_quantity + '</td>' +
                '<td>' + item.transfer_product_sum_quantity + '</td>' +
                '<td>' + item.available_stock + '</td>' +
                '<td> Rs ' + item.product_detail_sum_total_purchase_price + '</td>' +
                '<td> Rs ' + item.order_sale_price + '</td>' +
                '</tr>';
            $('#stock_table_body').append(row);
        });
        totalRow = '<tr>' +
            '<th>Total Purchase</th>' +
            '<td>' + purchaseTotal + '</td>' +
            '</tr>' +
            '<tr>' +
            '<th>Total Sale</th>' +
            '<td>' + saleTotal + '</td>' +
            '</tr>';
        $('#stock_table_total').append(totalRow);
    }
}

$(document).ready(function () {
    $("#datatable_stock_report").hide();
    filter();
    $("#search_location").change(function () {
        var selected = $('select#search_location option:selected');
        let location = selected.closest('optgroup').attr('value');
        $('#search_location_type').val(location);
    });


    $("#apply-filters").on('click', function (e) {
        e.preventDefault();
        submitFilter();
    });

})
