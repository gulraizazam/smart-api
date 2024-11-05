<div id="incentive_content">
    <table id="incentive_table" class="table table-striped">
        <thead>
            <tr>
                <th>Patient Name</th>
                <th>Appointment Date</th>
                <th>Payment Date</th>
                <th>Cash Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($incentives as $appointment)
                @foreach($appointment->packageadvance as $advance)
                    <tr>
                        <td>{{ $appointment->user->name ?? 'N/A' }}</td> <!-- Display patient name -->
                        <td>{{ \Carbon\Carbon::parse($appointment->scheduled_date)->format('d M Y') }}</td> <!-- Appointment date formatted -->
                        <td>{{ \Carbon\Carbon::parse($advance->created_at)->format('d M Y') }}</td> <!-- Payment date formatted -->
                        <td>{{ number_format($advance->cash_amount, 2) }}</td> <!-- Cash amount formatted -->
                    </tr>
                @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"><strong>Total Revenue for Selected Range</strong></td>
                <td>{{ number_format($totalRevenue, 2) }}</td> <!-- Total revenue -->
            </tr>
            <tr>
                <td colspan="3"><strong>Month-wise Revenue</strong></td>
                <td></td>
            </tr>
            @foreach($monthWiseRevenue as $month => $amount)
                <tr>
                    <td colspan="3">{{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</td> <!-- Month -->
                    <td>{{ number_format($amount, 2) }}</td> <!-- Month-wise cash amount -->
                </tr>
            @endforeach
            <tr>
                <td colspan="3"><strong>Net Revenue (After Refunds)</strong></td>
                <td>{{ number_format($netRevenue, 2) }}</td> <!-- Net revenue -->
            </tr>
        </tfoot>
    </table>
</div>
