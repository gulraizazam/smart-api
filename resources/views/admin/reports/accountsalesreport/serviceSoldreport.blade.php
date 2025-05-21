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
                            @php
                                $serviceIds = $soldServices->pluck('service_id')->unique();
                                $services = \App\Models\Services::whereIn('id', $serviceIds)->get()->keyBy('id');

                                $locationIds = $soldServices->pluck('location_id')->filter()->unique();
                                $locations = \App\Models\Locations::whereIn('id', $locationIds)->get()->keyBy('id');
                            @endphp

                            @foreach($soldServices as $reportRow)
                                <tr>
                                    <td><a href="{{ route('service.barchart'. $reportRow->service_id) }}">{{ $services[$reportRow->service_id]->name ?? 'N/A' }}</a></td>

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

    <div class="clear clearfix"></div>

    <script src="{{ url('js/admin/scrollbar/scrollbardev.js') }}" type="text/javascript"></script>
</div>
