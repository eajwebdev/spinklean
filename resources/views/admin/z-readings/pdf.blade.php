<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $documentNumber ?? $reading->reading_number }}</title>
    <style>
        @page { margin: 10px; }
        body { color:#000; font-family:DejaVu Sans,sans-serif; font-size:6px; line-height:1.15; }
        table { border-collapse:collapse; width:100%; }
        th, td { border:1px solid #111; padding:2px; vertical-align:middle; }
        th { background:#f2f2f2; font-size:5.5px; text-align:center; text-transform:uppercase; }
        .title { font-size:10px; font-weight:bold; text-align:center; }
        .meta { margin-bottom:4px; width:36%; }
        .meta td:first-child { background:#f2f2f2; font-weight:bold; text-align:right; width:42%; }
        .right { text-align:right; }
        .center { text-align:center; }
        .blue { background:#d9e5f6; font-weight:bold; }
        .yellow { background:#fff563; font-weight:bold; }
        .total { background:#f2f2f2; font-weight:bold; }
        .blank-row td { height:8px; }
        .section-title { background:#f2f2f2; font-size:7px; font-weight:bold; text-align:center; }
        .no-border { border:0; padding:0; vertical-align:top; }
        .page-break { page-break-before:always; }
        .machine { margin-bottom:6px; }
        .machine th { font-size:7px; }
        .machine td:first-child { font-weight:bold; width:66%; }
        .signature { border-top:1px solid #111; margin-top:24px; padding-top:3px; text-align:center; }
        .muted { color:#555; }
    </style>
</head>
<body>
@php
    $currency = $settings->currency ?? 'PHP';
    $orders = collect($details['job_order_items'] ?? []);
    $expenseItems = collect(data_get($details, 'expense_breakdown.items', []));
    $previousPayments = collect(data_get($details, 'payment_breakdown.previous_payment_items', []));
    $inventoryUsage = collect($details['inventory_usage'] ?? []);
    $serviceTotals = collect($details['service_totals'] ?? []);
    $machineCycles = collect($details['machine_cycles'] ?? []);
    $machineCounters = collect($reading->machine_counters ?? []);
    $machineCount = max(1, (int) $reading->branch?->machine_count, (int) $machineCounters->keys()->max(), (int) $machineCycles->max('machine_number'));
    $minimumRows = 30;
    $money = fn ($value) => number_format((float) $value, 2);
    $columns = collect($details['sales_columns'] ?? [])
        ->filter()
        ->values();
    if ($columns->isEmpty()) {
        $columns = collect(['Small Machine', 'Big Machine', 'Delivery', 'Extra Services', 'Special Items', 'Establishment', 'For Sale Items']);
    }
    $cashOnHand = (float) $reading->expected_cash_amount
        + (float) data_get($details, 'expense_breakdown.money_movements.cash_in', 0);
    $totalExpenses = (float) $expenseItems->sum('amount');
    $storeCashExpenses = (float) data_get($details, 'expense_breakdown.store_cash', 0);
    $storeGcashExpenses = (float) data_get($details, 'expense_breakdown.store_gcash', 0);
    $storeBankExpenses = (float) data_get($details, 'expense_breakdown.store_bank', 0);
@endphp

<table style="margin-bottom:3px;">
    <tr>
        <td class="no-border" style="width:36%;">
            <table class="meta">
                <tr><td>{{ isset($dateRangeLabel) ? 'Date Range' : 'Date' }}</td><td>{{ $dateRangeLabel ?? $reading->business_date?->format('M d, Y') }}</td></tr>
                <tr><td>{{ $attendantLabel ?? 'Laundry Attendant' }}</td><td>{{ $reading->preparer?->name ?? $reading->signature_name }}</td></tr>
            </table>
        </td>
        <td class="no-border title">
            {{ strtoupper($settings->business_name ?? 'Laundry System') }}<br>
            {{ strtoupper($documentTitle ?? 'DAILY Z READING') }} - {{ strtoupper($reading->branch?->name) }}
        </td>
        <td class="no-border right" style="width:24%;">
            <strong>{{ $documentNumber ?? $reading->reading_number }}</strong><br>
            <span class="muted">{{ ($generatedAt ?? $reading->closed_at)?->format('M d, Y h:i A') }}</span>
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th style="width:2%;">Load</th>
            <th style="width:7%;">Name</th>
            <th style="width:7%;">Address</th>
            <th style="width:4%;">Time</th>
            @foreach($columns as $label)<th>{{ $label }}</th>@endforeach
            <th>Amount</th><th>Cash</th><th>GCash</th><th>Bank</th><th>Reference #</th><th>Unpaid</th><th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @for($index = 0; $index < max($minimumRows, $orders->count()); $index++)
            @php
                $order = $orders->get($index);
                $payments = collect($order['payments'] ?? []);
                $references = $payments->pluck('reference_no')->filter()->implode(', ');
            @endphp
            <tr class="{{ $order ? '' : 'blank-row' }}">
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $order['customer_name'] ?? '' }}</td>
                <td>{{ $order['address'] ?? '' }}</td>
                <td class="center">{{ $order ? \Illuminate\Support\Carbon::parse($order['created_at'])->format('h:i A') : '' }}</td>
                @foreach($columns as $key)
                    <td class="right">{{ ($amount = (float) data_get($order, "service_amounts.{$key}", 0)) > 0 ? $money($amount) : '' }}</td>
                @endforeach
                <td class="right">{{ $order ? $money($order['total']) : '' }}</td>
                <td class="right">{{ ($amount = (float) $payments->where('type', 'cash')->sum('amount')) > 0 ? $money($amount) : '' }}</td>
                <td class="right">{{ ($amount = (float) $payments->where('type', 'gcash')->sum('amount')) > 0 ? $money($amount) : '' }}</td>
                <td class="right">{{ ($amount = (float) $payments->where('type', 'bank')->sum('amount')) > 0 ? $money($amount) : '' }}</td>
                <td>{{ $references }}</td>
                <td class="right">{{ $order && (float) $order['balance'] > 0 ? $money($order['balance']) : '' }}</td>
                <td>{{ $order['notes'] ?? '' }}</td>
            </tr>
        @endfor
        <tr class="total">
            <td colspan="4" class="right">TOTAL</td>
            @foreach($columns as $key)
                <td class="right">{{ $money($orders->sum(fn ($order) => data_get($order, "service_amounts.{$key}", 0))) }}</td>
            @endforeach
            <td class="right">{{ $money($details['daily_total_sales']) }}</td>
            <td class="right">{{ $money(data_get($details, 'payment_breakdown.current_sales.cash', 0)) }}</td>
            <td class="right">{{ $money(data_get($details, 'payment_breakdown.current_sales.gcash', 0)) }}</td>
            <td class="right">{{ $money(data_get($details, 'payment_breakdown.current_sales.bank', 0)) }}</td>
            <td></td>
            <td class="right">{{ $money($details['daily_unpaid_amount']) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table style="margin-top:5px;">
    <tr>
        <td class="no-border" style="width:41%; padding-right:5px;">
            <table>
                <tr><th colspan="7">Establishment / Service Totals</th></tr>
                <tr><th>Category</th><th>Service</th><th>Qty</th><th>Amount</th><th>Service</th><th>Qty</th><th>Amount</th></tr>
                @forelse($serviceTotals->chunk(2) as $row)
                    <tr>
                        @foreach([0, 1] as $position)
                            @php
                                $service = $row->get($position);
                            @endphp
                            @if($position === 0)
                                <td>{{ $service['category_name'] ?? '' }}</td>
                            @endif
                            <td>{{ $service['service_name'] ?? '' }}</td>
                            <td class="right">{{ isset($service) ? number_format((float) $service['quantity'], 2) : '' }}</td>
                            <td class="right">{{ isset($service) ? $money($service['total_amount']) : '' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="7" class="center">No service entries</td></tr>
                @endforelse
            </table>
        </td>
        <td class="no-border" style="width:25%; padding-right:5px;">
            <table>
                <tr><th colspan="3">Inventory</th></tr>
                <tr><th>Item</th><th>Qty</th><th>Unit</th></tr>
                @forelse($inventoryUsage as $usage)
                    <tr><td>{{ $usage['item_name'] }}</td><td class="right">{{ number_format((float) $usage['quantity'], 2) }}</td><td>{{ $usage['unit'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="center">No inventory usage</td></tr>
                @endforelse
            </table>
            <table style="margin-top:4px;">
                <tr><td>Cash Collections + Cash In</td><td class="right">{{ $money($cashOnHand) }}</td></tr>
                <tr><td>Store Cash Expenses</td><td class="right">{{ $money($storeCashExpenses) }}</td></tr>
                <tr><td>Cash Withdrawals</td><td class="right">{{ $money(data_get($details, 'expense_breakdown.money_movements.cash_out', 0)) }}</td></tr>
                <tr class="yellow"><td>Expected Cash Drawer</td><td class="right">{{ $money($reading->expected_cash_drawer_amount) }}</td></tr>
                <tr><td>Store GCash Expenses</td><td class="right">{{ $money($storeGcashExpenses) }}</td></tr>
                <tr><td>Store Bank Expenses</td><td class="right">{{ $money($storeBankExpenses) }}</td></tr>
                <tr><td>Owner-Paid Expenses (Not Counted)</td><td class="right">{{ $money(data_get($details, 'expense_breakdown.owner', 0)) }}</td></tr>
                <tr class="total"><td>All Recorded Expenses</td><td class="right">{{ $money($totalExpenses) }}</td></tr>
            </table>
        </td>
        <td class="no-border" style="width:34%;">
            <table>
                <tr><th colspan="7">Previous Payment</th></tr>
                <tr><th>Date</th><th>Name</th><th>Cash</th><th>GCash</th><th>Bank</th><th>Reference #</th><th>JO #</th></tr>
                @forelse($previousPayments as $payment)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($payment['paid_at'])->format('M d') }}</td>
                        <td>{{ $payment['customer_name'] }}</td>
                        <td class="right">{{ $payment['type'] === 'cash' ? $money($payment['amount']) : '' }}</td>
                        <td class="right">{{ $payment['type'] === 'gcash' ? $money($payment['amount']) : '' }}</td>
                        <td class="right">{{ $payment['type'] === 'bank' ? $money($payment['amount']) : '' }}</td>
                        <td>{{ $payment['reference_no'] }}</td><td>{{ $payment['job_order_number'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="center">No previous payments</td></tr>
                @endforelse
                <tr class="total">
                    <td colspan="2">Net Total</td>
                    <td class="right">{{ $money($previousPayments->where('type', 'cash')->sum('amount')) }}</td>
                    <td class="right">{{ $money($previousPayments->where('type', 'gcash')->sum('amount')) }}</td>
                    <td class="right">{{ $money($previousPayments->where('type', 'bank')->sum('amount')) }}</td>
                    <td colspan="2"></td>
                </tr>
                <tr class="total"><td colspan="5">Overall Total</td><td colspan="2" class="right">{{ $money($previousPayments->sum('amount')) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="page-break"></div>
<div class="title" style="margin-bottom:6px;">MACHINE COUNTERS AND DETAILED CLOSING</div>

<table>
    <tr>
        <td class="no-border" style="width:50%; padding-right:6px;">
            @for($machine = 1; $machine <= $machineCount; $machine++)
                @php
                    $counter = data_get($reading->machine_counters, $machine.'.wash', []);
                    $systemCycles = (int) $machineCycles->where('machine_number', $machine)->where('cycle_type', 'wash')->sum('cycle_count');
                @endphp
                <table class="machine">
                    <tr><th colspan="2">Wash {{ $machine }}</th></tr>
                    <tr><td>Wash Beginning</td><td class="right">{{ $counter['beginning'] ?? '' }}</td></tr>
                    <tr><td>Wash Ending</td><td class="right">{{ $counter['ending'] ?? '' }}</td></tr>
                    <tr class="blue"><td>Total Wash Cycle</td><td class="right">{{ $counter['total'] ?? $systemCycles }}</td></tr>
                </table>
            @endfor
        </td>
        <td class="no-border" style="width:50%; padding-left:6px;">
            @for($machine = 1; $machine <= $machineCount; $machine++)
                @php
                    $counter = data_get($reading->machine_counters, $machine.'.dry', []);
                    $systemCycles = (int) $machineCycles->where('machine_number', $machine)->where('cycle_type', 'dry')->sum('cycle_count');
                @endphp
                <table class="machine">
                    <tr><th colspan="2">Dry {{ $machine }}</th></tr>
                    <tr><td>Dry Beginning</td><td class="right">{{ $counter['beginning'] ?? '' }}</td></tr>
                    <tr><td>Dry Ending</td><td class="right">{{ $counter['ending'] ?? '' }}</td></tr>
                    <tr class="blue"><td>Total Dry Cycle</td><td class="right">{{ $counter['total'] ?? $systemCycles }}</td></tr>
                </table>
            @endfor
        </td>
    </tr>
</table>

<table style="margin-top:5px;">
    <tr class="total"><td>Total Wash Cycle</td><td class="right">{{ number_format((int) (collect($reading->machine_counters)->sum(fn ($counter) => data_get($counter, 'wash.total', 0)) ?: $machineCycles->where('cycle_type', 'wash')->sum('cycle_count'))) }}</td></tr>
    <tr class="total"><td>Total Dry Cycle</td><td class="right">{{ number_format((int) (collect($reading->machine_counters)->sum(fn ($counter) => data_get($counter, 'dry.total', 0)) ?: $machineCycles->where('cycle_type', 'dry')->sum('cycle_count'))) }}</td></tr>
</table>

<table style="margin-top:8px;">
    <tr>
        <td class="no-border" style="width:58%; padding-right:6px;">
            <table>
                <tr><th colspan="6">Detailed Expenses</th></tr>
                <tr><th>Expense</th><th>Category</th><th>Payment</th><th>Paid From</th><th>Reference / Remarks</th><th>Amount</th></tr>
                @forelse($expenseItems as $expense)
                    <tr><td>{{ $expense['title'] }}</td><td>{{ $expense['category'] }}</td><td>{{ strtoupper($expense['payment_method'] ?: 'cash') }}</td><td>{{ $expense['paid_from'] === 'owner' ? 'Owner (reimbursable)' : 'Store' }}</td><td>{{ $expense['reference_no'] ?: $expense['remarks'] }}</td><td class="right">{{ $money($expense['amount']) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="center">No expenses</td></tr>
                @endforelse
                <tr class="total"><td colspan="5">Total Expenses</td><td class="right">{{ $money($totalExpenses) }}</td></tr>
            </table>
        </td>
        <td class="no-border" style="width:42%; padding-left:6px;">
            <table>
                <tr><th colspan="3">Cash Count</th></tr>
                <tr><th>Denomination</th><th>Qty</th><th>Amount</th></tr>
                @foreach($denominations as $value => $label)
                    @php
                        $quantity = (int) data_get($reading->cash_count, $value, 0);
                    @endphp
                    <tr><td>{{ $label }}</td><td class="right">{{ $quantity }}</td><td class="right">{{ $money((float) $value * $quantity) }}</td></tr>
                @endforeach
                <tr class="total"><td colspan="2">Actual Cash</td><td class="right">{{ $money($reading->actual_cash_amount) }}</td></tr>
                <tr><td colspan="2">Expected Cash Drawer</td><td class="right">{{ $money($reading->expected_cash_drawer_amount) }}</td></tr>
                <tr><td colspan="2">Expected GCash</td><td class="right">{{ $money($reading->expected_gcash_amount) }}</td></tr>
                <tr><td colspan="2">Actual GCash</td><td class="right">{{ $money($reading->actual_gcash_amount) }}</td></tr>
                <tr><td colspan="2">Expected Bank</td><td class="right">{{ $money($reading->expected_bank_amount) }}</td></tr>
                <tr><td colspan="2">Actual Bank</td><td class="right">{{ $money($reading->actual_bank_amount) }}</td></tr>
                <tr class="total"><td colspan="2">Expected Total</td><td class="right">{{ $money($reading->expected_total_amount) }}</td></tr>
                <tr class="total"><td colspan="2">Actual Total</td><td class="right">{{ $money($reading->actual_total_amount) }}</td></tr>
                <tr class="yellow"><td colspan="2">Over / Short</td><td class="right">{{ $money($reading->over_short_amount) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table style="margin-top:28px;">
    <tr>
        <td class="no-border" style="width:33%; padding:0 18px;"><div class="signature">{{ $reading->signature_name }}<br><span class="muted">Prepared by</span></div></td>
        <td class="no-border" style="width:33%; padding:0 18px;"><div class="signature">{{ collect($signatories['branch_manager'] ?? [])->first() }}<br><span class="muted">Checked by</span></div></td>
        <td class="no-border" style="width:33%; padding:0 18px;"><div class="signature">&nbsp;<br><span class="muted">Approved by</span></div></td>
    </tr>
</table>
</body>
</html>
