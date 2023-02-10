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
            @foreach($rr as $rec)
            <?php 
                $service = \App\Models\Services::where('id',$rec->service_id)->first();
                $location = \App\Models\Cities::where('id',$rec->city_id)->first();
                $created_by = \App\Models\User::where('id',$rec->lead_created_by)->first();
                $leadsstat = \App\Models\LeadStatuses::where('id',$rec->lead_status_id)->first();
            ?>
            <tr>
                <td>{{$rec->name ?? ''}}</td>
                <td>{{$rec->phone ?? ''}}</td>
                <td>{{$location->name ?? ''}}</td>
              
                <td>{{$service->name ?? ''}}</td>
                <td>{{$leadsstat->name ?? ''}}</td>
                <td>{{$rec->active ?? ''}}</td>
               
                <td>{{$created_by->name ?? ''}}</td>
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