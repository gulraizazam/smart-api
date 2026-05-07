<!DOCTYPE html>
{{-- Generic tabular-report PDF template.
     Used by SPA-driven export endpoints (DoctorRatings, Memberships, …)
     so each report doesn't need its own PDF-specific Blade.
     Variables expected:
       $title         — string, page title
       $subtitle      — optional string (e.g. date range / filter summary)
       $headings      — string[]
       $rows          — array<array<scalar|null>>  (in heading order)
       $totalsRow     — optional array<scalar|null> rendered as a bold totals
                        row directly below the table (in heading order)
       $summaryBlock  — optional array<array{label: string, value: scalar|null,
                        bold?: bool}> rendered as a key/value breakdown panel
                        below the table (used by sales reports for the
                        Cash / Card / Bank / Refund / Net split)
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2937; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 4px; color: #0f172a; }
        .subtitle { font-size: 10px; color: #64748b; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #f1f5f9; color: #334155; font-size: 9.5px; font-weight: 700;
            text-align: left; border: 1px solid #cbd5e1; padding: 6px 8px; text-transform: uppercase; letter-spacing: 0.04em;
        }
        tbody td { border: 1px solid #e2e8f0; padding: 5px 8px; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        tfoot td {
            background: #eef2f7; border: 1px solid #cbd5e1; padding: 6px 8px;
            font-weight: 700; color: #0f172a;
        }
        .summary { width: 320px; margin-top: 16px; border-collapse: collapse; }
        .summary td {
            border: 1px solid #e2e8f0; padding: 5px 8px; font-size: 10px;
        }
        .summary td:first-child { color: #334155; font-weight: 700; background: #f8fafc; width: 60%; }
        .summary td:last-child { text-align: right; color: #0f172a; font-variant-numeric: tabular-nums; }
        .summary tr.bold td { font-weight: 700; background: #eef2f7; }
        .meta { color: #64748b; font-size: 9px; margin-top: 12px; text-align: right; }
        .empty { padding: 30px; text-align: center; color: #64748b; font-style: italic; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    @if(!empty($subtitle))
        <div class="subtitle">{{ $subtitle }}</div>
    @endif

    @if (empty($rows))
        <div class="empty">No rows for the selected filters.</div>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($headings as $h)
                        <th>{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell ?? '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if(!empty($totalsRow ?? null))
                <tfoot>
                    <tr>
                        @foreach ($totalsRow as $cell)
                            <td>{{ $cell ?? '' }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>

        @if(!empty($summaryBlock ?? null))
            <table class="summary">
                @foreach ($summaryBlock as $entry)
                    <tr class="{{ !empty($entry['bold']) ? 'bold' : '' }}">
                        <td>{{ $entry['label'] }}</td>
                        <td>{{ $entry['value'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endif

    <div class="meta">
        Generated {{ date('Y-m-d H:i') }} · {{ count($rows) }} {{ count($rows) === 1 ? 'row' : 'rows' }}
    </div>
</body>
</html>
