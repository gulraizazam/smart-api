<div id="future_treatments_report">
    <div class="row mb-3">
        <div class="col-md-12">
            <h5 class="font-weight-bold">Filter Summary:</h5>
            <p class="mb-1"><strong>Date Range:</strong> {{ $filters['start_date'] }} to {{ $filters['end_date'] }}</p>
            @if($filters['centre_id'])
                <p class="mb-1"><strong>Centre:</strong> Selected</p>
            @endif
            @if($filters['service_id'])
                <p class="mb-1"><strong>Service:</strong> Selected</p>
            @endif
            <p class="mb-1"><strong>Total Records:</strong> {{ $appointments->count() }}</p>
        </div>
    </div>

    @if($appointments->count() > 0)
        <table id="future_treatments_table" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Service Name</th>
                    <th>Scheduled Date</th>
                    <th>Appointment Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($appointments as $appointment)
                    <tr>
                        <td>{{ $appointment->patient_name }}</td>
                        <td>{{ $appointment->service_name }}</td>
                        <td>{{ \Carbon\Carbon::parse($appointment->scheduled_date)->format('d M Y h:i A') }}</td>
                        <td>{{ $appointment->appointment_status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> No future treatments found for the selected filters.
        </div>
    @endif
</div>
