<div id="incentive_content">
    <table id="incentive_table" class="table table-striped">
        <thead>
            <tr>
                <th>Appointment ID</th>
                <th>Patient Name</th>
                <th>Appointment Date</th>
                <th>Payment Date</th>
                <th>Cash Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($packageAdvances as $advance)
                <tr>
                    <td>{{ $advance->appointment_id }}</td>
                    <td>{{ $advance->appointment->user->name ?? 'N/A' }}</td>
                    <td>{{ \Carbon\Carbon::parse($advance->appointment->scheduled_date)->format('d M Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($advance->created_at)->format('d M Y') }}</td>
                    <td>{{ number_format($advance->total_cash_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4"><strong>Total Amount for Selected Range</strong></td>
                <td>{{ number_format($currentRangeTotal, 2) }}</td> <!-- Total cash amount for selected range -->
            </tr>
        </tfoot>
    </table>

    <h3>Other Months Total</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Month</th>
                <th>Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monthWiseTotals as $yearMonth => $total)
                <tr>
                    <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $yearMonth)->format('F Y') }}</td>
                    <td>{{ number_format($total->total_cash_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
