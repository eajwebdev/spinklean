<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CycleRecord;
use App\Models\JobOrder;
use App\Support\Activity;
use App\Support\SmsNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CycleController extends Controller
{
    private const CYCLE_HISTORY_LIMIT = 5;

    private const FILTER_STATUSES = ['pending', 'washing', 'drying', 'folding', 'ready_for_pickup', 'ready_for_delivery', 'completed'];

    private const CYCLE_TYPES = [
        'wash' => 'Washing',
        'dry' => 'Drying',
        'fold' => 'Folding',
        'iron' => 'Ironing',
    ];

    private const COMPLETION_STATUSES = [
        'ready_for_pickup' => 'Ready for Pickup',
        'ready_for_delivery' => 'Ready for Delivery',
        'completed' => 'Completed',
    ];

    private const RELEASE_ACTIONS = [
        'release_here' => 'Release Here',
        'return_to_dropoff' => 'Return to Drop-off',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->canManageAllBranches();
        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('id')
            ->get(['id', 'name', 'machine_count']);
        $requestedBranchId = $request->integer('branch_id');
        $selectedBranchId = $canChooseBranch
            ? ($branches->contains('id', $requestedBranchId) ? $requestedBranchId : $branches->first()?->id)
            : $user->branch_id;
        $selectedCustomerId = $request->integer('customer_id') ?: null;
        $customerBranchId = $selectedBranchId;
        [$dateFrom, $dateTo] = $this->dateRange($request);
        $selectedStatus = in_array($request->status, self::FILTER_STATUSES, true) ? $request->status : null;
        $statusLabels = [
            'pending' => 'Pending',
            'washing' => 'Washing',
            'drying' => 'Drying',
            'folding' => 'Folding / Ironing',
            'ready_for_pickup' => 'Ready for Pickup',
            'ready_for_delivery' => 'Ready for Delivery',
            'completed' => 'Completed',
        ];

        $customers = Customer::query()
            ->where('is_active', true)
            ->when($customerBranchId, fn ($query) => $query->where(fn ($query) => $query
                ->where('branch_id', $customerBranchId)
                ->orWhereHas('jobOrders', fn ($query) => $query
                    ->when($selectedStatus, fn ($query) => $query->where('status', $selectedStatus), fn ($query) => $query->whereNotIn('status', ['ready_for_pickup', 'ready_for_delivery', 'completed', 'cancelled']))
                    ->where(fn ($query) => $query
                        ->where(fn ($query) => $query
                            ->where('processing_branch_id', $customerBranchId)
                            ->whereNotNull('production_accepted_at'))
                        ->orWhere(fn ($query) => $query
                            ->whereNull('processing_branch_id')
                            ->where('branch_id', $customerBranchId))))))
            ->when(! $customerBranchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'phone']);

        if ($selectedCustomerId && ! $customers->contains('id', $selectedCustomerId)) {
            $selectedCustomerId = null;
        }

        $ordersQuery = JobOrder::query()
            ->where('status', '!=', 'cancelled')
            ->when($selectedStatus, fn ($q) => $q->where('status', $selectedStatus), fn ($q) => $q->whereNotIn('status', ['ready_for_pickup', 'ready_for_delivery', 'completed']))
            ->when($selectedBranchId, fn ($q) => $q->where(fn ($query) => $query
                ->where('branch_id', $selectedBranchId)
                ->orWhere(fn ($query) => $query
                    ->where('processing_branch_id', $selectedBranchId)
                    ->whereNotNull('production_accepted_at'))
                ->orWhere('current_branch_id', $selectedBranchId)
                ->orWhere('release_branch_id', $selectedBranchId)
                ->orWhere(fn ($query) => $query
                    ->whereNull('processing_branch_id')
                    ->where('branch_id', $selectedBranchId))))
            ->when(! $canChooseBranch, fn ($q) => $q->where(fn ($query) => $query
                ->where('branch_id', $user->branch_id)
                ->orWhere(fn ($query) => $query
                    ->where('processing_branch_id', $user->branch_id)
                    ->whereNotNull('production_accepted_at'))
                ->orWhere('current_branch_id', $user->branch_id)
                ->orWhere('release_branch_id', $user->branch_id)
                ->orWhere(fn ($query) => $query
                    ->whereNull('processing_branch_id')
                    ->where('branch_id', $user->branch_id))))
            ->when($selectedCustomerId, fn ($q) => $q->where('customer_id', $selectedCustomerId))
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;

                $q->where(fn ($query) => $query
                    ->where('job_order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")));
            });

        $orders = (clone $ordersQuery)
            ->select([
                'id',
                'branch_id',
                'processing_branch_id',
                'current_branch_id',
                'release_branch_id',
                'customer_id',
                'job_order_number',
                'status',
                'transaction_type',
                'is_rush',
                'production_accepted_at',
                'created_at',
            ])
            ->withCount([
                'cycles',
                'cycles as active_cycles_count' => fn ($query) => $query->whereNull('ended_at'),
            ])
            ->with([
                'branch:id,name,machine_count',
                'processingBranch:id,name,machine_count',
                'customer' => fn ($query) => $query
                    ->select(['id', 'name'])
                    ->withCount('jobOrders'),
                'cycles' => fn ($query) => $query
                    ->select([
                        'id',
                        'job_order_id',
                        'user_id',
                        'cycle_type',
                        'machine_number',
                        'cycle_number',
                        'started_at',
                        'ended_at',
                    ])
                    ->latest('started_at')
                    ->latest('id')
                    ->limit(self::CYCLE_HISTORY_LIMIT)
                    ->with('user:id,name'),
            ])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $this->hydrateRouteBranches($orders->getCollection());

        $machineOverviewBranches = $branches
            ->when($selectedBranchId, fn ($branches) => $branches->where('id', $selectedBranchId))
            ->values();
        $machineOverviewBranchIds = $machineOverviewBranches->pluck('id');
        $hasMachineOverview = $machineOverviewBranches->sum(fn (Branch $branch) => (int) $branch->machine_count) > 0;

        $activeMachinesByBranch = $hasMachineOverview ? DB::table('cycle_records')
            ->join('job_orders', 'job_orders.id', '=', 'cycle_records.job_order_id')
            ->join('customers', 'customers.id', '=', 'job_orders.customer_id')
            ->whereNull('cycle_records.ended_at')
            ->whereNull('job_orders.deleted_at')
            ->whereNotNull('cycle_records.machine_number')
            ->whereIn('cycle_records.cycle_type', ['wash', 'dry'])
            ->whereIn(DB::raw('COALESCE(job_orders.processing_branch_id, job_orders.branch_id)'), $machineOverviewBranchIds)
            ->get([
                DB::raw('COALESCE(job_orders.processing_branch_id, job_orders.branch_id) as operating_branch_id'),
                'cycle_records.machine_number',
                'cycle_records.cycle_type',
                'job_orders.job_order_number',
                'job_orders.is_rush',
                'customers.name as customer_name',
                DB::raw('(SELECT COUNT(*) FROM job_orders AS customer_orders WHERE customer_orders.customer_id = customers.id AND customer_orders.deleted_at IS NULL) as customer_orders_count'),
            ])
            ->groupBy('operating_branch_id')
            ->map(fn ($cycles) => $cycles
                ->groupBy('cycle_type')
                ->map(fn ($typedCycles) => $typedCycles
                    ->mapWithKeys(fn ($cycle) => [(int) $cycle->machine_number => [
                        'job_order_number' => $cycle->job_order_number,
                        'customer_name' => $cycle->customer_name,
                        'is_rush' => (bool) $cycle->is_rush,
                        'is_loyal' => (int) $cycle->customer_orders_count >= 10,
                        'cycle_type' => $cycle->cycle_type,
                    ]])
                    ->all())
                ->all()
            )
            ->all() : [];

        $activityDateFrom = $dateFrom ?: now()->toDateString();
        $activityDateTo = $dateTo ?: now()->toDateString();
        $machineActivityByBranch = $hasMachineOverview ? DB::table('cycle_records')
            ->join('job_orders', 'job_orders.id', '=', 'cycle_records.job_order_id')
            ->whereNull('job_orders.deleted_at')
            ->whereIn('cycle_records.cycle_type', ['wash', 'dry'])
            ->whereNotNull('cycle_records.machine_number')
            ->where('cycle_records.started_at', '>=', Carbon::parse($activityDateFrom)->startOfDay())
            ->where('cycle_records.started_at', '<=', Carbon::parse($activityDateTo)->endOfDay())
            ->whereIn(DB::raw('COALESCE(job_orders.processing_branch_id, job_orders.branch_id)'), $machineOverviewBranchIds)
            ->groupByRaw('COALESCE(job_orders.processing_branch_id, job_orders.branch_id), cycle_records.machine_number, cycle_records.cycle_type')
            ->get([
                DB::raw('COALESCE(job_orders.processing_branch_id, job_orders.branch_id) as operating_branch_id'),
                'cycle_records.machine_number',
                'cycle_records.cycle_type',
                DB::raw('COUNT(*) as aggregate'),
            ])
            ->groupBy('operating_branch_id')
            ->map(fn ($records) => $records
                ->groupBy('machine_number')
                ->map(fn ($machineRecords) => [
                    'wash' => (int) ($machineRecords->firstWhere('cycle_type', 'wash')?->aggregate ?? 0),
                    'dry' => (int) ($machineRecords->firstWhere('cycle_type', 'dry')?->aggregate ?? 0),
                ])
                ->all())
            ->all() : [];

        return view('admin.cycles.index', [
            'activeMachinesByBranch' => $activeMachinesByBranch,
            'branches' => $branches,
            'canChooseBranch' => $canChooseBranch,
            'customers' => $customers,
            'orders' => $orders,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'activityDateFrom' => $activityDateFrom,
            'activityDateTo' => $activityDateTo,
            'machineActivityByBranch' => $machineActivityByBranch,
            'machineOverviewBranches' => $machineOverviewBranches,
            'selectedBranchId' => $selectedBranchId,
            'selectedCustomerId' => $selectedCustomerId,
            'statusFilters' => self::FILTER_STATUSES,
            'cycleTypes' => self::CYCLE_TYPES,
            'completionStatuses' => self::COMPLETION_STATUSES,
            'releaseActions' => self::RELEASE_ACTIONS,
            'statusLabels' => $statusLabels,
        ]);
    }

    public function updateStatus(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeOrder($request, $jobOrder);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::COMPLETION_STATUSES))],
        ]);

        $activeCycles = $jobOrder->cycles()
            ->whereNull('ended_at')
            ->get(['id', 'cycle_type', 'machine_number', 'cycle_number']);

        if ($activeCycles->isNotEmpty()) {
            $labels = $activeCycles
                ->map(fn (CycleRecord $cycle) => (self::CYCLE_TYPES[$cycle->cycle_type] ?? ucfirst($cycle->cycle_type))
                    .($cycle->machine_number ? " (Machine #{$cycle->machine_number})" : ''))
                ->unique()
                ->implode(', ');

            $message = "Cannot mark {$jobOrder->job_order_number} as ".self::COMPLETION_STATUSES[$validated['status']].". End the active cycle(s) first: {$labels}.";

            return back()->with('error', $message)->withErrors(['status' => $message]);
        }

        $processingBranchId = $jobOrder->processing_branch_id ?: $jobOrder->branch_id;
        $jobOrder->update([
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === 'completed' ? now() : null,
            'production_completed_at' => $jobOrder->production_completed_at ?: now(),
            'current_branch_id' => $processingBranchId,
            'release_branch_id' => $processingBranchId,
            'released_at' => $validated['status'] === 'completed' ? now() : null,
        ]);

        Activity::log($request, 'job_order_status_updated', $jobOrder, [
            'job_order_number' => $jobOrder->job_order_number,
            'status' => $validated['status'],
        ], $jobOrder->branch_id);

        $jobOrder->loadMissing('customer');
        SmsNotifier::jobOrderStatus($jobOrder);

        return back()->with('success', 'Job order status updated.');
    }

    public function releaseAction(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeReleaseAction($request, $jobOrder);
        abort_unless(in_array($jobOrder->status, ['ready_for_pickup', 'ready_for_delivery'], true), 422);

        $validated = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::RELEASE_ACTIONS))],
        ]);

        if ($validated['action'] === 'return_to_dropoff') {
            $processingBranchId = $jobOrder->processing_branch_id ?: $jobOrder->branch_id;
            abort_unless((int) $processingBranchId === (int) $request->user()->branch_id || $request->user()->canManageAllBranches(), 403);
            abort_unless((int) $processingBranchId !== (int) $jobOrder->branch_id, 422);

            $jobOrder->update([
                'status' => $jobOrder->transaction_type === 'delivery' ? 'ready_for_delivery' : 'ready_for_pickup',
                'current_branch_id' => $jobOrder->branch_id,
                'release_branch_id' => $jobOrder->branch_id,
                'production_completed_at' => $jobOrder->production_completed_at ?: now(),
                'returned_to_branch_at' => now(),
                'completed_at' => null,
                'released_at' => null,
            ]);

            Activity::log($request, 'job_order_returned_to_dropoff', $jobOrder, [
                'job_order_number' => $jobOrder->job_order_number,
                'dropoff_branch_id' => $jobOrder->branch_id,
            ], $jobOrder->branch_id);

            return back()->with('success', 'Laundry returned to drop-off branch for release.');
        }

        abort_unless((int) ($jobOrder->release_branch_id ?: $jobOrder->current_branch_id ?: $jobOrder->branch_id) === (int) $request->user()->branch_id || $request->user()->canManageAllBranches(), 403);

        $jobOrder->endActiveCycles();

        $jobOrder->update([
            'status' => 'completed',
            'completed_at' => now(),
            'released_at' => now(),
            'release_branch_id' => $jobOrder->release_branch_id ?: ($jobOrder->current_branch_id ?: $request->user()->branch_id),
        ]);

        Activity::log($request, 'job_order_released', $jobOrder, [
            'job_order_number' => $jobOrder->job_order_number,
            'release_branch_id' => $jobOrder->release_branch_id,
        ], $jobOrder->release_branch_id);

        $jobOrder->loadMissing('customer');
        SmsNotifier::jobOrderStatus($jobOrder);

        return back()->with('success', 'Laundry released successfully.');
    }

    public function storeCycle(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeOrder($request, $jobOrder);

        if ($request->filled('machine_number') && ! $request->filled('machine_numbers')) {
            $request->merge(['machine_numbers' => [(int) $request->input('machine_number')]]);
        }

        $validated = $request->validate([
            'cycle_type' => ['required', Rule::in(array_keys(self::CYCLE_TYPES))],
            'machine_numbers' => ['nullable', 'array'],
            'machine_numbers.*' => ['integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $processingBranch = $jobOrder->processingBranch ?: $jobOrder->branch;
        $machineCount = (int) ($processingBranch?->machine_count ?? 0);
        
        // For wash/dry cycles, require at least one machine
        if (in_array($validated['cycle_type'], ['wash', 'dry'], true) && $machineCount > 0 && empty($validated['machine_numbers'])) {
            return back()->withErrors([
                'machine_number' => 'Please select at least one machine.',
                'machine_numbers' => 'Please select at least one machine.',
            ])->withInput();
        }

        // Validate machine numbers are within range
        if (! empty($validated['machine_numbers'])) {
            foreach ($validated['machine_numbers'] as $machineNumber) {
                if ($machineCount <= 0 || (int) $machineNumber > $machineCount) {
                    return back()->withErrors([
                        'machine_number' => 'Please choose valid machines.',
                        'machine_numbers' => 'Please choose valid machines.',
                    ])->withInput();
                }
            }
        }

        if (
            $validated['cycle_type'] === 'dry'
            && $jobOrder->cycles()->where('cycle_type', 'wash')->whereNull('ended_at')->exists()
        ) {
            return back()->withErrors([
                'cycle_type' => "End the active Washing cycle for {$jobOrder->job_order_number} before starting Drying.",
            ])->withInput();
        }

        // Check for machine conflicts for each selected machine
        $machineNumbers = $validated['machine_numbers'] ?? [];
        if (in_array($validated['cycle_type'], ['wash', 'dry'], true) && ! empty($machineNumbers)) {
            $conflictingMachines = [];
            foreach ($machineNumbers as $machineNumber) {
                $conflictingCycle = $this->machineConflict($jobOrder, $validated['cycle_type'], (int) $machineNumber);
                if ($conflictingCycle) {
                    $conflictingMachines[(int) $machineNumber] = $conflictingCycle;
                }
            }
            
            if (! empty($conflictingMachines)) {
                $machineLabel = $validated['cycle_type'] === 'wash' ? 'Wash' : 'Dry';
                if (count($conflictingMachines) === 1) {
                    $machineNumber = array_key_first($conflictingMachines);
                    $message = "{$machineLabel} #{$machineNumber} is currently used by {$conflictingMachines[$machineNumber]->jobOrder?->job_order_number}.";

                    return back()->withErrors([
                        'machine_number' => $message,
                        'machine_numbers' => $message,
                    ])->withInput();
                }

                $machines = implode(', ', array_map(fn ($m) => "#{$m}", array_keys($conflictingMachines)));
                return back()->withErrors([
                    'machine_number' => "{$machineLabel} machine(s) {$machines} are currently in use.",
                    'machine_numbers' => "{$machineLabel} machine(s) {$machines} are currently in use.",
                ])->withInput();
            }
        }

        $cycleNumber = $jobOrder->cycles()->where('cycle_type', $validated['cycle_type'])->max('cycle_number') + 1;
        $createdCycles = [];

        // Create one cycle record for each selected machine (or one record for non-machine cycles)
        if (in_array($validated['cycle_type'], ['wash', 'dry'], true) && ! empty($machineNumbers)) {
            foreach ($machineNumbers as $machineNumber) {
                $cycle = $jobOrder->cycles()->create([
                    'user_id' => $request->user()->id,
                    'cycle_type' => $validated['cycle_type'],
                    'machine_number' => (int) $machineNumber,
                    'cycle_number' => $cycleNumber,
                    'started_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);
                $createdCycles[] = $cycle;
            }
        } else {
            $cycle = $jobOrder->cycles()->create([
                'user_id' => $request->user()->id,
                'cycle_type' => $validated['cycle_type'],
                'machine_number' => null,
                'cycle_number' => $cycleNumber,
                'started_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);
            $createdCycles[] = $cycle;
        }

        $status = match ($validated['cycle_type']) {
            'wash' => 'washing',
            'dry' => 'drying',
            'fold', 'iron' => 'folding',
        };

        $jobOrder->update([
            'status' => $status,
            'current_branch_id' => $jobOrder->processing_branch_id ?: $jobOrder->branch_id,
            'release_branch_id' => $jobOrder->processing_branch_id ?: $jobOrder->branch_id,
            'completed_at' => null,
            'released_at' => null,
        ]);

        $machineStr = ! empty($machineNumbers) ? ' on machine(s) #' . implode(', #', $machineNumbers) : '';
        Activity::log($request, 'cycle_started', $createdCycles[0] ?? null, [
            'job_order_number' => $jobOrder->job_order_number,
            'cycle_type' => $validated['cycle_type'],
            'machine_numbers' => $machineNumbers,
            'cycle_number' => $cycleNumber,
        ], $jobOrder->branch_id);

        return back()->with('success', self::CYCLE_TYPES[$validated['cycle_type']].' cycle started' . $machineStr . '.');
    }

    public function endCycle(Request $request, CycleRecord $cycle)
    {
        $this->authorizeOrder($request, $cycle->jobOrder);

        $cycle->update(['ended_at' => now()]);

        Activity::log($request, 'cycle_completed', $cycle, [
            'job_order_number' => $cycle->jobOrder?->job_order_number,
            'cycle_type' => $cycle->cycle_type,
            'cycle_number' => $cycle->cycle_number,
        ], $cycle->jobOrder?->branch_id);

        return back()->with('success', 'Cycle completed.');
    }

    public function destroyCycle(Request $request, CycleRecord $cycle)
    {
        $cycle->loadMissing('jobOrder');
        $this->authorizeOrder($request, $cycle->jobOrder);

        DB::transaction(function () use ($request, $cycle): void {
            $jobOrder = $cycle->jobOrder;
            $cycleType = $cycle->cycle_type;
            $cycleNumber = $cycle->cycle_number;

            Activity::log($request, 'cycle_removed', $cycle, [
                'job_order_number' => $jobOrder?->job_order_number,
                'cycle_type' => $cycleType,
                'cycle_number' => $cycleNumber,
            ], $jobOrder?->branch_id);

            $cycle->delete();
            $this->renumberCycles($jobOrder, $cycleType);
        });

        return back()->with('success', 'Cycle record removed.');
    }

    private function renumberCycles(JobOrder $jobOrder, string $cycleType): void
    {
        $jobOrder->cycles()
            ->where('cycle_type', $cycleType)
            ->orderBy('started_at')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(function (CycleRecord $cycle, int $index): void {
                $nextNumber = $index + 1;

                if ((int) $cycle->cycle_number !== $nextNumber) {
                    $cycle->update(['cycle_number' => $nextNumber]);
                }
            });
    }

    private function machineConflict(JobOrder $jobOrder, string $cycleType, int $machineNumber): ?CycleRecord
    {
        return CycleRecord::query()
            ->with('jobOrder:id,job_order_number')
            ->where('cycle_type', $cycleType)
            ->where('machine_number', $machineNumber)
            ->whereNull('ended_at')
            ->whereHas('jobOrder', fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('processing_branch_id', $jobOrder->processing_branch_id ?: $jobOrder->branch_id)
                    ->orWhere(fn ($query) => $query
                        ->whereNull('processing_branch_id')
                        ->where('branch_id', $jobOrder->processing_branch_id ?: $jobOrder->branch_id)))
                ->whereNotIn('status', ['completed', 'cancelled']))
            ->first();
    }

    private function hydrateRouteBranches($orders): void
    {
        $branchIds = $orders
            ->flatMap(fn (JobOrder $order) => collect([$order->current_branch_id, $order->release_branch_id])
                ->filter()
                ->reject(fn ($branchId) => in_array((int) $branchId, [
                    (int) $order->branch_id,
                    (int) $order->processing_branch_id,
                ], true)))
            ->filter()
            ->unique()
            ->values();

        $extraBranches = $branchIds->isEmpty()
            ? collect()
            : Branch::query()->whereIn('id', $branchIds)->get(['id', 'name', 'machine_count'])->keyBy('id');

        $orders->each(function (JobOrder $order) use ($extraBranches): void {
            $order->setRelation('currentBranch', $this->routeBranchFor($order, $order->current_branch_id, $extraBranches));
            $order->setRelation('releaseBranch', $this->routeBranchFor($order, $order->release_branch_id, $extraBranches));
        });
    }

    private function routeBranchFor(JobOrder $order, ?int $branchId, $extraBranches): ?Branch
    {
        $branchId = $branchId ?: ($order->processing_branch_id ?: $order->branch_id);

        if ((int) $branchId === (int) $order->branch_id) {
            return $order->branch;
        }

        if ((int) $branchId === (int) $order->processing_branch_id) {
            return $order->processingBranch;
        }

        return $extraBranches->get((int) $branchId);
    }

    private function authorizeOrder(Request $request, JobOrder $jobOrder): void
    {
        if (in_array($request->user()->role, ['super_admin', 'admin'], true)) {
            return;
        }

        abort_unless(
            (int) $jobOrder->branch_id === (int) $request->user()->branch_id
                || (
                    (int) ($jobOrder->processing_branch_id ?: $jobOrder->branch_id) === (int) $request->user()->branch_id
                    && $jobOrder->production_accepted_at
                ),
            403
        );
    }

    private function authorizeReleaseAction(Request $request, JobOrder $jobOrder): void
    {
        if ($request->user()->canManageAllBranches()) {
            return;
        }

        abort_unless(in_array((int) $request->user()->branch_id, [
            (int) $jobOrder->branch_id,
            (int) ($jobOrder->processing_branch_id ?: $jobOrder->branch_id),
            (int) ($jobOrder->current_branch_id ?: $jobOrder->branch_id),
            (int) ($jobOrder->release_branch_id ?: $jobOrder->branch_id),
        ], true), 403);
    }

    private function dateRange(Request $request): array
    {
        if ($request->filled('date_range')) {
            $parts = preg_split('/\s+to\s+/', $request->date_range);

            return [
                $this->parseDate($parts[0] ?? null),
                $this->parseDate($parts[1] ?? $parts[0] ?? null),
            ];
        }

        $from = $this->parseDate($request->date_from);
        $to = $this->parseDate($request->date_to ?: $request->date);

        if ($from || $to) {
            return [$from, $to];
        }

        return [today()->toDateString(), today()->toDateString()];
    }

    private function parseDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
