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
            <h1>{{ 'DAR Report' }}</h1>
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

<div class="card mb-8 menu_section" style="width: 100%">

    @include('admin.reports.common.tab')

</div>

<div class="panel-body sn-table-body">
    <div class="bordered">
        <div class="sn-table-head">

            <div class="row">
                <div class="col-md-2">
                    <img style="width: 145px;" src="{{ asset('assets/media/logos/smart.svg') }}" height="80">
                </div>
                <div class="col-md-6">&nbsp;</div>
                <div class="col-md-4">
                    <table class="dark-th-table table table-bordered">
                        <tr class="bg-light">
                            <th width="25%">Duration</th>
                            <td>From {{ $start_date }} to {{ $end_date }}</td>
                        </tr>
                        <tr class="bg-light">
                            <th>Date</th>
                            <td>{{ \Carbon\Carbon::now()->format('Y-m-d') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="table-wrapper all-sections section-detail" id="topscroll">
                <table class="table">
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
                        {{--<th>Appointment Status Child</th>--}}
                    </tr>
                    </thead>
                    @php $walkin = 0; $count = 1;$consultantbooked = 0;$treatmentbooked = 0;$consultantarrived = 0;$treatmentarrived = 0; @endphp
                    @if(count($reportData))
                        @foreach($reportData as $reportsingle)

                            <tr>
                                @if($reportsingle['appointment_slug'] == 'consultancy')

                                <td>{{$count++}}</td>
                                <td>{{$reportsingle['schedule_date']}}</td>
                                <td>{{$reportsingle['id']}}</td>
                                <td>{{$reportsingle['client_name']}}</td>
                                <td>{{$reportsingle['appointment_type']}}</td>
                                <td>{{$reportsingle['doctor_name']}}</td>
                                <td>{{$reportsingle['service']}}</td>
                                <td>{{$reportsingle['appointment_status_parent']}}</td>
                                {{--<td>{{$reportsingle['appointment_status_child']}}</td>--}}
                            </tr>
                            @endif
                        @endforeach

                    @else
                        <tr>
                            <td colspan="12" align="center">No record round.</td>
                        </tr>
                    @endif
                </table>
            </div>

            <div class="pt-4 border-top  all-sections section-states" style="display: none;">
                @if(isset($locationData) && count($locationData) > 0)
                    @foreach($locationData as $key => $location)

                        <div class="col-md-6 mb-3">
                            <h3 class="">{{$key}}</h3>

                            <table class="table border">
                        <thead>
                    <tr class="">
                        <td class="bg-light">Consultation Booked</td>
                        <td class="bg-light" style="text-align:right;">{{$location['consultantbooked']}}</td>
                    </tr>
                    <tr class="">
                        <td class="border-top bg-light" style="">Consultation Arrived</td>
                        <td class="border-top bg-light" style="text-align:right;">{{$location['consultantarrived']}}</td>
                    </tr>
                    <tr class="">
                        <td class="border-top bg-light" style="">Total Walkin</td>
                        <td class="border-top bg-light" style="text-align:right;">{{$location['walking']}}</td>
                    </tr>

                    @if(isset($location['consultantbooked']) && $location['consultantbooked'] > 0)
                        <tr class="">
                            <td class="border-top bg-light" style="">Consultation Arrival Ratio</td>
                            <td class="border-top bg-light" style="text-align:right;">
                                <?php
                                if (isset($location['consultantbooked']) && isset($location['consultantarrived'])) {
                                    $booking_without_walkin = $location['consultantbooked'] - $location['walking'];
                                    $arrived_without_walkin = $location['consultantarrived'] - $location['walking'];
                                    echo number_format(($arrived_without_walkin / $booking_without_walkin) * 100, 2) . '%';
                                } else {
                                    echo '00.00 %';
                                }

                                ?>
                            </td>
                        </tr>
                    @endif
                        </thead>
                    </table>

                        </div>

                    @endforeach
                @endif
            </div>


        </div>
    </div>
    <div class="clear clearfix"></div>
    <!-- Liabilities and Assets -->
    <script src="{{ url('assets/js/fake-scroll.js') }}" type="text/javascript"></script>
</div>
