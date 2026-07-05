@extends('layouts.app')

@section('page_title', 'Job Order Details')

@section('content')
<style>
    @media print {
        body * { visibility: hidden !important; }
        .receipt-print-area, .receipt-print-area * { visibility: visible !important; }
        .receipt-print-area {
            left: 0 !important;
            margin: 0 auto !important;
            max-width: 420px !important;
            position: absolute !important;
            right: 0 !important;
            top: 0 !important;
            width: 100% !important;
        }
        .receipt-print-actions { display: none !important; }
    }
</style>
<div x-data="{ receiptOpen: false }" class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="jobOrders" class="h-3.5 w-3.5"></span>
                {{ $order->job_order_number }}
            </div>
            <h1 class="text-xl font-semibold tracking-normal">{{ $order->customer?->name }}</h1>
            <p class="text-sm text-muted">{{ $order->branch?->name }} - {{ $order->created_at->format('M d, Y h:i A') }}</p>
            @if($order->processingBranch && (int) $order->processing_branch_id !== (int) $order->branch_id)
                <p class="text-sm text-muted">Assigned receiving branch: {{ $order->processingBranch->name }}</p>
                @if($order->production_accepted_at)
                    <p class="text-sm text-emerald-600">Received by QR scan {{ $order->production_accepted_at->format('M d, Y h:i A') }}</p>
                @else
                    <p class="text-sm text-amber-600">Waiting for {{ $order->processingBranch->name }} to scan QR before cycle starts.</p>
                @endif
            @endif
            <span class="{{ \App\Support\StatusBadge::classes($order->transaction_type === 'delivery' ? 'delivery' : 'regular') }}">{{ $order->transaction_type === 'delivery' ? 'Delivery / Pick-up' : 'Walk-in / Drop Off' }}</span>
            @if($order->is_rush)
                <span class="inline-flex rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold uppercase text-amber-800 dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-300">Rush Order</span>
            @endif
        </div>

        <div class="flex gap-2">
            <a href="{{ route('admin.job-orders.index') }}" class="inline-flex h-9 items-center rounded-md border border-border px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">Back</a>
            <a href="{{ route('admin.job-orders.edit', $order) }}" class="inline-flex h-9 items-center gap-2 rounded-md border border-border px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                <span data-lucide="settings" class="h-4 w-4"></span>
                Edit
            </a>
            <button type="button" @click="receiptOpen = true" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
                <span data-lucide="receipt" class="h-4 w-4"></span>
                Receipt
            </button>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-4">
            <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Price</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        @foreach($order->items as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $item->description }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ $settings->currency ?? 'PHP' }} {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ $settings->currency ?? 'PHP' }} {{ number_format((float) $item->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold">Payments</h2>
                <div class="space-y-2">
                    @forelse($order->payments as $payment)
                        <div class="flex items-center justify-between rounded-md border border-border p-3 text-sm dark:border-gray-800">
                            <div>
                                <p class="font-medium">{{ $payment->payment_number }}</p>
                                <p class="text-xs text-muted">{{ \App\Support\StatusBadge::label($payment->payment_type) }} - {{ $payment->paid_at?->format('M d, Y h:i A') }}{{ $payment->reference_no ? ' - Ref: '.$payment->reference_no : '' }}</p>
                            </div>
                            <span class="font-semibold">{{ $settings->currency ?? 'PHP' }} {{ number_format((float) $payment->amount, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted">No payments recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <aside class="space-y-4">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold">Summary</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-muted">Status</span><span class="{{ \App\Support\StatusBadge::classes($order->status) }}">{{ \App\Support\StatusBadge::label($order->status) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Subtotal</span><span>{{ $settings->currency ?? 'PHP' }} {{ number_format((float) $order->subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Discount</span><span>{{ $settings->currency ?? 'PHP' }} {{ number_format((float) $order->discount, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">VAT</span><span>{{ $settings->currency ?? 'PHP' }} {{ number_format((float) $order->tax, 2) }}</span></div>
                    <div class="flex justify-between font-semibold"><span>Total</span><span>{{ $settings->currency ?? 'PHP' }} {{ number_format((float) $order->total, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Paid</span><span>{{ $settings->currency ?? 'PHP' }} {{ number_format((float) $order->paid_amount, 2) }}</span></div>
                    <div class="flex justify-between font-semibold"><span>Balance</span><span>{{ $settings->currency ?? 'PHP' }} {{ number_format((float) $order->balance, 2) }}</span></div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-2 text-sm font-semibold">Notes</h2>
                <p class="text-sm text-muted">{{ $order->notes ?: 'No notes.' }}</p>
            </div>
        </aside>
    </div>

    <div x-cloak x-show="receiptOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.outside="receiptOpen = false" class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-4 shadow-2xl dark:bg-gray-900">
            <div class="receipt-print-actions mb-3 flex items-center justify-between gap-2">
                <h2 class="inline-flex min-w-0 items-center gap-2 text-sm font-semibold"><span data-lucide="receipt" class="h-4 w-4 text-primary"></span><span class="truncate">{{ $order->job_order_number }} Receipt</span></h2>
                <div class="flex gap-2">
                    <button type="button" onclick="window.print()" class="inline-flex h-8 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white hover:opacity-90"><span data-lucide="printer" class="h-3.5 w-3.5"></span>Print</button>
                    <button type="button" @click="receiptOpen = false" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>
            </div>
            <div class="receipt-print-area">
                @include('admin.job-orders.partials.receipt-card', ['order' => $order, 'settings' => $settings, 'branchSetting' => $order->branch?->setting])
            </div>
        </div>
    </div>
</div>
@endsection
