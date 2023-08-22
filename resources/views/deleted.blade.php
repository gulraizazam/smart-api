@inject('request', 'Illuminate\Http\Request')
@if($request->get('medium_type') != 'web')
    @if($request->get('medium_type') == 'pdf')
        @include('partials.pdf_head')
    @else
        @include('partials.head')
    @endif
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    
    <style type="text/css">
        @page {
            margin: 10px 20px;
        }

        @media print {
            table {
                font-size: 12px;
            }

            .tr-root-group {
                background-color: #F3F3F3;
                color: rgba(0, 0, 0, 0.98);
                font-weight: bold;
            }

            .tr-group {
                font-weight: bold;
            }

            .bold-text {
                font-weight: bold;
            }

            .error-text {
                font-weight: bold;
                color: #FF0000;
            }

            .ok-text {
                color: #006400;
            }

        }

    </style>
@endif
<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1>{{ 'Deleted Customer Report' }}</h1>
        </div>
       
    </div>
</div>
<div class="panel-body sn-table-body">
    <div class="bordered">
        <div class="sn-table-head">

            <div class="row">
                <div class="col-md-2">
                    <img style="width: 180px;" src="{{asset('logo_final.png')}}">
                </div>
                <div class="col-md-6">&nbsp;</div>
                
            </div>
            <div class="pt-4 border-top  all-sections section-states" >
               
            </div>
            <div class="table-wrapper all-sections section-detail" id="topscroll">
                <table class="table" id="arrived_patients_table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient Name</th>
                        
                        <th>Service</th>
                        <th>Created By</th>
                        <th>Deleted By</th>
                        <th>Centre</th>
                        <th>Scheduled Date</th>
                    </tr>
                    </thead>
                    
                <tbody>
                @foreach($appointments as $patient)
                    <?php
                    $created = \App\Models\User::whereId($patient->created_by)->first();
                    $deleted = \App\Models\User::whereId($patient->deleted_by)->first();
                    $service = \App\Models\Services::whereId($patient->service_id)->first();
                    $loc = \App\Models\Locations::whereId($patient->location_id)->first();
                    ?>
                    <tr>
                        <td>{{$patient->id}}</td>
                        <td>{{$patient->name}}</td>
                        
                     
                       
                        <td>{{$service->name}}</td>
                        <td>{{$created->name}}</td>
                        <td>{{$deleted->name}}</td>
                        <td>{{$loc->name}}</td>
                        <td>{{$patient->scheduled_date}}</td> 
                    </tr>
                @endforeach
                </tbody>
                </table>
            </div>

            


        </div>
    </div>
    <div class="clear clearfix"></div>
    <!-- Liabilities and Assets -->
    <script src="{{ url('assets/js/fake-scroll.js') }}" type="text/javascript"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.js" integrity="sha256-JlqSTELeR4TLqP0OG9dxM7yDPqX1ox/HfgiSLBj8+kM=" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
        <script>
            $("#arrived_patients_table").DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5',
                ],
                "ordering": false
            });
        </script>
</div>

