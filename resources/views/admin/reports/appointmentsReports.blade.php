<div id="revenue_report">
    

    <h3>Appointments</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Patient ID</th>
                <th>Patient Name</th>
                <th>Appointment Date</th>
                <th>Appointment Created Date/Time</th>
                <th>Appointment Arrival Date/Time</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($appointments as $apt)
                <tr>
                    <td>{{ $apt->patient_id }}</td>
                    <td>{{ $apt->patient->patient_name }}</td>
                    <td>{{ $apt->scheduled_date }}</td>
                    <td>{{ date('d-m-Y H:i:s', strtotime($apt->created_at)) }}</td>
                    <td>
                        @if ($apt->hasInvoices)
                            {{ date('d-m-Y H:i:s', strtotime($apt->hasInvoices->created_at)) }}
                        @else
                            N/A
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
