<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Job Orders</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 11px; margin: 18px; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .muted { color: #666; }
        .meta { margin-bottom: 10px; }
        .summary { width: 100%; border-collapse: collapse; margin: 10px 0 14px; }
        .summary td { border: 1px solid #ccc; padding: 6px 8px; }
        .summary .label { color: #666; font-size: 10px; text-transform: uppercase; }
        .summary .value { font-size: 13px; font-weight: bold; }
        table.orders { width: 100%; border-collapse: collapse; }
        table.orders th, table.orders td { border: 1px solid #ccc; padding: 5px 6px; }
        table.orders th { background: #f3f4f6; text-align: left; font-size: 10px; text-transform: uppercase; }
        .right { text-align: right; }
        tfoot td { font-weight: bold; background: #f3f4f6; }
    </style>
</head>
<body>
    @php $currency = $settings->currency ?? 'PHP'; @endphp

    <h1>{{ $settings->business_name ?? 'Spin Klean Laundry' }}</h1>
    <div class="meta">
        <div><strong>Customer Job Orders</strong></div>
        <div>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</div>
        <div class="muted">
            {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M d, Y') }}
            · Generated {{ ($generatedAt ?? now())->format('M d, Y h:i A') }}
        </div>
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Job Orders</div><div class="value">{{ number_format($summary['orders']) }}</div></td>
            <td><div class="label">Total</div><div class="value">{{ $currency }} {{ number_format((float) $summary['total'], 2) }}</div></td>
            <td><div class="label">Paid</div><div class="value">{{ $currency }} {{ number_format((float) $summary['paid'], 2) }}</div></td>
            <td><div class="label">Balance</div><div class="value">{{ $currency }} {{ number_format((float) $summary['balance'], 2) }}</div></td>
        </tr>
    </table>

    <table class="orders">
        <thead>
            <tr>
                <th>Job Order</th>
                <th>Date</th>
                <th>Branch</th>
                <th>Items</th>
                <th>Status</th>
                <th class="right">Total</th>
                <th class="right">Paid</th>
                <th class="right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                <tr>
                    <td>{{ $order->job_order_number }}{{ $order->is_rush ? ' (Rush)' : '' }}</td>
                    <td>{{ $order->created_at?->format('M d, Y') }}</td>
                    <td>{{ $order->branch?->name }}</td>
                    <td>{{ $order->items->map(fn ($i) => ($i->service?->name ?: $i->description).' x'.rtrim(rtrim(number_format((float) $i->quantity, 2), '0'), '.'))->implode(', ') ?: '—' }}</td>
                    <td>{{ \Illuminate\Support\Str::of($order->status)->replace('_', ' ')->title() }}</td>
                    <td class="right">{{ number_format((float) $order->total, 2) }}</td>
                    <td class="right">{{ number_format((float) $order->paid_amount, 2) }}</td>
                    <td class="right">{{ number_format((float) $order->balance, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; padding:16px;" class="muted">No job orders found for this customer in the selected date range.</td></tr>
            @endforelse
        </tbody>
        @if($orders->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="5">Total — {{ number_format($summary['orders']) }} job order{{ $summary['orders'] === 1 ? '' : 's' }}</td>
                    <td class="right">{{ number_format((float) $summary['total'], 2) }}</td>
                    <td class="right">{{ number_format((float) $summary['paid'], 2) }}</td>
                    <td class="right">{{ number_format((float) $summary['balance'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
