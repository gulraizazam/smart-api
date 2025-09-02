<div id="consultant_revenue_report">
    <h4>Consultant Revenue Report for Selected Location</h4>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Consultant</th>
                <th>Total Consultation Revenue</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData as $report)
                <tr>
                    <td>{{ $report->consultant_name ?? 'Unknown' }}</td>
                    <td>{{ number_format($report->total_consultation_revenue, 2) }}</td>
                    <td>
                        <a href="{{ route('admin.consultant.revenue.detail', $report->consultant_id) }}"
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