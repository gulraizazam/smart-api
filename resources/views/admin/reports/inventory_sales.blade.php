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
            <th>Patient ID</th>
            <th>Patient Name</th>
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
                <td>{{$report['patient_id']}}</td>
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

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Payment Type</th>
            <th>Total Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Cash Total</td>
            <td id="cash-total">{{ $cashTotal ?? 0 }}</td>
        </tr>
        <tr>
            <td>Card Total</td>
            <td id="card-total">{{ $cardTotal ?? 0 }}</td>
        </tr>
        <tr>
            <td>Bank Transfer Total</td>
            <td id="bank-transfer-total">{{ $bankTransferTotal ?? 0 }}</td>
        </tr>
        <tr>
            <th>Overall Total</th>
            <th id="overall-total">{{ $overallTotal ?? 0 }}</th>
        </tr>
    </tbody>
</table>