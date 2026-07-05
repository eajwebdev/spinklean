@extends('layouts.app')

@section('page_title', 'Receivables')

@section('content')
<div x-data="{ payOpen: null }" class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="receivables" class="h-3.5 w-3.5"></span>
                Unpaid balances
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Receivables</h1>
            <p class="text-sm text-muted">Track partial payments and PO customer accounts.</p>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:min-w-80">
            <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium text-muted">Open Orders</p>
                <p class="mt-1 text-lg font-semibold">{{ number_format((int) ($summary->orders_count ?? 0)) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium text-muted">Total Balance</p>
                <p class="mt-1 text-lg font-semibold">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) ($summary->total_balance ?? 0), 2) }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" action="{{ route('admin.receivables.index') }}" class="grid grid-cols-1 gap-2 lg:grid-cols-[1fr_11rem_11rem_11rem_auto]">
            <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                <input name="search" value="{{ request('search') }}" type="search" placeholder="Search job order or customer..." class="w-full bg-transparent text-sm outline-none">
            </div>

            @if($canChooseBranch)
                <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
            @endif

            <select name="billing_type" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All billing</option>
                @foreach($billingTypes as $billingType)
                    <option value="{{ $billingType }}" @selected(request('billing_type') === $billingType)>{{ \App\Support\StatusBadge::label($billingType) }}</option>
                @endforeach
            </select>

            <select name="status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                @endforeach
            </select>

            <button type="submit" title="Filter" aria-label="Filter receivables" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4"></span>
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Job Order</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Sales Branch</th>
                        <th class="px-4 py-3">Release At</th>
                        <th class="px-4 py-3">Billing</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Paid</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($receivables as $order)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $order->job_order_number }}</p>
                                <p class="text-xs text-muted">{{ $order->created_at->format('M d, Y h:i A') }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $order->customer?->name ?? 'Walk-in' }}</p>
                                <p class="text-xs text-muted">{{ $order->customer?->phone ?: 'No phone' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $order->branch?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3">{{ $order->releaseBranch?->name ?? $order->currentBranch?->name ?? $order->branch?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3"><span class="{{ \App\Support\StatusBadge::classes($order->customer?->billing_type ?? 'regular') }}">{{ \App\Support\StatusBadge::label($order->customer?->billing_type ?? 'regular') }}</span></td>
                            <td class="px-4 py-3 text-right">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $order->paid_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-amber-700 dark:text-amber-300">{{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $order->balance, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ \App\Support\StatusBadge::classes($order->status) }}">
                                    {{ \App\Support\StatusBadge::label($order->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="payOpen = {{ $order->id }}" title="Record payment" aria-label="Record payment" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="payments" class="h-4 w-4"></span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-muted">No receivables found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-border px-4 py-3 dark:border-gray-800">
            {{ $receivables->links() }}
        </div>
    </div>

    @foreach($receivables as $order)
        <div x-cloak x-show="payOpen === {{ $order->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="payOpen = null" class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="payments" class="h-4 w-4 text-primary"></span>Record Payment</h2>
                        <p class="text-sm text-muted">{{ $order->job_order_number }} - {{ $order->customer?->name }}</p>
                    </div>
                    <button type="button" @click="payOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>

                <form method="POST" action="{{ route('admin.receivables.payments.store', $order) }}" class="space-y-4">
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
    @endforeach
</div>
@endsection
