<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PO Statement of Account</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 24px; color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1, h2, p { margin: 0; }
        .header { width: 100%; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .business { font-size: 17px; font-weight: bold; color: #0f766e; }
        .title { font-size: 18px; font-weight: bold; text-align: right; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
        .bill-to { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .bill-to td { width: 50%; padding: 9px; vertical-align: top; border: 1px solid #d1d5db; }
        .label { margin-bottom: 3px; color: #6b7280; font-size: 8px; text-transform: uppercase; }
        .customer { font-size: 13px; font-weight: bold; }
        .summary { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .summary td { width: 25%; padding: 8px; border: 1px solid #d1d5db; }
        .summary .value { margin-top: 3px; font-size: 12px; font-weight: bold; }
        .amount-due { color: #b91c1c; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { padding: 6px 5px; border: 1px solid #d1d5db; }
        table.data th { background: #f3f4f6; font-size: 8px; text-align: left; text-transform: uppercase; }
        table.data th.right { text-align: right; }
        table.data tfoot td { background: #f3f4f6; font-weight: bold; }
        .section-title { margin: 16px 0 6px; font-size: 12px; font-weight: bold; }
        .empty { padding: 18px !important; color: #6b7280; text-align: center; }
        .note { margin-top: 14px; padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; color: #4b5563; }
        .footer { position: fixed; right: 0; bottom: -8px; left: 0; color: #6b7280; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    @php
        $currency = $settings->currency ?? 'PHP';
        $statementNumber = 'SOA-'.str_pad((string) $customer->id, 5, '0', STR_PAD_LEFT).'-'.\Illuminate\Support\Carbon::parse($dateTo)->format('Ymd');
    @endphp

    <table class="header">
        <tr>
            <td>
                <div class="business">{{ $settings->business_name ?? 'Spin Klean Laundry' }}</div>
                <div class="muted">{{ $settings->business_address }}</div>
                <div class="muted">{{ collect([$settings->contact_number, $settings->business_email])->filter()->implode(' | ') }}</div>
            </td>
            <td class="right">
                <div class="title">STATEMENT OF ACCOUNT</div>
                <div><strong>{{ $statementNumber }}</strong></div>
                <div class="muted">Generated {{ ($generatedAt ?? now())->format('M d, Y h:i A') }}</div>
            </td>
        </tr>
    </table>

    <table class="bill-to">
        <tr>
            <td>
                <div class="label">Bill To</div>
                <div class="customer">{{ $customer->name }}</div>
                <div>{{ $customer->address ?: 'No address recorded' }}</div>
                <div>{{ collect([$customer->phone, $customer->email])->filter()->implode(' | ') }}</div>
            </td>
            <td>
                <div class="label">Statement Period</div>
                <div><strong>{{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M d, Y') }}</strong></div>
                <div class="muted">Branch: {{ $selectedBranchId ? ($branches->firstWhere('id', $selectedBranchId)?->name ?? 'Selected branch') : 'All branches' }}</div>
                <div class="muted">Status: {{ $selectedStatus ? \App\Support\StatusBadge::label($selectedStatus) : 'All active PO statuses' }}</div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td><div class="label">PO Transactions</div><div class="value">{{ number_format($summary['transactions']) }}</div></td>
            <td><div class="label">Total Charges</div><div class="value">{{ $currency }} {{ number_format((float) $summary['amount'], 2) }}</div></td>
            <td><div class="label">Total Paid</div><div class="value">{{ $currency }} {{ number_format((float) $summary['paid'], 2) }}</div></td>
            <td><div class="label">Amount Due</div><div class="value amount-due">{{ $currency }} {{ number_format((float) $summary['balance'], 2) }}</div></td>
        </tr>
    </table>

    <div class="section-title">PO Transactions</div>
    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th>PO Number</th>
                <th>Job Order</th>
                <th>Branch</th>
                <th>Status</th>
                <th class="right">Amount</th>
                <th class="right">Paid</th>
                <th class="right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->transaction_date?->format('M d, Y') }}</td>
                    <td>{{ $transaction->po_number }}</td>
                    <td>{{ $transaction->jobOrder?->job_order_number ?? 'N/A' }}</td>
                    <td>{{ $transaction->branch?->name ?? 'N/A' }}</td>
                    <td>{{ \App\Support\StatusBadge::label($transaction->status) }}</td>
                    <td class="right">{{ number_format((float) $transaction->amount, 2) }}</td>
                    <td class="right">{{ number_format((float) $transaction->paid_amount, 2) }}</td>
                    <td class="right">{{ number_format((float) $transaction->balance, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No active PO transactions found for this statement period.</td></tr>
            @endforelse
        </tbody>
        @if($transactions->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="5">Statement Total</td>
                    <td class="right">{{ number_format((float) $summary['amount'], 2) }}</td>
                    <td class="right">{{ number_format((float) $summary['paid'], 2) }}</td>
                    <td class="right">{{ number_format((float) $summary['balance'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if($payments->isNotEmpty())
        <div class="section-title">Payment History</div>
        <table class="data">
            <thead>
                <tr><th>Date</th><th>Payment Number</th><th>PO Number</th><th>Method</th><th>Reference</th><th class="right">Amount</th></tr>
            </thead>
            <tbody>
                @foreach($payments as $payment)
                    @php($paymentPo = $transactions->firstWhere('id', $payment->po_transaction_id))
                    <tr>
                        <td>{{ $payment->paid_at?->format('M d, Y') }}</td>
                        <td>{{ $payment->payment_number }}</td>
                        <td>{{ $paymentPo?->po_number ?? 'N/A' }}</td>
                        <td>{{ \App\Support\StatusBadge::label($payment->payment_method) }}</td>
                        <td>{{ $payment->reference_no ?: 'N/A' }}</td>
                        <td class="right">{{ $currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="note">
        This statement includes only active PO transactions. Canceled and deleted job orders are excluded from all charges and balances.
    </div>

    <div class="footer">{{ $settings->business_name ?? 'Spin Klean Laundry' }} | {{ $statementNumber }}</div>
</body>
</html>
