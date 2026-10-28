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
            <div class="flex items-center gap-2">
                <select name="order_type" class="h-9 rounded-md border border-border bg-white px-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <option value="">All orders</option>
                    <option value="po" @selected($selectedOrderType === 'po')>PO only</option>
                    <option value="regular" @selected($selectedOrderType === 'regular')>Regular only</option>
                </select>
                <button type="submit" title="Filter" aria-label="Filter cycles" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border hover:bg-smoke dark:border-gray-800 dark:hover:bg-gray-950">
                    <span data-lucide="search" class="h-4 w-4"></span>
                </button>
            </div>
        </form>
    </div>

    <div
        x-data="{
            pendingCount: 0,
            async checkIncoming() {
                try {
                    const res = await fetch('{{ route('admin.transfers.count') }}', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (res.ok) {
                        const data = await res.json();
                        this.pendingCount = data.pending_count ?? 0;
                    }
                } catch(e) {}
            }
        }"
        x-init="checkIncoming()"
        @tags-updated.window="checkIncoming()"
        @incoming-tags-changed.window="pendingCount = $event.detail.count"
        x-show="pendingCount > 0"
        x-cloak
        class="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3.5 text-amber-900 shadow-sm dark:border-amber-900/60 dark:bg-amber-500/10 dark:text-amber-300 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-200/70 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">
                <span data-lucide="tag" class="h-5 w-5"></span>
            </div>
            <div>
                <p class="text-sm font-bold">Incoming Laundry Tags Waiting to be Received</p>
                <p class="text-xs opacity-90"><span class="js-tag-count font-bold" x-text="pendingCount">0</span> incoming order(s) transferred from drop-off branches ready to be added to this queue.</p>
            </div>
        </div>
        <button
            type="button"
            @click="$dispatch('open-incoming-tags-modal')"
            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md bg-amber-600 px-3.5 text-xs font-bold text-white shadow hover:bg-amber-700"
        >
            <span data-lucide="inbox" class="h-4 w-4"></span>
            TAG (<span class="js-tag-count" x-text="pendingCount">0</span>) - Receive Tags
        </button>
    </div>

    <div
        x-data="{
            hasOverview: @js($machineOverviewBranches->isNotEmpty()),
            overviewCollapsed: false,
            init() {
                if (!this.hasOverview) return;
                try { this.overviewCollapsed = localStorage.getItem('cycles.overviewCollapsed') === '1'; } catch (e) {}
            },
            toggleOverview() {
                this.overviewCollapsed = !this.overviewCollapsed;
                try { localStorage.setItem('cycles.overviewCollapsed', this.overviewCollapsed ? '1' : '0'); } catch (e) {}
            },
        }"
        class="grid items-start gap-4"
        :class="!hasOverview || !overviewCollapsed ? 'xl:grid-cols-[minmax(36rem,46rem)_minmax(26rem,1fr)]' : 'xl:grid-cols-[5rem_minmax(0,1fr)]'"
    >
    @if($machineOverviewBranches->isNotEmpty())
        <section class="rounded-xl border border-border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" :class="overviewCollapsed ? 'px-2 py-3' : 'p-4'">
            {{-- Collapsed: a slim column of machine images so the cycle queue gets the width. --}}
            <div x-show="overviewCollapsed" x-cloak class="flex flex-col items-center gap-2">
                <button
                    type="button"
                    @click="toggleOverview()"
                    title="Show machine overview"
                    aria-label="Show machine overview"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white shadow-sm transition hover:opacity-90"
                >
                    <span data-lucide="chevrons-right" class="h-4 w-4"></span>
                </button>
                @foreach($machineOverviewBranches as $machineBranch)
                    @php($branchActiveMachines = $activeMachinesByBranch[$machineBranch->id] ?? [])
                    @foreach(['wash' => 'Wash', 'dry' => 'Dry'] as $machineType => $machineLabel)
                        @if((int) $machineBranch->machine_count > 0)
                            <span class="mt-1 h-1 w-6 rounded-full {{ $machineType === 'wash' ? 'bg-sky-500' : 'bg-violet-500' }}" title="{{ $machineLabel }} machines"></span>
                        @endif
                        @for($machine = 1; $machine <= (int) $machineBranch->machine_count; $machine++)
                            @php($activeMachine = data_get($branchActiveMachines, $machineType.'.'.$machine))
                            <img
                                src="{{ asset($activeMachine ? 'unavailable.png' : 'available.png') }}"
                                alt="{{ $machineLabel }} #{{ $machine }} {{ $activeMachine ? 'in use' : 'available' }}"
                                title="{{ $machineLabel }} #{{ $machine }} · {{ $activeMachine ? 'In use: '.$activeMachine['customer_name'].' ('.$activeMachine['job_order_number'].')' : 'Available' }}"
                                width="48"
                                height="48"
                                loading="lazy"
                                decoding="async"
                                class="machine-status-image {{ $activeMachine ? 'machine-status-image-running' : 'machine-status-image-ready' }} h-12 w-12 rounded-lg object-cover ring-2 {{ $activeMachine ? 'ring-red-400' : 'ring-emerald-400' }}"
                            >
                        @endfor
                    @endforeach
                @endforeach
            </div>

            <div x-show="!overviewCollapsed">
            <div class="mb-4 flex items-start justify-between gap-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">Machine overview</p>
                    <h2 class="mt-1 text-lg font-semibold">Live availability</h2>
                    <p class="mt-1 text-xs text-muted">Latest Z Reading plus cycles recorded afterward</p>
                </div>
                <button
                    type="button"
                    @click="toggleOverview()"
                    title="Collapse machine overview"
                    aria-label="Collapse machine overview"
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border text-muted shadow-sm transition hover:border-primary hover:bg-primary hover:text-white dark:border-gray-800"
                >
                    <span data-lucide="chevrons-left" class="h-4 w-4"></span>
                </button>
            </div>

            <div class="space-y-4">
                @foreach($machineOverviewBranches as $machineBranch)
                    @php($machineTotal = (int) $machineBranch->machine_count)
                    @php($branchActiveMachines = $activeMachinesByBranch[$machineBranch->id] ?? [])
                    @php($branchMachineReadings = $machineCounterReadingsByBranch[$machineBranch->id] ?? [])
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
                                                @php($counterReading = (int) data_get($branchMachineReadings, $machine.'.'.$machineType, 0))
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
                                                        <p class="text-base font-bold {{ $machineType === 'wash' ? 'text-sky-600' : 'text-violet-600' }}">{{ str_pad((string) $counterReading, 4, '0', STR_PAD_LEFT) }}</p>
                                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-muted">{{ $machineType === 'wash' ? 'Current washer reading' : 'Current dryer reading' }}</p>
                                                    </div>
                                                    @if(! $isAvailable)
                                                        <div class="border-t border-border px-2 py-1.5 text-center text-[10px] font-medium text-red-600 dark:border-gray-800" title="{{ $activeMachine['job_order_number'] }}">
                                                            <p class="truncate">{{ $activeMachine['customer_name'] }}</p>
                                                            <p class="mt-0.5 truncate font-semibold">{{ $activeMachine['job_order_number'] }}</p>
                                                            @if($activeMachine['is_rush'] || $activeMachine['is_loyal'] || $activeMachine['is_po'])
                                                                <div class="mt-1 flex justify-center gap-1">
                                                                    @if($activeMachine['is_po'])
                                                                        <span class="rounded bg-indigo-100 px-1.5 py-0.5 font-semibold uppercase text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">PO</span>
                                                                    @endif
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

                                @php($cycleSources = $machineCycleSourcesByBranch[$machineBranch->id] ?? null)
                                @if($cycleSources)
                                    <div class="rounded-lg border border-border dark:border-gray-800">
                                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-3 py-2 dark:border-gray-800">
                                            <h4 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted">Machine cycle count</h4>
                                            <span class="text-[11px] text-muted">{{ $cycleSources['since'] ? 'Since Z Reading '.$cycleSources['since']->format('M d, Y') : 'All recorded cycles (no Z Reading yet)' }}</span>
                                        </div>
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-left text-[10px] uppercase tracking-wide text-muted">
                                                    <th class="px-3 py-1.5 font-semibold">Laundry from</th>
                                                    <th class="px-3 py-1.5 text-right font-semibold">Wash</th>
                                                    <th class="px-3 py-1.5 text-right font-semibold">Dry</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-t border-border dark:border-gray-800">
                                                    <td class="px-3 py-1.5">{{ $machineBranch->name }} (own orders)</td>
                                                    <td class="px-3 py-1.5 text-right font-semibold text-sky-600">{{ $cycleSources['own']['wash'] }}</td>
                                                    <td class="px-3 py-1.5 text-right font-semibold text-violet-600">{{ $cycleSources['own']['dry'] }}</td>
                                                </tr>
                                                @foreach($cycleSources['dropoffs'] as $dropoff)
                                                    <tr class="border-t border-border dark:border-gray-800">
                                                        <td class="px-3 py-1.5">{{ $dropoff['branch_name'] }} <span class="text-muted">(no machine)</span></td>
                                                        <td class="px-3 py-1.5 text-right font-semibold text-sky-600">{{ $dropoff['wash'] }}</td>
                                                        <td class="px-3 py-1.5 text-right font-semibold text-violet-600">{{ $dropoff['dry'] }}</td>
                                                    </tr>
                                                @endforeach
                                                <tr class="border-t-2 border-border bg-smoke font-bold dark:border-gray-800 dark:bg-gray-950">
                                                    <td class="px-3 py-1.5">Total machine cycles</td>
                                                    <td class="px-3 py-1.5 text-right text-sky-600">{{ $cycleSources['total']['wash'] }}</td>
                                                    <td class="px-3 py-1.5 text-right text-violet-600">{{ $cycleSources['total']['dry'] }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @else
                            <p class="w-full rounded-lg border border-dashed border-border py-6 text-center text-sm text-muted dark:border-gray-800">No machines configured.</p>
                        @endif
                    </article>
                @endforeach
            </div>
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
        <div class="grid grid-cols-1 items-start gap-3" :class="hasOverview && overviewCollapsed ? 'md:grid-cols-2' : ''">
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
            @php($po = $order->poTransaction)
            @php($poOrderedPieces = (float) ($order->ordered_pieces ?? 0))
            @php($poDeliveredPieces = $po ? (float) $po->deliveryItems->sum('quantity') : 0)
            @php($poRemainingPieces = max(round($poOrderedPieces - $poDeliveredPieces, 2), 0))
            @php($poDrNumbers = $po ? $po->deliveryItems->pluck('delivery.dr_number')->filter()->unique()->values() : collect())
            @php($canCreateDr = $po && (auth()->user()->isAdmin() || auth()->user()->hasMenuAccess('po_transactions')))
            @php($formatPieces = fn ($value) => rtrim(rtrim(number_format($value, 2), '0'), '.'))
            {{-- PO pipeline: washed -> dried -> marked ready -> delivered. Without recorded batches, a finished order counts every piece as done. --}}
            @php($poProgress = $poBatchProgress[$order->id] ?? null)
            @php($poProductionDone = in_array($order->status, ['ready_for_pickup', 'ready_for_delivery', 'completed'], true))
            @php($poWashed = $poProgress['washed'] ?? ($poProductionDone ? $poOrderedPieces : 0))
            @php($poWashedDone = $poProgress['washed_done'] ?? $poWashed)
            @php($poDried = $poProgress['dried'] ?? $poWashed)
            @php($poDriedDone = $poProgress['dried_done'] ?? $poWashed)
            @php($poReady = $poProductionDone ? $poOrderedPieces : ($poProgress['ready'] ?? 0))
            @php($poLeftToWash = max(round($poOrderedPieces - $poWashed, 2), 0))
            @php($poWaitingToDry = max(round($poWashedDone - $poDried, 2), 0))
            @php($poToMarkReady = max(round($poDriedDone - $poReady, 2), 0))
            @php($poReadyToDeliver = max(round($poReady - $poDeliveredPieces, 2), 0))
            @php($poPercent = fn ($value) => $poOrderedPieces > 0 ? min(100, round($value / $poOrderedPieces * 100)) : 0)
            <div class="rounded-lg border bg-white p-4 shadow-sm dark:bg-gray-900 {{ $po ? 'border-indigo-200 dark:border-indigo-900/60' : 'border-border dark:border-gray-800' }}">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <p class="truncate font-semibold">{{ $order->customer?->name ?? 'Unknown customer' }}</p>
                            @if($order->tag_number)
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-900 dark:bg-amber-900/50 dark:text-amber-200">
                                    TAG #{{ $order->tag_number }}
                                </span>
                            @endif
                            @if($po)
                                <span class="rounded-md border border-indigo-200 bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-indigo-700 dark:border-indigo-900/60 dark:bg-indigo-500/10 dark:text-indigo-300" title="Purchase order">PO{{ $po->po_number ? ' #'.$po->po_number : '' }}</span>
                            @endif
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
                            Drop-off: {{ $order->branch?->name }} - Receiving/Processing: {{ $processingBranch?->name }}
                        </p>
                        @if($isCrossBranchProduction)
                            @if($order->production_accepted_at)
                                <p class="text-xs text-emerald-600 font-medium">Received into production {{ $order->production_accepted_at->format('M d, h:i A') }}</p>
                            @else
                                <p class="text-xs text-amber-600 font-medium">Transferred from {{ $order->branch?->name }}. Pending tag reception.</p>
                            @endif
                        @endif
                        @if(filled($order->notes))
                            <div class="mt-1.5 flex items-start gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2 py-1.5 text-xs text-sky-900 dark:border-sky-900/60 dark:bg-sky-500/10 dark:text-sky-200">
                                <span data-lucide="sticky-note" class="mt-0.5 h-3.5 w-3.5 shrink-0"></span>
                                <span class="whitespace-pre-line break-words"><span class="font-semibold">Notes:</span> {{ $order->notes }}</span>
                            </div>
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

                @php($mustReturnToDropoff = $order->awaitingReturnToDropoff())
                @if($po)
                    {{-- PO batch progress: washed in batches over several days, delivered on DRs. --}}
                    <div class="mb-3 rounded-lg border border-indigo-200 bg-indigo-50/50 p-3 dark:border-indigo-900/60 dark:bg-indigo-500/10">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-indigo-800 dark:text-indigo-300">PO batch progress · {{ $formatPieces($poOrderedPieces) }} pcs</span>
                            @if($canCreateDr && $poReadyToDeliver > 0 && ! $mustReturnToDropoff)
                                <a
                                    href="{{ route('admin.po-deliveries.create', ['customer_id' => $order->customer_id, 'po_transaction_id' => $po->id]) }}"
                                    class="inline-flex h-7 items-center gap-1 rounded-md bg-indigo-600 px-2.5 text-[11px] font-semibold text-white shadow-sm hover:bg-indigo-700"
                                >
                                    <span data-lucide="file-text" class="h-3.5 w-3.5"></span>
                                    Create DR ({{ $formatPieces($poReadyToDeliver) }} pcs)
                                </a>
                            @endif
                        </div>
                        <div class="relative h-2 overflow-hidden rounded-full bg-white dark:bg-gray-900" title="Washed {{ $poPercent($poWashed) }}% · Dried {{ $poPercent($poDried) }}% · Ready {{ $poPercent($poReady) }}% · Delivered {{ $poPercent($poDeliveredPieces) }}%">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-sky-300" style="width: {{ $poPercent($poWashed) }}%"></div>
                            <div class="absolute inset-y-0 left-0 rounded-full bg-violet-400" style="width: {{ $poPercent($poDried) }}%"></div>
                            <div class="absolute inset-y-0 left-0 rounded-full bg-orange-400" style="width: {{ $poPercent($poReady) }}%"></div>
                            <div class="absolute inset-y-0 left-0 rounded-full bg-emerald-500" style="width: {{ $poPercent($poDeliveredPieces) }}%"></div>
                        </div>
                        <div class="mt-2 grid grid-cols-4 gap-1 text-center">
                            @foreach([
                                ['Washed', $poWashed, 'text-sky-600', $poLeftToWash, 'left to wash'],
                                ['Dried', $poDried, 'text-violet-600', $poWaitingToDry, 'waiting to dry'],
                                ['Ready', $poReady, 'text-orange-600', $poToMarkReady, 'done, not marked'],
                                ['Delivered', $poDeliveredPieces, 'text-emerald-600', $poReadyToDeliver, 'ready to deliver'],
                            ] as [$stepLabel, $stepValue, $stepColor, $stepPending, $stepPendingLabel])
                                <div class="rounded-md bg-white/70 px-1 py-1.5 dark:bg-gray-900/60">
                                    <p class="text-sm font-bold {{ $stepColor }}">{{ $formatPieces($stepValue) }}</p>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-muted">{{ $stepLabel }}</p>
                                    <p class="text-[10px] {{ $stepPending > 0 ? 'font-semibold text-amber-600' : 'text-muted' }}">{{ $formatPieces($stepPending) }} {{ $stepPendingLabel }}</p>
                                </div>
                            @endforeach
                        </div>
                        @if($order->relationLoaded('poReadyBatches') && $order->poReadyBatches->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach($order->poReadyBatches as $readyBatch)
                                    <span class="rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-semibold text-orange-800 dark:bg-orange-500/15 dark:text-orange-300" title="Marked by {{ $readyBatch->user?->name ?? 'System user' }}">
                                        {{ $formatPieces((float) $readyBatch->pieces) }} pcs ready for {{ $readyBatch->ready_for }} · {{ $readyBatch->created_at?->format('M d, h:i A') }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        @if($poDrNumbers->isNotEmpty())
                            <p class="mt-2 text-[11px] text-muted">DR {{ $poDrNumbers->implode(', ') }}</p>
                        @endif
                    </div>
                @endif

                @php($returnInTransit = $order->returned_to_branch_at && ! $order->returned_received_at)
                @if(! in_array($order->status, ['returning_to_branch', 'back_at_branch', 'ready_for_pickup', 'ready_for_delivery', 'completed'], true))
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
                                    {{-- PO batches: wash up to what is left; dry only what finished washing. --}}
                                    @php($batchLimit = $type === 'wash' ? $poLeftToWash : $poWaitingToDry)
                                    @php($asksBatchPieces = $po && ($type === 'wash' || $poProgress))
                                    @if($asksBatchPieces)
                                        @if($batchLimit > 0)
                                            <label class="flex items-center gap-2 text-[11px] font-medium text-indigo-800 dark:text-indigo-300">
                                                Pcs in this batch
                                                <input
                                                    type="number"
                                                    name="pieces"
                                                    min="0.01"
                                                    step="any"
                                                    max="{{ $batchLimit }}"
                                                    required
                                                    placeholder="max {{ $formatPieces($batchLimit) }}"
                                                    class="h-7 w-28 rounded border border-indigo-200 bg-white px-2 text-xs dark:border-indigo-900/60 dark:bg-gray-900"
                                                >
                                                <span class="font-normal text-muted">{{ $type === 'wash' ? $formatPieces($batchLimit).' left to wash' : $formatPieces($batchLimit).' washed, waiting to dry' }}</span>
                                            </label>
                                        @elseif($type === 'wash')
                                            <p class="text-[11px] font-medium text-emerald-600">All {{ $formatPieces($poOrderedPieces) }} pcs washed.</p>
                                        @else
                                            <p class="text-[11px] font-medium text-muted">{{ $poDried >= $poWashed && $poLeftToWash <= 0 ? 'All '.$formatPieces($poOrderedPieces).' pcs dried.' : 'No washed pcs waiting to dry. End a Washing cycle first.' }}</p>
                                        @endif
                                    @endif
                                    <button type="submit" title="Start {{ $label }}" @disabled($asksBatchPieces && $batchLimit <= 0) class="inline-flex h-7 items-center justify-center gap-1 rounded bg-primary px-2 text-[11px] font-medium text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                                        <span data-lucide="plus" class="h-3 w-3"></span>
                                        {{ $label }}
                                    </button>
                                </div>
                            </form>
                        @endforeach
                    </div>

                    <div class="mb-3 grid gap-1 {{ $poProgress && ! $mustReturnToDropoff ? 'grid-cols-2' : 'grid-cols-4' }}">
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
                        @if($mustReturnToDropoff)
                            <form method="POST" action="{{ route('admin.cycles.release', $order) }}" class="col-span-2" x-data>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="return_to_dropoff">
                                @if(! $canReturnToDropoff)
                                    <p class="flex h-9 items-center justify-center rounded-md bg-smoke px-1 text-center text-[11px] text-muted dark:bg-gray-950">Processed at {{ $processingBranch?->name }}</p>
                                @elseif((int) $order->active_cycles_count > 0)
                                    <button
                                        type="button"
                                        x-on:click="Swal.fire({ title: 'Cycle still running', text: @js('This job order still has '.$order->active_cycles_count.' active '.\Illuminate\Support\Str::plural('cycle', $order->active_cycles_count).'. End all cycles before returning it.'), icon: 'warning', confirmButtonColor: '#dc2626' })"
                                        class="inline-flex h-9 w-full cursor-not-allowed items-center justify-center gap-1 rounded-md bg-purple-600 px-1 text-[11px] font-semibold text-white opacity-50"
                                        title="End all active cycles first"
                                    >
                                        <span data-lucide="truck" class="h-4 w-4"></span>
                                        Return to {{ $order->branch?->name }}
                                    </button>
                                @else
                                    <button
                                        type="submit"
                                        x-on:click.prevent="Swal.fire({ title: @js('Return to '.$order->branch?->name.'?'), text: @js('Production is finished. Send Tag #'.$order->tag_number.' back to '.$order->branch?->name.'. The customer gets the ready SMS once they confirm receipt.'), icon: 'question', showCancelButton: true, confirmButtonText: 'Return Laundry', confirmButtonColor: '#7c3aed' }).then((r) => { if (r.isConfirmed) $el.closest('form').submit(); })"
                                        class="inline-flex h-9 w-full items-center justify-center gap-1 rounded-md bg-purple-600 px-1 text-[11px] font-semibold text-white hover:bg-purple-700"
                                    >
                                        <span data-lucide="truck" class="h-4 w-4"></span>
                                        Return to {{ $order->branch?->name }}
                                    </button>
                                @endif
                            </form>
                        @elseif(! $poProgress)
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
                                        x-on:click.prevent="Swal.fire({ title: @js($readyAction['label'].'?'), text: @js($po ? 'This will finish production. Deliver the laundry with Delivery Receipts; partial deliveries are allowed.' : 'This will finish production and notify the customer that the laundry is ready.'), icon: 'question', showCancelButton: true, confirmButtonColor: @js($readyAction['color']), confirmButtonText: 'Mark as Ready' }).then((result) => { if (result.isConfirmed) $el.closest('form').submit(); })"
                                        class="inline-flex h-9 w-full items-center justify-center gap-1 rounded-md px-1 text-[11px] font-semibold text-white {{ $readyAction['classes'] }}"
                                    >
                                        <span data-lucide="{{ $readyAction['icon'] }}" class="h-4 w-4"></span>
                                        {{ $readyAction['label'] }}
                                    </button>
                                @endif
                            </form>
                        @endforeach
                        @endif
                    </div>

                    @if($poProgress && ! $mustReturnToDropoff)
                        {{-- Washed-and-dried PO pieces are marked ready batch by batch; the last batch finishes production. --}}
                        <form method="POST" action="{{ route('admin.cycles.po-ready', $order) }}" class="mb-3 rounded-md border border-orange-200 bg-orange-50/50 p-2 dark:border-orange-900/60 dark:bg-orange-500/10">
                            @csrf
                            <p class="mb-1.5 text-[11px] font-semibold text-orange-800 dark:text-orange-300">
                                {{ $poToMarkReady > 0 ? $formatPieces($poToMarkReady).' pcs washed and dried, not yet marked ready' : 'Mark ready after a batch finishes washing and drying' }}
                                <span class="font-normal text-muted">· {{ $formatPieces($poReady) }} of {{ $formatPieces($poOrderedPieces) }} pcs ready</span>
                            </p>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <input
                                    type="number"
                                    name="pieces"
                                    min="0.01"
                                    step="any"
                                    max="{{ $poToMarkReady }}"
                                    value="{{ $poToMarkReady > 0 ? $formatPieces($poToMarkReady) : '' }}"
                                    required
                                    @disabled($poToMarkReady <= 0)
                                    aria-label="Pcs to mark ready"
                                    class="h-9 w-24 rounded-md border border-orange-200 bg-white px-2 text-xs disabled:opacity-50 dark:border-orange-900/60 dark:bg-gray-900"
                                >
                                <button type="submit" name="status" value="ready_for_delivery" @disabled($poToMarkReady <= 0) class="inline-flex h-9 flex-1 items-center justify-center gap-1 rounded-md bg-orange-600 px-2 text-[11px] font-semibold text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span data-lucide="truck" class="h-4 w-4"></span>
                                    Ready for Delivery
                                </button>
                                <button type="submit" name="status" value="ready_for_pickup" @disabled($poToMarkReady <= 0) class="inline-flex h-9 flex-1 items-center justify-center gap-1 rounded-md bg-teal-600 px-2 text-[11px] font-semibold text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span data-lucide="package-check" class="h-4 w-4"></span>
                                    Ready for Pickup
                                </button>
                            </div>
                        </form>
                    @endif
                @else
                    @if(in_array($order->status, ['returning_to_branch', 'back_at_branch'], true))
                        <div class="mb-3 flex items-center gap-1.5 rounded-lg border border-purple-200 bg-purple-50/60 p-3 text-xs font-semibold text-purple-800 dark:border-purple-900/60 dark:bg-purple-500/10 dark:text-purple-300">
                            <span data-lucide="{{ $order->status === 'returning_to_branch' ? 'truck' : 'package-check' }}" class="h-4 w-4"></span>
                            <span>
                                {{ $order->status === 'returning_to_branch'
                                    ? 'Returning to '.$order->branch?->name.' (waiting for them to receive it)'
                                    : 'Received back at '.$order->branch?->name.'.' }}
                            </span>
                        </div>
                    @endif
                    @if(in_array($order->status, ['ready_for_pickup', 'ready_for_delivery'], true))
                        @php($pendingReturn = $returnInTransit)
                        <div class="mb-3 rounded-lg border border-teal-200 bg-teal-50/50 p-3 dark:border-teal-900/60 dark:bg-teal-500/10">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-xs font-bold text-teal-800 dark:text-teal-300">
                                    {{ $order->status === 'ready_for_pickup' ? 'Ready for Pickup' : 'Ready for Delivery' }}
                                </span>
                                @if($order->tag_number)
                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">
                                        TAG #{{ $order->tag_number }}
                                    </span>
                                @endif
                            </div>

                            @if($pendingReturn)
                                <div class="rounded bg-purple-100 p-2 text-xs font-semibold text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 flex items-center gap-1.5">
                                    <span data-lucide="truck" class="h-4 w-4"></span>
                                    <span>In Transit: Returning back to {{ $order->branch?->name }}</span>
                                </div>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @if($canReturnToDropoff && $mustReturnToDropoff)
                                        <form method="POST" action="{{ route('admin.cycles.release', $order) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="action" value="return_to_dropoff">
                                            <button
                                                type="submit"
                                                x-on:click.prevent="Swal.fire({ title: 'Return to {{ $order->branch?->name }}?', text: 'This will initiate a return transfer of Tag #{{ $order->tag_number }} back to the drop-off branch.', icon: 'question', showCancelButton: true, confirmButtonText: 'Return Laundry', confirmButtonColor: '#7c3aed' }).then((r) => { if (r.isConfirmed) $el.closest('form').submit(); })"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md bg-purple-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-purple-700"
                                            >
                                                <span data-lucide="truck" class="h-3.5 w-3.5"></span>
                                                Return to {{ $order->branch?->name }}
                                            </button>
                                        </form>
                                    @endif

                                    @if($po && ! $mustReturnToDropoff)
                                        <form method="POST" action="{{ route('admin.cycles.release', $order) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="action" value="release_here">
                                            <button
                                                type="submit"
                                                x-on:click.prevent="Swal.fire({ title: 'Deliver all remaining?', text: @js('The remaining '.$formatPieces($poRemainingPieces).' pcs go on a final DR dated today and the job order is completed. Use Create DR instead for a partial delivery.'), icon: 'warning', showCancelButton: true, confirmButtonText: 'Deliver All', confirmButtonColor: '#0f766e' }).then((r) => { if (r.isConfirmed) $el.closest('form').submit(); })"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md bg-teal-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-teal-700"
                                            >
                                                <span data-lucide="package-check" class="h-3.5 w-3.5"></span>
                                                Deliver All Remaining
                                            </button>
                                        </form>
                                    @endif

                                    @unless($mustReturnToDropoff || $po)
                                    <form method="POST" action="{{ route('admin.cycles.release', $order) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="release_here">
                                        <button
                                            type="submit"
                                            x-on:click.prevent="Swal.fire({ title: 'Direct Release / Delivery?', text: 'Confirm release to customer or direct delivery rider from this branch. Payment will be verified.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Release Laundry', confirmButtonColor: '#0f766e' }).then((r) => { if (r.isConfirmed) $el.closest('form').submit(); })"
                                            class="inline-flex h-8 items-center gap-1.5 rounded-md bg-teal-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-teal-700"
                                        >
                                            <span data-lucide="package-check" class="h-3.5 w-3.5"></span>
                                            Direct Release
                                        </button>
                                    </form>
                                    @endunless
                                </div>
                            @endif
                        </div>
                    @endif
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
                                @if($cycle->pieces !== null)
                                    <p class="text-[11px] font-semibold text-indigo-700 dark:text-indigo-300">Batch: {{ $formatPieces((float) $cycle->pieces) }} pcs</p>
                                @endif
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
