<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Cash Flow Monthly Report</title></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333;max-width:600px;margin:0 auto;">
    <h2 style="color:#3699FF;">Cash Flow Monthly Report</h2>
    <p style="color:#888;">{{ $data['month_label'] ?? 'Previous Month' }}</p>

    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <tr style="background:#C9F7F5;"><td style="padding:10px;border:1px solid #ddd;font-weight:bold;">Total Inflows</td><td style="padding:10px;border:1px solid #ddd;text-align:right;font-weight:bold;color:#1BC5BD;">PKR {{ number_format($data['total_inflows'] ?? 0) }}</td></tr>
        <tr style="background:#FFE2E5;"><td style="padding:10px;border:1px solid #ddd;font-weight:bold;">Total Outflows</td><td style="padding:10px;border:1px solid #ddd;text-align:right;font-weight:bold;color:#F64E60;">PKR {{ number_format($data['total_outflows'] ?? 0) }}</td></tr>
        <tr style="background:#EEE5FF;"><td style="padding:10px;border:1px solid #ddd;font-weight:bold;">Net Cash Flow</td><td style="padding:10px;border:1px solid #ddd;text-align:right;font-weight:bold;color:#8950FC;">PKR {{ number_format($data['net_cash_flow'] ?? 0) }}</td></tr>
    </table>

    @if(!empty($data['outflows']))
    <h3>Outflows by Category</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <tr style="background:#F3F6F9;"><th style="padding:6px;text-align:left;border:1px solid #ddd;">Category</th><th style="padding:6px;text-align:right;border:1px solid #ddd;">Amount</th><th style="padding:6px;text-align:right;border:1px solid #ddd;">Count</th></tr>
        @foreach($data['outflows'] as $row)
        <tr><td style="padding:6px;border:1px solid #ddd;">{{ $row['category'] ?? '' }}</td><td style="padding:6px;border:1px solid #ddd;text-align:right;">PKR {{ number_format($row['total'] ?? 0) }}</td><td style="padding:6px;border:1px solid #ddd;text-align:right;">{{ $row['count'] ?? 0 }}</td></tr>
        @endforeach
    </table>
    @endif

    @if(!empty($data['pool_breakdown']))
    <h3>Pool Balances</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <tr style="background:#F3F6F9;"><th style="padding:6px;text-align:left;border:1px solid #ddd;">Pool</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Type</th><th style="padding:6px;text-align:right;border:1px solid #ddd;">Balance</th></tr>
        @foreach($data['pool_breakdown'] as $pool)
        <tr><td style="padding:6px;border:1px solid #ddd;">{{ $pool['name'] ?? '' }}</td><td style="padding:6px;border:1px solid #ddd;">{{ $pool['type'] ?? '' }}</td><td style="padding:6px;border:1px solid #ddd;text-align:right;font-weight:bold;">PKR {{ number_format($pool['cached_balance'] ?? 0) }}</td></tr>
        @endforeach
    </table>
    @endif

    <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
    <p style="color:#aaa;font-size:12px;">This is an automated monthly report from Allura Aesthetics CRM Cash Flow Module.</p>
</body>
</html>
