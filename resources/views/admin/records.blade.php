<html>
    <head>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
    </head>
    <body>
    <table id="example" class="display" style="width:100%">
        <thead>
            <tr>
                <th>Patient Name</th>
                <th>Phone</th>
                <th>City</th>
               
                <th>Service</th>
                <th>Lead status</th>
                <th>Active</th>
              
                <th>Created By</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            @php
                $serviceIds = collect($rr)->pluck('service_id')->filter()->unique();
                $cityIds = collect($rr)->pluck('city_id')->filter()->unique();
                $createdByIds = collect($rr)->pluck('lead_created_by')->filter()->unique();
                $statusIds = collect($rr)->pluck('lead_status_id')->filter()->unique();

                $servicesMap = \App\Models\Services::whereIn('id', $serviceIds)->pluck('name', 'id');
                $citiesMap = \App\Models\Cities::whereIn('id', $cityIds)->pluck('name', 'id');
                $usersMap = \App\Models\User::whereIn('id', $createdByIds)->pluck('name', 'id');
                $statusesMap = \App\Models\LeadStatuses::whereIn('id', $statusIds)->pluck('name', 'id');
            @endphp
            @foreach($rr as $rec)
            <tr>
                <td>{{$rec->name ?? ''}}</td>
                <td>{{$rec->phone ?? ''}}</td>
                <td>{{$citiesMap[$rec->city_id] ?? ''}}</td>

                <td>{{$servicesMap[$rec->service_id] ?? ''}}</td>
                <td>{{$statusesMap[$rec->lead_status_id] ?? ''}}</td>
                <td>{{$rec->active ?? ''}}</td>

                <td>{{$usersMap[$rec->lead_created_by] ?? ''}}</td>
                <td>{{$rec->created_at ?? ''}}</td>

            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
            <th>Patient Name</th>
                <th>Phone</th>
                <th>City</th>
               
                <th>Service</th>
                <th>Lead status</th>
                <th>Active</th>
              
                <th>Created By</th>
                <th>Created At</th>
            </tr>
        </tfoot>
    </table>
    </body>
    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script>$(document).ready(function () {
            $('#example').DataTable();
        });
</script>
</html>