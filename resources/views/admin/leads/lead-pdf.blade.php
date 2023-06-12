<div>
    <table>
        <tr style="background-color: #3F4254; font-size: 14px;">
            <th style="padding: 10px 15px 10px 15px; color: #fff;">ID</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Full Name</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Phone</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">City</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Centre</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Region</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Lead Status</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Service</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Child Service</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Created At</th>
        </tr>

        @foreach($leads as $lead)
            @php
                if (!Gate::allows('contact')) {
                   $phone = '***********';
               } else {
                   $phone = $lead->phone ?? 'N/A';
               }
            @endphp
            @foreach($lead->lead_service as $service)
                <tr style="font-size: 14px; background-color: #f6f6f6;">
                    <td style="padding: 10px 5px 10px 5px;">C-{{$lead->id ?? $loop->iteration}}</td>
                    <td style="padding: 10px 5px 10px 5px; width: 15%;">{{$lead->name ?? 'N/A'}}</td>
                    <td style="padding: 10px 5px 10px 5px;">{{$phone ?? 'N/A'}}</td>
                    <td style="padding: 10px 5px 10px 5px;">{{$lead->city->name ?? 'N/A'}} </td>
                    <td style="padding: 10px 5px 10px 5px;">{{$lead->towns->name ?? 'N/A'}} </td>
                    <td style="padding: 10px 5px 10px 5px;">{{$lead->region->name ?? 'N/A'}}</td>
                    <td style="padding: 10px 5px 10px 5px; width: 15%;">{{$lead->lead_status->name ?? 'N/A'}}</td>
                    <td style="padding: 10px 5px 10px 5px;; width: 15%;">{{$service->service->name ?? 'N/A'}}</td>
                    <td style="padding: 10px 5px 10px 5px;; width: 15%;">{{$service->childservice->name ?? 'N/A'}}</td>
                    <td style="padding: 10px 5px 10px 5px;">{{\Carbon\Carbon::parse($lead->lead_created_at)->format('F j,Y h:i A')}}</td>
                </tr>
            @endforeach
        @endforeach

    </table>
</div>
