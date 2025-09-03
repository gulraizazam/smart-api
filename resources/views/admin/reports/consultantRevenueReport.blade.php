<div id="consultant_revenue_report">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Consultant Revenue Report for Selected Location</h4>
        
    </div>

    <div class="alert alert-success">
        <i class="fas fa-user-md"></i>
        <strong>Consultant Revenue Report:</strong> Shows revenue attributed to doctors who <strong>performed</strong> the appointments, regardless of who sold the services.
        <br>
        <small class="text-muted">This helps evaluate which consultants generate the most upselling opportunities for their patients.</small>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Consultant (Appointment Doctor)</th>
                <th>Total Upselling Revenue</th>
                <th>Total Consumed Amount</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData as $report)
                <tr>
                    <td>{{ $report->consultant_name ?? 'Unknown' }}</td>
                    <td>{{ number_format($report->total_consultation_revenue, 2) }}</td>
                    <td>{{ number_format($report->total_consumed_amount, 2) }}</td>
                    <td>
                        <a href="{{ route('admin.consultant.revenue.detail', $report->consultant_id) }}"
                           class="btn btn-success btn-sm">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No data available for this location.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>