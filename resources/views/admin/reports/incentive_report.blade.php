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
                        <td>{{ $appointment->scheduled_date }}</td> <!-- Appointment schedule date -->
                        <td>{{ $advance->created_at->format('Y-m-d') }}</td> <!-- Payment date -->
                        <td>{{ $advance->cash_amount }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"><strong>Total Incentive</strong></td>
                <td>{{ $totalIncentive }}</td>
            </tr>
        </tfoot>
    </table>
</div>
