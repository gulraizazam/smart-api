@inject('request', 'Illuminate\Http\Request')

@if($request->get('medium_type') != 'web')
    @if($request->get('medium_type') == 'pdf')
        @include('partials.pdf_head')
    @else
        @include('partials.head')
    @endif
@endif

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
        min-width: 280px;
        background: #f9f9f9;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .summary-box h3 {
        margin-bottom: 10px;
        font-size: 18px;
        color: #333;
        font-weight: bold;
    }

    .summary-box p {
        font-size: 24px;
        font-weight: bold;
        color: #007bff;
        margin: 10px 0;
    }

    .summary-box small {
        color: #666;
        font-size: 14px;
    }

    .service-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin: 30px 0;
    }

    .service-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease;
    }

    .service-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .service-name {
        font-size: 20px;
        font-weight: bold;
        color: #333;
        margin-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }

    .gender-revenue-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 12px 0;
        padding: 10px;
        border-radius: 8px;
    }

    .male-row {
        background-color: #e3f2fd;
    }

    .female-row {
        background-color: #fce4ec;
    }

    .total-row {
        background-color: #f5f5f5;
        border: 2px solid #ddd;
        font-weight: bold;
    }

    .gender-label {
        font-weight: bold;
        font-size: 16px;
    }

    .male-label {
        color: #1976d2;
    }

    .female-label {
        color: #c2185b;
    }

    .total-label {
        color: #333;
    }

    .revenue-amount {
        font-size: 18px;
        font-weight: bold;
    }

    .transaction-count {
        font-size: 12px;
        color: #666;
        margin-left: 10px;
    }

    .grand-totals {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin: 30px 0;
        text-align: center;
    }

    .grand-totals h2 {
        margin-bottom: 20px;
        font-size: 28px;
    }

    .grand-totals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .grand-total-item {
        background: rgba(255,255,255,0.1);
        padding: 15px;
        border-radius: 10px;
        backdrop-filter: blur(10px);
    }

    .grand-total-item h4 {
        margin-bottom: 5px;
        font-size: 16px;
        opacity: 0.9;
    }

    .grand-total-item p {
        font-size: 24px;
        font-weight: bold;
        margin: 0;
    }

    @media print {
        .service-card {
            break-inside: avoid;
            box-shadow: none;
            border: 1px solid #ccc;
        }

        .service-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .service-cards {
            grid-template-columns: 1fr;
        }

        .summary-box {
            min-width: 100%;
        }
    }
</style>

<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1>Gender-wise Service Revenue Report</h1>
        </div>
    </div>

    <!-- Header Info -->
    <div class="row" style="margin: 20px 0;">
        <div class="col-md-2">
            <img src="{{ asset('allura-logo1.jpeg') }}" style="height: 120px;">
        </div>
        <div class="col-md-6">&nbsp;</div>
        <div class="col-md-4">
            <table class="table table-bordered">
                <tr>
                    <th width="25%">Duration</th>
                    <td>From {{ $start_date ?? 'N/A' }} to {{ $end_date ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Date</th>
                    <td>{{ now()->format('Y-m-d') }}</td>
                </tr>
                @if(isset($selectedLocations) && count($selectedLocations) > 0)
                <tr>
                    <th>Locations</th>
                    <td>
                        @if(count($selectedLocations) <= 3)
                            {{ implode(', ', $selectedLocations) }}
                        @else
                            {{ count($selectedLocations) }} locations selected
                        @endif
                    </td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    @if(count($reportData) > 0)
        @php
            $totalMaleRevenue = collect($reportData)->sum('male_revenue');
            $totalFemaleRevenue = collect($reportData)->sum('female_revenue');
            $grandTotal = $totalMaleRevenue + $totalFemaleRevenue;
            
            $totalMaleCount = collect($reportData)->sum('male_count');
            $totalFemaleCount = collect($reportData)->sum('female_count');
            $totalTransactions = $totalMaleCount + $totalFemaleCount;
        @endphp

        <!-- Grand Totals Section -->
        <div class="grand-totals">
            <h2>Overall Summary</h2>
            <div class="grand-totals-grid">
                <div class="grand-total-item">
                    <h4>Total Male Revenue</h4>
                    <p>{{ number_format($totalMaleRevenue, 2) }}</p>
                    <small>{{ $totalMaleCount }} transactions</small>
                </div>
                <div class="grand-total-item">
                    <h4>Total Female Revenue</h4>
                    <p>{{ number_format($totalFemaleRevenue, 2) }}</p>
                    <small>{{ $totalFemaleCount }} transactions</small>
                </div>
                <div class="grand-total-item">
                    <h4>Grand Total</h4>
                    <p>{{ number_format($grandTotal, 2) }}</p>
                    <small>{{ $totalTransactions }} transactions</small>
                </div>
            </div>
        </div>

        <!-- Service Cards -->
        <div class="service-cards">
            @foreach($reportData as $serviceData)
                @if($serviceData['male_revenue'] > 0 || $serviceData['female_revenue'] > 0)
                    <div class="service-card">
                        <div class="service-name">
                            {{ $serviceData['name'] }}
                        </div>

                        @if($serviceData['male_revenue'] > 0)
                            <div class="gender-revenue-row male-row">
                                <span class="gender-label male-label">👨 Male Revenue</span>
                                <div>
                                    <span class="revenue-amount male-label">{{ number_format($serviceData['male_revenue'], 2) }}</span>
                                    <span class="transaction-count">({{ $serviceData['male_count'] }} transactions)</span>
                                </div>
                            </div>
                        @endif

                        @if($serviceData['female_revenue'] > 0)
                            <div class="gender-revenue-row female-row">
                                <span class="gender-label female-label">👩 Female Revenue</span>
                                <div>
                                    <span class="revenue-amount female-label">{{ number_format($serviceData['female_revenue'], 2) }}</span>
                                    <span class="transaction-count">({{ $serviceData['female_count'] }} transactions)</span>
                                </div>
                            </div>
                        @endif

                        <div class="gender-revenue-row total-row">
                            <span class="gender-label total-label">📊 Total Revenue</span>
                            <div>
                                <span class="revenue-amount total-label">{{ number_format($serviceData['total_revenue'], 2) }}</span>
                                <span class="transaction-count">({{ $serviceData['total_count'] }} transactions)</span>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 10px; margin: 20px 0;">
            <h3>No Data Found</h3>
            <p>No revenue records found for the selected date range.</p>
        </div>
    @endif
</div>