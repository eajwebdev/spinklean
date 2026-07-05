@extends('layouts.app')

@section('page_title', 'Billing')

@section('content')
@php
    $months = collect(range(1, 12))->mapWithKeys(fn ($month) => [$month => \Illuminate\Support\Carbon::create(null, $month, 1)->format('F')]);
    $currency = $settings->currency ?? 'PHP';
@endphp

<div class="space-y-4" x-data="{ payOpen: null }">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-900">
                <span data-lucide="receipt" class="h-3.5 w-3.5"></span>
                Superadmin controlled
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Billing Dashboard</h1>
            <p class="text-sm text-muted">Manage global trial access, branch subscription generation, and payment history.</p>
        </div>
        <span class="{{ \App\Support\StatusBadge::classes($summary['system_status'] ?? $summary['trial_status']) }}">
            {{ $summary['system_status_label'] ?? 'Trial: '.ucfirst($summary['trial_status']) }}
        </span>
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs text-muted">Paid Total</p>
            <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format((float) $summary['paid'], 2) }}</p>
        </div>
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs text-muted">Open Records</p>
            <p class="mt-1 text-lg font-semibold">{{ $summary['unpaid_count'] }}</p>
        </div>
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs text-muted">Branches</p>
            <p class="mt-1 text-lg font-semibold">{{ $summary['branches'] }}</p>
        </div>
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs text-muted">Grace Period</p>
            <p class="mt-1 text-lg font-semibold">{{ $trial->grace_period_days }} day{{ $trial->grace_period_days === 1 ? '' : 's' }}</p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <section class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">Global Trial Settings</h2>
            <form method="POST" action="{{ route('admin.billing.trial.update') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="trial_enabled" value="1" @checked(old('trial_enabled', $trial->trial_enabled)) class="rounded border-border text-primary">
                    Enable global free trial
                </label>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Start Date</label>
                        <input type="date" name="trial_start_date" value="{{ old('trial_start_date', $trial->trial_start_date?->toDateString()) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">End Date</label>
                        <input type="date" name="trial_end_date" value="{{ old('trial_end_date', $trial->trial_end_date?->toDateString()) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Grace Days</label>
                        <input type="number" min="0" name="grace_period_days" value="{{ old('grace_period_days', $trial->grace_period_days) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Remarks</label>
                    <textarea name="trial_remarks" rows="2" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">{{ old('trial_remarks', $trial->trial_remarks) }}</textarea>
                </div>
                <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Save Trial Settings</button>
            </form>
        </section>

        <section class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold">Generate Branch Billing</h2>
            <form method="POST" action="{{ route('admin.billing.generate') }}" class="space-y-3" x-data="{ selected: [] }">
                @csrf
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Subscription Start</label>
                        <input type="date" name="subscription_start_date" value="{{ old('subscription_start_date', now()->startOfMonth()->toDateString()) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Subscription End</label>
                        <input type="date" name="subscription_end_date" value="{{ old('subscription_end_date', now()->endOfMonth()->toDateString()) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Due Date</label>
                        <input type="date" name="due_date" value="{{ old('due_date', now()->addDays(5)->toDateString()) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" required>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="update_unpaid" value="1" class="rounded border-border text-primary">
                        Update/regenerate existing unpaid record for the same dates
                    </label>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium">Branches and Monthly Price</p>
                    <div class="grid max-h-72 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                        @foreach($branches as $branch)
                            <label class="rounded-md border border-border p-2 text-sm dark:border-gray-700">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="branches[]" value="{{ $branch->id }}" x-model="selected" class="rounded border-border text-primary">
                                    <span class="font-medium">{{ $branch->name }}</span>
                                </div>
                                <input type="number" step="0.01" min="0" name="prices[{{ $branch->id }}]" value="{{ old('prices.'.$branch->id, 0) }}" class="mt-2 h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="Monthly price">
                            </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Generate Billing</button>
            </form>
        </section>
    </div>

    <section class="rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-border p-4 dark:border-gray-800">
            <h2 class="text-base font-semibold">Billing Records</h2>
            <form method="GET" action="{{ route('admin.billing.index') }}" class="mt-3 grid gap-2 md:grid-cols-6">
                <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    <option value="">All branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                <input type="number" name="billing_year" placeholder="Year" value="{{ $filters['billing_year'] ?? '' }}" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                <select name="billing_month" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    <option value="">All months</option>
                    @foreach($months as $number => $name)
                        <option value="{{ $number }}" @selected(($filters['billing_month'] ?? '') == $number)>{{ $name }}</option>
                    @endforeach
                </select>
                <input type="date" name="subscription_date" placeholder="Subscription date" value="{{ $filters['subscription_date'] ?? '' }}" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                <select name="status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    <option value="">All statuses</option>
                    @foreach(['unpaid', 'paid', 'overdue', 'suspended'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="h-9 rounded-md border border-border px-3 text-sm font-medium hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Period</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($records as $record)
                        <tr>
                            <td class="px-4 py-3">{{ $record->branch?->name }}</td>
                            <td class="px-4 py-3">{{ $record->periodLabel() }}</td>
                            <td class="px-4 py-3 text-right">{{ $currency }} {{ number_format((float) $record->amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $record->due_date?->format('M d, Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ \App\Support\StatusBadge::classes($record->status) }}">{{ \App\Support\StatusBadge::label($record->status) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($record->payment_date)
                                    <p>{{ $record->payment_date->format('M d, Y') }} via {{ $record->payment_method }}</p>
                                    <p class="text-xs text-muted">Ref: {{ $record->reference_no ?: 'N/A' }}</p>
                                    @if($record->expense)
                                        <p class="text-xs text-primary">Expense #{{ $record->expense->id }}</p>
                                    @endif
                                @else
                                    <span class="text-muted">No payment</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($record->status !== 'paid')
                                    <form method="POST" action="{{ route('admin.billing.records.status', $record) }}" class="mb-1 inline-flex items-center gap-1">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="h-8 rounded-md border border-border bg-white px-2 text-xs dark:border-gray-700 dark:bg-gray-950">
                                            @foreach(['unpaid', 'overdue', 'suspended'] as $status)
                                                <option value="{{ $status }}" @selected($record->status === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="h-8 rounded-md border border-border px-2 text-xs font-medium hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">Set</button>
                                    </form>
                                @endif
                                <button type="button" @click="payOpen = {{ $record->id }}" class="h-8 rounded-md border border-border px-3 text-xs font-medium hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    Mark Paid
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-muted">No billing records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-border px-4 py-3 dark:border-gray-800">
            {{ $records->links() }}
        </div>
    </section>

    @foreach($records as $record)
        <div x-cloak x-show="payOpen === {{ $record->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="payOpen = null" class="w-full max-w-lg rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Mark Paid - {{ $record->branch?->name }} {{ $record->periodLabel() }}</h2>
                    <button type="button" @click="payOpen = null" class="rounded-md p-2 hover:bg-smoke dark:hover:bg-gray-800"><span data-lucide="x" class="h-4 w-4"></span></button>
                </div>
                <form method="POST" action="{{ route('admin.billing.records.mark-paid', $record) }}" class="space-y-3" x-data="{ addExpense: {{ old('add_to_expenses', $record->expense_id ? '1' : '1') ? 'true' : 'false' }} }">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Payment Date</label>
                        <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Payment Method</label>
                        <input name="payment_method" value="{{ old('payment_method', 'Cash') }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Reference No.</label>
                        <input name="reference_no" value="{{ old('reference_no') }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Remarks</label>
                        <textarea name="remarks" rows="3" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">{{ old('remarks') }}</textarea>
                    </div>
                    <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                        <label class="flex items-start gap-2 text-sm font-medium">
                            <input type="checkbox" name="add_to_expenses" value="1" x-model="addExpense" class="mt-1 rounded border-border text-primary">
                            <span>
                                Add to branch expenses
                                <span class="block text-xs font-normal text-muted">Use this if this billing payment should appear in Expenses and Z Reading/Cash Count.</span>
                            </span>
                        </label>

                        <div x-show="addExpense" x-transition class="mt-3">
                            <label class="mb-1.5 block text-sm font-medium">Paid From</label>
                            <input type="hidden" name="paid_from" value="store_cash">
                            <div class="flex h-9 items-center rounded-md border border-border bg-white px-3 text-sm font-medium dark:border-gray-700 dark:bg-gray-900">Store-funded</div>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Save Payment</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
