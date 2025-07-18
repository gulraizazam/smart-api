<div id="doctor_sales_report">
    <h4>Doctor Sales Report for Selected Location</h4>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Doctor</th>
                <th>Total Sold Amount</th>
                <th>Services Sold</th>
                <th>Package IDs</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groupedData as $report)
                <tr>
                    <td>{{ $report['doctor_name'] ?? 'Unknown' }}</td>
                    <td>{{ number_format($report['total_sold_amount'], 2) }}</td>
                    <td>{{ $report['services_sold'] }}</td>
                    <td>{{ $report['packages_sold'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No data available for this location.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
