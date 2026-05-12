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
            <h1>{{ 'SALE SUMMARY DOCTORS WISE'  }}</h1>
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
                    <th width="20%">Doctor</th>
                    <th>Service</th>
                    <th>Total</th>
                </thead>
                <tbody>
                @if(count($reportData))
                    <?php $servicegrandtotal = 0;?>
                    @foreach($reportData as $reportpackagedata)
                        <tr>
                            <td><?php echo $reportpackagedata['name']; ?></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php $count = 0; $servicetotal = 0;?>
                        @foreach($reportpackagedata['records'] as $reportRow )
                            <tr>
                                <td></td>
                                <td>{{$reportRow['name']}}</td>
                                <td>
                                    <?php
                                    $servicetotal += $reportRow['amount'];
                                    echo number_format($reportRow['amount'], 2);
                                    ?>
                                </td>
                            </tr>
                        @endforeach
                        <tr style="background-color:#3aaddc;color: #fff;">
                            <td style="color: #fff;"><?php echo $reportpackagedata['name']; ?></td>
                            <td style="color: #fff;">Total</td>
                            <td style="color: #fff;">
                                <?php
                                $servicegrandtotal += $servicetotal;
                                echo number_format($servicetotal,2);
                                ?>
                            </td>
                        </tr>
                    @endforeach
                    <tr style="background: #364150;color: #fff;font-weight: bold">
                        <td></td>
                        <td style="color: #fff; font-weight: bold">Grand Total</td>
                        <td style="color: #fff;" ><?php echo number_format($servicegrandtotal,2); ?></td>
                    </tr>
                @else
                    <tr>
                        <td colspan="12" align="center" >No record round.</td>
                    </tr>
                @endif
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>
</div>
