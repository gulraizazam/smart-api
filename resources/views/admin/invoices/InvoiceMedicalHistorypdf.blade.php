<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Consultation Form — {{ $account->name ?? 'Cutera Aesthetics' }}</title>
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
            font-size: 12px;
            line-height: 1.4;
            -webkit-font-smoothing: antialiased;
        }

        /* A4-friendly canvas. Comfortable typography while still
           guaranteeing single-page output — the room comes from
           tighter section margins and a denser checklist, not
           shrunken type. */
        .invoice-pdf {
            background: var(--elevated);
            max-width: 820px;
            margin: 16px auto;
            padding: 22px 30px 18px;
            border: 1px solid var(--hairline);
            border-radius: 10px;
        }

        /* ───────── Top contact strip ───────── */
        .top-strip {
            text-align: right;
            color: var(--fg-subtle);
            font-size: 10.5px;
            letter-spacing: 0.3px;
            margin-bottom: 8px;
        }
        .top-strip span { white-space: nowrap; }
        .top-strip .sep {
            display: inline-block;
            margin: 0 6px;
            color: var(--hairline);
        }

        /* ───────── Brand row ───────── */
        .brand-row {
            display: table;
            width: 100%;
            margin-bottom: 14px;
        }
        .brand-row .brand,
        .brand-row .ref {
            display: table-cell;
            vertical-align: middle;
        }
        .brand-row .ref {
            text-align: right;
            width: 240px;
        }
        .logo {
            width: 195px;
            display: block;
            margin: 0 0 3px -2px;
        }
        .ref .stamp {
            display: inline-block;
            background: var(--ink);
            color: #fff;
            padding: 5px 14px;
            font-size: 11.5px;
            letter-spacing: 2.5px;
            font-weight: 600;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .ref .number {
            font-size: 10.5px;
            color: var(--fg-subtle);
        }
        .ref .number strong {
            color: var(--fg);
            font-weight: 600;
        }

        /* ───────── Client info card ───────── */
        .info-card {
            background: var(--surface);
            border: 1px solid var(--hairline);
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 12px;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-grid .info-cell {
            display: table-cell;
            width: 33.33%;
            vertical-align: top;
            padding-right: 14px;
        }
        .info-grid .info-cell:last-child { padding-right: 0; }
        .info-row { margin: 3px 0; }
        .info-label {
            display: inline-block;
            width: 74px;
            font-size: 10.5px;
            color: var(--fg-subtle);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .info-value {
            display: inline-block;
            color: var(--fg);
            font-size: 12px;
            font-weight: 500;
        }
        .info-value .blank {
            display: inline-block;
            min-width: 120px;
            border-bottom: 1px dotted var(--hairline);
            height: 13px;
        }

        /* ───────── Cancellation watermark ───────── */
        .cancel-mark {
            text-align: center;
            margin: 6px 0;
        }
        .cancel-mark img { width: 18%; }

        /* ───────── Tables (line items / advised) ───────── */
        .items {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 12px;
            border: 1px solid var(--hairline);
            border-radius: 6px;
            overflow: hidden;
        }
        .items thead th {
            background: var(--surface);
            color: var(--fg-subtle);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-align: left;
            padding: 7px 10px;
            border-bottom: 1px solid var(--hairline);
        }
        .items tbody td {
            padding: 8px 10px;
            font-size: 12px;
            color: var(--fg);
            border-bottom: 1px solid var(--hairline-2);
            vertical-align: top;
        }
        .items tbody tr:last-child td { border-bottom: none; }
        .items .num-col { text-align: right; }

        /* Treatment-recommended grid: hand-fillable rows with enough
           height to write a short note. */
        .recommend {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 5px 0 12px;
        }
        .recommend th {
            background: var(--surface);
            color: var(--fg-subtle);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-align: left;
            padding: 7px 9px;
            border: 1px solid var(--hairline);
        }
        .recommend td {
            border: 1px solid var(--hairline);
            padding: 22px 9px;
            font-size: 12px;
            color: var(--fg);
            vertical-align: top;
        }

        /* ───────── Section banner ───────── */
        .banner {
            background: var(--surface);
            border: 1px solid var(--hairline);
            border-radius: 5px;
            padding: 7px 12px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--fg);
            text-align: center;
            margin: 12px 0 6px;
            letter-spacing: 0.2px;
        }
        .banner.thin {
            font-weight: 500;
            color: var(--fg-muted);
            line-height: 1.35;
        }

        /* ───────── Health checklist ───────── */
        .checklist {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 10px;
            border: 1px solid var(--hairline);
            border-radius: 6px;
            overflow: hidden;
        }
        .checklist td {
            padding: 6px 10px;
            font-size: 11.5px;
            color: var(--fg);
            border-bottom: 1px solid var(--hairline-2);
            border-right: 1px solid var(--hairline-2);
            vertical-align: middle;
            width: 33.33%;
        }
        .checklist td:last-child { border-right: none; }
        .checklist tr:last-child td { border-bottom: none; }
        .checkbox {
            width: 13px;
            height: 13px;
            display: inline-block;
            border: 1.1px solid var(--ink);
            border-radius: 2px;
            vertical-align: -2px;
            margin-right: 6px;
        }

        /* ───────── Notes block ───────── */
        .notes {
            border: 1px solid var(--hairline);
            border-radius: 6px;
            padding: 8px 12px;
            background: var(--elevated);
            margin-bottom: 10px;
        }
        .notes .line {
            border-bottom: 1px dotted var(--hairline);
            height: 20px;
        }
        .notes .line:last-child { border-bottom: none; }

        /* ───────── Footer signature row ───────── */
        .footer {
            display: table;
            width: 100%;
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid var(--hairline);
        }
        .footer .left,
        .footer .right {
            display: table-cell;
            vertical-align: top;
            font-size: 11px;
            color: var(--fg-muted);
        }
        .footer .right { text-align: right; }
        .footer .sig-line {
            display: inline-block;
            min-width: 200px;
            border-bottom: 1px solid var(--ink);
            margin-left: 8px;
            height: 13px;
        }
        .footer .legal {
            display: block;
            color: var(--fg-subtle);
            margin-top: 5px;
            font-size: 10.5px;
        }
        .footer .legal strong { color: var(--fg); font-weight: 600; }

        /* ───────── Print rules ─────────
           Margin trimmed and shrink-to-fit forced so the form lands on
           one page even when the browser's default print scaling is on. */
        @if($download != 'download')
            @media print {
                body { background: #fff; }
                .invoice-pdf {
                    margin: 0;
                    border: none;
                    border-radius: 0;
                    padding: 8px 12px;
                    max-width: none;
                }
                /* Hard guarantee — no element nudges to a second sheet. */
                .invoice-pdf,
                .invoice-pdf * {
                    page-break-inside: avoid;
                }
            }
            @page {
                size: A4 portrait;
                margin: 8mm 8mm;
            }
        @endif
    </style>
</head>
<body>
<div class="invoice-pdf">

    {{-- ────────── Top contact strip ────────── --}}
    <div class="top-strip">
        <span>Phone {{ $company_phone_number->data }}</span>
        <span class="sep">|</span>
        <span>Email {{ $account->email }}</span>
    </div>

    {{-- Cancellation watermark, optional. --}}
    @if($invoicestatus->slug == 'cancelled')
        <div class="cancel-mark">
            <img src="{{ asset('assets/media/cancld.png') }}" alt="Cancelled"/>
        </div>
    @endif

    {{-- ────────── Brand row ────────── --}}
    <div class="brand-row">
        <div class="brand">
            <img class="logo" src="{{ !empty($download) ? public_path('assets/media/new_logo.png') : asset('assets/media/new_logo.png') }}" alt="{{ $account->name ?? 'Cutera Aesthetics' }}"/>
        </div>
        <div class="ref">
            <span class="stamp">CONSULTATION</span><br/>
            <span class="number">No. <strong>#{{ $Invoiceinfo->id }}</strong> &nbsp;·&nbsp; {{ \Carbon\Carbon::parse($Invoiceinfo->created_at)->format('F j, Y') }}</span>
        </div>
    </div>

    {{-- ────────── Client + clinic info ────────── --}}
    <div class="info-card">
        <div class="info-grid">
            <div class="info-cell">
                <div class="info-row">
                    <span class="info-label">Name</span>
                    <span class="info-value">{{ $patient->name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value">C-{{ $patient->id }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age</span>
                    <span class="info-value"><span class="blank"></span></span>
                </div>
            </div>
            <div class="info-cell">
                <div class="info-row">
                    <span class="info-label">Height</span>
                    <span class="info-value"><span class="blank"></span></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Weight</span>
                    <span class="info-value"><span class="blank"></span></span>
                </div>
                <div class="info-row">
                    <span class="info-label">BMI</span>
                    <span class="info-value"><span class="blank"></span></span>
                </div>
            </div>
            <div class="info-cell">
                <div class="info-row">
                    <span class="info-label">Consultant</span>
                    <span class="info-value">{{ $appointment_info->doctor->name ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Centre</span>
                    <span class="info-value">{{ $location_info->name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact</span>
                    <span class="info-value">{{ $location_info->fdo_phone }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ────────── Service line ────────── --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:32px;">#</th>
                <th>Consultancy / Service</th>
                <th class="num-col">Service Price</th>
                <th>Discount</th>
                <th>Type</th>
                <th class="num-col">Disc. Price</th>
                <th class="num-col">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>{{ $service->name }}</td>
                <td class="num-col">
                    @if($Invoiceinfo->is_exclusive == '0')
                        @if($Invoiceinfo->service_price == '0')
                            {{ number_format($Invoiceinfo->tax_including_price) }}
                        @else
                            {{ number_format(($Invoiceinfo->service_price)-($Invoiceinfo->tax_price)) }}
                        @endif
                    @elseif($Invoiceinfo->is_exclusive == '1')
                        @if($Invoiceinfo->service_price == '0')
                            {{ number_format($Invoiceinfo->tax_including_price) }}
                        @else
                            {{ number_format($Invoiceinfo->service_price) }}
                        @endif
                    @endif
                </td>
                <td>{{ $discount?->name ?? '—' }}</td>
                <td>{{ $Invoiceinfo->discount_type ?? '—' }}</td>
                <td class="num-col">
                    @if($Invoiceinfo->discount_price != null)
                        {{ number_format($Invoiceinfo->discount_price) }}
                    @else
                        —
                    @endif
                </td>
                <td class="num-col" style="font-weight:600;color:var(--ink);">
                    {{ number_format($Invoiceinfo->tax_including_price) }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- ────────── Health history checklist ────────── --}}
    <div class="banner">Health History — please check all that apply</div>
    <table class="checklist">
        <tr>
            <td><span class="checkbox"></span> Illness or injury within 5 years</td>
            <td><span class="checkbox"></span> History of heart disease</td>
            <td><span class="checkbox"></span> History of seizures or epilepsy</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> Any surgeries done</td>
            <td><span class="checkbox"></span> Heart surgery / prosthesis / stents</td>
            <td><span class="checkbox"></span> Skin disease</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> History of cardiovascular problems</td>
            <td><span class="checkbox"></span> Dental implants / bridge / Ti plates</td>
            <td><span class="checkbox"></span> High blood pressure</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> Anemia</td>
            <td><span class="checkbox"></span> History of hernia / hernia surgery</td>
            <td><span class="checkbox"></span> Hormonal disorders / hormonal therapy</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> Kidney disease or dialysis</td>
            <td><span class="checkbox"></span> Psychiatric disorders / depression</td>
            <td><span class="checkbox"></span> Polycystic ovaries</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> Nervous disorders</td>
            <td><span class="checkbox"></span> HIV / AIDS</td>
            <td><span class="checkbox"></span> Fibroids</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> Thyroid disorders</td>
            <td><span class="checkbox"></span> Hepatitis</td>
            <td><span class="checkbox"></span> Pregnancy</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> Liver disease</td>
            <td><span class="checkbox"></span> Cushing's Syndrome</td>
            <td><span class="checkbox"></span> Cancer</td>
        </tr>
        <tr>
            <td><span class="checkbox"></span> History of drug or alcohol use</td>
            <td><span class="checkbox"></span> Diabetes</td>
            <td><span class="checkbox"></span> Others</td>
        </tr>
    </table>

    {{-- ────────── Notes block ────────── --}}
    <div class="banner thin">
        Please explain any marked answer — fully describe diagnosis, physician, treatment, medication.
        Also list any medications currently taken or recently used.
    </div>
    <div class="notes">
        <div class="line"></div>
        <div class="line"></div>
        <div class="line"></div>
        <div class="line"></div>
    </div>

    {{-- ────────── Treatment recommended ────────── --}}
    <div class="banner">Treatment Recommended</div>
    <table class="recommend">
        <tr>
            <th colspan="2">Treatment Advised</th>
            <th>No. of Sessions</th>
            <th>Retail Price</th>
            <th>Discount %</th>
            <th>Price Offered</th>
            <th>Willing to Pay</th>
            <th>Converted?</th>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>

    {{-- ────────── Footer ────────── --}}
    <div class="footer">
        <div class="left">
            <strong style="color:var(--fg);">Consultant signature</strong>
            <span class="sig-line"></span>
            <span class="legal">{{ $appointment_info->doctor->name ?? '' }}</span>
        </div>
        <div class="right">
            {{ $location_info->address }}
            <span class="legal"><strong>NTN</strong> {{ $location_info->ntn }} &nbsp;·&nbsp; <strong>STN</strong> {{ $location_info->stn }}</span>
        </div>
    </div>
</div>

<script>
    window.onload = function() { window.print(); };
    window.onafterprint = function() { window.close(); };
</script>
</body>
</html>
