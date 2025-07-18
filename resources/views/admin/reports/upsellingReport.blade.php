<div id="doctor_sales_report">
    <h4>Doctor Sales Report for Selected Location</h4>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Doctor</th>
                <th>Total Sold Amount</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData as $report)
                <tr>
                    <td>{{ $report->doctor_name ?? 'Unknown' }}</td>
                    <td>{{ number_format($report->total_sold_amount, 2) }}</td>
                    <td>
                        <a href="{{ route('doctor.upselling.detail', $report->doctor_id) }}"
                           class="btn btn-primary btn-sm">
                            View Details
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">No data available for this location.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
