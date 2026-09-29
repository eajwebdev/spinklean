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
            @php($selectedWash = old('machines.wash', $completion?->cleanedWashMachines() ?? []))
            @php($selectedDry = old('machines.dry', $completion?->cleanedDryMachines() ?? []))
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-semibold">{{ $task->name }}</h2>
                            @if($task->affectsMachineCounter())
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                    {{ $task->machineImpactLabel() }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-muted">{{ $completion ? 'Completed '.$completion->completed_at?->format('M d, h:i A').' by '.$completedBy : 'Pending photo proof' }}</p>
                    </div>
                    <span class="{{ \App\Support\StatusBadge::classes($completion ? 'completed' : 'pending') }}">{{ $completion ? 'Done' : 'Pending' }}</span>
                </div>

                @if($completion)
                    <a href="{{ \App\Support\PublicUpload::url($completion->photo_path) }}" target="_blank" class="mb-3 block overflow-hidden rounded-md border border-border dark:border-gray-800">
                        <img src="{{ \App\Support\PublicUpload::url($completion->photo_path) }}" alt="{{ $task->name }} proof" class="h-44 w-full object-cover">
                    </a>
                    @if($completion->hasCleanedMachines())
                        <div class="mb-3 rounded-md border border-blue-200 bg-blue-50/80 p-2.5 text-xs text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/40 dark:text-blue-200">
                            <div class="flex items-center gap-1.5 font-semibold">
                                <span data-lucide="check-circle" class="h-3.5 w-3.5 text-blue-600"></span>
                                Cleaned Machines Recorded (+1 cycle each in Z Reading):
                            </div>
                            <div class="mt-1 font-semibold text-blue-800 dark:text-blue-300">
                                {{ $completion->cleanedMachinesSummary() }}
                            </div>
                        </div>
                    @endif
                    @if($completion->remarks)
                        <p class="mb-3 rounded-md bg-smoke p-2 text-sm text-muted dark:bg-gray-950">{{ $completion->remarks }}</p>
                    @endif
                @endif

                <form method="POST" action="{{ route('admin.daily-tasks.complete', $task) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    <input type="hidden" name="work_date" value="{{ $workDate }}">

                    @if($task->affectsMachineCounter())
                        <div
                            x-data="{
                                selectAll(type, count) {
                                    for (let i = 1; i <= count; i++) {
                                        const el = document.getElementById(type + '_{{ $task->id }}_' + i);
                                        if (el) el.checked = true;
                                    }
                                },
                                clearAll(type, count) {
                                    for (let i = 1; i <= count; i++) {
                                        const el = document.getElementById(type + '_{{ $task->id }}_' + i);
                                        if (el) el.checked = false;
                                    }
                                }
                            }"
                            class="space-y-2.5 rounded-lg border border-dashed border-blue-200 bg-blue-50/30 p-3 dark:border-blue-900/40 dark:bg-blue-950/20"
                        >
                            <div class="flex items-center justify-between text-xs font-semibold text-blue-950 dark:text-blue-100">
                                <span class="flex items-center gap-1.5">
                                    <span data-lucide="cpu" class="h-4 w-4 text-blue-600"></span>
                                    Select Machines Cleaned (+1 cycle in Z Reading):
                                </span>
                            </div>

                            @if($task->affectsWash())
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-[11px] font-medium text-muted">
                                        <span>Cleaned Washers:</span>
                                        <div class="flex gap-2">
                                            <button type="button" @click="selectAll('wash', {{ $machineCount }})" class="text-primary hover:underline">Select All</button>
                                            <span class="text-gray-300 dark:text-gray-700">|</span>
                                            <button type="button" @click="clearAll('wash', {{ $machineCount }})" class="text-muted hover:underline">Clear</button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                                        @for($m = 1; $m <= $machineCount; $m++)
                                            <label for="wash_{{ $task->id }}_{{ $m }}" class="flex items-center gap-2 rounded-md border border-border bg-white px-2.5 py-1.5 text-xs font-medium cursor-pointer hover:bg-smoke dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800">
                                                <input type="checkbox" id="wash_{{ $task->id }}_{{ $m }}" name="machines[wash][]" value="{{ $m }}" @checked(in_array($m, $selectedWash)) class="rounded border-border text-primary focus:ring-primary">
                                                <span>Wash {{ $m }}</span>
                                            </label>
                                        @endfor
                                    </div>
                                </div>
                            @endif

                            @if($task->affectsDry())
                                <div class="space-y-1 pt-1">
                                    <div class="flex items-center justify-between text-[11px] font-medium text-muted">
                                        <span>Cleaned Dryers:</span>
                                        <div class="flex gap-2">
                                            <button type="button" @click="selectAll('dry', {{ $machineCount }})" class="text-primary hover:underline">Select All</button>
                                            <span class="text-gray-300 dark:text-gray-700">|</span>
                                            <button type="button" @click="clearAll('dry', {{ $machineCount }})" class="text-muted hover:underline">Clear</button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                                        @for($m = 1; $m <= $machineCount; $m++)
                                            <label for="dry_{{ $task->id }}_{{ $m }}" class="flex items-center gap-2 rounded-md border border-border bg-white px-2.5 py-1.5 text-xs font-medium cursor-pointer hover:bg-smoke dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800">
                                                <input type="checkbox" id="dry_{{ $task->id }}_{{ $m }}" name="machines[dry][]" value="{{ $m }}" @checked(in_array($m, $selectedDry)) class="rounded border-border text-primary focus:ring-primary">
                                                <span>Dry {{ $m }}</span>
                                            </label>
                                        @endfor
                                    </div>
                                </div>
                            @endif

                            @error('machines')
                                <p class="text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

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
