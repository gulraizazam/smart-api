<div id="incentive_content">
    <table id="incentive_table" class="table table-striped">
        <thead>
            <tr>
                <th>Patient ID</th>
                <th>Patient Name</th>
                <th>Appointment Date</th>
                <th>Payment Date</th>
                <th>Cash Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($packageAdvances as $advance)
                <tr>
                    <td>{{ $advance->appointment->patient->id ?? 'N/A' }}</td> <!-- Appointment ID -->
                    <td>{{ $advance->appointment->patient->name ?? 'N/A' }}</td> <!-- Patient Name -->
                    <td>{{ \Carbon\Carbon::parse($advance->appointment->scheduled_date)->format('d M Y') }}</td> <!-- Appointment Date -->
                    <td>{{ \Carbon\Carbon::parse($advance->created_at)->format('d M Y') }}</td> <!-- Payment Date -->
                    <td>{{ number_format($advance->cash_amount, 2) }}</td> <!-- Cash Amount -->
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4"><strong>Total Amount for Selected Range</strong></td>
                <td>{{ number_format($packageAdvances->sum('cash_amount'), 2) }}</td> <!-- Total cash amount for selected range -->
            </tr>
        </tfoot>
    </table>
</div>
