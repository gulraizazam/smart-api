<div id="revenue_report">
    <h4>Patients Report</h4>

    <table id="patients_table" class="display">
        <thead>
            <tr>
                <th>Patient ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Centre</th>
                <th>Membership</th>
                <th>Last Arrival Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($patients as $row)
            @php
                $location = \App\Models\Locations::where('id', $row->appointmentsPatient->first()->location_id)->first();
            @endphp
                <tr>
                    <td>{{ $row->id ?? 'N/A' }}</td>
                    <td>{{ $row->name ?? 'N/A' }}</td>
                    <td>{{ $row->phone ?? 'N/A' }}</td>
                    <td>{{$location->name ?? 'N/A' }}</td>
                    {{-- Assuming 'name' column exists in membership --}}
                    <td>{{ $row->membership->membershipType->name ?? 'N/A' }}</td>

                    {{-- Get scheduled_date from the first appointment in the loaded relation --}}
                    <td>
                        {{ optional($row->appointmentsPatient->first())->scheduled_date
                            ? \Carbon\Carbon::parse($row->appointmentsPatient->first()->scheduled_date)->format('d M Y')
                            : 'N/A' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
