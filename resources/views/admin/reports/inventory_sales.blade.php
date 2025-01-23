<div>
    <h2>Sales Report</h2>  
</div>

<table  class="table">
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Location</th>
            <th>Product Name</th>
            <th>Quantity</th>
            <th>Purchased By</th>
            <th>Order Date</th>
            <th>Payment Mode</th>
            <th>Order Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($reportData as $report)
            <tr>
                <td>{{$report['order_id']}}</td>
                <td>{{$report['location_name']}}</td>
                <td>{{$report['product_name']}}</td>
                <td>{{$report['quantity']}}</td>
                <td>{{$report['purchased_by']}}</td>
                <td>{{$report['order_date']}}</td>
                <td>
                    <span class="badge badge-success">
                        @if($report['payment_mode'] == 1)
                            Cash
                        @elseif($report['payment_mode'] == 2)
                            Card
                        @else
                            Bank Transfer
                        @endif
                      
                    </span>
                    </td>
                <td>{{$report['total_revenue']}}</td>
            </tr>
        @endforeach
        <!-- Report data will be populated here -->
    </tbody>
</table>

<h3>Totals:</h3>
<ul>
    <li>Cash Total: <span id="cash-total">{{ $cashTotal ?? 0 }}</span></li>
    <li>Card Total: <span id="card-total">{{ $cardTotal ?? 0 }}</span></li>
    <li>Bank Transfer Total: <span id="bank-transfer-total">{{ $bankTransferTotal ?? 0 }}</span></li>
    <li>Overall Total: <span id="overall-total">{{ $overallTotal ?? 0 }}</span></li>
</ul>