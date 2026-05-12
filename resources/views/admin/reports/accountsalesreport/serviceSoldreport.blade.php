@inject('request', 'Illuminate\Http\Request')

@if($request->get('medium_type') != 'web')
    @if($request->get('medium_type') == 'pdf')
        @include('partials.pdf_head')
    @else
        @include('partials.head')
    @endif
@endif

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<style>
    @page {
        margin: 10px 20px;
    }

    .card-summary {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }

    .summary-box {
        flex: 1;
        background: #f9f9f9;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        text-align: center;
    }

    .summary-box h3 {
        margin-bottom: 5px;
        font-size: 18px;
        color: #333;
    }

    .summary-box p {
        font-size: 16px;
        font-weight: bold;
        color: #007bff;
    }

    @media print {
        .summary-box {
            border: none;
        }

        table {
            font-size: 12px;
        }
    }
</style>

<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1>Services Sales Count Report</h1>
        </div>
    </div>

    <!-- Summary Cards -->
    @if($soldServices->count() > 0 && $mostSold && $leastSold)
        <div class="card-summary">
            <div class="summary-box">
                <h3>Most Sold Service</h3>
                <p>{{ $services[$mostSold->service_id]->name ?? 'N/A' }} ({{ $mostSold->total_sold }})</p>
            </div>
            <div class="summary-box">
                <h3>Least Sold Service</h3>
                <p>{{ $services[$leastSold->service_id]->name ?? 'N/A' }} ({{ $leastSold->total_sold }})</p>
            </div>
        </div>
    @endif

    <div class="panel-body sn-table-body">
        <div class="bordered">
            <div class="sn-table-head">
                <div class="row">
                    <div class="col-md-2">
                        <img src="{{ asset('allura-logo2.jpeg') }}" style="height: 120px;">
                    </div>
                    <div class="col-md-6">&nbsp;</div>
                    <div class="col-md-4">
                        <table class="dark-th-table table table-bordered">
                            <tr>
                                <th width="25%">Duration</th>
                                <td>From {{ $start_date ?? 'N/A' }} to {{ $end_date ?? 'N/A' }}</td>
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
                               @if(isset($soldServices[0]) && property_exists($soldServices[0], 'location_id'))
                                    <th>Centre</th>
                                @endif
                                <th>Sold</th>
                                <th>Service Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($soldServices->count())
                                @foreach($soldServices as $reportRow)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.service.barchart', [
                                                'service_id' => $reportRow->service_id,
                                                'start_date' => $start_date,
                                                'end_date' => $end_date,
                                                'location_id' => $locationId
                                            ]) }}">
                                                {{ $services[$reportRow->service_id]->name ?? 'N/A' }}
                                            </a>
                                        </td>

                                        @if(isset($reportRow->location_id))
    <td>{{ $locations[$reportRow->location_id]->name ?? 'N/A' }}</td>
@endif

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
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#servicesSoldTable').DataTable({
                paging: false,
                ordering: true,
                info: false,
                searching: true
            });
        });
    </script>
</div>
