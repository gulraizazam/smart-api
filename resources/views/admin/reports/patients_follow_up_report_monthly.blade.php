@inject('request', 'Illuminate\Http\Request')
@if($request->get('medium_type') != 'web')
    @if($request->get('medium_type') == 'pdf')
        @include('partials.pdf_head')
    @else
        @include('partials.head')
    @endif
    
    
 
@endif
<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
           
        </div>
       
    </div>
</div>
<div class="panel-body sn-table-body">
    <div class="bordered">
        <div class="sn-table-head">

            <div class="row">
                <div class="col-md-2">
                    <img style="width: 180px;" src="{{asset('allura-logo2.jpeg')}}">
                </div>
                <div class="col-md-6">&nbsp;</div>
                
            </div>
            <div class="pt-4 border-top  all-sections section-states" >
               
            </div>
            <div class="table-wrapper all-sections section-detail" id="topscroll">
                <table class="table follow_up_table" >
                    <thead>
                    <tr>
                    <th class='table-cols'>Patient Id</th>
                    <th class='table-cols'>Name</th>
                    <th class='table-cols'>Phone</th>
                    <th class='table-cols'>Location</th>
                    
                    <th class='table-cols'>Last Arrived</th>
                    <th class='table-cols'> Balance</th>
                    </tr>
                    </thead>
                    
                <tbody>
                @php
                    $locationIds = collect($patient_data)->pluck('location_id')->filter()->unique();
                    $locationsMap = \App\Models\Locations::whereIn('id', $locationIds)->pluck('name', 'id');
                @endphp
                @foreach($patient_data as $patient)
                    <tr>
                        <td>C-{{$patient['patient_id']}}</td>
                        <td>{{$patient['name']}}</td>
                        <td>{{$patient['phone']}}</td>
                        <td>{{$locationsMap[$patient['location_id']] ?? ''}}</td>

                        <td>{{ $patient['scheduled_date'] }}</td>
                        <td>PKR: {{$patient['cash_receive']-$patient['settle_amount_with_tax']}}</td>
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

