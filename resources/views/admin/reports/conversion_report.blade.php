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
                border-spacing:0 !important;
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
            <h1>{{ 'Conversion Report'  }}</h1>
        </div>

    </div>
</div>
<div class="panel-body sn-table-body">
    <div class="bordered">
        <div class="sn-table-head">

            <div class="row">
                <div class="col-md-2">
                    <img style="width: 180px;" src="{{asset('logo_final.png')}}" >
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
            <div class="row">
                <div class="col-md-3 mb-3">
                    <table class="table border">
                        <thead>
                            <tr class="">
                                <td class="bg-light">Highest Conversion Value</td>
                                <td class="bg-light" style="text-align:right;">PKR:{{$maxConversion ?? 0 }}</td>
                            </tr>
                        </thead>
                    </table>
                </div>
                <div class="col-md-3 mb-3">
                    <table class="table border">
                        <thead>
                             <tr class="">
                                <td class="border-top bg-light" >Lowest Conversion Value</td>
                                <td class="border-top bg-light" style="text-align:right;">
                                PKR:{{ number_format($minConversion ?? 0, 2) }}
                                </td>
                            </tr>

                        </thead>
                    </table>
                </div>
                <div class="col-md-3 mb-3">
                    <table class="table border">
                        <thead>
                            <tr class="">
                                <td class="bg-light">Average Conversion Value</td>
                                <td class="bg-light" style="text-align:right;">PKR: {{ number_format($average_client_coversion ?? 0, 2) }}</td>
                            </tr>

                        </thead>
                    </table>
                </div>
                <div class="col-md-3 mb-3">
                    <table class="table border">
                        <thead>
                            <tr class="">
                                <td class="bg-light">Arrival to Conversion Ratio</td>
                                <td class="bg-light" style="text-align:right;">{{ number_format($arrival_to_conversion_ratio ?? 0, 2) }} %</td>
                            </tr>
                            <tr class="">
                                <td class="bg-light">Total Conversion</td>
                                <td class="bg-light" style="text-align:right;">{{ $total_conversion ?? 0 }}</td>
                            </tr>
                            <tr class="">
                                <td class="bg-light">Total Arrival</td>
                                <td class="bg-light" style="text-align:right;">{{ $total_arrival ?? 0 }}</td>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="row">
                @foreach($CategoryConversionData as $conversion)
                <div class="col-md-4 mb-3">
                    <table class="table border">
                        <thead>
                            <tr class="">
                                <td class="bg-light">{{$conversion['service']}}</td>
                                <td class="bg-light" style="text-align:right;">Conversions: {{$conversion['total_conversion']}}/ {{$conversion['total_arrival']}}</td>
                            </tr>
                            <tr class="">
                                <td class="bg-light" style="text-align:right;">Total Conversion Value</td>
                                <td class="bg-light" style="text-align:right;">PKR: {{$conversion['sum']}}</td>
                            </tr>
                            <tr class="">
                                <td class="bg-light" style="text-align:right;">Average Conversion Value</td>
                                <td class="bg-light" style="text-align:right;">PKR: {{number_format($conversion['avg'],2)}}</td>
                            </tr>
                        </thead>
                    </table>
                </div>
                @endforeach
            </div>
            <div class="table-wrapper" id="topscroll">
                <table class="table" id="conversion_table">
                    <thead>
                    <th>Patient ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>

                    <th>Service</th>
                    <th>Conversion Spend</th>
                    <th>Conversion Date</th>
                    <th>Location</th>
                    <th>Client Value</th>
                    </thead>
                    <tbody>
                    @php
                        $total = 0;
                        $count = 0;
                    @endphp
                    {{-- @if(count($report_data))
                        @foreach($report_data as $appointment)
                            @if($appointment['converted'] != '' && $appointment['conversion_spend'] > 0)
                                <tr>
                                    <td>{{ $appointment['patient_id'] }}</td>
                                    <td>{{$appointment['client']}}</td>
                                    <td>{{$appointment['doctor']}}</td>

                                    <td>{{$appointment['service']}}</td>
                                    <td style="text-align: center">PKR: {{$appointment['conversion_spend']}}</td>
                                    <td>{{ \Carbon\Carbon::parse($appointment['conversion_date'])}}</td>
                                    <td>{{$appointment['centre']}}</td>
                                    <td>PKR: {{$conversionsByPatient[$appointment['patient_id']]}}</td>
                                </tr>
                                @php
                                    $total += $appointment['conversion_spend']?$appointment['conversion_spend']:0 ;
                                    $count++;
                                @endphp
                            @endif
                        @endforeach
                    @else
                        <tr>
                            <td colspan="12" align="center"></td>
                            <td colspan="12" align="center"></td>
                            <td colspan="12" align="center"></td>
                            <td colspan="12" align="center"></td>
                            <td colspan="12" align="center"></td>
                            <td colspan="12" align="center"></td>
                            <td colspan="12" align="center"></td>
                            <td colspan="12" align="center"></td>
                        </tr>
                    @endif --}}
                    </tbody>
                </table>
                <div class="col-md-12 mb-3">
                    <table class="table border">
                        <thead>
                            <tr class="">
                                <td class="bg-light">Total Conversions Value</td>
                                <td class="bg-light" style="text-align:right;"> PKR: {{ number_format($total,2) }}</td>
                            </tr>
                            <tr class="">
                                <td class="border-top bg-light" >Total Conversions</td>
                                <td class="border-top bg-light" style="text-align:right;">
                                {{ $count }}
                                </td>
                            </tr>
                            <tr class="">
                                <td class="border-top bg-light" >Average client value </td>
                                <td class="border-top bg-light" style="text-align:right;">
                                PKR: {{  number_format($avg_cxlient_valu, 2) }}
                                </td>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>

</div>
