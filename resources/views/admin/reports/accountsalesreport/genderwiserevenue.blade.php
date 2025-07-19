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
        flex-wrap: wrap;
    }

    .summary-box {
        flex: 1;
        min-width: 200px;
        background: #f9f9f9;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        text-align: center;
    }

    .summary-box h3 {
        margin-bottom: 5px;
        font-size: 16px;
        color: #333;
    }

    .summary-box p {
        font-size: 14px;
        font-weight: bold;
        color: #007bff;
        margin: 5px 0;
    }

    .gender-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
        margin: 2px;
    }

    .male-badge {
        background-color: #007bff;
        color: white;
    }

    .female-badge {
        background-color: #e83e8c;
        color: white;
    }

    .unknown-badge {
        background-color: #6c757d;
        color: white;
    }

    .revenue-breakdown {
        font-size: 12px;
        margin-top: 5px;
    }

    @media print {
        .summary-box {
            border: none;
        }

        table {
            font-size: 10px;
        }

        .gender-badge {
            font-size: 10px;
        }
    }

    .text-right {
        text-align: right;
    }

    .font-weight-bold {
        font-weight: bold;
    }
</style>

<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1>Gender-wise Service Revenue Report</h1>
        </div>
    </div>

    <!-- Summary Cards -->
    @if(count($reportData) > 0)
        @php
            $totalMaleRevenue = collect($reportData)->sum('male_revenue');
            $totalFemaleRevenue = collect($reportData)->sum('female_revenue');
            $totalUnknownRevenue = collect($reportData)->sum('unknown_gender_revenue');
            $grandTotal = $totalMaleRevenue + $totalFemaleRevenue + $totalUnknownRevenue;
            
            $totalMaleCount = collect($reportData)->sum('male_count');
            $totalFemaleCount = collect($reportData)->sum('female_count');
            $totalUnknownCount = collect($reportData)->sum('unknown_gender_count');
            $totalTransactions = $totalMaleCount + $totalFemaleCount + $totalUnknownCount;
        @endphp
        
        <div class="card-summary">
            <div class="summary-box">
                <h3>Total Male Revenue</h3>
                <p>{{ number_format($totalMaleRevenue, 2) }}</p>
                <small>{{ $totalMaleCount }} transactions</small>
            </div>
            <div class="summary-box">
                <h3>Total Female Revenue</h3>
                <p>{{ number_format($totalFemaleRevenue, 2) }}</p>
                <small>{{ $totalFemaleCount }} transactions</small>
            </div>
            <div class="summary-box">
                <h3>Unknown Gender Revenue</h3>
                <p>{{ number_format($totalUnknownRevenue, 2) }}</p>
                <small>{{ $totalUnknownCount }} transactions</small>
            </div>
            <div class="summary-box">
                <h3>Grand Total</h3>
                <p>{{ number_format($grandTotal, 2) }}</p>
                <small>{{ $totalTransactions }} transactions</small>
            </div>
        </div>
    @endif

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
                    <table class="table" id="genderServiceRevenueTable">
                        <thead>
                            <tr>
                                <th>Service Name</th>
                                @if(isset($isLocationWise) && $isLocationWise)
                                    <th>Centre</th>
                                @endif
                                <th class="text-right">Male Revenue</th>
                                <th class="text-right">Female Revenue</th>
                                <th class="text-right">Unknown Gender</th>
                                <th class="text-right">Total Revenue</th>
                                <th class="text-center">Gender Breakdown</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(count($reportData) > 0)
                                @if(isset($isLocationWise) && $isLocationWise)
                                    {{-- Location-wise display --}}
                                    @foreach($reportData as $locationData)
                                        @if(count($locationData['services']) > 0)
                                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                                <td colspan="7">
                                                    {{ $locationData['location_name'] }} - {{ $locationData['city'] }}, {{ $locationData['region'] }}
                                                </td>
                                            </tr>
                                            @foreach($locationData['services'] as $serviceData)
                                                <tr>
                                                    <td style="padding-left: 20px;">{{ $serviceData['name'] }}</td>
                                                    <td>{{ $locationData['location_name'] }}</td>
                                                    <td class="text-right">{{ number_format($serviceData['male_revenue'], 2) }}</td>
                                                    <td class="text-right">{{ number_format($serviceData['female_revenue'], 2) }}</td>
                                                    <td class="text-right">{{ number_format($serviceData['unknown_gender_revenue'], 2) }}</td>
                                                    <td class="text-right font-weight-bold">{{ number_format($serviceData['total_revenue'], 2) }}</td>
                                                    <td class="text-center">
                                                        @if($serviceData['male_count'] > 0)
                                                            <span class="gender-badge male-badge">M: {{ $serviceData['male_count'] }}</span>
                                                        @endif
                                                        @if($serviceData['female_count'] > 0)
                                                            <span class="gender-badge female-badge">F: {{ $serviceData['female_count'] }}</span>
                                                        @endif
                                                        @if($serviceData['unknown_gender_count'] > 0)
                                                            <span class="gender-badge unknown-badge">U: {{ $serviceData['unknown_gender_count'] }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach
                                @else
                                    {{-- Global service-wise display --}}
                                    @foreach($reportData as $serviceData)
                                        <tr>
                                            <td>{{ $serviceData['name'] }}</td>
                                            <td class="text-right">{{ number_format($serviceData['male_revenue'], 2) }}</td>
                                            <td class="text-right">{{ number_format($serviceData['female_revenue'], 2) }}</td>
                                            <td class="text-right">{{ number_format($serviceData['unknown_gender_revenue'], 2) }}</td>
                                            <td class="text-right font-weight-bold">{{ number_format($serviceData['total_revenue'], 2) }}</td>
                                            <td class="text-center">
                                                @if($serviceData['male_count'] > 0)
                                                    <span class="gender-badge male-badge">M: {{ $serviceData['male_count'] }}</span>
                                                @endif
                                                @if($serviceData['female_count'] > 0)
                                                    <span class="gender-badge female-badge">F: {{ $serviceData['female_count'] }}</span>
                                                @endif
                                                @if($serviceData['unknown_gender_count'] > 0)
                                                    <span class="gender-badge unknown-badge">U: {{ $serviceData['unknown_gender_count'] }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif

                                {{-- Summary Row --}}
                                <tr style="background-color: #e9ecef; font-weight: bold; border-top: 2px solid #dee2e6;">
                                    <td>
                                        @if(isset($isLocationWise) && $isLocationWise)
                                            <strong>GRAND TOTAL</strong>
                                        @else
                                            <strong>TOTAL</strong>
                                        @endif
                                    </td>
                                    @if(isset($isLocationWise) && $isLocationWise)
                                        <td>-</td>
                                    @endif
                                    <td class="text-right">{{ number_format($totalMaleRevenue, 2) }}</td>
                                    <td class="text-right">{{ number_format($totalFemaleRevenue, 2) }}</td>
                                    <td class="text-right">{{ number_format($totalUnknownRevenue, 2) }}</td>
                                    <td class="text-right font-weight-bold">{{ number_format($grandTotal, 2) }}</td>
                                    <td class="text-center">
                                        @if($totalMaleCount > 0)
                                            <span class="gender-badge male-badge">M: {{ $totalMaleCount }}</span>
                                        @endif
                                        @if($totalFemaleCount > 0)
                                            <span class="gender-badge female-badge">F: {{ $totalFemaleCount }}</span>
                                        @endif
                                        @if($totalUnknownCount > 0)
                                            <span class="gender-badge unknown-badge">U: {{ $totalUnknownCount }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <td colspan="7" class="text-center">No record found.</td>
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
            $('#genderServiceRevenueTable').DataTable({
                paging: false,
                ordering: true,
                info: false,
                searching: true,
                columnDefs: [
                    { targets: [2, 3, 4, 5], className: 'text-right' }, // Right align revenue columns
                    { targets: [6], className: 'text-center' } // Center align gender breakdown column
                ]
            });
        });
    </script>
</div>