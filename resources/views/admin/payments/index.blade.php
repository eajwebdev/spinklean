@extends('layouts.app')

@section('page_title', 'Payments')

@section('content')
@php
    $dateRangeValue = request('date_range') ?: ($dateFrom && $dateTo ? $dateFrom.' to '.$dateTo : '');
    $hasFilters = request()->filled('search') || request()->filled('branch_id') || request()->filled('payment_type') || $dateRangeValue;
@endphp
<div
    x-data="{
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
        }
    }"
    class="space-y-4"
>
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="payments" class="h-3.5 w-3.5"></span>
                Payment audit
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Payments</h1>
            <p class="text-sm text-muted">Review POS collections, receivable payments, and cashier activity.</p>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:min-w-[48rem] lg:grid-cols-5">
            <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium text-muted">Payments</p>
                <p class="mt-1 text-lg font-semibold">{{ number_format((int) ($summary->payments_count ?? 0)) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium text-muted">Sales Owned</p>
                <p class="mt-1 text-lg font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $salesOwnerTotal, 2) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium text-muted">Collected Here</p>
                <p class="mt-1 text-lg font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $physicalCollectionTotal, 2) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium text-muted">Collected Today</p>
                <p class="mt-1 text-lg font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $todayCollectionTotal, 2) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium text-muted">Cross-Branch</p>
                <p class="mt-1 text-lg font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $crossBranchTotal, 2) }}</p>
            </div>
        </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="space-y-3">
                <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                    <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                    <input name="search" value="{{ request('search') }}" type="search" placeholder="Search payment, JO, customer, cashier..." class="w-full bg-transparent text-sm outline-none">
                </div>

                <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">
                    @if($canChooseBranch)
                        <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                            <option value="">All sales/collection branches</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
                    @endif

                    <select name="payment_type" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <option value="">All types</option>
                        @foreach($paymentTypes as $type)
                            <option value="{{ $type }}" @selected(request('payment_type') === $type)>{{ \App\Support\StatusBadge::label($type) }}</option>
                        @endforeach
                    </select>

                    <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                        <span data-lucide="calendar" class="h-4 w-4 text-muted"></span>
                        <input
                            x-ref="dateRange"
                            x-model="dateRange"
                            name="date_range"
                            type="text"
                            placeholder="Date range"
                            class="w-full bg-transparent text-sm outline-none"
                            autocomplete="off"
                        >
                    </div>

                    <div class="grid grid-cols-[1fr_auto] gap-2">
                        <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
                            <span data-lucide="search" class="h-4 w-4"></span>
                            Filter
                        </button>

                        @if($hasFilters)
                            <a href="{{ route('admin.payments.index') }}" title="Clear filters" aria-label="Clear filters" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-white hover:bg-smoke dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900">
                                <span data-lucide="x" class="h-4 w-4"></span>
                            </a>
                        @endif
                    </div>
                </div>

                @if($hasFilters)
                    <div class="flex flex-wrap gap-1.5 text-xs">
                        @if(request('search'))
                            <span class="rounded-md bg-smoke px-2 py-1 text-muted dark:bg-gray-950">Search: {{ request('search') }}</span>
                        @endif
                        @if(request('payment_type'))
                            <span class="{{ \App\Support\StatusBadge::classes(request('payment_type')) }}">Type: {{ \App\Support\StatusBadge::label(request('payment_type')) }}</span>
                        @endif
                        @if($dateRangeValue)
                            <span class="rounded-md bg-smoke px-2 py-1 text-muted dark:bg-gray-950">Date: {{ $dateRangeValue }}</span>
                        @endif
                    </div>
                @endif
            </form>
        </div>

        <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-2 text-xs font-medium uppercase text-muted">Type Mix</p>
            <div class="space-y-2">
                @forelse($paymentsByType as $type)
                    @php($percent = (float) ($summary->total_amount ?? 0) > 0 ? ((float) $type->total_amount / (float) $summary->total_amount) * 100 : 0)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="font-medium">{{ \App\Support\StatusBadge::label($type->payment_type) }}</span>
                            <span class="text-muted">{{ number_format($percent, 0) }}%</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-smoke dark:bg-gray-950">
                            <div class="h-full rounded-full bg-primary" style="width: {{ min($percent, 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-muted">No payment data.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3">Job Order</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Sales Branch</th>
                        <th class="px-4 py-3">Collected At</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Settlement</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Received By</th>
                        <th class="px-4 py-3">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($payments as $payment)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $payment->payment_number }}</p>
                                <p class="text-xs text-muted">{{ $payment->paid_at?->format('M d, Y h:i A') ?? $payment->created_at->format('M d, Y h:i A') }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium">{{ $payment->jobOrder?->job_order_number ?? 'N/A' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $payment->customer?->name ?? 'Walk-in' }}</p>
                                <p class="text-xs text-muted">{{ $payment->customer?->phone ?: 'No phone' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $payment->branch?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $payment->collectedBranch?->name ?? $payment->branch?->name ?? 'N/A' }}</p>
                                @if((int) ($payment->collected_branch_id ?: $payment->branch_id) !== (int) $payment->branch_id)
                                    <p class="text-xs text-amber-700 dark:text-amber-300">For remittance</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="{{ \App\Support\StatusBadge::classes($payment->payment_type) }}">
                                    {{ \App\Support\StatusBadge::label($payment->payment_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $payment->reference_no ?: 'N/A' }}</td>
                            <td class="px-4 py-3">
                                @if((int) ($payment->collected_branch_id ?: $payment->branch_id) !== (int) $payment->branch_id)
                                    <span class="{{ \App\Support\StatusBadge::classes('pending') }}">{{ \App\Support\StatusBadge::label($payment->settlement_status ?: 'pending') }}</span>
                                @else
                                    <span class="{{ \App\Support\StatusBadge::classes('ok') }}">Local</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $payment->receiver?->name ?? 'System' }}</td>
                            <td class="max-w-56 px-4 py-3">
                                <p class="truncate text-muted" title="{{ $payment->remarks }}">{{ $payment->remarks ?: 'N/A' }}</p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-10 text-center text-muted">No payments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-border px-4 py-3 dark:border-gray-800">
            {{ $payments->links() }}
        </div>
    </div>
</div>
@endsection
