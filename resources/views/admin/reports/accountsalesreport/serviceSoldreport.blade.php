@inject('request', 'Illuminate\Http\Request')
@if($request->get('medium_type') != 'web')
    @if($request->get('medium_type') == 'pdf')
        @include('partials.pdf_head')
    @else
        @include('partials.head')
    @endif
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
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
            <h1>Services Sales Count Report</h1>
        </div>
       
    </div>
</div>

<div class="panel-body sn-table-body">
    <div class="bordered">
        <div class="sn-table-head">
            <div class="row">
                <div class="col-md-2">
                    <img src="{{ asset('logo_final.png') }}" style="height: 120px;">
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
                            <td>{{ now()->format('Y-m-d') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="table-wrapper" id="topscroll">
                <table class="table" id="servicesSoldTable">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Centre</th>
                            <th>Sold</th>
                            <th>Service Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($soldServices->count())
                            @php
                                $services = \App\Models\Services::whereIn('id', $soldServices->pluck('service_id'))->get()->keyBy('id');
                                $locations = \App\Models\Locations::whereIn('id', $soldServices->pluck('location_id'))->get()->keyBy('id');
                            @endphp

                            @foreach($soldServices as $reportRow)
                                <tr>
                                    <td>{{ $services[$reportRow->service_id]->name ?? 'N/A' }}</td>
                                    <td>{{ $locations[$reportRow->location_id]->name ?? 'N/A' }}</td>
                                    <td>{{ $reportRow->total_sold }}</td>
                                    <td>{{ number_format($services[$reportRow->service_id]->price ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="4" class="text-center">No record found.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>
    <script src="{{ url('js/admin/scrollbar/scrollbardev.js') }}" type="text/javascript"></script>
    @if($request->get('medium_type') == 'web')
    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- DataTables core and extensions -->
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

    <script>
        $(document).ready(function () {
            $("#servicesSoldTable").DataTable({
                dom: 'Bfrtip',
                buttons: ['excelHtml5', 'csvHtml5', 'pdfHtml5'],
                ordering: false,
                pageLength: 50
            });
        });
    </script>
@endif
</div>
