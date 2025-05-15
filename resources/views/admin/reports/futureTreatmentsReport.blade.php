<div id="revenue_report">
    <h4>Patients Report</h4>

    <table id="patients_table" class="display">
        <thead>
            <tr>
                
                <th>Patient ID</th>
                <th>Name</th>
                <th>Phone</th>

                <th>Membership</th>
                <th>Last Arrival Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($patients as $row)
                <tr>
                    
                        <td> {{$row->id ?? 'N/A' }}</td>
                    

                    
                        <td>{{ $row->name ?? 'N/A' }}</td>
                    

                    <td>{{ $row->phone ?? 'N/A' }}</td>
                    <td>Gold</td>
                    <td>{{ \Carbon\Carbon::parse($row->scheduled_date, null)->format('M j, y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
