@extends('layouts.app')

@section('page_title', 'Z Reading')

@section('content')
@php($currency = $appSettings?->currency ?? 'PHP')

<div class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="receipt" class="h-3.5 w-3.5"></span>
                End-of-day closing
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Z Reading</h1>
            <p class="text-sm text-muted">Review saved cash counts, over/short balances, and signed PDF reports.</p>
        </div>

        <a href="{{ route('admin.z-readings.create', ['branch_id' => $branch->id, 'business_date' => $businessDate]) }}" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
            <span data-lucide="plus" class="h-4 w-4"></span>
            Create Z Reading
        </a>
    </div>

    <form method="GET" action="{{ route('admin.z-readings.index') }}" class="grid gap-2 rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-[1fr_1fr_auto]">
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

        <input name="business_date" value="{{ $businessDate }}" type="date" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">

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
                        <th class="px-4 py-3">Reading</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Prepared By</th>
                        <th class="px-4 py-3 text-right">Expected</th>
                        <th class="px-4 py-3 text-right">Actual</th>
                        <th class="px-4 py-3 text-right">Over / Short</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($readings as $history)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $history->reading_number }}</p>
                                <p class="text-xs text-muted">{{ $history->business_date?->format('M d, Y') }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $history->branch?->name }}</td>
                            <td class="px-4 py-3">{{ $history->preparer?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 text-right">{{ $currency }} {{ number_format((float) $history->expected_total_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ $currency }} {{ number_format((float) $history->actual_total_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ (float) $history->over_short_amount < 0 ? 'text-red-600' : ((float) $history->over_short_amount > 0 ? 'text-emerald-600' : '') }}">
                                {{ $currency }} {{ number_format((float) $history->over_short_amount, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.z-readings.create', ['branch_id' => $history->branch_id, 'business_date' => $history->business_date?->toDateString()]) }}" title="Open" aria-label="Open" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                                        <span data-lucide="eye" class="h-4 w-4"></span>
                                    </a>
                                    <a href="{{ route('admin.z-readings.pdf', $history) }}" target="_blank" title="PDF" aria-label="PDF" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                                        <span data-lucide="file-text" class="h-4 w-4"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-muted">No Z readings saved yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-3 dark:border-gray-800">
            {{ $readings->links() }}
        </div>
    </div>
</div>
@endsection
