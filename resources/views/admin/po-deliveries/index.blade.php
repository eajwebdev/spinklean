@extends('layouts.app')

@section('page_title', 'Delivery Receipts')

@section('content')
@php
    $currency = $settings->currency ?? 'PHP';
    $dateRangeValue = request('date_range', '');
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
        },
    }"
    class="space-y-4"
>
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="truck" class="h-3.5 w-3.5"></span>
                Corporate billing
            </div>
            <h1 class="text-xl font-semibold">Delivery Receipts</h1>
            <p class="text-sm text-muted">Every delivery trip to a PO customer. The Statement of Account bills each DR on its delivery date.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.po-transactions.index') }}" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900">
                <span data-lucide="arrow-left" class="h-4 w-4"></span>
                PO Transactions
            </a>
            <a href="{{ route('admin.po-deliveries.create', array_filter(['customer_id' => request('customer_id')])) }}" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
                <span data-lucide="plus" class="h-4 w-4"></span>
                New DR
            </a>
        </div>
    </div>

    @if(session('printDeliveryId'))
        <div class="flex flex-col gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-medium">The Delivery Receipt is saved. Print two copies: one for the customer to sign, one to keep.</p>
            <a href="{{ route('admin.po-deliveries.print', session('printDeliveryId')) }}" target="_blank" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-green-700 px-3 text-sm font-semibold text-white hover:opacity-90">
                <span data-lucide="printer" class="h-4 w-4"></span>
                Print DR
            </a>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.po-deliveries.index') }}" class="flex flex-col gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:flex-row md:items-center">
        <div class="flex h-9 min-w-0 flex-1 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
            <span data-lucide="search" class="h-4 w-4 text-muted"></span>
            <input name="search" value="{{ request('search') }}" type="search" placeholder="Search DR # or job order..." class="w-full bg-transparent text-sm outline-none">
        </div>
        <select name="customer_id" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950 md:w-56">
            <option value="">All PO customers</option>
            @foreach($customers as $option)
                <option value="{{ $option->id }}" @selected((int) request('customer_id') === (int) $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
        <div class="flex h-9 w-full items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950 md:w-64">
            <span data-lucide="calendar" class="h-4 w-4 text-muted"></span>
            <input x-ref="dateRange" x-model="dateRange" name="date_range" type="text" placeholder="Delivery dates" autocomplete="off" class="w-full bg-transparent text-sm outline-none">
        </div>
        <button type="submit" title="Filter" aria-label="Filter delivery receipts" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
            <span data-lucide="search" class="h-4 w-4"></span>
        </button>
    </form>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">DR #</th>
                        <th class="px-4 py-3">Delivered</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Job Orders</th>
                        <th class="px-4 py-3 text-right">Pieces</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Recorded By</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($deliveries as $delivery)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $delivery->dr_number }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $delivery->delivered_on->format('M d, Y') }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $delivery->customer?->name }}</p>
                                <p class="text-xs text-muted">{{ $delivery->branch?->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $delivery->items->map(fn ($item) => $item->jobOrder?->job_order_number)->filter()->unique()->implode(', ') }}</td>
                            <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $delivery->items->reject(fn ($item) => \App\Support\StatementOfAccountRows::isDeliveryCharge($item))->sum('quantity'), 2), '0'), '.') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">{{ $currency }} {{ number_format((float) $delivery->amount, 2) }}</td>
                            <td class="px-4 py-3 text-xs text-muted">{{ $delivery->creator?->name ?? 'System' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('admin.po-deliveries.print', $delivery) }}" target="_blank" title="Print DR" aria-label="Print delivery receipt" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-700 dark:hover:bg-gray-800">
                                    <span data-lucide="printer" class="h-4 w-4"></span>
                                </a>
                                <form method="POST" action="{{ route('admin.po-deliveries.destroy', $delivery) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Void DR"
                                        aria-label="Void delivery receipt"
                                        x-on:click.prevent="Swal.fire({ title: @js('Void DR #'.$delivery->dr_number.'?'), text: 'Its pieces go back to not yet delivered and it is removed from the Statement of Account. Payments are not changed.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Void DR', confirmButtonColor: '#dc2626' }).then((r) => { if (r.isConfirmed) $el.closest('form').submit(); })"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-900/60 dark:hover:bg-red-500/10"
                                    >
                                        <span data-lucide="trash-2" class="h-4 w-4"></span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-muted">No delivery receipts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-3 dark:border-gray-800">
            {{ $deliveries->links() }}
        </div>
    </div>
</div>
@endsection
