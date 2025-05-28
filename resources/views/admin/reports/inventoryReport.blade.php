<div id="revenue_report">
    <h4>Inventory Stock Report</h4>

    <table id="inv_table" class="display">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Opening Stock</th>
                <th>Addition in Range</th>
                <th>Total Stock</th>
                <th>Sold Stock</th>
                <th>Remaining Stock</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report as $product)
                <tr>
                    <td>{{ $product['product_name'] }}</td>
                    <td>{{ $product['opening_stock'] }}</td>
                    <td>{{ $product['addition'] }}</td>
                    <td>{{ $product['total_stock'] }}</td>
                    <td>{{ $product['sold_stock'] }}</td>
                    <td>{{ $product['remaining_stock'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
