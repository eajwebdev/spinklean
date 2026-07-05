@extends('layouts.app')

@section('page_title', 'PO Transactions')

@section('content')
@php($dateRangeValue = request('date_range') ?: ($dateFrom && $dateTo ? $dateFrom.' to '.$dateTo : ''))
<div
    x-data="{
        editOpen: null,
        historyOpen: null,
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
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="file-text" class="h-3.5 w-3.5"></span>
                Corporate billing
            </div>
            <h1 class="text-xl font-semibold">PO Transactions</h1>
            <p class="text-sm text-muted">Track purchase order billing separately from cashier unpaid orders.</p>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.po-transactions.index', request()->except('page', 'status')) }}" class="rounded-lg border border-border bg-white p-3 transition hover:border-primary/40 hover:shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs font-medium text-muted">Total PO Amount</p>
            <p class="mt-1 text-xl font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) ($summary->total_amount ?? 0), 2) }}</p>
        </a>
        <a href="{{ route('admin.po-transactions.index', array_merge(request()->except('page'), ['status' => 'pending'])) }}" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-800 transition hover:border-amber-400 hover:shadow-sm dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-300">
            <p class="text-xs font-medium">Total Pending PO</p>
            <p class="mt-1 text-xl font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) ($summary->pending_amount ?? 0), 2) }}</p>
        </a>
        <a href="{{ route('admin.po-transactions.index', array_merge(request()->except('page'), ['status' => 'paid'])) }}" class="rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 transition hover:border-green-400 hover:shadow-sm dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
            <p class="text-xs font-medium">Total Paid PO</p>
            <p class="mt-1 text-xl font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) ($summary->paid_amount ?? 0), 2) }}</p>
        </a>
        <a href="{{ route('admin.po-transactions.index', request()->except('page', 'status')) }}" class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-blue-800 transition hover:border-blue-400 hover:shadow-sm dark:border-blue-900/60 dark:bg-blue-500/10 dark:text-blue-300">
            <p class="text-xs font-medium">Outstanding PO Balance</p>
            <p class="mt-1 text-xl font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) ($summary->outstanding_balance ?? 0), 2) }}</p>
        </a>
    </div>

    <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" action="{{ route('admin.po-transactions.index') }}" class="flex flex-col gap-2 md:flex-row md:items-center">
            <div class="flex h-9 min-w-0 flex-1 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                <input name="search" value="{{ request('search') }}" type="search" placeholder="Search PO, JO, company, customer..." class="w-full bg-transparent text-sm outline-none">
            </div>

            @if($canChooseBranch)
                <select name="branch_id" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950 md:w-48">
                    <option value="">All branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
            @endif

            <select name="status" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950 md:w-48">
                <option value="">All status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                @endforeach
            </select>

            <div class="flex h-9 w-full items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950 md:w-64">
                <span data-lucide="calendar" class="h-4 w-4 text-muted"></span>
                <input x-ref="dateRange" x-model="dateRange" name="date_range" type="text" placeholder="Date range" autocomplete="off" class="w-full bg-transparent text-sm outline-none">
            </div>

            <button type="submit" title="Filter" aria-label="Filter PO transactions" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4"></span>
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Customer Name</th>
                        <th class="px-4 py-3">Company Name</th>
                        <th class="px-4 py-3">PO Number</th>
                        <th class="px-4 py-3">Job Order Number</th>
                        <th class="px-4 py-3">Transaction Date</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-right">Paid</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($transactions as $transaction)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $transaction->customer?->name ?? 'N/A' }}</p>
                                <p class="text-xs text-muted">{{ $transaction->branch?->name ?? 'N/A' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $transaction->company_name ?: ($transaction->customer?->name ?? 'N/A') }}</td>
                            <td class="px-4 py-3 font-medium">{{ $transaction->po_number }}</td>
                            <td class="px-4 py-3">
                                @if($transaction->jobOrder)
                                    <a href="{{ route('admin.job-orders.show', $transaction->jobOrder) }}" class="font-medium text-primary hover:underline">{{ $transaction->jobOrder->job_order_number }}</a>
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $transaction->transaction_date?->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <p class="font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $transaction->amount, 2) }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <p class="font-semibold text-green-700 dark:text-green-300">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $transaction->paid_amount, 2) }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <p class="font-semibold {{ (float) $transaction->balance > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-muted' }}">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $transaction->balance, 2) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="{{ \App\Support\StatusBadge::classes($transaction->status) }}">{{ \App\Support\StatusBadge::label($transaction->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="historyOpen = {{ $transaction->id }}" title="Payment history" aria-label="View PO payment history" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="payments" class="h-4 w-4"></span>
                                </button>
                                <button type="button" @click="editOpen = {{ $transaction->id }}" title="Update PO" aria-label="Update PO transaction" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="settings" class="h-4 w-4"></span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-muted">No PO transactions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-border px-4 py-3 dark:border-gray-800">
            {{ $transactions->links() }}
        </div>
    </div>

    @foreach($transactions as $transaction)
        <div x-cloak x-show="historyOpen === {{ $transaction->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="historyOpen = null" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="payments" class="h-4 w-4 text-primary"></span>PO Payment History</h2>
                        <p class="text-sm text-muted">{{ $transaction->po_number }} - {{ $transaction->jobOrder?->job_order_number }}</p>
                    </div>
                    <button type="button" @click="historyOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>

                <div class="mb-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-md border border-border p-3 dark:border-gray-800">
                        <p class="text-xs text-muted">PO Amount</p>
                        <p class="font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $transaction->amount, 2) }}</p>
                    </div>
                    <div class="rounded-md border border-border p-3 dark:border-gray-800">
                        <p class="text-xs text-muted">Paid</p>
                        <p class="font-semibold text-green-700 dark:text-green-300">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $transaction->paid_amount, 2) }}</p>
                    </div>
                    <div class="rounded-md border border-border p-3 dark:border-gray-800">
                        <p class="text-xs text-muted">Balance</p>
                        <p class="font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $transaction->balance, 2) }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-md border border-border dark:border-gray-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-smoke text-xs uppercase text-muted dark:bg-gray-950">
                            <tr><th class="px-3 py-2">Payment #</th><th class="px-3 py-2">Method</th><th class="px-3 py-2">Reference</th><th class="px-3 py-2">Received By</th><th class="px-3 py-2">Date</th><th class="px-3 py-2 text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border dark:divide-gray-800">
                            @forelse($transaction->payments->sortByDesc('paid_at') as $payment)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $payment->payment_number }}</td>
                                    <td class="px-3 py-2"><span class="{{ \App\Support\StatusBadge::classes($payment->payment_method) }}">{{ \App\Support\StatusBadge::label($payment->payment_method) }}</span></td>
                                    <td class="px-3 py-2">{{ $payment->reference_no ?: 'N/A' }}</td>
                                    <td class="px-3 py-2">{{ $payment->receiver?->name ?? 'N/A' }}</td>
                                    <td class="px-3 py-2">{{ $payment->paid_at?->format('M d, Y h:i A') }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-8 text-center text-muted">No PO payments recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div x-cloak x-show="editOpen === {{ $transaction->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="editOpen = null" class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="file-text" class="h-4 w-4 text-primary"></span>Update PO</h2>
                        <p class="text-sm text-muted">{{ $transaction->po_number }} - {{ $transaction->jobOrder?->job_order_number }}</p>
                    </div>
                    <button type="button" @click="editOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>

                <form method="POST" action="{{ route('admin.po-transactions.update', $transaction) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="rounded-lg border border-border bg-smoke p-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <div class="flex items-center justify-between">
                            <span class="text-muted">PO amount</span>
                            <span class="font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $transaction->amount, 2) }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-muted">Remaining balance</span>
                            <span class="font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $transaction->balance, 2) }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Status</label>
                        <select name="status" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected($transaction->status === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Payment Method</label>
                        <select name="payment_method" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                            <option value="">No payment</option>
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Payment Amount</label>
                        <input type="number" step="0.01" min="0" max="{{ $transaction->balance }}" name="paid_amount" value="0" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" @disabled((float) $transaction->balance <= 0)>
                        <p class="mt-1 text-xs text-muted">Amount must be equal to or less than the remaining balance.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Reference No.</label>
                        <input name="reference_no" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Cheque, bank, or GCash reference">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Remarks</label>
                        <textarea name="remarks" rows="3" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Optional notes"></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">
                            <span data-lucide="save" class="h-4 w-4"></span>
                            Save PO
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
