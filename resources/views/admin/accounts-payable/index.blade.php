@extends('layouts.app')

@section('page_title', 'Accounts Payable')

@section('content')
@php($currency = $appSettings?->currency ?? 'PHP')
<div x-data="{ createOpen: false, payOpen: null }" class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="receivables" class="h-3.5 w-3.5"></span>
                Owner funding and reimbursements
            </div>
            <h1 class="text-xl font-semibold">Accounts Payable</h1>
            <p class="text-sm text-muted">Track money the store owes to the owner or another creditor, including partial and full repayments.</p>
        </div>
        <button type="button" @click="createOpen = true" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
            <span data-lucide="plus" class="h-4 w-4"></span>
            Record Owner Funding
        </button>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => 'Total Obligations', 'value' => $summary->original_total ?? 0],
            ['label' => 'Repaid', 'value' => $summary->paid_total ?? 0],
            ['label' => 'Outstanding', 'value' => $summary->balance_total ?? 0],
        ] as $card)
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">{{ $card['label'] }}</p>
                <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format((float) $card['value'], 2) }}</p>
            </div>
        @endforeach
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs text-muted">Open Payables</p>
            <p class="mt-1 text-lg font-semibold">{{ number_format((int) ($summary->open_count ?? 0)) }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.accounts-payable.index') }}" class="grid gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-[1fr_12rem_12rem_auto]">
        <input name="search" value="{{ request('search') }}" placeholder="Search payable, creditor, reference..." class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
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
        <select name="status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
            <option value="">All statuses</option>
            @foreach(['unpaid', 'partial', 'paid'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
            @endforeach
        </select>
        <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white">
            <span data-lucide="search" class="h-4 w-4"></span>Filter
        </button>
    </form>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Payable</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3 text-right">Original</th>
                        <th class="px-4 py-3 text-right">Repaid</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($payables as $payable)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $payable->payable_number }} - {{ $payable->creditor_name }}</p>
                                <p class="max-w-80 truncate text-xs text-muted">{{ $payable->description }}</p>
                                <p class="text-xs text-muted">{{ $payable->funded_at?->format('M d, Y') }}{{ $payable->due_date ? ' | Due '.$payable->due_date->format('M d, Y') : '' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $payable->branch?->name }}</td>
                            <td class="px-4 py-3">
                                {{ $payable->source_type === 'owner_paid_expense' ? 'Owner-paid expense' : 'Owner funding' }}
                                <p class="text-xs text-muted">{{ \App\Support\StatusBadge::label($payable->funding_method) }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">{{ $currency }} {{ number_format((float) $payable->original_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ $currency }} {{ number_format((float) $payable->paid_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $currency }} {{ number_format((float) $payable->balance, 2) }}</td>
                            <td class="px-4 py-3"><span class="{{ \App\Support\StatusBadge::classes($payable->status) }}">{{ \App\Support\StatusBadge::label($payable->status) }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if((float) $payable->balance > 0)
                                        <button type="button" @click="payOpen = {{ $payable->id }}" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-2.5 text-xs font-medium text-white">Repay</button>
                                    @else
                                        <span class="text-xs text-muted">Settled</span>
                                    @endif
                                    @if($payable->payments->isEmpty() && (float) $payable->paid_amount <= 0 && ! $payable->expense)
                                        <form method="POST" action="{{ route('admin.accounts-payable.destroy', $payable) }}" onsubmit="return confirm('Delete payable {{ $payable->payable_number }}? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Delete" aria-label="Delete payable" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                                                <span data-lucide="trash" class="h-4 w-4"></span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if($payable->payments->isNotEmpty())
                            <tr class="bg-smoke/50 dark:bg-gray-950/40">
                                <td colspan="8" class="px-4 py-2">
                                    <div class="flex flex-wrap gap-2 text-xs text-muted">
                                        <span class="font-medium text-dark dark:text-white">Repayment history:</span>
                                        @foreach($payable->payments->sortByDesc('payment_date') as $payment)
                                            <span>{{ $payment->payment_date?->format('M d, Y') }} - {{ $currency }} {{ number_format((float) $payment->amount, 2) }} via {{ \App\Support\StatusBadge::label($payment->payment_method) }}</span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-muted">No accounts payable recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-3 dark:border-gray-800">{{ $payables->links() }}</div>
    </div>

    <div x-cloak x-show="createOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.outside="createOpen = false" class="w-full max-w-2xl rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between"><div><h2 class="text-lg font-semibold">Record Owner Funding</h2><p class="text-sm text-muted">Use this when non-store money is given to the branch and must be paid back.</p></div><button type="button" @click="createOpen = false"><span data-lucide="x" class="h-4 w-4"></span></button></div>
            <form method="POST" action="{{ route('admin.accounts-payable.store') }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                @if($canChooseBranch)
                    <label class="text-sm font-medium">Branch<select name="branch_id" required class="mt-1.5 h-9 w-full rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
                @else
                    <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
                @endif
                <label class="text-sm font-medium">Creditor<input name="creditor_name" value="Owner" required class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950"></label>
                <label class="text-sm font-medium">Amount<input type="number" name="amount" min="0.01" step="0.01" required class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950"></label>
                <label class="text-sm font-medium">Funding Method<select name="funding_method" class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950">@foreach($methods as $method)<option value="{{ $method }}">{{ \App\Support\StatusBadge::label($method) }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">Date Received<input type="date" name="funded_at" value="{{ today()->toDateString() }}" required class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950"></label>
                <label class="text-sm font-medium">Due Date<input type="date" name="due_date" class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950"></label>
                <label class="text-sm font-medium md:col-span-2">Purpose<input name="description" required placeholder="Working capital, emergency store cash, equipment..." class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950"></label>
                <label class="text-sm font-medium md:col-span-2">Reference<input name="reference_no" class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950"></label>
                <div class="md:col-span-2 flex justify-end"><button class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white">Save Payable</button></div>
            </form>
        </div>
    </div>

    @foreach($payables as $payable)
        <div x-cloak x-show="payOpen === {{ $payable->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="payOpen = null" class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-gray-900">
                <h2 class="text-lg font-semibold">Repay {{ $payable->payable_number }}</h2>
                <p class="mb-4 text-sm text-muted">Remaining: {{ $currency }} {{ number_format((float) $payable->balance, 2) }}</p>
                <form method="POST" action="{{ route('admin.accounts-payable.payments.store', $payable) }}" class="space-y-3">
                    @csrf
                    <label class="block text-sm font-medium">Amount<input type="number" name="amount" min="0.01" max="{{ $payable->balance }}" step="0.01" value="{{ $payable->balance }}" required class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950"></label>
                    <label class="block text-sm font-medium">Payment Date<input type="date" name="payment_date" value="{{ today()->toDateString() }}" required class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950"></label>
                    <label class="block text-sm font-medium">Payment Method<select name="payment_method" class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950">@foreach($methods as $method)<option value="{{ $method }}">{{ \App\Support\StatusBadge::label($method) }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium">Reference<input name="reference_no" class="mt-1.5 h-9 w-full rounded-md border border-border px-3 dark:border-gray-800 dark:bg-gray-950"></label>
                    <label class="block text-sm font-medium">Notes<textarea name="notes" rows="2" class="mt-1.5 w-full rounded-md border border-border px-3 py-2 dark:border-gray-800 dark:bg-gray-950"></textarea></label>
                    <div class="flex justify-end"><button class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white">Save Repayment</button></div>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
