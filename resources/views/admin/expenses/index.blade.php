@extends('layouts.app')

@section('page_title', 'Expenses')

@section('content')
@php
    $currency = $appSettings?->currency ?? 'PHP';
    $dateRangeValue = request('date_range') ?: ($dateFrom && $dateTo ? $dateFrom.' to '.$dateTo : '');
@endphp

<div
    x-data="{
        createOpen: false,
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
                <span data-lucide="expense" class="h-3.5 w-3.5"></span>
                Cash management
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Expenses</h1>
            <p class="text-sm text-muted">Record business costs paid from store funds, or by the owner for later reimbursement.</p>
        </div>

        <button type="button" @click="createOpen = true" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
            <span data-lucide="plus" class="h-4 w-4"></span>
            Record Expense
        </button>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs text-muted">Total Expenses</p>
            <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format((float) ($summary->total_expenses ?? 0), 2) }}</p>
        </div>
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs text-muted">Paid With Store Funds</p>
            <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format((float) ($summary->store_cash_expenses ?? 0), 2) }}</p>
        </div>
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs text-muted">Owner-Paid (For Reimbursement)</p>
            <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format((float) ($summary->owner_expenses ?? 0), 2) }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.expenses.index') }}" class="grid gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:grid-cols-[1fr_1fr_1fr_1fr_auto]">
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

        <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
            <span data-lucide="calendar" class="h-4 w-4 text-muted"></span>
            <input x-ref="dateRange" x-model="dateRange" name="date_range" placeholder="Date range" class="w-full bg-transparent text-sm outline-none" autocomplete="off">
        </div>

        <select name="paid_from" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
            <option value="">All sources</option>
            <option value="store_cash" @selected(request('paid_from') === 'store_cash')>Store-funded</option>
            <option value="owner" @selected(request('paid_from') === 'owner')>Owner-funded</option>
        </select>

        <select name="expense_type" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
            <option value="">All categories</option>
            @foreach($categories as $category)
                <option value="{{ $category }}" @selected(request('expense_type') === $category)>{{ \App\Support\StatusBadge::label($category) }}</option>
            @endforeach
        </select>

        <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
            <span data-lucide="search" class="h-4 w-4"></span>
            Filter
        </button>
    </form>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Expense</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Paid From</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($expenses as $expense)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $expense->title }}</p>
                                <p class="text-xs text-muted">{{ $expense->expense_date?->format('M d, Y') }} - {{ \App\Support\StatusBadge::label($expense->category) }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $expense->branch?->name }}</td>
                            <td class="px-4 py-3">
                                {{ $expense->paid_from === 'owner' ? 'Owner-funded' : 'Store-funded' }}
                                @if($expense->accountsPayable)
                                    <p class="text-xs text-muted">{{ $expense->accountsPayable->payable_number }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $expense->reference_no ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $currency }} {{ number_format((float) $expense->amount, 2) }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.expenses.destroy', $expense) }}" class="flex justify-end">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete" aria-label="Delete expense" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                                        <span data-lucide="trash" class="h-4 w-4"></span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No expenses found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-3 dark:border-gray-800">{{ $expenses->links() }}</div>
    </div>

    <div x-cloak x-show="createOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.outside="createOpen = false" class="w-full max-w-2xl rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="inline-flex items-center gap-2 text-lg font-semibold"><span data-lucide="expense" class="h-4 w-4 text-primary"></span>Record Expense</h2>
                <button type="button" @click="createOpen = false" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
            </div>
            <form method="POST" action="{{ route('admin.expenses.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @if($canChooseBranch)
                        <label class="text-sm font-medium">Branch
                            <select name="branch_id" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @else
                        <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
                    @endif
                    <label class="text-sm font-medium">Expense Date<input type="date" name="expense_date" value="{{ today()->toDateString() }}" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"></label>
                    <label class="text-sm font-medium">Category
                        <select name="category" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                            @foreach($categories as $category)
                                <option value="{{ $category }}">{{ \App\Support\StatusBadge::label($category) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium">Amount<input type="number" min="0.01" step="0.01" name="amount" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"></label>
                    <label class="text-sm font-medium md:col-span-2">Title<input name="title" required placeholder="Detergent stock, gas, utilities..." class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"></label>
                    <label class="text-sm font-medium">Payment Method<input name="payment_method" placeholder="Cash, GCash, or Bank" class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"></label>
                    <label class="text-sm font-medium">Paid From
                        <select name="paid_from" class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                            <option value="store_cash">Store-funded</option>
                            <option value="owner">Owner-funded (for reimbursement)</option>
                        </select>
                        <span class="mt-1 block text-xs font-normal text-muted">Owner-funded expenses do not touch store cash and create an accounts payable to repay the owner.</span>
                    </label>
                    <label class="text-sm font-medium md:col-span-2">Reference<input name="reference_no" class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950"></label>
                    <label class="text-sm font-medium md:col-span-2">Remarks<textarea name="remarks" rows="3" class="mt-1.5 w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"></textarea></label>
                </div>
                <div class="flex justify-end">
                    <button class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
