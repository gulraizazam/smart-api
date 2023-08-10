<div class="card card-custom card-stretch gutter-b" style="height: 600px; overflow-y: auto;">
    <!--begin::Header-->
    <div class="card-header align-items-center border-0 mt-4">
        <h3 class="card-title align-items-start flex-column">
            <span class="font-weight-bolder text-dark">Today's Activities!</span>
            <span class="text-muted mt-3 font-weight-bold font-size-sm" id="totalactivities">{{count($finance_log) }} activities</span>
        </h3>
    </div>
           
    <div class="card-body pt-4">
        @if(isset($unauthorized))
            <div class="text-center">
                <span >Your are not authorized</span>
            </div>
        @else

        @if(count($finance_log)   > 0)
            <div class="timeline timeline-6 mt-3">
                

                @foreach($finance_log as $log)
                    @if($log['appointment_type'] == "Plan")
                        <div class="timeline-item align-items-start">
                            <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">
                                {{\Illuminate\Support\Carbon::parse($log['created_at'])->format("h:i")}}
                            </div>
                            <div class="timeline-badge">
                                <i class="fa fa-genderless text-danger icon-xl"></i>
                            </div>
                            <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">
                                <span style="color: #056FBF;">{{$log['created_by'] ?? 'N/A'}}</span>
                                {{$log['action']}} 
                                @if($log['action'] == 'refunded')
                                <strong >Rs. {{ round($log['amount'])}}</strong> to
                                @else
                                    <strong >Rs. {{ round($log['amount'])}}</strong> from
                                    @endif
                                <span  style="color: #056FBF;"> {{$log['patient']}}</span> for
                                <a href="{{route('admin.packages.index')}}"><span  style="color: #e55c00;">Plan ID: {{$log['planId']}}</span></a>
                                    at  {{$log['location']}} Centre.
                            </div>
                        </div>
                    
                    @elseif($log['appointment_type'] == "Consultancy")
                        <div class="timeline-item align-items-start">
                            <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">
                                {{\Illuminate\Support\Carbon::parse($log['created_at'])->format("h:i")}}
                            </div>
                            <div class="timeline-badge">
                                <i class="fa fa-genderless text-danger icon-xl"></i>
                            </div>
                            <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">
                                <span style="color: #056FBF;">{{$log['created_by'] ?? 'N/A'}}</span>
                                {{$log['action']}} 
                                    <strong >Rs. {{ round($log['amount']) }}</strong> from
                                <span  style="color: #056FBF;"> {{$log['patient']}}</span> for
                                <span  style="color: #F5B183;">{{$log['appointment_type']}}</span>
                                    at  {{$log['location']}} Centre.
                            </div>
                        </div>
                    @else
                        <div class="timeline-item align-items-start">
                            <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">
                                {{\Illuminate\Support\Carbon::parse($log['created_at'])->format("h:i")}}
                            </div>
                            <div class="timeline-badge">
                                <i class="fa fa-genderless text-danger icon-xl"></i>
                            </div>
                            <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">
                                <span style="color: #056FBF;">{{$log['created_by'] ?? 'N/A'}}</span>
                                {{$log['action']}} 
                                    <strong >Rs. {{ round($log['amount']) }}</strong> from
                                <span  style="color: #056FBF;"> {{$log['patient']}}</span> for
                                <span  style="color: #F5B183;">{{$log['appointment_type']}}</span>
                                    at  {{$log['location']}} Centre.
                            </div>
                        </div>
                    @endif
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