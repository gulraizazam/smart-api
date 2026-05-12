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
            <h1>{{ 'Collection By Serivce' }}</h1>
        </div>
        <div class="sn-buttons">
            @if($request->get('medium_type') == 'web')
                <a class="btn sn-white-btn btn-default" href="javascript:;" onclick="printReport('excel');">
                    <i class="fa fa-file-excel-o"></i><span>Excel</span>
                </a>
                <a class="btn sn-white-btn btn-default" href="javascript:;" onclick="printReport('pdf');">
                    <i class="fa fa-file-pdf-o"></i><span>PDF</span>
                </a>
                <a class="btn sn-white-btn btn-default" href="javascript:;" onclick="printReport('print');">
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
                    <img style="width: 180px;" src="{{asset('allura-logo1.jpeg')}}">
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
                            <th>Service</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @if(count($reportData))
                        <?php $grand = 0; ?>
                        @foreach($reportData as $reportData)
                            @if($reportData['amount'])
                                <tr>
                                    <td>{{$reportData['name']}}</td>
                                    <td>{{number_format($reportData['amount'],2)}}</td>
                                    @php
                                        $grand +=$reportData['amount'];
                                    @endphp
                                </tr>
                            @endif
                        @endforeach
                        <tr style="background: #364150;color: #fff;">
                            <td style="font-weight: bold;color: #fff;">Grand Total</td>
                            <td style="font-weight: bold;color: #fff;">{{number_format($grand,2)}}</td>
                        </tr>
                    @else
                        <tr>
                            <td colspan="12" align="center">No record round.</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
        </div>
    </div>
    </div>
    <div class="clear clearfix"></div>

</div>
