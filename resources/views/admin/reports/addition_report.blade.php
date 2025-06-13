
<div id="revenue_report">
<h4>Products Addition Report</h4>

    <table  id="inv_table" class="display">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Location</th>
                <th>Quantity</th>
                <th>Date</th>
                
            </tr>
        </thead>
        <tbody>
            @foreach ($stocks as $product)
                
                    <tr>
                        <td>{{ $product->product_name }}</td>
                        <td>{{ $product->location_name }}</td>
                        <td>{{ $product->quantity }}</td>
                        <td>{{ $product->created_at }}</td>
                        
                    </tr>
                
            @endforeach
        </tbody>
    </table>
</div>
