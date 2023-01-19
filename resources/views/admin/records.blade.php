<html>
    <head>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
    </head>
    <body>
    <table id="example" class="display" style="width:100%">
        <thead>
            <tr>
                <th>Name</th>
                <th>scheduled_date</th>
                <th>first_scheduled_date</th>
                <th>scheduled_time</th>
                <th>doctor_id</th>
                <th>location_id</th>
                <th>appointment_id</th>
                <th>service_id</th>
                <th>total_price(invoice)</th>
                <th>cash_amount</th>
                <th>invoice_id</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $rec)
            <?php 
                $service = \App\Models\Services::where('id',$rec->service_id)->first();
                $doc = \App\Models\Doctors::where('id',$rec->doctor_id)->first();
                $location = \App\Models\Locations::where('id',$rec->slocation_id)->first();
                
            ?>
            <tr>
                <td>{{$rec->name}}</td>
                <td>{{$rec->scheduled_date}}</td>
                <td>{{$rec->first_scheduled_date}}</td>
                <td>{{$rec->scheduled_time}}</td>
                <td>{{$doc->name}}</td>
                <td>{{$rec->location_id}}</td>
                <td>{{$rec->appointment_id}}</td>
                <td>{{$service->name}}</td>
                <td>{{$rec->total_price}}</td>
                <td>{{$rec->cash_amount}}</td>
                <td>{{$rec->invoice_id}}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th>Name</th>
                <th>Position</th>
                <th>Office</th>
                <th>Age</th>
                <th>Start date</th>
                <th>Salary</th>
            </tr>
        </tfoot>
    </table>
    </body>
    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script>$$(document).ready(function () {
            $('#example').DataTable();
        });
</script>
</html>