@extends('layouts.app')

@section('page_title', 'Cycle Monitoring')

@section('content')
@php($dateRangeValue = request('date_range') ?: ($dateFrom && $dateTo ? $dateFrom.' to '.$dateTo : ''))
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
    <div class="flex flex-col gap-3 rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-smoke px-2.5 py-1 text-xs font-medium text-muted dark:border-gray-800 dark:bg-gray-900">
                <span data-lucide="cycles" class="h-3.5 w-3.5"></span>
                Operations board
            </div>
            <h1 class="text-xl font-semibold">Cycle Monitoring</h1>
        </div>

        <form method="GET" class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(12rem,1fr)_12rem_14rem_16rem_12rem_auto]">
            <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4 text-muted"></span>
                <input name="search" value="{{ request('search') }}" type="search" placeholder="Search job or customer..." class="w-full bg-transparent text-sm outline-none">
            </div>
            @if($canChooseBranch)
                <select name="branch_id" onchange="this.form.customer_id.value = ''; this.form.submit()" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="customer_id" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">All customers</option>
                @if($customers->isEmpty())
                    <option value="" disabled>No customers in selected branch</option>
                @else
                    <optgroup label="{{ $canChooseBranch ? 'Customers in selected branch' : 'Customers in your branch' }}">
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) $selectedCustomerId === (int) $customer->id)>
                                {{ $customer->name }}
                            </option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <div class="flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 dark:border-gray-800 dark:bg-gray-950">
                <span data-lucide="calendar" class="h-4 w-4 text-muted"></span>
                <input x-ref="dateRange" x-model="dateRange" name="date_range" type="text" placeholder="Date range" autocomplete="off" class="w-full bg-transparent text-sm outline-none">
            </div>
            <select name="status" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">In Progress Status</option>
                @foreach($statusFilters as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $statusLabels[$status] ?? str_replace('_', ' ', ucfirst($status)) }}</option>
                @endforeach
            </select>
            <button type="submit" title="Filter" aria-label="Filter cycles" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                <span data-lucide="search" class="h-4 w-4"></span>
            </button>
        </form>
    </div>

    <div class="grid items-start gap-4 xl:grid-cols-[minmax(36rem,46rem)_minmax(26rem,1fr)]">
    @if($machineOverviewBranches->isNotEmpty())
        <section class="rounded-xl border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">Machine overview</p>
                    <h2 class="mt-1 text-lg font-semibold">Live availability</h2>
                </div>
                <p class="text-xs text-muted">
                    Usage counts by cycle date only:
                    {{ \Illuminate\Support\Carbon::parse($activityDateFrom)->format('M d, Y') }}
                    @if($activityDateFrom !== $activityDateTo)
                        - {{ \Illuminate\Support\Carbon::parse($activityDateTo)->format('M d, Y') }}
                    @endif
                </p>
            </div>

            <div class="space-y-4">
                @foreach($machineOverviewBranches as $machineBranch)
                    @php($machineTotal = (int) $machineBranch->machine_count)
                    @php($branchActiveMachines = $activeMachinesByBranch[$machineBranch->id] ?? [])
                    @php($branchMachineActivity = $machineActivityByBranch[$machineBranch->id] ?? [])
                    <article>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold">{{ $machineBranch->name }}</h3>
                                <p class="text-xs text-muted">{{ $machineTotal }} {{ \Illuminate\Support\Str::plural('machine', $machineTotal) }} configured</p>
                            </div>
                        </div>

                        @if($machineTotal > 0)
                            <div class="space-y-4">
                                @foreach(['wash' => 'Wash Machines', 'dry' => 'Dry Machines'] as $machineType => $machineLabel)
                                    <div>
                                        <div class="mb-2 flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full {{ $machineType === 'wash' ? 'bg-sky-500' : 'bg-violet-500' }}"></span>
                                            <h4 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted">{{ $machineLabel }}</h4>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-5">
                                            @for($machine = 1; $machine <= $machineTotal; $machine++)
                                                @php($activeMachine = data_get($branchActiveMachines, $machineType.'.'.$machine))
                                                @php($isAvailable = ! $activeMachine)
                                                @php($activityCount = (int) data_get($branchMachineActivity, $machine.'.'.$machineType, 0))
                                                <div class="machine-status-card min-w-0 overflow-hidden rounded-xl border border-border bg-gradient-to-b from-white to-slate-50 shadow-sm dark:border-gray-800 dark:from-gray-900 dark:to-gray-950">
                                                    <div class="flex items-center justify-between px-2.5 py-2">
                                                        <span class="truncate text-xs font-semibold">{{ $machineType === 'wash' ? 'Wash' : 'Dry' }} #{{ $machine }}</span>
                                                        <span class="h-2.5 w-2.5 rounded-full {{ $isAvailable ? 'bg-emerald-500' : 'machine-status-dot-running bg-red-500' }}" title="{{ $isAvailable ? 'Available' : 'In use' }}"></span>
                                                    </div>
                                                    <img
                                                        src="{{ asset($isAvailable ? 'available.png' : 'unavailable.png') }}"
                                                        alt="{{ $machineType === 'wash' ? 'Wash' : 'Dry' }} machine #{{ $machine }} {{ $isAvailable ? 'available' : 'unavailable' }}"
                                                        width="112"
                                                        height="112"
                                                        loading="lazy"
                                                        decoding="async"
                                                        class="machine-status-image {{ $isAvailable ? 'machine-status-image-ready' : 'machine-status-image-running' }} mx-auto h-20 w-20 rounded-lg object-cover"
                                                    >
                                                    <div class="border-t border-border px-1.5 py-1.5 text-center dark:border-gray-800">
                                                        <p class="text-base font-bold {{ $machineType === 'wash' ? 'text-sky-600' : 'text-violet-600' }}">{{ $activityCount }}</p>
                                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-muted">{{ $machineType === 'wash' ? 'Washing cycles' : 'Drying cycles' }}</p>
                                                    </div>
                                                    @if(! $isAvailable)
                                                        <div class="border-t border-border px-2 py-1.5 text-center text-[10px] font-medium text-red-600 dark:border-gray-800" title="{{ $activeMachine['job_order_number'] }}">
                                                            <p class="truncate">{{ $activeMachine['customer_name'] }}</p>
                                                            <p class="mt-0.5 truncate font-semibold">{{ $activeMachine['job_order_number'] }}</p>
                                                            @if($activeMachine['is_rush'] || $activeMachine['is_loyal'])
                                                                <div class="mt-1 flex justify-center gap-1">
                                                                    @if($activeMachine['is_rush'])
                                                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 font-semibold uppercase text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Rush</span>
                                                                    @endif
                                                                    @if($activeMachine['is_loyal'])
                                                                        <span class="rounded bg-violet-100 px-1.5 py-0.5 font-semibold uppercase text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Loyal</span>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endfor
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="w-full rounded-lg border border-dashed border-border py-6 text-center text-sm text-muted dark:border-gray-800">No machines configured.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="min-w-0 overflow-hidden rounded-xl border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-border px-4 py-3 dark:border-gray-800">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">Job Orders</p>
                <h2 class="mt-1 text-lg font-semibold">Cycle queue</h2>
            </div>
            <span class="rounded-md bg-smoke px-2 py-1 text-xs font-medium text-muted dark:bg-gray-950">{{ $orders->total() }} orders</span>
        </div>

        <div class="h-[42rem] overflow-y-auto p-3">
        <div class="grid grid-cols-1 gap-3">
        @forelse($orders as $order)
            @php($processingBranch = $order->processingBranch ?: $order->branch)
            @php($processingBranchId = $order->processing_branch_id ?: $order->branch_id)
            @php($currentBranch = $order->currentBranch ?: $processingBranch)
            @php($releaseBranch = $order->releaseBranch ?: $currentBranch)
            @php($activeMachines = $activeMachinesByBranch[$processingBranchId] ?? [])
            @php($userBranchId = auth()->user()->branch_id)
            @php($canManageAllBranches = auth()->user()->canManageAllBranches())
            @php($canReleaseHere = $canManageAllBranches || (int) ($releaseBranch?->id ?? $order->branch_id) === (int) $userBranchId)
            @php($canReturnToDropoff = (int) $processingBranchId !== (int) $order->branch_id && ($canManageAllBranches || (int) $processingBranchId === (int) $userBranchId))
            @php($isCrossBranchProduction = (int) $processingBranchId !== (int) $order->branch_id)
            <div class="rounded-lg border border-border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <p class="truncate font-semibold">{{ $order->customer?->name ?? 'Unknown customer' }}</p>
                            @if($order->is_rush)
                                <span class="rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-800 dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-300">Rush</span>
                            @endif
                            @if((int) ($order->customer?->job_orders_count ?? 0) >= 10)
                                <span class="rounded-md border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-violet-700 dark:border-violet-900/60 dark:bg-violet-500/10 dark:text-violet-300">Loyal Customer</span>
                            @endif
                        </div>
                        <p class="truncate text-sm text-muted">{{ $order->job_order_number }}</p>
                        <p class="text-xs font-medium text-primary">Order date: {{ $order->created_at?->format('M d, Y') }}</p>
                        <p class="truncate text-xs text-muted">
                            Drop-off: {{ $order->branch?->name }} - Receiving/Processing: {{ $processingBranch?->name }} - Release: {{ $releaseBranch?->name }}
                        </p>
                        @if($isCrossBranchProduction)
                            @if($order->production_accepted_at)
                                <p class="text-xs text-emerald-600">Received by QR scan {{ $order->production_accepted_at->format('M d, h:i A') }}</p>
                            @else
                                <p class="text-xs text-amber-600">Assigned only. Waiting for {{ $processingBranch?->name }} QR scan before branch cycle.</p>
                            @endif
                        @endif
                    </div>
                    <span class="shrink-0 {{ \App\Support\StatusBadge::classes($order->status) }}">
                        {{ $statusLabels[$order->status] ?? str_replace('_', ' ', ucfirst($order->status)) }}
                    </span>
                </div>

                {{--
                Branch routing summary is temporarily hidden until multi-branch operations are in use.
                <div class="mb-3 grid gap-2 text-xs text-muted sm:grid-cols-4">
                    <div class="rounded-md bg-smoke px-2.5 py-2 dark:bg-gray-950">
                        <span class="block font-medium text-ink dark:text-gray-100">Drop-off</span>
                        {{ $order->branch?->name ?? 'Unassigned' }}
                    </div>
                    <div class="rounded-md bg-smoke px-2.5 py-2 dark:bg-gray-950">
                        <span class="block font-medium text-ink dark:text-gray-100">Receiving</span>
                        {{ $processingBranch?->name ?? 'Unassigned' }}
                        @if($isCrossBranchProduction)
                            <span class="block {{ $order->production_accepted_at ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ $order->production_accepted_at ? 'QR received' : 'Pending QR scan' }}
                            </span>
                        @endif
                    </div>
                    <div class="rounded-md bg-smoke px-2.5 py-2 dark:bg-gray-950">
                        <span class="block font-medium text-ink dark:text-gray-100">Current</span>
                        {{ $currentBranch?->name ?? 'Unassigned' }}
                    </div>
                    <div class="rounded-md bg-smoke px-2.5 py-2 dark:bg-gray-950">
                        <span class="block font-medium text-ink dark:text-gray-100">Release</span>
                        {{ $releaseBranch?->name ?? 'Unassigned' }}
                    </div>
                </div>
                --}}

                @if(! in_array($order->status, ['ready_for_pickup', 'ready_for_delivery', 'completed'], true))
                    <div class="mb-2 space-y-1">
                        @foreach(['wash' => $cycleTypes['wash'], 'dry' => $cycleTypes['dry']] as $type => $label)
                            <form method="POST" action="{{ route('admin.cycles.store', $order) }}">
                                @csrf
                                <input type="hidden" name="cycle_type" value="{{ $type }}">
                                <div class="flex flex-col gap-1.5 rounded-md border border-border bg-white p-1.5 dark:border-gray-800 dark:bg-gray-950">
                                    @if((int) ($processingBranch?->machine_count ?? 0) > 0)
                                        <div class="flex flex-wrap gap-1">
                                            @for($machine = 1; $machine <= (int) $processingBranch->machine_count; $machine++)
                                                @php($usingMachine = data_get($activeMachines, $type.'.'.$machine))
                                                <label class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium {{ $usingMachine ? 'bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400 opacity-50 cursor-not-allowed' : 'hover:bg-smoke dark:hover:bg-gray-900 cursor-pointer' }} border border-border dark:border-gray-700">
                                                    <input type="checkbox" name="machine_numbers[]" value="{{ $machine }}" {{ $usingMachine ? 'disabled' : '' }} class="h-3 w-3 rounded border-border text-primary">
                                                    <span>#{{ $machine }}</span>
                                                </label>
                                            @endfor
                                        </div>
                                    @endif
                                    <button type="submit" title="Start {{ $label }}" class="inline-flex h-7 items-center justify-center gap-1 rounded bg-primary px-2 text-[11px] font-medium text-white hover:opacity-90">
                                        <span data-lucide="plus" class="h-3 w-3"></span>
                                        {{ $label }}
                                    </button>
                                </div>
                            </form>
                        @endforeach
                    </div>

                    <div class="mb-3 grid grid-cols-4 gap-1">
                        @foreach(['fold' => $cycleTypes['fold'], 'iron' => $cycleTypes['iron']] as $type => $label)
                            <form method="POST" action="{{ route('admin.cycles.store', $order) }}">
                                @csrf
                                <input type="hidden" name="cycle_type" value="{{ $type }}">
                                <button type="submit" title="Start {{ $label }}" class="inline-flex h-9 w-full items-center justify-center gap-1 rounded-md bg-primary px-1 text-[11px] font-medium text-white hover:opacity-90">
                                    <span data-lucide="plus" class="h-3 w-3"></span>
                                    {{ $label }}
                                </button>
                            </form>
                        @endforeach
                        @foreach([
                            'ready_for_pickup' => ['label' => 'Ready for Pickup', 'icon' => 'package-check', 'classes' => 'bg-teal-600 hover:bg-teal-700', 'color' => '#0f766e'],
                            'ready_for_delivery' => ['label' => 'Ready for Delivery', 'icon' => 'truck', 'classes' => 'bg-orange-600 hover:bg-orange-700', 'color' => '#ea580c'],
                        ] as $readyStatus => $readyAction)
                            <form method="POST" action="{{ route('admin.cycles.status', $order) }}" x-data>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $readyStatus }}">
                                @if((int) $order->active_cycles_count > 0)
                                    <button
                                        type="button"
                                        x-on:click="Swal.fire({ title: 'Cycle still running', text: @js('This job order still has '.$order->active_cycles_count.' active '.\Illuminate\Support\Str::plural('cycle', $order->active_cycles_count).'. End all cycles before marking it as '.$readyAction['label'].'.'), icon: 'warning', confirmButtonColor: '#dc2626' })"
                                        class="inline-flex h-9 w-full cursor-not-allowed items-center justify-center gap-1 rounded-md px-1 text-[11px] font-semibold text-white opacity-50 {{ $readyAction['classes'] }}"
                                        title="End all active cycles first"
                                    >
                                        <span data-lucide="{{ $readyAction['icon'] }}" class="h-4 w-4"></span>
                                        {{ $readyAction['label'] }}
                                    </button>
                                @else
                                    <button
                                        type="submit"
                                        x-on:click.prevent="Swal.fire({ title: @js($readyAction['label'].'?'), text: 'This will finish production and notify the customer that the laundry is ready.', icon: 'question', showCancelButton: true, confirmButtonColor: @js($readyAction['color']), confirmButtonText: 'Mark as Ready' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })"
                                        class="inline-flex h-9 w-full items-center justify-center gap-1 rounded-md px-1 text-[11px] font-semibold text-white {{ $readyAction['classes'] }}"
                                    >
                                        <span data-lucide="{{ $readyAction['icon'] }}" class="h-4 w-4"></span>
                                        {{ $readyAction['label'] }}
                                    </button>
                                @endif
                            </form>
                        @endforeach
                    </div>

                @endif

                <div class="border-t border-border pt-3 dark:border-gray-800">
                    @if($order->cycles_count > $order->cycles->count())
                        <p class="text-xs text-muted">
                            Showing latest {{ $order->cycles->count() }} of {{ $order->cycles_count }} cycle records.
                        </p>
                    @endif

                    <div class="flex flex-nowrap gap-2 overflow-x-auto pb-2">
                    @forelse($order->cycles as $cycle)
                        <div class="flex w-52 shrink-0 items-center justify-between gap-2 rounded-md border border-border bg-smoke px-2.5 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
                            <div class="min-w-0">
                                <p class="font-medium">
                                    {{ $cycleTypes[$cycle->cycle_type] ?? ucfirst($cycle->cycle_type) }} #{{ $cycle->cycle_number }}
                                    @if($cycle->machine_number)
                                        <span class="text-xs font-normal text-muted">Machine #{{ $cycle->machine_number }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-muted">
                                    {{ $cycle->started_at?->format('M d, h:i A') ?? 'Not started' }}
                                    @if($cycle->ended_at) - {{ $cycle->ended_at->format('h:i A') }} @endif
                                </p>
                                <p class="truncate text-[11px] text-muted">{{ $cycle->user?->name ?? 'System user' }}</p>
                            </div>

                            <div class="flex items-center gap-1">
                                @if(! $cycle->ended_at)
                                    <form method="POST" action="{{ route('admin.cycles.end', $cycle) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button title="End cycle" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border hover:bg-white dark:border-gray-800 dark:hover:bg-gray-900">
                                            <span data-lucide="check" class="h-4 w-4"></span>
                                        </button>
                                    </form>
                                @else
                                    <span class="px-2 text-xs text-muted">Done</span>
                                @endif

                                <form method="POST" action="{{ route('admin.cycles.destroy', $cycle) }}" x-data>
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Remove cycle"
                                        aria-label="Remove cycle"
                                        x-on:click.prevent="Swal.fire({ title: 'Remove cycle?', text: 'Use this to clean duplicate or accidental cycle taps.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Remove' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-900/70 dark:hover:bg-red-950/30"
                                    >
                                        <span data-lucide="trash" class="h-4 w-4"></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="w-full rounded-md border border-dashed border-border py-4 text-center text-sm text-muted dark:border-gray-800">No cycles yet.</p>
                    @endforelse
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-border bg-white p-10 text-center text-sm text-muted dark:border-gray-800 dark:bg-gray-900">
                No active job orders to monitor.
            </div>
        @endforelse
        </div>
        </div>

        <div class="border-t border-border px-4 py-3 dark:border-gray-800">{{ $orders->links() }}</div>
    </section>
    </div>
</div>
@endsection
