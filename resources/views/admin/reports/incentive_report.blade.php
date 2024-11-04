<div id="incentive_content">
    <table id="incentive_table" class="table table-striped">
        <thead>
            <tr>
                <th>Appointment Date</th>
                <th>Cash Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($incentives as $appointment)
                @foreach($appointment->packageadvance as $advance)
                    <tr>
                        <td>{{ $appointment->appointment_date }}</td>
                        <td>{{ $advance->cash_amount }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td><strong>Total Incentive</strong></td>
                <td>{{ $totalIncentive }}</td>
            </tr>
        </tfoot>
    </table>
</div>
