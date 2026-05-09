<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Cash Flow Daily Digest</title></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333;max-width:600px;margin:0 auto;">
    <h2 style="color:#3699FF;">Cash Flow Daily Digest</h2>
    <p style="color:#888;">{{ now()->format('l, d M Y') }}</p>

    @if(!empty($data['flagged_entries']))
    <h3 style="color:#F64E60;">Flagged Entries ({{ count($data['flagged_entries']) }})</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <tr style="background:#FFF4DE;"><th style="padding:6px;text-align:left;border:1px solid #ddd;">Date</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Amount</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Flag Reason</th></tr>
        @foreach($data['flagged_entries'] as $e)
        <tr><td style="padding:6px;border:1px solid #ddd;">{{ $e['expense_date'] ?? '' }}</td><td style="padding:6px;border:1px solid #ddd;">PKR {{ number_format($e['amount'] ?? 0) }}</td><td style="padding:6px;border:1px solid #ddd;">{{ $e['flag_reason'] ?? '' }}</td></tr>
        @endforeach
    </table>
    @endif

    @if(!empty($data['pending_approvals']))
    <h3 style="color:#FFA800;">Pending Approvals ({{ count($data['pending_approvals']) }})</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <tr style="background:#FFF4DE;"><th style="padding:6px;text-align:left;border:1px solid #ddd;">Date</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Amount</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Description</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Age</th></tr>
        @foreach($data['pending_approvals'] as $e)
        <tr><td style="padding:6px;border:1px solid #ddd;">{{ $e['expense_date'] ?? '' }}</td><td style="padding:6px;border:1px solid #ddd;">PKR {{ number_format($e['amount'] ?? 0) }}</td><td style="padding:6px;border:1px solid #ddd;">{{ $e['description'] ?? '' }}</td><td style="padding:6px;border:1px solid #ddd;">{{ $e['age_days'] ?? 0 }}d</td></tr>
        @endforeach
    </table>
    @endif

    @if(!empty($data['rejected_entries']))
    <h3 style="color:#F64E60;">Rejected Entries ({{ count($data['rejected_entries']) }})</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <tr style="background:#FFE2E5;"><th style="padding:6px;text-align:left;border:1px solid #ddd;">Date</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Amount</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Reason</th></tr>
        @foreach($data['rejected_entries'] as $e)
        <tr><td style="padding:6px;border:1px solid #ddd;">{{ $e['expense_date'] ?? '' }}</td><td style="padding:6px;border:1px solid #ddd;">PKR {{ number_format($e['amount'] ?? 0) }}</td><td style="padding:6px;border:1px solid #ddd;">{{ $e['rejection_reason'] ?? '' }}</td></tr>
        @endforeach
    </table>
    @endif

    @if(!empty($data['self_approved']))
    <h3 style="color:#FFA800;">Admin Self-Approved ({{ count($data['self_approved']) }})</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <tr style="background:#FFF4DE;"><th style="padding:6px;text-align:left;border:1px solid #ddd;">Date</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Amount</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Description</th></tr>
        @foreach($data['self_approved'] as $e)
        <tr><td style="padding:6px;border:1px solid #ddd;">{{ $e['expense_date'] ?? '' }}</td><td style="padding:6px;border:1px solid #ddd;">PKR {{ number_format($e['amount'] ?? 0) }}</td><td style="padding:6px;border:1px solid #ddd;">{{ $e['description'] ?? '' }}</td></tr>
        @endforeach
    </table>
    @endif

    @if(!empty($data['voided_entries']))
    <h3 style="color:#F64E60;">Voided Entries ({{ count($data['voided_entries']) }})</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <tr style="background:#FFE2E5;"><th style="padding:6px;text-align:left;border:1px solid #ddd;">Date</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Amount</th><th style="padding:6px;text-align:left;border:1px solid #ddd;">Void Reason</th></tr>
        @foreach($data['voided_entries'] as $e)
        <tr><td style="padding:6px;border:1px solid #ddd;">{{ $e['expense_date'] ?? '' }}</td><td style="padding:6px;border:1px solid #ddd;">PKR {{ number_format($e['amount'] ?? 0) }}</td><td style="padding:6px;border:1px solid #ddd;">{{ $e['void_reason'] ?? '' }}</td></tr>
        @endforeach
    </table>
    @endif

    @if(empty($data['flagged_entries']) && empty($data['pending_approvals']) && empty($data['rejected_entries']) && empty($data['self_approved']) && empty($data['voided_entries']))
    <p style="color:#1BC5BD;font-weight:bold;">All clear — no flagged, pending, rejected, self-approved, or voided entries.</p>
    @endif

    <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
    <p style="color:#aaa;font-size:12px;">This is an automated digest from Allura Aesthetics CRM Cash Flow Module.</p>
</body>
</html>
