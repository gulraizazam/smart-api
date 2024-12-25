<div id="revenue_report">
    

   
    <table class="table table-bordered" id="appointments_table">
        <thead>
            <tr>
                <th>Patient ID</th>
                <th>Patient Name</th>
                <th>Schedule Date</th>
                <th>Schedule Time</th>
                <th>Location</th>
                <th>Created Date/Time</th>
                <th>Arrival Date/Time</th>
                <th>Created By</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($appointments as $apt)
                <tr>
                    <td>{{ $apt->patient_id }}</td>
                    <td>{{ $apt->patient->name }}</td>
                    <td>{{ $apt->scheduled_date }}</td>
                    <td>{{$apt->scheduled_time}}</td>
                    <td>{{$apt->location->name ?? 'N/A'}}</td>
                    <td>{{ date('d-m-Y H:i:s', strtotime($apt->created_at)) }}</td>
                    <td>
                        @if ($apt->hasInvoices->isNotEmpty())
                            {{ date('d-m-Y H:i:s', strtotime($apt->hasInvoices->first()->created_at)) }}
                        @else
                            N/A
                        @endif
                    </td>
                    <td>{{$apt->user->name ?? 'N/A'}}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
