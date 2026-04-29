<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Order Invoice — {{ $account->name ?? 'Cutera Aesthetics' }}</title>
    <meta content="Cutera Aesthetics — Medical Spa Order Invoice" name="description" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <style>
        /* ───────── Theme tokens (mirror the SPA) ───────── */
        :root {
            --fg:          #0f172a;
            --fg-muted:    #475569;
            --fg-subtle:   #94a3b8;
            --hairline:    #e2e8f0;
            --hairline-2:  #f1f5f9;
            --surface:     #f8fafc;
            --elevated:    #ffffff;
            --accent:      #0891b2;
            --accent-soft: #ecfeff;
            --ink:         #1e293b;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #f8fafc;
            color: var(--fg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .invoice-pdf {
            background: var(--elevated);
            max-width: 820px;
            margin: 32px auto;
            padding: 40px 44px 36px;
            border: 1px solid var(--hairline);
            border-radius: 12px;
        }

        /* ───────── Brand header ───────── */
        .brand-row {
            display: table;
            width: 100%;
            margin-bottom: 28px;
        }
        .brand-row .brand,
        .brand-row .invoice-mark {
            display: table-cell;
            vertical-align: top;
        }
        .brand-row .invoice-mark {
            text-align: right;
            width: 140px;
        }
        .logo {
            width: 220px;
            margin: 0 0 8px -2px;
            display: block;
        }
        .brand-address {
            margin: 0;
            color: var(--fg-muted);
            font-size: 11.5px;
        }
        .brand-meta {
            margin: 4px 0 0;
            color: var(--fg-subtle);
            font-size: 10.5px;
            letter-spacing: 0.2px;
        }
        .brand-meta .sep {
            display: inline-block;
            margin: 0 6px;
            color: var(--hairline);
        }
        .invoice-mark .stamp {
            display: inline-block;
            background: var(--ink);
            color: #fff;
            padding: 7px 18px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 4px;
            border-radius: 6px;
        }
        .invoice-mark .num {
            margin: 10px 0 0;
            font-size: 11px;
            color: var(--fg-subtle);
            letter-spacing: 0.4px;
        }
        .invoice-mark .num strong {
            color: var(--fg);
            font-weight: 600;
        }

        /* ───────── Meta strip ───────── */
        .meta-strip {
            display: table;
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--hairline);
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 24px;
        }
        .meta-strip .meta-cell {
            display: table-cell;
            vertical-align: top;
            padding-right: 18px;
        }
        .meta-strip .meta-cell:last-child {
            padding-right: 0;
            text-align: right;
        }
        .meta-label {
            display: block;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--fg-subtle);
            margin-bottom: 4px;
        }
        .meta-value {
            font-size: 13px;
            color: var(--fg);
            font-weight: 500;
        }
        .meta-value .sub {
            display: block;
            font-size: 11px;
            color: var(--fg-muted);
            font-weight: 400;
            margin-top: 2px;
        }

        /* ───────── Line items table ───────── */
        .items {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 24px;
            border: 1px solid var(--hairline);
            border-radius: 8px;
            overflow: hidden;
        }
        .items thead th {
            background: var(--surface);
            color: var(--fg-subtle);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid var(--hairline);
        }
        .items tbody td {
            padding: 12px;
            font-size: 12.5px;
            color: var(--fg);
            border-bottom: 1px solid var(--hairline-2);
            vertical-align: top;
        }
        .items tbody tr:last-child td {
            border-bottom: none;
        }
        .items .num-col { text-align: right; }
        .items .total-col {
            font-weight: 600;
            color: var(--ink);
        }

        /* ───────── Totals callout ───────── */
        .totals {
            display: table;
            width: 100%;
            margin-bottom: 32px;
        }
        .totals .totals-spacer { display: table-cell; }
        .totals .totals-card {
            display: table-cell;
            width: 280px;
            background: var(--surface);
            border: 1px solid var(--hairline);
            border-radius: 8px;
            padding: 14px 16px;
        }
        .totals-row {
            display: table;
            width: 100%;
            margin: 4px 0;
        }
        .totals-row .lbl,
        .totals-row .val {
            display: table-cell;
            font-size: 12px;
        }
        .totals-row .lbl {
            color: var(--fg-muted);
        }
        .totals-row .val {
            text-align: right;
            color: var(--fg);
            font-weight: 500;
        }
        .totals-row.grand {
            border-top: 1px solid var(--hairline);
            padding-top: 8px;
            margin-top: 8px;
        }
        .totals-row.grand .lbl,
        .totals-row.grand .val {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            padding-top: 6px;
        }

        /* ───────── Signatures ───────── */
        .signatures {
            display: table;
            width: 100%;
            margin-top: 56px;
        }
        .signatures .sig {
            display: table-cell;
            width: 50%;
            padding-top: 12px;
            vertical-align: top;
        }
        .signatures .sig:first-child { padding-right: 32px; }
        .signatures .sig:last-child  { padding-left: 32px; }
        .signatures .sig .line {
            border-top: 1px solid var(--ink);
            padding-top: 8px;
            font-size: 12px;
            color: var(--fg-muted);
        }
        .signatures .sig .name {
            display: block;
            font-size: 12.5px;
            color: var(--fg);
            font-weight: 600;
            margin-top: 4px;
        }

        /* ───────── Print rules ───────── */
        @if ($download != 'download')
            @media print {
                body { background: #fff; }
                .invoice-pdf {
                    margin: 0;
                    border: none;
                    border-radius: 0;
                    padding: 24px 28px;
                    max-width: none;
                }
            }
            @page {
                size: auto;
                margin: 14mm 12mm;
            }
        @endif
    </style>
</head>
<body>
<div class="invoice-pdf">

    {{-- ────────── Brand header ────────── --}}
    <div class="brand-row">
        <div class="brand">
            <img class="logo" src="{{ !empty($download) ? public_path('assets/media/new_logo.png') : asset('assets/media/new_logo.png') }}" alt="{{ $account->name ?? 'Cutera Aesthetics' }}"/>
            <p class="brand-address">{{ $location_info->address }}</p>
            <p class="brand-meta">
                Phone {{ $location_info->fdo_phone }}
                <span class="sep">|</span> Email {{ $account->email }}
                <span class="sep">|</span> www.cuteraesthetics.com
                <span class="sep">|</span> NTN {{ $location_info->ntn }}
                <span class="sep">|</span> STN {{ $location_info->stn }}
            </p>
        </div>
        <div class="invoice-mark">
            <span class="stamp">INVOICE</span>
            <p class="num">Order No. <strong>#{{ $invoice_info->id }}</strong></p>
        </div>
    </div>

    {{-- ────────── Meta strip ────────── --}}
    <div class="meta-strip">
        <div class="meta-cell">
            <span class="meta-label">Issued</span>
            <div class="meta-value">
                {{ \Carbon\Carbon::parse($invoice_info->created_at)->format('F j, Y') }}
                <span class="sub">{{ \Carbon\Carbon::parse($invoice_info->created_at)->format('h:i a') }}</span>
            </div>
        </div>
        <div class="meta-cell">
            <span class="meta-label">Patient</span>
            <div class="meta-value">
                {{ ucfirst($patient->name) }}
                <span class="sub">C-{{ $patient->id }}</span>
            </div>
        </div>
        <div class="meta-cell">
            <span class="meta-label">Centre</span>
            <div class="meta-value">
                {{ $location_info->name }}
                <span class="sub">{{ $location_info->fdo_phone }}</span>
            </div>
        </div>
    </div>

    {{-- ────────── Line items ────────── --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:32px;">#</th>
                <th>Product</th>
                <th class="num-col">Unit Price</th>
                <th class="num-col">Qty</th>
                <th class="num-col">Discount %</th>
                <th class="num-col">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice_info->orderDetail as $product)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $product->product->name }}</td>
                    <td class="num-col">{{ number_format((float) $product->sale_price) }}</td>
                    <td class="num-col">{{ $product->quantity }}</td>
                    <td class="num-col">{{ $invoice_info->discount ?? 0 }}</td>
                    <td class="num-col total-col">{{ number_format((float) $product->sale_price * (int) $product->quantity) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ────────── Totals callout ────────── --}}
    <div class="totals">
        <div class="totals-spacer"></div>
        <div class="totals-card">
            <div class="totals-row grand">
                <div class="lbl">Grand Total</div>
                <div class="val">Rs. {{ number_format((float) $invoice_info->total_price) }}</div>
            </div>
        </div>
    </div>

    {{-- ────────── Signatures ────────── --}}
    <div class="signatures">
        <div class="sig">
            <div class="line">Customer signature</div>
            <span class="name">{{ ucfirst($patient->name) }}</span>
        </div>
        <div class="sig">
            <div class="line">Authorised signatory</div>
            <span class="name">{{ $account->name ?? '—' }}</span>
        </div>
    </div>
</div>

<script>
    window.onload = function() { window.print(); };
    window.onafterprint = function() { window.close(); };
</script>
</body>
</html>
