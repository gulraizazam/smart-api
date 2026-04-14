<div>
    <table>
        <tr style="background-color: #3F4254; font-size: 14px;">
            <th style="padding: 10px 15px 10px 15px; color: #fff;">ID</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Full Name</th>
            @can('contact')
                <th style="padding: 10px 15px 10px 15px; color: #fff;">Phone</th>
            @endcan
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Location</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Balance</th>
        </tr>

        @php
            $locationIds = collect($patient_data)->pluck('location_id')->filter()->unique();
            $locationsMap = \App\Models\Locations::whereIn('id', $locationIds)->pluck('name', 'id');
        @endphp
        @foreach($patient_data as $patient)
            <tr style="font-size: 14px; background-color: #f6f6f6;">
                <td style="padding: 10px 5px 10px 5px;">C-{{$patient->patient_id}}</td>
                <td style="padding: 10px 5px 10px 5px; width: 15%;">{{$patient->name}}</td>
                @can('contact')
                    <td style="padding: 10px 5px 10px 5px;">{{$patient->phone}}</td>
                @endcan
                <td style="padding: 10px 5px 10px 5px;">{{$locationsMap[$patient->location_id] ?? ''}} </td>
                <td style="padding: 10px 5px 10px 5px; width: 15%;">PKR: {{$patient->cash_receive-$patient->settle_amount_with_tax}}</td>
            </tr>
        @endforeach

    </table>
</div>
