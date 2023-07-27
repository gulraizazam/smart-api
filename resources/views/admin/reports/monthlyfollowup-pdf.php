<div>
    <table>
        <tr style="background-color: #3F4254; font-size: 14px;">
            <th style="padding: 10px 15px 10px 15px; color: #fff;">ID</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Full Name</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Phone</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Location</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Balance</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Scheduled Date</th>
            
        </tr>

        @foreach($patient_data as $patient)
        
            @php
                $location_name = \App\Models\Locations::whereId($patient->location_id)->first();
            @endphp
            <tr style="font-size: 14px; background-color: #f6f6f6;">
                <td style="padding: 10px 5px 10px 5px;">C-{{$patient->patient_id}}</td>
                <td style="padding: 10px 5px 10px 5px; width: 15%;">{{$patient->name}}</td>
                <td style="padding: 10px 5px 10px 5px;">{{$patient->phone}}</td>
                <td style="padding: 10px 5px 10px 5px;">{{$location_name->name}} </td>
                <td style="padding: 10px 5px 10px 5px; width: 15%;">PKR: {{$patient->cash_receive-$patient->settle_amount_with_tax}}</td>
                <td style="padding: 10px 5px 10px 5px; width: 15%;">{{$patient->scheduled_date}}</td>
                
            </tr>
            
        @endforeach

    </table>
</div>
