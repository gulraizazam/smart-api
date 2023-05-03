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
            <h1>{{ 'Staff Wise Arrival Report' }}</h1>
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
                @if(isset($Appointments) && count($Appointments) > 0 )
                <div class="col-md-12 mb-3">
                    <h3 class="">{{$user ? $user : $Appointments[0]->location->name ?? ''}}</h3>
                    <table class="table border">
                        <thead>
                            <tr class="">
                                <td class="bg-light">Total Scheduled Appointments</td>
                                <td class="bg-light" style="text-align:right;">{{count($Appointments) ?? 0}}</td>
                            </tr>
                            <tr class="">
                                <td class="border-top bg-light"> Arrived</td>
                                <td class="border-top bg-light" style="text-align:right;">{{$arrived ?? 0}}</td>
                            </tr>
                                <tr class="">
                                    <td class="border-top bg-light" >Arrival Ratio</td>
                                    <td class="border-top bg-light" style="text-align:right;">
                                        <?php
                                        if (isset($arrived) && isset($Appointments)) {
                                            echo number_format(($arrived / count($Appointments)) * 100, 2) . '%';
                                        } else {
                                            echo '00.00 %';
                                        }
                                        ?>
                                    </td>
                                </tr>
                        </thead>
                    </table>
                </div>
                @else
                <div class="col-md-12 mb-3">
                    <h3 class="">{{$user ? $user : $Appointments[0]->location->name ?? ''}}</h3>
                    <table class="table border">
                        <thead>
                            <tr class="">
                                <td class="bg-light">Total Scheduled Appointments</td>
                                <td class="bg-light" style="text-align:right;">{{count($Appointments) ?? 0}}</td>
                            </tr>
                            <tr class="">
                                <td class="border-top bg-light"> Arrived</td>
                                <td class="border-top bg-light" style="text-align:right;">{{$arrived ?? 0}}</td>
                            </tr>
                                <tr class="">
                                    <td class="border-top bg-light" >Arrival Ratio</td>
                                    <td class="border-top bg-light" style="text-align:right;">
                                        <?php
                                        if (count($Appointments) > 0) {
                                            if (isset($arrived) && isset($Appointments)) {
                                                echo number_format(($arrived / count($Appointments)) * 100, 2) . '%';
                                            } else {
                                                echo '00.00 %';
                                            }
                                        } else{
                                            echo '00.00 %';
                                        }
                                        ?>
                                    </td>
                                </tr>
                        </thead>
                    </table>
                </div>
                @endif
            </div>
            {{-- <div class="table-wrapper all-sections section-detail" id="topscroll">
                <table class="table" id="arrived_status_table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient Name</th>
                            <th>Phone</th>
                            <th>Centre</th>
                            <th>Scheduled Date</th>
                            <th>Created By</th>
                            <th>Appointment Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($Appointments as $patient)
                            <tr>
                                <td>{{$patient->appointment->id}}</td>
                                <td>{{$patient->appointment->name}}</td>
                                <td>{{$patient->appointment->phone}}</td>
                                <td>{{$patient->location->name}}</td>
                                <td>{{$patient['created_at']}}</td>
                                <td>{{$patient->user->name}}</td>
                                <td>@if($patient['appointment_status_id'] == 1)
                                    <label class="label label-warning" style="width:100px;border-radius:2px">Pending</label>
                                    @endif
                                    @if($patient['appointment_status_id'] == 2)
                                    <label class="label label-success" style="width:100px;border-radius:2px">Arrived</label>
                                    @endif
                                    @if($patient['appointment_status_id'] == 3)
                                    <label class="label label-info" style="width:100px;border-radius:2px">No Show</label>
                                    @endif
                                    @if($patient['appointment_status_id'] == 4)
                                    <label class="label label-danger" style="width:100px;border-radius:2px">Cancelled</label>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div> --}}
        </div>
    </div>
    <div class="clear clearfix"></div>
    <!-- Liabilities and Assets -->
    <script src="{{ url('assets/js/fake-scroll.js') }}" type="text/javascript"></script>
</div>

