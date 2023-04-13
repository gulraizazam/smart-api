@inject('request', 'Illuminate\Http\Request')
@if($request->get('medium_type') != 'web')
    @if($request->get('medium_type') == 'pdf')
        @include('partials.pdf_head')
    @else
        @include('partials.head')
    @endif
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
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
            <h1>{{ 'Non-Converted Customer Report' }}</h1>
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
                        
                        <th>Phone</th>
                        <th>City</th>
                        <th>Gender</th>
                       
                    </tr>
                    </thead>
                    
                <tbody>
                @foreach($Leads as $patient)
                    
                    <tr>
                        <td>{{$patient->phone}}</td>
                        <td>{{$patient->city->name ?? 'Empty'}}</td>
                        <td>{{$patient->gender==1 ? 'Male' : 'Female'}}</td>
                        
                    </tr>
                @endforeach
                </tbody>
                </table>
            </div>

            


        </div>
    </div>
    <div class="clear clearfix"></div>
    <!-- Liabilities and Assets -->
    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>        
    <script>
        $(document).ready(function () {
            $("#arrived_patients_table").DataTable({
                            dom: 'Bfrtip',
                            buttons: [
                                
                                'excelHtml5',
                                'csvHtml5',
                                'pdfHtml5',  
                            ],
                            "ordering": false
                        });
        });
    </script>
    
</div>

