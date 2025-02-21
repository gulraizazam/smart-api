<table id="inv_table" class="display">
    <thead>
        <tr>
            <th>Doctor Name</th>
            <th>Product Name</th>
            <th>Total Quantity Sold</th>
            <th>Sub Total</th>
            <th>Order Date</th>

        </tr>
    </thead>
    <tbody>
        @foreach ($report as $doctorReport)
            @foreach ($doctorReport['product_sales'] as $productSale)
                <tr>
                    <td>{{ $doctorReport['doctor_name'] }}</td>
                    <td>{{ $productSale['product_name'] }}</td>
                    <td>{{ $productSale['total_quantity'] }}</td>
                    <td>{{ $productSale['subtotal'] }}</td>
                    <td>
                    {{ implode(', ', $productSale['order_dates']->toArray()) }}
                </td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
<h3>Overall Total: <span id="overall-total">{{$overallTotal ?? 0}}</span></h3>