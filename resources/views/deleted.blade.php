@inject('request', 'Illuminate\Http\Request')
@if($request->get('medium_type') != 'web')
    @if($request->get('medium_type') == 'pdf')
        @include('partials.pdf_head')
    @else
        @include('partials.head')
    @endif
    
    
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
    
</div>

