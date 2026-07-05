@extends('layouts.app')

@section('page_title', 'Job Orders')

@section('content')
@php($dateRangeValue = request('date_range') ?: ($dateFrom && $dateTo ? $dateFrom.' to '.$dateTo : ''))
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
<div
    x-data="{
        statusOpen: null,
        cancelOpen: null,
        paymentOpen: null,
        payOpen: null,
        receiptOpen: null,
        dateRange: @js($dateRangeValue),
        init() {
            this.$nextTick(() => {
                if (!window.flatpickr) return;
                window.flatpickr(this.$refs.dateRange, {
                    mode: 'range',
                    dateFormat: 'Y-m-d',
                    defaultDate: this.dateRange ? this.dateRange.split(' to ') : null,
                    onClose: (dates, value) => this.dateRange = value,
                });
            });
        },
    }"
    class="space-y-4"
>
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-900">
                <span data-lucide="jobOrders" class="h-3.5 w-3.5"></span>
                {{ in_array(auth()->user()->role, ['branch_manager', 'cashier'], true) ? 'Cashier POS' : 'Laundry operations' }}
            </div>
            <h1 class="text-xl font-semibold">Job Orders</h1>
            <p class="text-sm text-muted">Create, filter, review, and edit laundry transactions.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.job-orders.create') }}" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
                <span data-lucide="plus" class="h-4 w-4"></span>
                New POS
            </a>
        </div>
    </div>

    <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_12rem_16rem_auto]">
            <input name="search" value="{{ request('search') }}" placeholder="Search JO or customer..." class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
            <select name="status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All status</option>
                <option value="active" @selected(request('status') === 'active')>In Process</option>
                <option value="released" @selected(request('status') === 'released')>Released to Customer</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                @endforeach
            </select>
            <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="calendar" class="h-4 w-4 text-muted"></span>
                <input x-ref="dateRange" x-model="dateRange" name="date_range" type="text" placeholder="Date range" autocomplete="off" class="w-full bg-transparent text-sm outline-none">
            </div>
            <button class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800"><span data-lucide="search" class="h-4 w-4"></span></button>
        </form>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <a href="{{ route('admin.job-orders.index', array_merge(request()->except('page'), ['status' => 'ready_for_pickup'])) }}" class="rounded-lg border border-teal-200 bg-teal-50 p-3 text-teal-800 transition hover:border-teal-400 hover:shadow-sm {{ request('status') === 'ready_for_pickup' ? 'ring-2 ring-teal-400' : '' }} dark:border-teal-900/60 dark:bg-teal-500/10 dark:text-teal-300">
            <p class="text-xs font-medium">Ready for Pickup</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format((int) ($statusCounts['ready_for_pickup'] ?? 0)) }}</p>
        </a>
        <a href="{{ route('admin.job-orders.index', array_merge(request()->except('page'), ['status' => 'ready_for_delivery'])) }}" class="rounded-lg border border-orange-200 bg-orange-50 p-3 text-orange-800 transition hover:border-orange-400 hover:shadow-sm {{ request('status') === 'ready_for_delivery' ? 'ring-2 ring-orange-400' : '' }} dark:border-orange-900/60 dark:bg-orange-500/10 dark:text-orange-300">
            <p class="text-xs font-medium">Ready for Delivery</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format((int) ($statusCounts['ready_for_delivery'] ?? 0)) }}</p>
        </a>
        <a href="{{ route('admin.job-orders.index', array_merge(request()->except('page'), ['status' => 'released'])) }}" class="rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 transition hover:border-green-400 hover:shadow-sm dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
            <p class="text-xs font-medium">Released to Customer</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format((int) ($statusCounts['released'] ?? 0)) }}</p>
        </a>
        <a href="{{ route('admin.job-orders.index', array_merge(request()->except('page'), ['status' => 'active'])) }}" class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-blue-800 transition hover:border-blue-400 hover:shadow-sm {{ request('status') === 'active' ? 'ring-2 ring-blue-400' : '' }} dark:border-blue-900/60 dark:bg-blue-500/10 dark:text-blue-300">
            <p class="text-xs font-medium">In Process</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format((int) ($statusCounts['active'] ?? 0)) }}</p>
        </a>
        <a href="{{ route('admin.job-orders.index', request()->except('page', 'status')) }}" class="rounded-lg border border-border bg-white p-3 transition hover:border-primary/40 hover:shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium text-muted">Total in Date Range</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format((int) ($statusCounts['total'] ?? 0)) }}</p>
        </a>
    </div>

    <div class="flex flex-wrap gap-2 rounded-lg border border-border bg-white p-3 text-xs shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-teal-500"></span>Ready for pickup</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-orange-500"></span>Ready for delivery</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>Released to customer</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>In process</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>Cancelled</span>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                <tr>
                    <th class="px-4 py-3">JO #</th><th class="px-4 py-3">Created</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Address</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Balance</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border dark:divide-gray-800">
                @forelse($orders as $order)
                    @php($isReadyForPickup = $order->status === 'ready_for_pickup')
                    @php($isReadyForDelivery = $order->status === 'ready_for_delivery')
                    @php($isReady = $isReadyForPickup || $isReadyForDelivery)
                    @php($isReleased = (bool) $order->released_at)
                    @php($isInProcess = in_array($order->status, ['pending', 'washing', 'drying', 'folding'], true))
                    @php($canRecordPayment = (float) $order->balance > 0 && $order->status !== 'cancelled' && ! $order->poTransaction && $order->customer?->billing_type !== 'po')
                    <tr>
                        <td class="border-l-4 px-4 py-3 font-medium {{ $isReadyForPickup ? 'border-l-teal-500 bg-teal-50/50 dark:bg-teal-500/5' : ($isReadyForDelivery ? 'border-l-orange-500 bg-orange-50/50 dark:bg-orange-500/5' : ($isReleased ? 'border-l-green-500 bg-green-50/50 dark:bg-green-500/5' : ($isInProcess ? 'border-l-blue-500 bg-blue-50/50 dark:bg-blue-500/5' : ($order->status === 'cancelled' ? 'border-l-red-500' : 'border-l-transparent')))) }}">
                            <p>{{ $order->job_order_number }}</p>
                            @if($order->is_rush)
                                <span class="mt-1 inline-flex rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-800 dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-300">Rush</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $order->created_at?->format('M d, Y') }}</p>
                            <p class="text-xs text-muted">{{ $order->created_at?->format('h:i A') }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ $order->customer?->name }}</p>
                            <span class="{{ \App\Support\StatusBadge::classes($order->transaction_type === 'delivery' ? 'delivery' : 'regular') }}">{{ $order->transaction_type === 'delivery' ? 'Delivery / Pick-up' : 'Walk-in / Drop Off' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ $order->branch?->name }}</p>
                            @if(($order->branch?->branch_type ?? 'full_service') === 'pickup_dropoff')
                                <p class="text-xs text-muted">Pickup & Drop-off</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            {{ $order->customer?->address }}
                            {{-- <p>{{ $order->processingBranch?->name ?? $order->branch?->name }}</p>
                            @if((int) ($order->processing_branch_id ?: $order->branch_id) !== (int) $order->branch_id)
                                @if($order->production_accepted_at)
                                    <p class="text-xs text-emerald-600">Received {{ $order->production_accepted_at->format('M d, h:i A') }}</p>
                                @else
                                    <p class="text-xs text-amber-600">Waiting for QR scan</p>
                                @endif
                            @endif --}}
                        </td>
                        <td class="px-4 py-3">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $order->total, 2) }}</td>
                        <td class="px-4 py-3">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $order->balance, 2) }}</td>
                        <td class="px-4 py-3">
                            <div class="space-y-1.5">
                                <span class="{{ \App\Support\StatusBadge::classes($order->status) }}">{{ \App\Support\StatusBadge::label($order->status) }}</span>
                                @if($isReadyForPickup)
                                    <p class="inline-flex items-center gap-1 text-xs font-medium text-teal-700 dark:text-teal-300"><span class="h-2 w-2 rounded-full bg-teal-500"></span>Awaiting customer pickup</p>
                                @elseif($isReadyForDelivery)
                                    <p class="inline-flex items-center gap-1 text-xs font-medium text-orange-700 dark:text-orange-300"><span class="h-2 w-2 rounded-full bg-orange-500"></span>Awaiting delivery</p>
                                @elseif($isReleased)
                                    <p class="inline-flex items-center gap-1 text-xs font-medium text-green-700 dark:text-green-300"><span class="h-2 w-2 rounded-full bg-green-500"></span>Released {{ $order->released_at->format('M d, h:i A') }}</p>
                                @elseif($isInProcess)
                                    <p class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 dark:text-blue-300"><span class="h-2 w-2 rounded-full bg-blue-500"></span>In process</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.job-orders.show', $order) }}" title="View" aria-label="View job order" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                <span data-lucide="eye" class="h-4 w-4"></span>
                            </a>
                            <a href="{{ route('admin.job-orders.edit', $order) }}" title="Edit" aria-label="Edit job order" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                <span data-lucide="settings" class="h-4 w-4"></span>
                            </a>
                            <button type="button" @click="paymentOpen = {{ $order->id }}" title="Payment history" aria-label="View payment history" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                <span data-lucide="payments" class="h-4 w-4"></span>
                            </button>
                            @if($canRecordPayment)
                                <button type="button" @click="payOpen = {{ $order->id }}" title="Record payment" aria-label="Record payment" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-500/10">
                                    <span data-lucide="dollar" class="h-4 w-4"></span>
                                </button>
                            @endif
                            <button type="button" @click="receiptOpen = {{ $order->id }}" title="Receipt" aria-label="Print receipt" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                <span data-lucide="receipt" class="h-4 w-4"></span>
                            </button>
                            @if($isReady)
                                <form method="POST" action="{{ route('admin.job-orders.release', $order) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" x-on:click.prevent="Swal.fire({ title: 'Complete laundry?', text: 'Confirm that this laundry was picked up or sent for delivery. This will mark the job order as completed.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#0f766e', confirmButtonText: 'Complete' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })" title="Release job order to customer" aria-label="Release job order to customer" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-teal-200 text-teal-700 hover:bg-teal-50 dark:border-teal-900/60 dark:text-teal-300 dark:hover:bg-teal-500/10">
                                        <span data-lucide="package-check" class="h-4 w-4"></span>
                                    </button>
                                </form>
                            @endif
                            @unless(in_array($order->status, ['completed', 'cancelled'], true))
                                <button type="button" @click="statusOpen = {{ $order->id }}" title="Update status" aria-label="Update status" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="activity" class="h-4 w-4"></span>
                                </button>
                                <button type="button" @click="cancelOpen = {{ $order->id }}" title="Cancel" aria-label="Cancel job order" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50">
                                    <span data-lucide="x" class="h-4 w-4"></span>
                                </button>
                            @endunless
                            @if(auth()->user()?->role === 'super_admin')
                                <form method="POST" action="{{ route('admin.job-orders.destroy', $order) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" x-on:click.prevent="Swal.fire({ title: 'Delete job order?', text: 'This will delete the connected payments, ledger entries, PO transaction, items, and cycle records. A deletion log will be saved.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Delete' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })" title="Delete" aria-label="Delete job order" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-700 hover:bg-red-50 dark:border-red-900/60 dark:text-red-300 dark:hover:bg-red-500/10">
                                        <span data-lucide="trash" class="h-4 w-4"></span>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-muted">No job orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-border px-4 py-3 dark:border-gray-800">{{ $orders->links() }}</div>
    </div>

    @foreach($orders as $order)
        <div x-cloak x-show="paymentOpen === {{ $order->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="paymentOpen = null" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="payments" class="h-4 w-4 text-primary"></span>Payment History</h2>
                        <p class="text-sm text-muted">{{ $order->job_order_number }} - {{ $order->customer?->name }}</p>
                    </div>
                    <button type="button" @click="paymentOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>

                <div class="mb-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-md border border-border p-3 dark:border-gray-800">
                        <p class="text-xs text-muted">Total</p>
                        <p class="font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $order->total, 2) }}</p>
                    </div>
                    <div class="rounded-md border border-border p-3 dark:border-gray-800">
                        <p class="text-xs text-muted">Paid</p>
                        <p class="font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $order->paid_amount, 2) }}</p>
                    </div>
                    <div class="rounded-md border border-border p-3 dark:border-gray-800">
                        <p class="text-xs text-muted">Balance</p>
                        <p class="font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $order->balance, 2) }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-md border border-border dark:border-gray-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-smoke text-xs uppercase text-muted dark:bg-gray-950">
                            <tr><th class="px-3 py-2">Payment #</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Reference</th><th class="px-3 py-2">Received By</th><th class="px-3 py-2">Date</th><th class="px-3 py-2 text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border dark:divide-gray-800">
                            @forelse($order->payments->sortByDesc('paid_at') as $payment)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $payment->payment_number }}</td>
                                    <td class="px-3 py-2"><span class="{{ \App\Support\StatusBadge::classes($payment->payment_type) }}">{{ \App\Support\StatusBadge::label($payment->payment_type) }}</span></td>
                                    <td class="px-3 py-2">{{ $payment->reference_no ?: 'N/A' }}</td>
                                    <td class="px-3 py-2">{{ $payment->receiver?->name ?? 'N/A' }}</td>
                                    <td class="px-3 py-2">{{ $payment->paid_at?->format('M d, Y h:i A') }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-8 text-center text-muted">No payments recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if((float) $order->balance > 0 && $order->status !== 'cancelled' && ! $order->poTransaction && $order->customer?->billing_type !== 'po')
            <div x-cloak x-show="payOpen === {{ $order->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div @click.outside="payOpen = null" class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="dollar" class="h-4 w-4 text-primary"></span>Record Payment</h2>
                            <p class="text-sm text-muted">{{ $order->job_order_number }} - {{ $order->customer?->name }}</p>
                        </div>
                        <button type="button" @click="payOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                    </div>

                    <form method="POST" action="{{ route('admin.job-orders.payments.store', $order) }}" class="space-y-4">
                        @csrf
                        <div class="rounded-lg border border-border bg-smoke p-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex items-center justify-between">
                                <span class="text-muted">Remaining balance</span>
                                <span class="font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $order->balance, 2) }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Payment Type</label>
                            <select name="payment_type" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="po">PO</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Amount</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $order->balance }}" name="amount" value="{{ $order->balance }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Reference No.</label>
                            <input name="reference_no" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="GCash/card reference">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Remarks</label>
                            <textarea name="remarks" rows="3" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Optional notes"></textarea>
                        </div>

                        @if($errors->any())
                            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">
                                <span data-lucide="payments" class="h-4 w-4"></span>
                                Save Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div x-cloak x-show="receiptOpen === {{ $order->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="receiptOpen = null" class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-4 shadow-2xl dark:bg-gray-900">
                <div class="receipt-print-actions mb-3 flex items-center justify-between gap-2">
                    <h2 class="inline-flex min-w-0 items-center gap-2 text-sm font-semibold"><span data-lucide="receipt" class="h-4 w-4 text-primary"></span><span class="truncate">{{ $order->job_order_number }} Receipt</span></h2>
                    <div class="flex gap-2">
                        <button type="button" onclick="window.print()" class="inline-flex h-8 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-white hover:opacity-90"><span data-lucide="printer" class="h-3.5 w-3.5"></span>Print</button>
                        <button type="button" @click="receiptOpen = null" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950"><span data-lucide="x" class="h-4 w-4"></span></button>
                    </div>
                </div>
                <div class="receipt-print-area">
                    @include('admin.job-orders.partials.receipt-card', ['order' => $order, 'settings' => $appSettings, 'branchSetting' => $order->branch?->setting])
                </div>
            </div>
        </div>

        <div x-cloak x-show="statusOpen === {{ $order->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="statusOpen = null" class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="activity" class="h-4 w-4 text-primary"></span>Update Status</h2>
                        <p class="text-sm text-muted">{{ $order->job_order_number }}</p>
                    </div>
                    <button type="button" @click="statusOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>

                <form method="POST" action="{{ route('admin.job-orders.status', $order) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                        @foreach(array_filter($statuses, fn ($status) => $status !== 'cancelled') as $status)
                            <option value="{{ $status }}" @selected($order->status === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                        @endforeach
                    </select>
                    <div class="flex justify-end">
                        <button class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Save Status</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-cloak x-show="cancelOpen === {{ $order->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="cancelOpen = null" class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold">Cancel job order?</h2>
                    <p class="mt-1 text-sm text-muted">{{ $order->job_order_number }} will be marked as cancelled.</p>
                </div>
                <form method="POST" action="{{ route('admin.job-orders.cancel', $order) }}" class="flex justify-end gap-2">
                    @csrf
                    @method('PATCH')
                    <button type="button" @click="cancelOpen = null" class="h-9 rounded-md border border-border px-4 text-sm font-medium hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">Keep</button>
                    <button class="h-9 rounded-md bg-red-600 px-4 text-sm font-medium text-white hover:bg-red-700">Cancel Order</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
