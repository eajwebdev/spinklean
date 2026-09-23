@extends('layouts.app')

@section('page_title', 'PO Statement of Account')

@section('content')
@php
    $currency = $settings->currency ?? 'PHP';
    $dateRangeValue = request('date_range') ?: $dateFrom.' to '.$dateTo;
@endphp
<div class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="file-text" class="h-3.5 w-3.5"></span>
                Corporate billing report
            </div>
            <h1 class="text-xl font-semibold">PO Statement of Account</h1>
            <p class="text-sm text-muted">Generate a customer statement from active PO transactions. Canceled job orders are excluded.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.po-transactions.index') }}" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900">
                <span data-lucide="arrow-left" class="h-4 w-4"></span>
                Back to PO Transactions
            </a>
            @if($customer)
                <a href="{{ route('admin.po-transactions.statement-of-account.pdf', request()->query()) }}" target="_blank" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
                    <span data-lucide="download" class="h-4 w-4"></span>
                    Download PDF
                </a>
            @endif
        </div>
    </div>

    <form
        method="GET"
        action="{{ route('admin.po-transactions.statement-of-account') }}"
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
            },
        }"
        class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:flex xl:items-end">
            <label class="min-w-0 xl:flex-1">
                <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-muted">PO Customer</span>
                <select name="customer_id" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">Select customer...</option>
                    @foreach($customers as $option)
                        <option value="{{ $option->id }}" @selected($customer && (int) $customer->id === (int) $option->id)>
                            {{ $option->name }}{{ $option->phone ? ' - '.$option->phone : '' }}
                        </option>
                    @endforeach
                </select>
            </label>

            @if($canChooseBranch)
                <label class="xl:w-48">
                    <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-muted">Branch</span>
                    <select name="branch_id" onchange="this.form.customer_id.value = ''; this.form.submit()" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <option value="">All branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
            @else
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
            @endif

            <label class="xl:w-44">
                <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-muted">PO Status</span>
                <select name="status" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="xl:w-64">
                <span class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-muted">Statement Period</span>
                <span class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                    <span data-lucide="calendar" class="h-4 w-4 shrink-0 text-muted"></span>
                    <input x-ref="dateRange" x-model="dateRange" name="date_range" type="text" required autocomplete="off" placeholder="Select date range" class="min-w-0 flex-1 bg-transparent text-sm outline-none">
                </span>
            </label>

            <div class="flex items-center gap-2 sm:col-span-2 xl:col-span-1 xl:shrink-0">
                <button type="submit" class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90 xl:flex-none">
                    <span data-lucide="file-search" class="h-4 w-4"></span>
                    Generate
                </button>
                <a href="{{ route('admin.po-transactions.statement-of-account') }}" title="Clear filters" aria-label="Clear statement filters" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                    <span data-lucide="rotate-ccw" class="h-4 w-4"></span>
                </a>
            </div>
        </div>
    </form>

    @if(! $customer)
        <div class="rounded-lg border border-dashed border-border bg-white p-6 text-center dark:border-gray-800 dark:bg-gray-900">
            <span data-lucide="file-search" class="mx-auto mb-2 h-6 w-6 text-muted"></span>
            <p class="text-sm font-medium">Choose a PO customer to begin</p>
            <p class="mt-1 text-xs text-muted">The report will use the selected period, branch, and PO status.</p>
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">PO Transactions</p>
                <p class="mt-1 text-lg font-semibold">{{ number_format($summary['transactions']) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Total Charges</p>
                <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format((float) $summary['amount'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Total Paid</p>
                <p class="mt-1 text-lg font-semibold text-green-600 dark:text-green-400">{{ $currency }} {{ number_format((float) $summary['paid'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Amount Due</p>
                <p class="mt-1 text-lg font-semibold {{ $summary['balance'] > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ $currency }} {{ number_format((float) $summary['balance'], 2) }}</p>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-border p-4 dark:border-gray-800">
                <div class="flex flex-col justify-between gap-2 sm:flex-row">
                    <div>
                        <h2 class="font-semibold">{{ $customer->name }}</h2>
                        <p class="text-sm text-muted">{{ $customer->address ?: 'No address recorded' }}</p>
                        <p class="text-sm text-muted">{{ collect([$customer->phone, $customer->email])->filter()->implode(' | ') ?: 'No contact details recorded' }}</p>
                    </div>
                    <div class="text-sm sm:text-right">
                        <p class="font-medium">Statement Period</p>
                        <p class="text-muted">{{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M d, Y') }}</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">PO Number</th>
                            <th class="px-4 py-3">Job Order</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Paid</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        @forelse($transactions as $transaction)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3">{{ $transaction->transaction_date?->format('M d, Y') }}</td>
                                <td class="px-4 py-3 font-medium">{{ $transaction->po_number }}</td>
                                <td class="px-4 py-3">{{ $transaction->jobOrder?->job_order_number ?? 'N/A' }}</td>
                                <td class="px-4 py-3">{{ $transaction->branch?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3"><span class="{{ \App\Support\StatusBadge::classes($transaction->status) }}">{{ \App\Support\StatusBadge::label($transaction->status) }}</span></td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $transaction->amount, 2) }}</td>
                                <td class="px-4 py-3 text-right text-green-700 dark:text-green-300">{{ number_format((float) $transaction->paid_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right font-medium {{ (float) $transaction->balance > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ number_format((float) $transaction->balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-muted">No active PO transactions found for this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if($transactions->isNotEmpty())
                        <tfoot class="border-t border-border bg-smoke font-semibold dark:border-gray-800 dark:bg-gray-950">
                            <tr>
                                <td colspan="5" class="px-4 py-3">Statement Total</td>
                                <td class="px-4 py-3 text-right">{{ $currency }} {{ number_format((float) $summary['amount'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $summary['paid'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $summary['balance'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        @if($payments->isNotEmpty())
            <div class="rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-border p-4 dark:border-gray-800">
                    <h2 class="font-semibold">Payment History</h2>
                    <p class="text-sm text-muted">Payments recorded against the PO transactions shown above.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                            <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Payment Number</th><th class="px-4 py-3">PO Number</th><th class="px-4 py-3">Method</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3 text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border dark:divide-gray-800">
                            @foreach($payments as $payment)
                                @php($paymentPo = $transactions->firstWhere('id', $payment->po_transaction_id))
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3">{{ $payment->paid_at?->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $payment->payment_number }}</td>
                                    <td class="px-4 py-3">{{ $paymentPo?->po_number ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">{{ \App\Support\StatusBadge::label($payment->payment_method) }}</td>
                                    <td class="px-4 py-3">{{ $payment->reference_no ?: 'N/A' }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ $currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
