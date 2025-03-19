
<div id="revenue_report">
<h4>Inventory Stock Report</h4>

    <table  id="inv_table" class="display">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Location</th>
                <th>Total Stock</th>
                <th>Sold Stock</th>
                <th>Remaining Stock</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report as $product)
                @foreach ($product['locations'] as $location)
                    <tr>
                        <td>{{ $product['product_name'] }}</td>
                        <td>{{ $location['location_name'] }}</td>
                        <td>{{ $location['total_stock'] }}</td>
                        <td>{{ $location['sold_stock'] }}</td>
                        <td>{{ $location['remaining_stock'] }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>
