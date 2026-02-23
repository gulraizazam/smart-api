<div id="doctor_revenue_report">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Doctor Revenue Report for Selected Location</h4>
        
    </div>

    <div class="alert alert-info">
        <i class="fas fa-user-md"></i>
        <strong>Doctor Revenue Report:</strong> Shows total revenue collected for each doctor based on payments received.
        <br>
        <small class="text-muted">Revenue is calculated from package_advances (cash_flow='in', cash_amount > 0) linked through packages and appointments to doctors.</small>
    </div>

    <table class="table table-bordered table-hover">
        <thead class="thead-light">
            <tr>
                <th>Doctor Name</th>
                <th>Total Revenue</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData as $report)
                <tr>
                    <td>{{ $report->doctor_name ?? 'Unknown' }}</td>
                    <td>{{ number_format($report->total_revenue, 2) }}</td>
                    <td>
                        <a href="{{ route('admin.admin.doctor.revenue.detail', $report->doctor_id) }}"
                           class="btn btn-success btn-sm">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">No data available for this location.</td>
                </tr>
            @endforelse
        </tbody>
        @if($reportData->count() > 0)
        <tfoot class="thead-light">
            <tr>
                <th>Total</th>
                <th>{{ number_format($reportData->sum('total_revenue'), 2) }}</th>
                <th></th>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
