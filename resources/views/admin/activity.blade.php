<div class="card card-custom card-stretch gutter-b" style="height: 600px; overflow-y: auto;">
    <!--begin::Header-->
    <div class="card-header align-items-center border-0 mt-4">
        <h3 class="card-title align-items-start flex-column">
            <span class="font-weight-bolder text-dark">Recent Activity</span>
            <span class="text-muted mt-3 font-weight-bold font-size-sm" id="totalactivities">{{count($finance_log) + count($plan_logs)+ count($appointment_log)}} activities</span>
        </h3>
    </div>
           
    <div class="card-body pt-4">
        @if(isset($unauthorized))
            <div class="text-center">
                <span >Your are not authorized</span>
            </div>
        @else

        @if(count($finance_log) + count($appointment_log) + count($plan_logs) > 0)
            <div class="timeline timeline-6 mt-3">
                @foreach($appointment_log as $appoint_log)
                    <div class="timeline-item align-items-start">
                        <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">{{\Illuminate\Support\Carbon::parse($appoint_log['time'])->format("h:i")}}</div>
                        <div class="timeline-badge">
                            <i class="fa fa-genderless text-success icon-xl"></i>
                        </div>
                            
                        <div class="timeline-content d-flex">
                            <span class="font-weight-bolder text-dark-75 pl-3 font-size-lg">
                                @if($appoint_log['type'] == 'rescheduled')
                                    <span style="color: #056FBF;">{{$appoint_log['action_by'] ?? 'N/A'}}</span>
                                    {{$appoint_log['action'] ?? 'N/A'}} <span style="color: #F5B183;">{{$appoint_log['screen'] ?? 'N/A'}}</span>
                                    for <span style="color: #3E7FBB;">{{$appoint_log['action_for']}}</span>
                                    to {{\Illuminate\Support\Carbon::parse($appoint_log['date'])->format("d/m/Y") ?? 'N/A'}}
                                @elseif($appoint_log['type'] == 'booked')

                                    <span style="color: #056FBF;">{{$appoint_log['action_by'] ?? 'N/A'}}</span>
                                    a {{$appoint_log['action'] ?? 'N/A'}}
                                    <span style="color: #F5B183;">{{$appoint_log['screen'] ?? 'N/A'}}</span>
                                    for <span style="color: #3E7FBB;">{{$appoint_log['action_for']}}</span>
                                    at <span style="color: #F5B183;">{{\Illuminate\Support\Carbon::parse($appoint_log['time'])->format("h:s A") ?? 'N/A'}} {{\Illuminate\Support\Carbon::parse($appoint_log['date'])->format("d/m/Y") ?? 'N/A'}} </span>
                                    in {{$appoint_log['address'] ?? 'N/A'}}

                                @else
                                    <span style="color: #056FBF;">{{$appoint_log['action_by'] ?? 'N/A'}}</span>
                                    {{$appoint_log['action'] ?? 'N/A'}} <span style="color: #F5B183;">{{$appoint_log['screen'] ?? 'N/A'}}</span>
                                    for <span style="color: #3E7FBB;">{{$appoint_log['action_for']}}</span>
                                    in {{$appoint_log['address'] ?? 'N/A'}}
                                @endif


                            </span>
                        </div>
                        
                    </div>

                @endforeach

                @foreach($finance_log as $log)

                    <?php
                    $username = \App\Models\User::whereId($log['created_by'])->first();
                    $center = \App\Models\Locations::whereId($log['location_id'])->first();
                    $service = \App\Models\Services::whereId($log['service_id'])->first();
                    if(isset($log['appointment_type_id']) && $log['appointment_type_id']==1){
                        $type ='Consultancy';
                        $action = 'received';
                        $subaction = 'from';
                    }else{
                        $type =$service->name . ' Treatment';
                        $action = 'consumed';
                        $subaction = 'against';
                    }
                    // if(isset($log['package_id'])){
                    //     $type = 'plan ID: '.$log['package_id'];
                    //     $action = 'consumed';
                    // }
                    ?>
                    
                    <div class="timeline-item align-items-start">
                    <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">
                        {{\Illuminate\Support\Carbon::parse($log['created_at'])->format("h:i")}}
                    </div>
                    <div class="timeline-badge">
                        <i class="fa fa-genderless text-danger icon-xl"></i>
                    </div>
                    <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">
                        <span style="color: #056FBF;">{{$username->name ?? 'N/A'}}</span>
                        {{$action}} 
                            <strong >Rs. {{ $log['total_price'] }}</strong> {{$subaction}}
                        <span  style="color: #056FBF;"> {{$log['name']}}</span> for
                        <span  style="color: #F5B183;">{{$type}}</span>
                            at  {{$center->name}} Centre.
                    </div>
                </div>

            @endforeach
            @foreach($plan_logs as $log)
                    
                    <?php
                    $username = \App\Models\User::whereId($log['created_by'])->first();
                    $center = \App\Models\Locations::whereId($log['location_id'])->first();
                    $patient = \App\Models\User::whereId($log['patient_id'])->first();
                    ?>
                    
                    <div class="timeline-item align-items-start">
                    <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">
                        {{\Illuminate\Support\Carbon::parse($log['created_at'])->format("h:i")}}
                    </div>
                    <div class="timeline-badge">
                        <i class="fa fa-genderless text-danger icon-xl"></i>
                    </div>
                    <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">
                        <span style="color: #056FBF;">{{$username->name ?? 'N/A'}}</span>
                        received
                            <strong >Rs. {{ $log['cash_amount'] }}</strong> from
                        <span  style="color: #056FBF;"> {{$patient->name}}</span> for plan ID :
                            <a href="{{route('admin.packages.index')}}"><span  style="color: #F5B183;">{{$log['package_id']}}</span></a>
                            at  {{$center->name}} Centre.
                    </div>
                </div>

            @endforeach
            
        </div>

        @else
            <div class="text-center">
                <span >No Activity Found</span>
            </div>
        @endif
        @endif
    </div>
</div>