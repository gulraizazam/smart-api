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
            <h1>{{ 'Conversion Report'  }}</h1>
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
                    <img style="width: 180px;" src="{{asset('allura-logo2.jpeg')}}" >
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
                    <th>ID</th>
                    <th>Doctor</th>
                    <th>Date of Inquiry</th>
                    <th>Client</th>
                    <th>Appointment Type</th>
                    <th>Service</th>
                    <th>Converted</th>
                    <th>Conversion Spend</th>
                    <th>Conversion Date</th>
                    <th>Region</th>
                    <th>City</th>
                    <th>Location</th>
                    </thead>
                    <tbody>
                    @php
                        $total = 0;
                        $count = 0;
                    @endphp
                    @if(count($report_data))
                        @foreach($report_data as $appointment)
                            @if($appointment['converted'] != '')
                                <tr>
                                    <td>{{ $appointment['patient_id'] }}</td>
                                    <td>{{$appointment['doctor']}}</td>
                                    <td>{{ $appointment['doi']  }}</td>
                                    <td>{{$appointment['client']}}</td>
                                    <td>{{'Consultancy'}}</td>
                                    <td>{{$appointment['service']}}</td>
                                    <td>{{$appointment['converted']}}</td>
                                    <td style="text-align: right">{{$appointment['conversion_spend']}}</td>
                                    <td>{{ \Carbon\Carbon::parse($appointment['conversion_date'])->format('F j,Y')}}</td>
                                    <td>{{$appointment['region']}}</td>
                                    <td>{{$appointment['city']}}</td>
                                    <td>{{$appointment['centre']}}</td>
                                </tr>
                                @php
                                    $total += $appointment['conversion_spend']?$appointment['conversion_spend']:0 ;
                                    $count++;
                                @endphp
                            @endif
                        @endforeach
                    @else
                        <tr>
                            <td colspan="12" align="center">No Record Found.</td>
                        </tr>
                    @endif
                    </tbody>
                    <tr class="shdoc-header">
                        <td style="color: #fff">Total</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td style="text-align: right ; color: #fff;">{{ number_format($total,2) }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr class="shdoc-header">
                        <td style="color: #fff">Total Count</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td style="text-align: right ; color: #fff;">{{ count($report_data) }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr class="shdoc-header">
                        <td style="color: #fff">Converted Count</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td style="text-align: right ; color: #fff;">{{ $count }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr class="shdoc-header">
                        <td style="color: #fff">Converted Ration</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td style="text-align: right ; color: #fff;">{{ $count > 0 ? number_format($count / count($report_data) * 100, 2) : 0}} %</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr class="shdoc-header">
                        <td style="color: #fff">Conversion Average</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td style="text-align: right ; color: #fff;">{{ $total > 0 ? number_format($total / $count, 2) : 0}}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>

</div>
