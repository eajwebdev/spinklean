@extends('layouts.app')

@section('page_title', 'SMS Logs')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="smsLogs" class="h-3.5 w-3.5"></span>
                Optional SMS
            </div>
            <h1 class="text-xl font-semibold tracking-normal">SMS Logs</h1>
            <p class="text-sm text-muted">{{ $settings->sms_enabled ? 'SMS is enabled. Provider integration can process queued messages.' : 'SMS is disabled. Enable it in System Settings when ready.' }}</p>
        </div>
    </div>

    <div class="rounded-lg border border-border bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" action="{{ route('admin.sms-logs.index') }}" class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_11rem_9rem_auto]">
            <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                <input name="search" value="{{ request('search') }}" placeholder="Search customer, number, message..." class="w-full bg-transparent text-sm outline-none">
            </div>
            @if($canChooseBranch)
                <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\StatusBadge::label($status) }}</option>
                @endforeach
            </select>
            <button class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4"></span>
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Recipient</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Message</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($logs as $log)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $log->recipient }}</td>
                            <td class="px-4 py-3">{{ $log->customer?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3">{{ $log->branch?->name ?? 'N/A' }}</td>
                            <td class="max-w-md px-4 py-3"><p class="truncate" title="{{ $log->message }}">{{ $log->message }}</p><p class="text-xs text-muted">{{ $log->response }}</p></td>
                            <td class="px-4 py-3"><span class="{{ \App\Support\StatusBadge::classes($log->status) }}">{{ \App\Support\StatusBadge::label($log->status) }}</span></td>
                            <td class="px-4 py-3">{{ $log->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No SMS logs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-3 dark:border-gray-800">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
