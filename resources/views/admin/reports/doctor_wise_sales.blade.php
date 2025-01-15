<table id="inv_table" class="display">
    <thead>
        <tr>
            <th>Doctor Name</th>
            <th>Product Name</th>
            <th>Total Quantity Sold</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($report as $doctorReport)
            @foreach ($doctorReport['product_sales'] as $productSale)
                <tr>
                    <td>{{ $doctorReport['doctor_name'] }}</td>
                    <td>{{ $productSale['product_name'] }}</td>
                    <td>{{ $productSale['total_quantity'] }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>