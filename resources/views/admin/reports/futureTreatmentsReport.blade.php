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
            @php
                $locationIds = collect($patients)->map(fn($row) => optional($row->appointmentsPatient->first())->location_id)->filter()->unique();
                $locationsMap = \App\Models\Locations::whereIn('id', $locationIds)->pluck('name', 'id');
            @endphp
            @foreach ($patients as $row)
            @php
                $firstAppointment = $row->appointmentsPatient->first();
            @endphp
                <tr>
                    <td>{{ $row->id ?? 'N/A' }}</td>
                    <td>{{ $row->name ?? 'N/A' }}</td>
                    <td>{{ $row->phone ?? 'N/A' }}</td>
                    <td>{{ $locationsMap[$firstAppointment->location_id ?? 0] ?? 'N/A' }}</td>
                    <td>{{ $row->membership->membershipType->name ?? 'N/A' }}</td>
                    <td>
                        {{ optional($firstAppointment)->scheduled_date
                            ? \Carbon\Carbon::parse($firstAppointment->scheduled_date)->format('d M Y')
                            : 'N/A' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
