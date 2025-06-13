<div>
    <table>
        <tr style="background-color: #3F4254; font-size: 14px;">
            <th style="padding: 10px 15px 10px 15px; color: #fff;">ID</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Membership Code</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Membership Type</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Patient Name</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">Start Date</th>
            <th style="padding: 10px 15px 10px 15px; color: #fff;">End Date</th>
           
        </tr>

        @foreach($membershipsData as $data)
           
           
                <tr style="font-size: 14px; background-color: #f6f6f6;">
                    <td style="padding: 10px 5px 10px 5px;">C-{{$lead->id ?? $loop->iteration}}</td>
                    <td style="padding: 10px 5px 10px 5px; width: 15%;">{{$data->code ?? 'N/A'}}</td>
                    <td style="padding: 10px 5px 10px 5px;">{{$data->membership_type_id==3 ? 'Gold' : 'Student' }}</td>
                    <td style="padding: 10px 5px 10px 5px;">{{$data->patient->name ?? 'N/A'}} </td>
                    <td style="padding: 10px 5px 10px 5px;">{{$data->start_date ?? 'N/A'}} </td>
                    <td style="padding: 10px 5px 10px 5px;">{{$data->end_date ?? 'N/A'}}</td>
                   
                </tr>
           
        @endforeach

    </table>
</div>
