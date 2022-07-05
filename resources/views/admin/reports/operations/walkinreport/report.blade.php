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
            <h1>{{ 'Walkin Report' }}</h1>
        </div>
        <div class="sn-buttons">
            @if($request->get('medium_type') == 'web')
                <a class="btn sn-white-btn btn-default" href="javascript:;"
                   onclick="printReport('excel');">
                    <i class="fa fa-file-excel-o"></i><span>Excel</span>
                </a>
                <a class="btn sn-white-btn btn-default" href="javascript:;" onclick="printReport('pdf');">
                    <i class="fa fa-file-pdf-o"></i><span>PDF</span>
                </a>
                <a class="btn sn-white-btn btn-default" href="javascript:;"
                   onclick="printReport('print');">
                    <i class="fa fa-print"></i><span>Print</span>
                </a>
            @endif
        </div>
    </div>
</div>
<div class="panel-body sn-table-body">
    <div class="bordered">
        <div class="sn-table-head">
            <div class="row">
                <div class="col-md-2">
                    <img style="width: 145px;" src="{{ asset('assets/media/logos/logo.svg') }}" height="80">
                </div>
                <div class="col-md-6">&nbsp;</div>
                <div class="col-md-4">
                    <table class="dark-th-table table table-bordered">
                        <tr>
                            <th width="25%">Duration</th>
                            <td>From {{ $start_date }} to {{ $end_date }}</td>
                        </tr>
                        <tr>
                            <th>Date</th>
                            <td>{{ \Carbon\Carbon::now()->format('Y-m-d') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="table-wrapper" id="topscroll">
                <table class="table">
                    <thead>
                    <tr>
                        <th colspan="10">Today's Appointments</th>
                        <th colspan="6">Treatment Booked</th>
                    </tr>
                    </thead>
                    <thead>
                    <tr>
                        <th>Sr#</th>
                        <th>Scheduled Date</th>
                        <th>Client id</th>
                        <th>Client Name</th>
                        <th>Appointment Type</th>
                        <th>Practitioner</th>
                        <th>Service</th>
                        <th>Appointment Status Parent</th>
                        <th>Appointment Status Child</th>
                        <th>--</th>
                        <th>Scheduled Date</th>
                        <th>Practitioner</th>
                        <th>Appointment Type</th>
                        <th>Service</th>
                        <th>Appointment Status Parent</th>
                        <th>Appointment Status Child</th>
                    </tr>
                    </thead>
                    @php $count = 1;$consultantbooked = 0;$treatmentbooked = 0;$consultantarrived = 0;$treatmentarrived = 0; @endphp
                    @if(count($reportData))
                        @foreach($reportData as $reportsingle)

                            <tr>
                                @if($reportsingle['appointment_slug'] == 'consultancy')
                                    <?php
                                        $consultantbooked++;
                                    ?>
                                @elseif($reportsingle['appointment_slug'] == 'treatment')
                                    <?php $treatmentbooked++; ?>
                                @endif
                                @if($reportsingle['appointment_slug'] == 'consultancy' && $reportsingle['appointment_status_isarrived'] == '1')
                                    <?php $consultantarrived++; ?>
                                @elseif($reportsingle['appointment_slug'] == 'treatment' && $reportsingle['appointment_status_isarrived'] == '1')
                                    <?php $treatmentarrived++; ?>
                                @endif
                                <td>{{$count++}}</td>
                                <td>{{$reportsingle['schedule_date']}}</td>
                                <td>{{$reportsingle['id']}}</td>
                                <td>{{$reportsingle['client_name']}}</td>
                                <td>{{$reportsingle['appointment_type']}}</td>
                                <td>{{$reportsingle['doctor_name']}}</td>
                                <td>{{$reportsingle['service']}}</td>
                                <td>{{$reportsingle['appointment_status_parent']}}</td>
                                <td>{{$reportsingle['appointment_status_child']}}</td>
                                <td>{{'-'}}</td>
                                @foreach($reportsingle['next_appointment_info'] as $next_appointment_info)
                                    <td>{{$next_appointment_info['schedule_date']}}</td>
                                    <td>{{$next_appointment_info['doctor_name']}}</td>
                                    <td>{{$next_appointment_info['appointment_type']}}</td>
                                    <td>{{$next_appointment_info['service']}}</td>
                                    <td>{{$next_appointment_info['appointment_status_child']}}</td>
                                    <td>{{$next_appointment_info['appointment_status_parent']}}</td>
                                @endforeach
                            </tr>
                        @endforeach

                        <tr class="shdoc-header">
                            <td style="color: #fff">Total Walkin</td>
                            <td style="text-align:right;color: #fff">{{$totalWalkin ?? 0}}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        {{--<tr class="shdoc-header">
                            <td style="color: #fff">Consultation Booked</td>
                            <td style="text-align:right;color: #fff">{{$consultantbooked}}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr class="shdoc-header">
                            <td style="color: #fff">Consultation Arrived</td>
                            <td style="text-align:right;color: #fff">{{$consultantarrived}}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr class="shdoc-header">
                            <td style="color: #fff">New Consultation Converted</td>
                            <td style="text-align:right;color: #fff">{{$newconsultant}}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        @if($consultantbooked>0)
                            <tr class="shdoc-header">
                                <td style="color: #fff">Consultation Arrival Ratio</td>
                                <td style="text-align:right;color: #fff"><?php echo number_format(($consultantarrived / $consultantbooked) * 100, 2) . '%'?></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endif
                        @if($consultantarrived>0)
                            <tr class="shdoc-header">
                                <td style="color: #fff">Consultation Conversion Ratio</td>
                                <td style="text-align:right;color: #fff"><?php echo number_format(($newconsultant / $consultantarrived) * 100, 2) . '%'?></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endif
                        <tr class="shdoc-header">
                            <td style="color: #fff">Treatment Booked</td>
                            <td style="text-align:right;color: #fff">{{$treatmentbooked}}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr class="shdoc-header">
                            <td style="color: #fff">Treatment Arrived</td>
                            <td style="text-align:right;color: #fff">{{$treatmentarrived}}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr class="shdoc-header">
                            <td style="color: #fff">New Treatment Converted</td>
                            <td style="text-align:right;color: #fff">{{$newtreatment}}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        @if($treatmentbooked>0)
                            <tr class="shdoc-header">
                                <td style="color: #fff">Treatment Arrival Ratio</td>
                                <td style="text-align:right;color: #fff"><?php echo number_format(($treatmentarrived / $treatmentbooked) * 100, 2) . '%'?></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endif
                        @if($treatmentarrived>0)
                            <tr class="shdoc-header">
                                <td style="color: #fff">Treatment Conversion Ratio</td>
                                <td style="text-align:right;color: #fff"><?php echo number_format(($newtreatment / $treatmentarrived) * 100, 2) . '%'?></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endif--}}
                    @else
                        <tr>
                            <td colspan="12" align="center">No record round.</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>
    <!-- Liabilities and Assets -->
    <script src="{{ url('assets/js/fake-scroll.js') }}" type="text/javascript"></script>
</div>
