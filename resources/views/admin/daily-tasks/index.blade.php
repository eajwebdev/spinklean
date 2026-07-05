@extends('layouts.app')

@section('page_title', 'End-of-Day Tasks')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="check" class="h-3.5 w-3.5"></span>
                Daily proof checklist
            </div>
            <h1 class="text-xl font-semibold tracking-normal">End-of-Day Tasks</h1>
            <p class="text-sm text-muted">Upload proof for the daily tasks configured in the Branches module.</p>
        </div>

        <form method="GET" class="grid grid-cols-1 gap-2 sm:grid-cols-[12rem_10rem_auto]">
            @if($canChooseBranch)
                <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
            @endif
            <input type="date" name="date" value="{{ $workDate }}" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
            <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90"><span data-lucide="search" class="h-4 w-4"></span>View</button>
        </form>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        @forelse($tasks as $task)
            @php($completion = $task->completions->first())
            @php($completedBy = $completion?->completer?->name ?? $completion?->employeeCompleter?->name ?? 'N/A')
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $task->name }}</h2>
                        <p class="text-xs text-muted">{{ $completion ? 'Completed '.$completion->completed_at?->format('M d, h:i A').' by '.$completedBy : 'Pending photo proof' }}</p>
                    </div>
                    <span class="{{ \App\Support\StatusBadge::classes($completion ? 'completed' : 'pending') }}">{{ $completion ? 'Done' : 'Pending' }}</span>
                </div>

                @if($completion)
                    <a href="{{ \App\Support\PublicUpload::url($completion->photo_path) }}" target="_blank" class="mb-3 block overflow-hidden rounded-md border border-border dark:border-gray-800">
                        <img src="{{ \App\Support\PublicUpload::url($completion->photo_path) }}" alt="{{ $task->name }} proof" class="h-44 w-full object-cover">
                    </a>
                    @if($completion->remarks)
                        <p class="mb-3 rounded-md bg-smoke p-2 text-sm text-muted dark:bg-gray-950">{{ $completion->remarks }}</p>
                    @endif
                @endif

                <form method="POST" action="{{ route('admin.daily-tasks.complete', $task) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    <input type="hidden" name="work_date" value="{{ $workDate }}">
                    <input type="file" name="photo" accept="image/*" required class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <textarea name="remarks" rows="2" placeholder="Remarks" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950"></textarea>
                    <button class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90"><span data-lucide="check" class="h-4 w-4"></span>{{ $completion ? 'Replace Proof' : 'Mark Done' }}</button>
                </form>
            </div>
        @empty
            <div class="rounded-lg border border-border bg-white p-8 text-center text-sm text-muted shadow-sm dark:border-gray-800 dark:bg-gray-900">No end-of-day tasks configured for this branch.</div>
        @endforelse
    </div>
</div>
@endsection
