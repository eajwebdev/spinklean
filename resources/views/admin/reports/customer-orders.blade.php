@extends('layouts.app')

@section('page_title', 'Customer Job Orders')

@section('content')
@php
    $currency = $settings->currency ?? 'PHP';
    $statusClass = fn ($status) => match ($status) {
        'released', 'completed' => 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
        'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
        'processing', 'in_production' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        default => 'bg-smoke text-muted dark:bg-gray-800',
    };
@endphp

<div class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="users" class="h-3.5 w-3.5"></span>
                Customer report
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Customer Job Orders</h1>
            <p class="text-sm text-muted">All job orders of a selected customer within a date range.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.reports.index') }}" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-medium hover:bg-smoke dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900">
                <span data-lucide="arrow-left" class="h-4 w-4"></span>
                Back to Reports
            </a>
            @if($customer)
                <a href="{{ route('admin.reports.customer-orders.pdf', request()->query()) }}" target="_blank" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
                    <span data-lucide="file-text" class="h-4 w-4"></span>
                    Download PDF
                </a>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('admin.reports.customer-orders') }}" class="grid gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-2 lg:grid-cols-[1fr_1fr_10rem_10rem_auto]">
        @if($canChooseBranch)
            <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All branches</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        @endif

        <select name="customer_id" required class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
            <option value="">Select customer…</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}" @selected($customer && (int) $customer->id === (int) $c->id)>{{ $c->name }}{{ $c->phone ? ' — '.$c->phone : '' }}</option>
            @endforeach
        </select>

        <input type="date" name="date_from" value="{{ $dateFrom }}" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
        <input type="date" name="date_to" value="{{ $dateTo }}" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">

        <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
            <span data-lucide="search" class="h-4 w-4"></span>
            Generate
        </button>
    </form>

    @if(! $customer)
        <div class="rounded-lg border border-dashed border-border bg-white p-10 text-center text-sm text-muted dark:border-gray-800 dark:bg-gray-900">
            Select a customer and date range, then click Generate to list their job orders.
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Job Orders</p>
                <p class="mt-1 text-lg font-semibold">{{ number_format($summary['orders']) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Total</p>
                <p class="mt-1 text-lg font-semibold">{{ $currency }} {{ number_format((float) $summary['total'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Paid</p>
                <p class="mt-1 text-lg font-semibold text-green-600 dark:text-green-400">{{ $currency }} {{ number_format((float) $summary['paid'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-muted">Balance</p>
                <p class="mt-1 text-lg font-semibold {{ $summary['balance'] > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ $currency }} {{ number_format((float) $summary['balance'], 2) }}</p>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-border p-4 dark:border-gray-800">
                <h2 class="text-base font-semibold">{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</h2>
                <p class="text-sm text-muted">{{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M d, Y') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3">Job Order</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Paid</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border dark:divide-gray-800">
                        @forelse($orders as $order)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $order->job_order_number }}@if($order->is_rush) <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-700 dark:bg-amber-950 dark:text-amber-300">Rush</span>@endif</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $order->created_at?->format('M d, Y') }}</td>
                                <td class="px-4 py-3">{{ $order->branch?->name }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs text-muted">{{ $order->items->map(fn ($i) => ($i->service?->name ?: $i->description).' ×'.rtrim(rtrim(number_format((float) $i->quantity, 2), '0'), '.'))->implode(', ') ?: '—' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass($order->status) }}">{{ \Illuminate\Support\Str::of($order->status)->replace('_', ' ')->title() }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $order->total, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $order->paid_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right {{ $order->balance > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ number_format((float) $order->balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-10 text-center text-muted">No job orders found for this customer in the selected date range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($orders->isNotEmpty())
                        <tfoot class="border-t border-border bg-smoke text-sm font-semibold dark:border-gray-800 dark:bg-gray-950">
                            <tr>
                                <td class="px-4 py-3" colspan="5">Total — {{ number_format($summary['orders']) }} job order{{ $summary['orders'] === 1 ? '' : 's' }}</td>
                                <td class="px-4 py-3 text-right">{{ $currency }} {{ number_format((float) $summary['total'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $summary['paid'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $summary['balance'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
