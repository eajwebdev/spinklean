@extends('layouts.app')

@section('page_title', 'Attendance')

@section('content')
@php($dateRangeValue = request('date_range') ?: ($dateFrom && $dateTo ? $dateFrom.' to '.$dateTo : ''))
<div
    x-data="{
        proofOpen: false,
        proofUrl: '',
        proofTitle: '',
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
                <span data-lucide="attendance" class="h-3.5 w-3.5"></span>
                Attendance records
            </div>
            <h1 class="text-xl font-semibold tracking-normal">Attendance</h1>
            <p class="text-sm text-muted">Review employee clock-in and clock-out logs by date.</p>
        </div>

        <form method="GET" class="grid grid-cols-1 gap-2 sm:grid-cols-[12rem_14rem_16rem_auto]">
            @if($canChooseBranch)
                <select name="branch_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="employee_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All employees</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) $selectedEmployeeId === (int) $employee->id)>
                        {{ $employee->name }}{{ $canChooseBranch && ! $selectedBranchId ? ' - '.($employee->branch?->name ?? 'No branch') : '' }}
                    </option>
                @endforeach
            </select>
            <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="calendar" class="h-4 w-4 text-muted"></span>
                <input x-ref="dateRange" x-model="dateRange" name="date_range" type="text" placeholder="Date range" autocomplete="off" class="w-full bg-transparent text-sm outline-none">
            </div>
            <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-white hover:opacity-90">
                <span data-lucide="search" class="h-4 w-4"></span>
                Filter
            </button>
        </form>
    </div>

    <div class="rounded-lg border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-1 border-b border-border px-4 py-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-base font-semibold">
                {{ $dateFrom === $dateTo ? \Illuminate\Support\Carbon::parse($dateFrom)->format('M d, Y') : \Illuminate\Support\Carbon::parse($dateFrom)->format('M d, Y').' - '.\Illuminate\Support\Carbon::parse($dateTo)->format('M d, Y') }} Logs
            </h2>
            <p class="text-sm text-muted">{{ $records->total() }} record{{ $records->total() === 1 ? '' : 's' }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-smoke text-xs uppercase text-muted dark:border-gray-800 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3">Clock In</th>
                        <th class="px-4 py-3">Time In Proof</th>
                        <th class="px-4 py-3">Clock Out</th>
                        <th class="px-4 py-3">Time Out Proof</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border dark:divide-gray-800">
                    @forelse($records as $record)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $record->employee?->name ?? 'Deleted employee' }}</p>
                                <p class="text-xs text-muted">{{ $record->employee?->username ?? 'N/A' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $record->branch?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3">{{ \App\Support\TimeDisplay::attendanceList($record->clock_in) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @forelse($record->clock_in_photos ?? [] as $index => $photo)
                                        @php($proofUrl = route('admin.attendance.proof', ['record' => $record, 'type' => 'clock-in', 'index' => $index]))
                                        <div class="w-24 overflow-hidden rounded-md border border-border bg-white dark:border-gray-800 dark:bg-gray-950">
                                            <button
                                                type="button"
                                                @click="proofOpen = true; proofUrl = @js($proofUrl); proofTitle = @js(($record->employee?->name ?? 'Employee').' Time In '.\App\Support\TimeDisplay::attendance($record->clock_in[$index] ?? null))"
                                                class="block w-full"
                                            >
                                                <img src="{{ $proofUrl }}" alt="Time in proof {{ $index + 1 }}" class="h-16 w-full object-cover">
                                            </button>
                                            <div class="flex border-t border-border dark:border-gray-800">
                                                <button
                                                    type="button"
                                                    @click="proofOpen = true; proofUrl = @js($proofUrl); proofTitle = @js(($record->employee?->name ?? 'Employee').' Time In '.\App\Support\TimeDisplay::attendance($record->clock_in[$index] ?? null))"
                                                    class="inline-flex h-7 flex-1 items-center justify-center gap-1 text-xs font-medium hover:bg-smoke dark:hover:bg-gray-900"
                                                >
                                                    <span data-lucide="eye" class="h-3.5 w-3.5"></span>
                                                    View
                                                </button>
                                                <a
                                                    href="{{ $proofUrl }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex h-7 w-8 items-center justify-center border-l border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-900"
                                                    title="Open proof"
                                                    aria-label="Open time in proof {{ $index + 1 }}"
                                                >
                                                    <span data-lucide="external-link" class="h-3.5 w-3.5"></span>
                                                </a>
                                            </div>
                                        </div>
                                    @empty
                                        <span class="text-xs text-muted">No time-in proof</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ \App\Support\TimeDisplay::attendanceList($record->clock_out) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @forelse($record->clock_out_photos ?? [] as $index => $photo)
                                        @php($proofUrl = route('admin.attendance.proof', ['record' => $record, 'type' => 'clock-out', 'index' => $index]))
                                        <div class="w-24 overflow-hidden rounded-md border border-border bg-white dark:border-gray-800 dark:bg-gray-950">
                                            <button
                                                type="button"
                                                @click="proofOpen = true; proofUrl = @js($proofUrl); proofTitle = @js(($record->employee?->name ?? 'Employee').' Time Out '.\App\Support\TimeDisplay::attendance($record->clock_out[$index] ?? null))"
                                                class="block w-full"
                                            >
                                                <img src="{{ $proofUrl }}" alt="Time out proof {{ $index + 1 }}" class="h-16 w-full object-cover">
                                            </button>
                                            <div class="flex border-t border-border dark:border-gray-800">
                                                <button
                                                    type="button"
                                                    @click="proofOpen = true; proofUrl = @js($proofUrl); proofTitle = @js(($record->employee?->name ?? 'Employee').' Time Out '.\App\Support\TimeDisplay::attendance($record->clock_out[$index] ?? null))"
                                                    class="inline-flex h-7 flex-1 items-center justify-center gap-1 text-xs font-medium hover:bg-smoke dark:hover:bg-gray-900"
                                                >
                                                    <span data-lucide="eye" class="h-3.5 w-3.5"></span>
                                                    View
                                                </button>
                                                <a
                                                    href="{{ $proofUrl }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex h-7 w-8 items-center justify-center border-l border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-900"
                                                    title="Open proof"
                                                    aria-label="Open time out proof {{ $index + 1 }}"
                                                >
                                                    <span data-lucide="external-link" class="h-3.5 w-3.5"></span>
                                                </a>
                                            </div>
                                        </div>
                                    @empty
                                        <span class="text-xs text-muted">No time-out proof</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No attendance logs for this date.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-3 dark:border-gray-800">{{ $records->links() }}</div>
    </div>

    <div x-cloak x-show="proofOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
        <div @click.outside="proofOpen = false" class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-4 shadow-2xl dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="min-w-0 truncate text-base font-semibold" x-text="proofTitle || 'Attendance Proof'"></h2>
                <button type="button" @click="proofOpen = false" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                    <span data-lucide="x" class="h-4 w-4"></span>
                </button>
            </div>
            <img :src="proofUrl" alt="Attendance proof" class="max-h-[75vh] w-full rounded-md object-contain">
        </div>
    </div>
</div>
@endsection
