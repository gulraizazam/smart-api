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
                <td colspan="3"><strong>Total Incentive for Selected Range</strong></td>
                <td>{{ number_format($totalIncentive, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3"><strong>Previous Total Incentive (Before Selected Range)</strong></td>
                <td>{{ number_format($previousTotalIncentive, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3"><strong>Net Revenue (After Refunds)</strong></td>
                <td>{{ number_format($netRevenue, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
    $(document).ready(function() {
        $("#incentive_table").DataTable({
            dom: 'Bfrtip',
            buttons: [
                'excelHtml5',
                'csvHtml5',
                'pdfHtml5'
            ],
            "ordering": false
        });
    });
</script>
