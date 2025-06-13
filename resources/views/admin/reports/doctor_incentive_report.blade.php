<div id="revenue_report">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Total Amount In Given Range</th>
                <th>Total Conversion Amount</th>
                <th>Difference</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format($totalDoctorRevenue, 2) }}</td>
                <td>{{ number_format($totalCashAmount, 2) }}</td>
                <td>{{ number_format($diff, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <h3>Patient Payments</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Patient ID</th>
                <th>Patient Name</th>
                <th>Appointment Date</th>
                <th>Payment Date</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($patients as $patient)
                <tr>
                    <td>{{ $patient->patient_id }}</td>
                    <td>{{ $patient->patient_name }}</td>
                    <td>{{ $patient->scheduled_date }}</td>
                    <td>{{ date('d-m-Y', strtotime($patient->payment_date)) }}</td>
                    <td>{{ number_format($patient->cash_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
