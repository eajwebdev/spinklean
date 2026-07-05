@extends('layouts.app')

@section('page_title', 'Petty Cash')

@section('content')
@php
    $currency = $appSettings?->currency ?? 'PHP';
    $cashIn = (float) ($summary->cash_in ?? 0);
    $cashOut = (float) ($summary->cash_out ?? 0);
@endphp

<div class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="wallet" class="h-3.5 w-3.5"></span>
                Cash flow
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Petty Cash</h1>
            <p class="text-sm text-muted">Track cash deposits and withdrawals for the branch drawer.</p>
        </div>

        <div class="grid grid-cols-3 gap-2 sm:min-w-[28rem]">
            <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium text-muted">Cash In</p>
                <p class="mt-1 text-base font-semibold">{{ $currency }} {{ number_format($cashIn, 2) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium text-muted">Cash Out</p>
                <p class="mt-1 text-base font-semibold">{{ $currency }} {{ number_format($cashOut, 2) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-smoke p-3 dark:border-gray-800 dark:bg-gray-950">
                <p class="text-xs font-medium text-muted">Net</p>
                <p class="mt-1 text-base font-semibold {{ ($cashIn - $cashOut) < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $currency }} {{ number_format($cashIn - $cashOut, 2) }}</p>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.petty-cash.index') }}" class="grid gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-[1fr_1fr_1fr_auto]">
        @if($canChooseBranch)
            <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                @foreach($branches as $optionBranch)
                    <option value="{{ $optionBranch->id }}" @selected((int) $branch->id === (int) $optionBranch->id)>{{ $optionBranch->name }}</option>
                @endforeach
            </select>
        @else
            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
            <div class="flex h-9 items-center rounded-md border border-border bg-smoke px-3 text-sm font-medium dark:border-gray-800 dark:bg-gray-950">{{ $branch->name }}</div>
        @endif

        <input name="movement_date" value="{{ $businessDate }}" type="date" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">

        <select name="type" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
            <option value="">All movements</option>
            @foreach($movementTypes as $type => $meta)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ $meta['label'] }}</option>
            @endforeach
        </select>

        <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
            <span data-lucide="search" class="h-4 w-4"></span>
            Filter
        </button>
    </form>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
        <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <span data-lucide="wallet" class="h-5 w-5"></span>
                </div>
                <div>
                    <h2 class="text-base font-semibold">New Petty Cash Voucher</h2>
                    <p class="text-sm text-muted">Record a branch cash deposit or withdrawal.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.petty-cash.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <input type="hidden" name="movement_date" value="{{ $businessDate }}">

                <label class="block">
                    <span class="text-xs font-medium text-muted">Voucher Type</span>
                    <select name="type" required class="mt-1 h-10 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        @foreach($movementTypes as $type => $meta)
                            <option value="{{ $type }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-muted">Amount</span>
                    <input name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" required placeholder="0.00" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-muted">Reference No.</span>
                    <input name="reference_no" maxlength="255" placeholder="OR or remittance reference..." class="mt-1 h-10 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-muted">Purpose / Notes</span>
                    <input name="description" maxlength="255" placeholder="What is this movement for?" class="mt-1 h-10 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                </label>

                <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">
                    <span data-lucide="save" class="h-4 w-4"></span>
                    Submit Voucher
                </button>
            </form>
        </div>

        <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-border p-4 dark:border-gray-800">
                <h2 class="text-base font-semibold">Recent Vouchers</h2>
                <p class="text-sm text-muted">Cash movements for the selected filters.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3">Voucher</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3">Recorded By</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        @forelse($movements as $movement)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $movement->type_label }}</p>
                                    <p class="text-xs text-muted">{{ $movement->movement_date?->format('M d, Y') }}{{ $movement->description ? ' - '.$movement->description : '' }}</p>
                                </td>
                                <td class="px-4 py-3">{{ $movement->branch?->name }}</td>
                                <td class="px-4 py-3">{{ $movement->reference_no ?: 'N/A' }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $movement->direction === 'in' ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $movement->direction === 'in' ? '+' : '-' }} {{ $currency }} {{ number_format((float) $movement->amount, 2) }}
                                </td>
                                <td class="px-4 py-3">{{ $movement->recorder?->name ?? 'System' }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.petty-cash.destroy', $movement) }}" class="flex justify-end">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete" aria-label="Delete voucher" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                                            <span data-lucide="trash" class="h-4 w-4"></span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-muted">No vouchers recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-4 py-3 dark:border-gray-800">
                {{ $movements->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
