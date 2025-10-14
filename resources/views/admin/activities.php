<div class="card-body pt-4">
                                <!--begin::Timeline-->
                                @if(isset($unauthorized))
                                    <div class="text-center">
                                        <span >Your are not authorized</span>
                                    </div>
                                @else

                                @if(count($finance_log) + count($appointment_log) > 0)
                                    <div class="timeline timeline-6 mt-3">


                                        @foreach($appointment_log as $appoint_log)

                                            <div class="timeline-item align-items-start">
                                                    <!--begin::Label-->
                                                    <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">{{\Illuminate\Support\Carbon::parse($appoint_log['time'])->format("h:i")}}</div>
                                                    <!--end::Label-->
                                                    <!--begin::Badge-->
                                                    <div class="timeline-badge">
                                                        <i class="fa fa-genderless text-success icon-xl"></i>
                                                    </div>
                                                    <!--end::Badge-->
                                                    <!--begin::Content-->
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
                                                        @elseif($appoint_log['type'] == 'received')

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
                                                    <!--end::Content-->
                                                </div>

                                        @endforeach

                                        @foreach($finance_log as $log)

                                        <div class="timeline-item align-items-start">
                                            <!--begin::Label-->
                                            <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">{{\Illuminate\Support\Carbon::parse($log['created_at'])->format("h:i")}}</div>
                                            <!--end::Label-->
                                            <!--begin::Badge-->
                                            <div class="timeline-badge">
                                                <i class="fa fa-genderless text-danger icon-xl"></i>
                                            </div>
                                            <!--end::Badge-->
                                            <!--begin::Desc-->
                                            <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">
                                                <span style="color: #056FBF;">{{$log['user_id'] ?? 'N/A'}}</span>
                                                {{$log['action'] ?? 'N/A'}} a payment of
                                                 <strong >{{ $log['cash_amount'] }}</strong> for
                                                <span  style="color: #056FBF;"> {{$log['patient_id']}}</span> from
                                                <span  style="color: #F5B183;">{{$log['appointment_type_id'] ?? 'Appointment'}}</span>
                                                 In  {{$log['location_id']}} Centre
                                            </div>
                                            <!--end::Desc-->
                                        </div>

                                    @endforeach

                                </div>

                                @else
                                    <div class="text-center">
                                        <span >No Activity Found</span>
                                    </div>
                                @endif
                                @endif

                            <!--end::Timeline-->
                            </div>
