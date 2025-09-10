@inject('request', 'Illuminate\Http\Request')
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sales Detail Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.4;
            font-size: 12px;
            background: #fff;
        }

        .page-container {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
        }

        /* Header Styles */
        .report-header {
            background: #364150;
            color: white;
            padding: 25px;
            margin-bottom: 20px;
        }

        .header-flex {
            display: table;
            width: 100%;
        }

        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 60%;
        }

        .header-right {
            display: table-cell;
            vertical-align: middle;
            width: 40%;
            text-align: right;
        }

        .logo {
            width: 180px;
            height: auto;
            margin-bottom: 10px;
            filter: brightness(0) invert(1);
        }

        .company-name {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #fff;
        }

        .company-tagline {
            font-size: 11px;
            color: #ddd;
        }

        .report-meta {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 5px;
        }

        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #fff;
        }

        .meta-row {
            margin-bottom: 5px;
            font-size: 11px;
        }

        .meta-label {
            font-weight: bold;
            display: inline-block;
            width: 60px;
        }

        /* Section Titles */
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #364150;
            margin: 20px 0 15px 0;
            padding: 8px 0;
            border-bottom: 2px solid #364150;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
        }

        .data-table th {
            background: #364150;
            color: white;
            font-weight: bold;
            text-align: left;
            padding: 12px 8px;
            border: 1px solid #2d3748;
            font-size: 9px;
            text-transform: uppercase;
        }

        .data-table td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .data-table tbody tr:nth-child(odd) {
            background: #fff;
        }

        /* Location Header Row */
        .location-header {
            background: #667eea !important;
            color: white !important;
            font-weight: bold;
        }

        .location-header td {
            padding: 10px 8px !important;
            font-size: 11px !important;
            border: 1px solid #5a67d8 !important;
        }

        /* Location Total Row */
        .location-total {
            background: #4a5568 !important;
            color: white !important;
            font-weight: bold;
        }

        .location-total td {
            padding: 10px 8px !important;
            font-size: 10px !important;
            border: 1px solid #2d3748 !important;
        }

        /* Amount Styling */
        .amount {
            font-weight: bold;
            text-align: right;
        }

        .amount.positive {
            color: #22543d;
        }

        .amount.negative {
            color: #c53030;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge.male {
            background: #bee3f8;
            color: #2c5282;
        }

        .badge.female {
            background: #fed7d7;
            color: #822727;
        }

        .badge.advance {
            background: #c6f6d5;
            color: #22543d;
        }

        /* Summary Section */
        .summary-container {
            margin-top: 30px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .summary-table th {
            background: #364150;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #2d3748;
        }

        .summary-table td {
            padding: 12px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .summary-table .amount-cell {
            text-align: right;
            font-weight: bold;
            font-size: 13px;
        }

        .summary-table .total-row {
            background: #364150 !important;
            color: white !important;
            font-weight: bold;
        }

        .summary-table .total-row td {
            background: #364150 !important;
            color: white !important;
            border: 1px solid #2d3748 !important;
        }

        /* Summary Grid Alternative */
        .summary-grid {
            display: table;
            width: 100%;
            margin-top: 20px;
        }

        .summary-row {
            display: table-row;
        }

        .summary-cell {
            display: table-cell;
            width: 50%;
            padding: 5px;
        }

        .summary-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #364150;
            padding: 15px;
            text-align: center;
        }

        .summary-card.cash {
            border-left-color: #38a169;
        }

        .summary-card.card {
            border-left-color: #3182ce;
        }

        .summary-card.bank {
            border-left-color: #805ad5;
        }

        .summary-card.refund {
            border-left-color: #e53e3e;
        }

        .summary-card.total {
            background: #364150;
            color: white;
            border-left-color: #667eea;
        }

        .summary-label {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
            color: #4a5568;
        }

        .summary-card.total .summary-label {
            color: #e2e8f0;
        }

        .summary-amount {
            font-size: 16px;
            font-weight: bold;
            color: #2d3748;
        }

        .summary-card.total .summary-amount {
            color: white;
        }

        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
            font-size: 14px;
            font-style: italic;
        }

        /* Page Break Control */
        .page-break {
            page-break-before: always;
        }

        .no-break {
            page-break-inside: avoid;
        }

        /* Print Specific */
        @media print {
            body {
                font-size: 11px;
            }
            
            .page-container {
                max-width: none;
                width: 100%;
            }
            
            .data-table {
                font-size: 9px;
            }
            
            .data-table th,
            .data-table td {
                padding: 6px 4px;
            }
        }

        /* Footer */
        .report-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #718096;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Header Section -->
        <div class="report-header no-break">
            <div class="header-flex">
                <div class="header-left">
                    <img src="https://crm2.cutera.pk/public/assets/media/new_logo.png" alt="Cutera Aesthetics" class="logo">
                    <div class="company-name">CUTERA AESTHETICS</div>
                    <div class="company-tagline">Premium Healthcare Solutions</div>
                </div>
                <div class="header-right">
                    <div class="report-meta">
                        <div class="report-title">Sales Detail Report</div>
                        <div class="meta-row">
                            <span class="meta-label">Duration:</span>
                            <strong>{{ $start_date }} to {{ $end_date }}</strong>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Generated:</span>
                            <strong>{{ Carbon\Carbon::now()->format('M d, Y') }}</strong>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Time:</span>
                            <strong>{{ Carbon\Carbon::now()->format('h:i A') }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Body -->
        <div class="report-body">
            <h2 class="section-title">Transaction Details</h2>
            
            @php
                $total_revenue_cash_location = 0;
                $total_revenue_card_location = 0;
                $total_revenue_bank_location = 0;
                $total_refund_location = 0;
            @endphp

            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 8%">ID</th>
                        <th style="width: 18%">Patient Name</th>
                        <th style="width: 10%">City</th>
                        <th style="width: 12%">Region</th>
                        <th style="width: 8%">Gender</th>
                        <th style="width: 10%">Trans. Type</th>
                        <th style="width: 10%">Cash</th>
                        <th style="width: 10%">Card</th>
                        <th style="width: 10%">Bank</th>
                        <th style="width: 10%">Refund</th>
                        <th style="width: 12%">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @if($report_data)
                        @foreach($report_data as $reportlocation)
                            <tr class="location-header">
                                <td colspan="11">
                                    <strong>{{ $reportlocation['name'] }}</strong> - {{ $reportlocation['city'] }}, {{ $reportlocation['region'] }}
                                </td>
                            </tr>
                            @foreach($reportlocation['revenue_data'] as $reportRow)
                                @php
                                    $total_revenue_cash_location += $reportRow['revenue_cash_in'] ? $reportRow['revenue_cash_in'] : 0;
                                    $total_revenue_card_location += $reportRow['revenue_card_in'] ? $reportRow['revenue_card_in'] : 0;
                                    $total_revenue_bank_location += $reportRow['revenue_bank_in'] ? $reportRow['revenue_bank_in'] : 0;
                                    $total_refund_location += $reportRow['refund_out'] ? $reportRow['refund_out'] : 0;
                                @endphp
                                <tr>
                                    <td><strong>{{ $reportRow['patient_id'] }}</strong></td>
                                    <td>{{ $reportRow['patient'] }}</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>
                                        <span class="badge {{ strtolower($reportRow['gender']) }}">
                                            {{ ucfirst($reportRow['gender']) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge advance">
                                            {{ $reportRow['transtype'] }}
                                        </span>
                                    </td>
                                    <td class="amount">
                                        @if($reportRow['revenue_cash_in'])
                                            <span class="positive">{{ number_format($reportRow['revenue_cash_in'], 2) }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="amount">
                                        @if($reportRow['revenue_card_in'])
                                            <span class="positive">{{ number_format($reportRow['revenue_card_in'], 2) }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="amount">
                                        @if($reportRow['revenue_bank_in'])
                                            <span class="positive">{{ number_format($reportRow['revenue_bank_in'], 2) }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="amount">
                                        @if($reportRow['refund_out'])
                                            <span class="negative">{{ number_format($reportRow['refund_out'], 2) }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $reportRow['created_at'] }}</td>
                                </tr>
                            @endforeach
                            <tr class="location-total">
                                <td colspan="6"><strong>{{ $reportlocation['name'] }} - TOTAL</strong></td>
                                <td class="amount"><strong>{{ number_format($total_revenue_cash_location, 2) }}</strong></td>
                                <td class="amount"><strong>{{ number_format($total_revenue_card_location, 2) }}</strong></td>
                                <td class="amount"><strong>{{ number_format($total_revenue_bank_location, 2) }}</strong></td>
                                <td class="amount"><strong>{{ number_format($total_refund_location, 2) }}</strong></td>
                                <td class="amount"><strong>{{ number_format(($total_revenue_cash_location + $total_revenue_card_location + $total_revenue_bank_location) - $total_refund_location, 2) }}</strong></td>
                            </tr>
                            @php
                                $total_revenue_cash_location = 0;
                                $total_revenue_card_location = 0;
                                $total_revenue_bank_location = 0;
                                $total_refund_location = 0;
                            @endphp
                        @endforeach
                    @else
                        <tr>
                            <td colspan="11" class="no-data">
                                <strong>No records found for the selected period</strong>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <!-- Financial Summary -->
            <div class="summary-container no-break">
                <h2 class="section-title">Financial Summary</h2>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th style="width: 70%">Revenue Category</th>
                            <th style="width: 30%">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Cash Revenue</strong></td>
                            <td class="amount-cell">{{ number_format($total_revenue_cash_in ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Card Revenue</strong></td>
                            <td class="amount-cell">{{ number_format($total_revenue_card_in ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Bank/Wire Transfer</strong></td>
                            <td class="amount-cell">{{ number_format($total_revenue_bank_in ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Total Revenue</strong></td>
                            <td class="amount-cell">{{ number_format($total_revenue ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Total Refunds</strong></td>
                            <td class="amount-cell" style="color: #c53030;">{{ number_format($total_refund ?? 0, 2) }}</td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>NET REVENUE (In Hand Balance)</strong></td>
                            <td class="amount-cell"><strong>{{ number_format(($total_revenue ?? 0) - ($total_refund ?? 0), 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="report-footer">
            <p>Generated by Cutera Aesthetics CRM System | {{ Carbon\Carbon::now()->format('F j, Y \a\t g:i A') }}</p>
        </div>
    </div>
</body>
</html>