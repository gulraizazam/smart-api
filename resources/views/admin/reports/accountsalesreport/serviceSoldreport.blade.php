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
            <h1>{{ 'Services Sales Count Report'  }}</h1>
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
                    <img src="{{asset('logo_final.png')}}" style="height: 180px;">
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
                    <th>Service Name</th>
                    <th>Centre</th>
                    <th>Sold</th>
                    <th>Service Price</th>
                    
                    </thead>
                    <tbody>
                    @if(count($soldServices))
                        
                        @foreach($soldServices as $reportRow)
                       
                        @php 
                            $service = \App\Models\Services::where('id',$reportRow->service_id)->first();
                            $location = \App\Models\Locations::where('id',$reportRow->location_id)->first();
                        @endphp
                            <tr>
                                <td style="text-align: center;">{{ $service->name }}</td>
                                <td>{{ $location->name }}</td>
                                <td>{{ $reportRow->total_sold }}</td>
                                <td>{{ $service->price }}</td>
                               </tr>
                        @endforeach

                       

                    @else
                        <tr>
                            <td colspan="16" align="center">No record round.</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>
    <!-- Liabilities and Assets -->
    <script src="{{ url('js/admin/scrollbar/scrollbardev.js') }}" type="text/javascript"></script>
</div>
