<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\JobOrder;
use App\Models\LaundryService;
use App\Models\LaundryServiceCategory;
use App\Models\Payment;
use App\Models\PoTransaction;
use App\Models\ServicePreset;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Activity;
use App\Support\SmsNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobOrderController extends Controller
{
    private const STATUSES = ['pending', 'washing', 'drying', 'folding', 'ready_for_pickup', 'ready_for_delivery', 'completed', 'cancelled'];

    public function index(Request $request)
    {
        $user = $request->user();
        [$dateFrom, $dateTo] = $this->dateRange($request);

        $ordersQuery = JobOrder::with(['branch.setting', 'processingBranch', 'currentBranch', 'releaseBranch', 'customer', 'items', 'payments.receiver', 'payments.collectedBranch', 'poTransaction'])
            ->when($user->role !== 'super_admin' && $user->role !== 'admin', fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(fn ($query) => $query
                    ->where('job_order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%")));
            });

        $statusCounts = [
            'total' => (clone $ordersQuery)->count(),
            'ready_for_pickup' => (clone $ordersQuery)->where('status', 'ready_for_pickup')->count(),
            'ready_for_delivery' => (clone $ordersQuery)->where('status', 'ready_for_delivery')->count(),
            'released' => (clone $ordersQuery)->whereNotNull('released_at')->count(),
            'active' => (clone $ordersQuery)->whereNotIn('status', ['ready_for_pickup', 'ready_for_delivery', 'completed', 'cancelled'])->count(),
            'cancelled' => (clone $ordersQuery)->where('status', 'cancelled')->count(),
        ];

        $statusFilter = $request->status;

        $orders = $ordersQuery
            ->when(in_array($statusFilter, self::STATUSES, true), fn ($q) => $q->where('status', $statusFilter))
            ->when($statusFilter === 'released', fn ($q) => $q->whereNotNull('released_at'))
            ->when($statusFilter === 'active', fn ($q) => $q->whereNotIn('status', ['ready_for_pickup', 'ready_for_delivery', 'completed', 'cancelled']))
            ->orderByRaw("CASE WHEN status IN ('ready_for_pickup', 'ready_for_delivery') THEN 0 WHEN status IN ('pending', 'washing', 'drying', 'folding') THEN 1 WHEN status = 'completed' THEN 2 ELSE 3 END")
            ->latest()
            ->paginate(8)
            ->withQueryString();

        return view('admin.job-orders.index', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeJobOrder($request, $jobOrder);

        $jobOrder->load(['branch.setting', 'processingBranch', 'currentBranch', 'releaseBranch', 'customer', 'creator', 'items.service', 'payments.receiver', 'payments.collectedBranch', 'cycles.user']);

        return view('admin.job-orders.show', [
            'order' => $jobOrder,
            'settings' => SystemSetting::current(),
        ]);
    }

    public function receipt(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeJobOrderReceipt($request, $jobOrder);

        $jobOrder->load(['branch.setting', 'processingBranch', 'currentBranch', 'releaseBranch', 'customer', 'creator', 'items.service', 'payments.collectedBranch']);

        return view('admin.job-orders.receipt', [
            'order' => $jobOrder,
            'branchSetting' => $jobOrder->branch?->setting,
            'settings' => SystemSetting::current(),
        ]);
    }

    public function acceptProductionScan(Request $request, JobOrder $jobOrder)
    {
        $productionBranch = $this->acceptProductionByBranch($request, $jobOrder, (int) $request->user()->branch_id);

        return redirect()
            ->route('admin.cycles.index', ['search' => $jobOrder->job_order_number])
            ->with('success', 'Laundry accepted by '.$productionBranch->name.' and added to production cycle monitoring.');
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = in_array($user->role, ['super_admin', 'admin'], true);
        $requestedBranchId = $request->integer('branch_id');
        $branchId = $canChooseBranch
            ? Branch::where('is_active', true)
                ->when($requestedBranchId, fn ($query) => $query->whereKey($requestedBranchId))
                ->value('id')
            : $user->branch_id;

        $branchId ??= Branch::where('is_active', true)->value('id');

        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $processingBranches = Branch::where('is_active', true)
            ->where('branch_type', 'full_service')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'branch_type', 'machine_count']);
        $customers = Customer::where('is_active', true)
            ->when(! in_array($user->role, ['super_admin', 'admin'], true), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'phone', 'billing_type']);
        $services = LaundryService::where('is_active', true)
            ->when(! in_array($user->role, ['super_admin', 'admin'], true), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'service_category_id', 'pricing_type', 'price']);

        $serviceCategories = LaundryServiceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'visibility', 'branch_id']);
        $servicePresets = ServicePreset::with(['items.service:id,branch_id,name,service_category_id,pricing_type,price'])
            ->where('is_active', true)
            ->when(! in_array($user->role, ['super_admin', 'admin'], true), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($preset) => [
                'id' => $preset->id,
                'branch_id' => $preset->branch_id,
                'service_category_id' => $preset->service_category_id,
                'name' => $preset->name,
                'items' => $preset->items
                    ->filter(fn ($item) => $item->service)
                    ->map(fn ($item) => [
                        'id' => $item->service->id,
                        'branch_id' => $item->service->branch_id,
                        'service_category_id' => $item->service->service_category_id,
                        'name' => $item->service->name,
                        'pricing_type' => $item->service->pricing_type,
                        'price' => (float) $item->service->price,
                        'quantity' => (float) $item->quantity,
                    ])
                    ->values(),
            ])
            ->values();

        $selectedCustomerId = '';
        if ($request->filled('customer_id')) {
            $selectedCustomerId = (string) Customer::where('is_active', true)
                ->whereKey($request->integer('customer_id'))
                ->where('branch_id', $branchId)
                ->value('id');
        }

        return view('admin.job-orders.create', compact('branches', 'processingBranches', 'customers', 'services', 'serviceCategories', 'servicePresets', 'branchId', 'selectedCustomerId'));
    }

    public function edit(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeJobOrder($request, $jobOrder);

        $jobOrder->load(['branch', 'processingBranch', 'customer', 'items.service', 'payments']);
        $user = $request->user();
        $branchId = $jobOrder->branch_id;
        $serviceIds = $jobOrder->items->pluck('laundry_service_id')->filter()->unique()->values();

        $customers = Customer::query()
            ->where('branch_id', $branchId)
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhere('id', $jobOrder->customer_id))
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'phone', 'billing_type']);

        $services = LaundryService::query()
            ->where('branch_id', $branchId)
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhereIn('id', $serviceIds))
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'service_category_id', 'report_category', 'pricing_type', 'price']);

        $branches = Branch::query()
            ->whereKey($branchId)
            ->get(['id', 'name', 'code', 'branch_type', 'machine_count']);

        $selectedCustomerId = (string) $jobOrder->customer_id;
        $initialItems = $jobOrder->items
            ->map(fn ($item) => [
                'id' => $item->laundry_service_id,
                'type' => 'service',
                'name' => $item->description,
                'report_category' => $item->service_category,
                'quantity' => (float) $item->quantity,
                'price' => (float) $item->unit_price,
            ])
            ->values();

        $serviceCategories = LaundryServiceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'visibility', 'branch_id']);
        $servicePresets = ServicePreset::with(['items.service:id,branch_id,name,service_category_id,pricing_type,price'])
            ->where('is_active', true)
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($preset) => [
                'id' => $preset->id,
                'branch_id' => $preset->branch_id,
                'service_category_id' => $preset->service_category_id,
                'name' => $preset->name,
                'items' => $preset->items
                    ->filter(fn ($item) => $item->service)
                    ->map(fn ($item) => [
                        'id' => $item->service->id,
                        'branch_id' => $item->service->branch_id,
                        'service_category_id' => $item->service->service_category_id,
                        'name' => $item->service->name,
                        'pricing_type' => $item->service->pricing_type,
                        'price' => (float) $item->service->price,
                        'quantity' => (float) $item->quantity,
                    ])
                    ->values(),
            ])
            ->values();

        $processingBranches = Branch::where('is_active', true)
            ->where('branch_type', 'full_service')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'branch_type', 'machine_count']);

        return view('admin.job-orders.create', [
            'branches' => $branches,
            'processingBranches' => $processingBranches,
            'customers' => $customers,
            'services' => $services,
            'serviceCategories' => $serviceCategories,
            'servicePresets' => $servicePresets,
            'branchId' => $branchId,
            'selectedCustomerId' => $selectedCustomerId,
            'jobOrder' => $jobOrder,
            'initialItems' => $initialItems,
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'processing_branch_id' => ['nullable', 'exists:branches,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.laundry_service_id' => ['nullable', 'exists:laundry_services,id'],
            'items.*.service_preset_id' => ['nullable', 'exists:service_presets,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['nullable', Rule::in(['cash', 'gcash', 'bank', 'unpaid', 'po', 'monthly_billing'])],
            'payment_reference_no' => ['nullable', 'string', 'max:255'],
            'transaction_type' => ['nullable', Rule::in(['walk_in', 'delivery'])],
            'is_rush' => ['nullable', 'boolean'],
            'send_sms' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        if (! in_array($user->role, ['super_admin', 'admin'], true)) {
            $validated['branch_id'] = $user->branch_id;
        }

        $originBranch = Branch::query()->findOrFail($validated['branch_id']);
        $validated['processing_branch_id'] = $this->resolveProcessingBranchId($originBranch, $validated['processing_branch_id'] ?? null, $user);

        $customerBelongsToBranch = Customer::query()
            ->whereKey($validated['customer_id'])
            ->where('branch_id', $validated['branch_id'])
            ->exists();

        if (! $customerBelongsToBranch) {
            throw ValidationException::withMessages([
                'customer_id' => 'Please choose a customer from the selected branch.',
            ]);
        }

        $validated['items'] = $this->expandPresetCartItems($validated['items'], (int) $validated['branch_id']);

        $serviceIds = collect($validated['items'])->pluck('laundry_service_id')->unique()->values();
        $selectedServices = LaundryService::query()
            ->whereIn('id', $serviceIds)
            ->where('branch_id', $validated['branch_id'])
            ->get()
            ->keyBy('id');

        if ($selectedServices->count() !== $serviceIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'All services must belong to the selected branch.',
            ]);
        }

        $createdOrder = null;
        $response = DB::transaction(function () use ($request, $validated, $selectedServices, $user, &$createdOrder) {
            $settings = SystemSetting::current();
            $subtotal = collect($validated['items'])->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
            $discount = min((float) ($validated['discount'] ?? 0), $subtotal);
            $taxable = max($subtotal - $discount, 0);
            $tax = $settings->vat_enabled ? ($taxable * ((float) $settings->vat_rate / 100)) : 0;
            $total = $taxable + $tax;
            $paymentType = $validated['payment_type'] ?? 'cash';
            $customer = Customer::query()->find($validated['customer_id']);
            $isPoTransaction = $paymentType === 'po' || $customer?->billing_type === 'po';
            $paid = in_array($paymentType, ['unpaid', 'po'], true) || $isPoTransaction
                ? 0
                : min((float) ($validated['paid_amount'] ?? 0), $total);

            if (in_array($paymentType, ['cash', 'gcash', 'bank'], true) && $paid <= 0) {
                $paid = $total;
            }

            $order = JobOrder::create([
                'branch_id' => $validated['branch_id'],
                'processing_branch_id' => $validated['processing_branch_id'],
                'current_branch_id' => $validated['branch_id'],
                'release_branch_id' => $validated['branch_id'],
                'customer_id' => $validated['customer_id'],
                'created_by' => $user->id,
                'job_order_number' => $this->nextJobOrderNumber((int) $validated['branch_id']),
                'status' => 'pending',
                'transaction_type' => $validated['transaction_type'] ?? 'walk_in',
                'is_rush' => (bool) ($validated['is_rush'] ?? false),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid_amount' => $paid,
                'balance' => $total - $paid,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $service = $selectedServices->get((int) $item['laundry_service_id']);
                $order->items()->create([
                    'laundry_service_id' => $item['laundry_service_id'],
                    'service_preset_id' => $item['service_preset_id'] ?? null,
                    'description' => $item['description'],
                    'service_category' => $service->report_category,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => (float) $item['quantity'] * (float) $item['unit_price'],
                ]);
            }

            if (! $order->branch?->isPickupDropoff()) {
                $this->deductInventoryForOrder($order, $validated['items'], $user->id);
                $order->update(['inventory_deducted_at' => now()]);
            }

            $running = (float) CustomerLedger::where('customer_id', $order->customer_id)->latest()->value('running_balance');

            if ($isPoTransaction) {
                $this->syncPoTransaction($order, $validated['payment_reference_no'] ?? null);
            } else {
                CustomerLedger::create([
                    'branch_id' => $order->branch_id,
                    'customer_id' => $order->customer_id,
                    'job_order_id' => $order->id,
                    'entry_type' => 'debit',
                    'amount' => $total,
                    'running_balance' => $running + $total,
                    'description' => "Job order {$order->job_order_number}",
                ]);
            }

            if ($paid > 0 && ! $isPoTransaction) {
                $collectedBranchId = $this->collectedBranchId($request, $order);
                $payment = Payment::create([
                    'branch_id' => $order->branch_id,
                    'collected_branch_id' => $collectedBranchId,
                    'job_order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'received_by' => $user->id,
                    'payment_number' => $this->nextPaymentNumber(),
                    'payment_type' => $paymentType,
                    'reference_no' => $validated['payment_reference_no'] ?? null,
                    'amount' => $paid,
                    'settlement_status' => $collectedBranchId === (int) $order->branch_id ? 'local' : 'pending',
                    'paid_at' => now(),
                ]);

                CustomerLedger::create([
                    'branch_id' => $order->branch_id,
                    'customer_id' => $order->customer_id,
                    'job_order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'entry_type' => 'credit',
                    'amount' => $paid,
                    'running_balance' => $running + $total - $paid,
                    'description' => "Payment {$payment->payment_number}",
                ]);
            }

            Activity::log($request, 'job_order_created', $order, [
                'job_order_number' => $order->job_order_number,
                'total' => $order->total,
                'is_rush' => $order->is_rush,
            ], $order->branch_id);

            $createdOrder = $order;

            return redirect()->route('admin.job-orders.index')->with('success', 'Job order created successfully.');
        });

        if ($request->boolean('send_sms') && $createdOrder) {
            SmsNotifier::jobOrderReceived($createdOrder);
        }

        return $response;
    }

    public function update(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeJobOrder($request, $jobOrder);

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'processing_branch_id' => ['nullable', 'exists:branches,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.laundry_service_id' => ['nullable', 'exists:laundry_services,id'],
            'items.*.service_preset_id' => ['nullable', 'exists:service_presets,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'transaction_type' => ['nullable', Rule::in(['walk_in', 'delivery'])],
            'is_rush' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $customerBelongsToBranch = Customer::query()
            ->whereKey($validated['customer_id'])
            ->where('branch_id', $jobOrder->branch_id)
            ->exists();

        if (! $customerBelongsToBranch) {
            throw ValidationException::withMessages([
                'customer_id' => 'Please choose a customer from this job order branch.',
            ]);
        }

        $validated['items'] = $this->expandPresetCartItems($validated['items'], (int) $jobOrder->branch_id);

        $serviceIds = collect($validated['items'])->pluck('laundry_service_id')->unique()->values();
        $selectedServices = LaundryService::query()
            ->whereIn('id', $serviceIds)
            ->where('branch_id', $jobOrder->branch_id)
            ->get()
            ->keyBy('id');

        if ($selectedServices->count() !== $serviceIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'All services must belong to this job order branch.',
            ]);
        }

        return DB::transaction(function () use ($request, $validated, $selectedServices, $jobOrder) {
            $previousProcessingBranchId = (int) ($jobOrder->processing_branch_id ?: $jobOrder->branch_id);
            $inventoryWasDeducted = (bool) $jobOrder->inventory_deducted_at;
            $validated['processing_branch_id'] = $this->resolveProcessingBranchId($jobOrder->branch, $validated['processing_branch_id'] ?? null, $request->user());
            $processingBranchChanged = (int) $validated['processing_branch_id'] !== $previousProcessingBranchId;
            $settings = SystemSetting::current();
            $subtotal = collect($validated['items'])->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
            $discount = min((float) ($validated['discount'] ?? 0), $subtotal);
            $taxable = max($subtotal - $discount, 0);
            $tax = $settings->vat_enabled ? ($taxable * ((float) $settings->vat_rate / 100)) : 0;
            $total = $taxable + $tax;
            $paid = (float) $jobOrder->payments()->sum('amount');

            if ($inventoryWasDeducted) {
                $this->restoreInventoryForOrder($jobOrder, $request->user()?->id);
            }

            $jobOrder->items()->delete();
            foreach ($validated['items'] as $item) {
                $service = $selectedServices->get((int) $item['laundry_service_id']);
                $jobOrder->items()->create([
                    'laundry_service_id' => $item['laundry_service_id'],
                    'service_preset_id' => $item['service_preset_id'] ?? null,
                    'description' => $item['description'],
                    'service_category' => $service->report_category,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => (float) $item['quantity'] * (float) $item['unit_price'],
                ]);
            }

            $orderUpdates = [
                'customer_id' => $validated['customer_id'],
                'processing_branch_id' => $validated['processing_branch_id'],
                'status' => $validated['status'],
                'transaction_type' => $validated['transaction_type'] ?? 'walk_in',
                'is_rush' => (bool) ($validated['is_rush'] ?? false),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid_amount' => $paid,
                'balance' => max($total - $paid, 0),
                'notes' => $validated['notes'] ?? null,
                'completed_at' => $validated['status'] === 'completed' ? ($jobOrder->completed_at ?: now()) : null,
            ];

            if ($processingBranchChanged && $jobOrder->branch?->isPickupDropoff()) {
                $orderUpdates += [
                    'current_branch_id' => $jobOrder->branch_id,
                    'release_branch_id' => $jobOrder->branch_id,
                    'production_accepted_at' => null,
                    'inventory_deducted_at' => null,
                    'production_completed_at' => null,
                    'returned_to_branch_at' => null,
                    'released_at' => null,
                ];
            }

            if (in_array($validated['status'], ['ready_for_pickup', 'ready_for_delivery', 'completed', 'cancelled'], true)) {
                $jobOrder->endActiveCycles();
            }

            $jobOrder->update($orderUpdates);

            $shouldDeductInventory = $validated['status'] !== 'cancelled'
                && (! $jobOrder->branch?->isPickupDropoff() || ($inventoryWasDeducted && ! $processingBranchChanged));

            if ($shouldDeductInventory) {
                $this->deductInventoryForOrder($jobOrder, $validated['items'], $request->user()?->id);
                $jobOrder->update(['inventory_deducted_at' => now()]);
            } elseif ($inventoryWasDeducted) {
                $jobOrder->update(['inventory_deducted_at' => null]);
            }

            Payment::query()
                ->where('job_order_id', $jobOrder->id)
                ->update([
                    'customer_id' => $jobOrder->customer_id,
                    'branch_id' => $jobOrder->branch_id,
                ]);

            CustomerLedger::query()
                ->where('job_order_id', $jobOrder->id)
                ->where('entry_type', 'debit')
                ->update([
                    'branch_id' => $jobOrder->branch_id,
                    'customer_id' => $jobOrder->customer_id,
                    'amount' => $total,
                    'description' => "Edited job order {$jobOrder->job_order_number}",
                ]);

            CustomerLedger::query()
                ->where('job_order_id', $jobOrder->id)
                ->where('entry_type', 'credit')
                ->update([
                    'branch_id' => $jobOrder->branch_id,
                    'customer_id' => $jobOrder->customer_id,
                ]);

            $jobOrder->load(['customer', 'poTransaction']);
            if ($jobOrder->poTransaction || $jobOrder->customer?->billing_type === 'po') {
                $this->syncPoTransaction($jobOrder);
            }

            Activity::log($request, 'job_order_updated', $jobOrder, [
                'job_order_number' => $jobOrder->job_order_number,
                'status' => $jobOrder->status,
                'total' => $jobOrder->total,
            ], $jobOrder->branch_id);

            return redirect()
                ->route('admin.job-orders.show', $jobOrder)
                ->with('success', 'Job order updated successfully.');
        });
    }

    public function updateStatus(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeJobOrder($request, $jobOrder);

        abort_if(in_array($jobOrder->status, ['completed', 'cancelled'], true), 422, 'Completed or cancelled job orders cannot be changed.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'washing', 'drying', 'folding', 'ready_for_pickup', 'ready_for_delivery', 'completed'])],
        ]);

        if (in_array($validated['status'], ['ready_for_pickup', 'ready_for_delivery', 'completed'], true)) {
            $jobOrder->endActiveCycles();
        }

        $jobOrder->update([
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === 'completed' ? now() : null,
        ]);

        Activity::log($request, 'job_order_status_updated', $jobOrder, [
            'job_order_number' => $jobOrder->job_order_number,
            'status' => $validated['status'],
        ], $jobOrder->branch_id);

        $jobOrder->loadMissing('customer');
        SmsNotifier::jobOrderStatus($jobOrder);

        return back()->with('success', 'Job order status updated successfully.');
    }

    public function cancel(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeJobOrder($request, $jobOrder);

        abort_if(in_array($jobOrder->status, ['completed', 'cancelled'], true), 422, 'Completed or cancelled job orders cannot be cancelled.');

        $jobOrder->endActiveCycles();

        $jobOrder->update([
            'status' => 'cancelled',
            'completed_at' => null,
        ]);

        Activity::log($request, 'job_order_cancelled', $jobOrder, [
            'job_order_number' => $jobOrder->job_order_number,
        ], $jobOrder->branch_id);

        return back()->with('success', 'Job order cancelled successfully.');
    }

    public function destroy(Request $request, JobOrder $jobOrder)
    {
        abort_unless($request->user()?->role === 'super_admin', 403);

        DB::transaction(function () use ($request, $jobOrder) {
            $jobOrder->loadMissing(['items', 'payments', 'poTransaction', 'cycles']);

            $paymentIds = $jobOrder->payments->pluck('id')->all();
            $snapshot = [
                'job_order_number' => $jobOrder->job_order_number,
                'customer_id' => $jobOrder->customer_id,
                'branch_id' => $jobOrder->branch_id,
                'status' => $jobOrder->status,
                'total' => (float) $jobOrder->total,
                'paid_amount' => (float) $jobOrder->paid_amount,
                'balance' => (float) $jobOrder->balance,
                'items_count' => $jobOrder->items->count(),
                'payments_count' => $jobOrder->payments->count(),
                'cycles_count' => $jobOrder->cycles->count(),
                'had_po_transaction' => (bool) $jobOrder->poTransaction,
            ];

            $this->restoreInventoryForOrder($jobOrder, $request->user()?->id);
            $this->deleteInventoryMovementsForOrder($jobOrder);

            CustomerLedger::query()
                ->where('job_order_id', $jobOrder->id)
                ->orWhereIn('payment_id', $paymentIds)
                ->delete();

            $jobOrder->poTransaction()?->delete();
            $jobOrder->payments()->delete();
            $jobOrder->cycles()->delete();
            $jobOrder->items()->delete();
            $jobOrder->delete();

            $this->recalculateCustomerLedger((int) $jobOrder->customer_id);

            Activity::log($request, 'job_order_deleted', $jobOrder, $snapshot, $jobOrder->branch_id);
        });

        return redirect()
            ->route('admin.job-orders.index')
            ->with('success', 'Job order deleted successfully. A deletion log was recorded.');
    }

    public function release(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeJobOrderRelease($request, $jobOrder);

        abort_unless(in_array($jobOrder->status, ['ready_for_pickup', 'ready_for_delivery'], true), 422);
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

        return back()->with('success', 'Laundry released to customer successfully.');
    }

    private function nextJobOrderNumber(int $branchId): string
    {
        $globalPrefix = SystemSetting::current()->job_order_prefix ?: 'JO';
        $branchPrefix = BranchSetting::query()
            ->where('branch_id', $branchId)
            ->value('job_order_prefix');
        $branchCode = Branch::query()
            ->whereKey($branchId)
            ->value('code') ?: 'BR'.$branchId;

        $prefix = $branchPrefix ?: $globalPrefix;
        $count = JobOrder::query()
            ->where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->count() + 1;

        $prefixParts = [trim((string) $prefix)];
        if (strcasecmp(trim((string) $prefix), trim((string) $branchCode)) !== 0) {
            $prefixParts[] = trim((string) $branchCode);
        }

        $prefixText = collect($prefixParts)
            ->filter()
            ->implode('-');

        return $prefixText.'-'.now()->format('Ymd').'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function nextPaymentNumber(): string
    {
        return 'PAY-'.now()->format('Ymd').'-'.str_pad((string) (Payment::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function authorizeJobOrder(Request $request, JobOrder $jobOrder): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless((int) $request->user()->branch_id === (int) $jobOrder->branch_id, 403);
    }

    private function authorizeJobOrderReceipt(Request $request, JobOrder $jobOrder): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless(in_array((int) $request->user()->branch_id, [
            (int) $jobOrder->branch_id,
            (int) ($jobOrder->processing_branch_id ?: $jobOrder->branch_id),
            (int) ($jobOrder->current_branch_id ?: $jobOrder->branch_id),
            (int) ($jobOrder->release_branch_id ?: $jobOrder->branch_id),
        ], true), 403);
    }

    private function authorizeJobOrderRelease(Request $request, JobOrder $jobOrder): void
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

    public function acceptProductionByBranch(Request $request, JobOrder $jobOrder, int $branchId): Branch
    {
        $productionBranch = Branch::query()
            ->whereKey($branchId)
            ->where('is_active', true)
            ->first();

        abort_unless($productionBranch && $productionBranch->isFullService(), 403);
        abort_if(in_array($jobOrder->status, ['completed', 'cancelled'], true), 422, 'Completed or cancelled job orders cannot be accepted for production.');

        $jobOrder->loadMissing('branch');
        abort_unless($jobOrder->branch?->isPickupDropoff(), 422, 'Only pickup/drop-off orders can be accepted by production scan.');

        $assignedProductionId = $jobOrder->processing_branch_id ?: null;
        abort_unless((int) $assignedProductionId === (int) $productionBranch->id, 403, 'This laundry is assigned to another production branch.');

        $hasCycles = $jobOrder->cycles()->exists();
        abort_if(
            $hasCycles && (int) ($jobOrder->processing_branch_id ?: $jobOrder->branch_id) !== (int) $productionBranch->id,
            422,
            'This job order already has production cycles in another branch.'
        );

        $jobOrder->update([
            'current_branch_id' => $productionBranch->id,
            'release_branch_id' => $productionBranch->id,
            'production_accepted_at' => $jobOrder->production_accepted_at ?: now(),
            'returned_to_branch_at' => null,
        ]);

        if (! $jobOrder->inventory_deducted_at) {
            $jobOrder->loadMissing('items');
            $items = $jobOrder->items
                ->map(fn ($item) => [
                    'laundry_service_id' => $item->laundry_service_id,
                    'quantity' => (float) $item->quantity,
                ])
                ->all();

            $this->deductInventoryForOrder($jobOrder, $items, $request->user()?->id);
            $jobOrder->update(['inventory_deducted_at' => now()]);
        }

        Activity::log($request, 'job_order_production_scan_accepted', $jobOrder, [
            'job_order_number' => $jobOrder->job_order_number,
            'dropoff_branch_id' => $jobOrder->branch_id,
            'processing_branch_id' => $productionBranch->id,
        ], $jobOrder->branch_id);

        return $productionBranch;
    }

    private function collectedBranchId(Request $request, JobOrder $order): int
    {
        return (int) ($request->user()->branch_id ?: $order->branch_id);
    }

    private function syncPoTransaction(JobOrder $order, ?string $poNumber = null): void
    {
        $order->loadMissing('customer');
        $existing = $order->poTransaction;
        $paidAmount = min((float) ($existing?->paid_amount ?? 0), (float) $order->total);
        $balance = max((float) $order->total - $paidAmount, 0);
        $status = $existing?->status ?? 'pending';

        if ($balance <= 0) {
            $status = 'paid';
        } elseif ($paidAmount > 0) {
            $status = 'partially_paid';
        }

        PoTransaction::query()->updateOrCreate(
            ['job_order_id' => $order->id],
            [
                'branch_id' => $order->branch_id,
                'customer_id' => $order->customer_id,
                'company_name' => $order->customer?->name,
                'po_number' => filled($poNumber) ? $poNumber : ($existing?->po_number ?: 'PO-'.$order->job_order_number),
                'transaction_date' => $order->created_at?->toDateString() ?: today()->toDateString(),
                'amount' => $order->total,
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'status' => $status,
                'billed_at' => in_array($status, ['billed', 'partially_paid', 'paid'], true) ? ($existing?->billed_at ?: now()) : null,
                'paid_at' => $status === 'paid' ? ($existing?->paid_at ?: now()) : null,
            ]
        );
    }

    private function resolveProcessingBranchId(Branch $originBranch, ?int $processingBranchId, User $user): int
    {
        if (! $user->canManageAllBranches() && $originBranch->isFullService()) {
            return (int) $originBranch->id;
        }

        if ($originBranch->isPickupDropoff()) {
            $processingBranchId = $processingBranchId ?: (int) Branch::query()
                ->where('is_active', true)
                ->where('branch_type', 'full_service')
                ->value('id');
        } else {
            $processingBranchId = $processingBranchId ?: (int) $originBranch->id;
        }

        $processingBranch = Branch::query()
            ->whereKey($processingBranchId)
            ->where('is_active', true)
            ->where('branch_type', 'full_service')
            ->first();

        if (! $processingBranch) {
            throw ValidationException::withMessages([
                'processing_branch_id' => 'Please choose an active full-service branch for production.',
            ]);
        }

        return (int) $processingBranch->id;
    }

    private function deductInventoryForOrder(JobOrder $order, array $items, ?int $userId): void
    {
        $serviceIds = collect($items)->pluck('laundry_service_id')->unique()->values();

        $services = LaundryService::query()
            ->with('inventoryUsages.inventory')
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        $deductions = [];

        foreach ($items as $item) {
            $service = $services->get((int) $item['laundry_service_id']);

            if (! $service) {
                continue;
            }

            foreach ($service->inventoryUsages as $usage) {
                $inventory = $this->productionInventoryForUsage($order, $usage);

                $deductions[$inventory->id] = ($deductions[$inventory->id] ?? 0)
                    + ((float) $usage->quantity * (float) $item['quantity']);
            }
        }

        foreach ($deductions as $inventoryId => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $inventory = Inventory::query()
                ->whereKey($inventoryId)
                ->where('branch_id', $order->processing_branch_id ?: $order->branch_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                throw ValidationException::withMessages([
                    'items' => 'A service inventory rule is linked to an invalid production stock item.',
                ]);
            }

            if ((float) $inventory->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'items' => "{$inventory->name} is insufficient. Available: {$inventory->quantity} {$inventory->unit}. Needed: ".number_format($quantity, 4).' '.$inventory->unit.'.',
                ]);
            }

            $inventory->movements()->create([
                'user_id' => $userId,
                'movement_type' => 'out',
                'quantity' => $quantity,
                'remarks' => "Auto deducted for {$order->job_order_number}",
            ]);

            $inventory->update([
                'quantity' => (float) $inventory->quantity - $quantity,
            ]);
        }
    }

    private function restoreInventoryForOrder(JobOrder $order, ?int $userId): void
    {
        $deductionRemark = "Auto deducted for {$order->job_order_number}";
        $restoreRemark = "Auto restored for {$order->job_order_number}";

        $movements = InventoryMovement::query()
            ->whereIn('remarks', [$deductionRemark, $restoreRemark])
            ->get()
            ->groupBy('inventory_id');

        foreach ($movements as $inventoryId => $inventoryMovements) {
            $deducted = (float) $inventoryMovements->where('movement_type', 'out')->sum('quantity');
            $restored = (float) $inventoryMovements->where('movement_type', 'in')->sum('quantity');
            $quantity = round($deducted - $restored, 4);

            if ($quantity <= 0) {
                continue;
            }

            $inventory = Inventory::query()->whereKey($inventoryId)->lockForUpdate()->first();
            if (! $inventory) {
                continue;
            }

            $inventory->movements()->create([
                'user_id' => $userId,
                'movement_type' => 'in',
                'quantity' => $quantity,
                'remarks' => $restoreRemark,
            ]);
            $inventory->update(['quantity' => (float) $inventory->quantity + $quantity]);
        }
    }

    private function deleteInventoryMovementsForOrder(JobOrder $order): void
    {
        InventoryMovement::query()
            ->whereIn('remarks', [
                "Auto deducted for {$order->job_order_number}",
                "Auto restored for {$order->job_order_number}",
            ])
            ->delete();
    }

    private function recalculateCustomerLedger(int $customerId): void
    {
        $runningBalance = 0.0;

        CustomerLedger::query()
            ->where('customer_id', $customerId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function (CustomerLedger $ledger) use (&$runningBalance): void {
                $amount = (float) $ledger->amount;
                $runningBalance += $ledger->entry_type === 'credit' ? -$amount : $amount;
                $runningBalance = max($runningBalance, 0);

                $ledger->update(['running_balance' => $runningBalance]);
            });
    }

    private function productionInventoryForUsage(JobOrder $order, $usage): Inventory
    {
        $sourceInventory = $usage->inventory;
        $productionBranchId = (int) ($order->processing_branch_id ?: $order->branch_id);

        if ($sourceInventory && (int) $sourceInventory->branch_id === $productionBranchId) {
            return $sourceInventory;
        }

        if (! $order->branch?->isPickupDropoff()) {
            throw ValidationException::withMessages([
                'items' => 'A service inventory rule is linked to an invalid branch stock item.',
            ]);
        }

        $productionInventory = Inventory::query()
            ->where('branch_id', $productionBranchId)
            ->where('is_active', true)
            ->when($sourceInventory?->sku, fn ($query) => $query->where('sku', $sourceInventory->sku))
            ->when(! $sourceInventory?->sku, fn ($query) => $query
                ->where('name', $sourceInventory?->name)
                ->where('unit', $sourceInventory?->unit))
            ->first();

        if (! $productionInventory) {
            throw ValidationException::withMessages([
                'items' => 'Production branch stock is not configured for '.$sourceInventory?->name.'. Add matching inventory stock in the assigned production branch.',
            ]);
        }

        return $productionInventory;
    }

    private function expandPresetCartItems(array $items, int $branchId): array
    {
        $presetIds = collect($items)
            ->pluck('service_preset_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $presets = ServicePreset::query()
            ->with(['items.service'])
            ->whereIn('id', $presetIds)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $expanded = [];

        foreach ($items as $item) {
            if (! empty($item['service_preset_id'])) {
                $preset = $presets->get((int) $item['service_preset_id']);

                if (! $preset) {
                    throw ValidationException::withMessages([
                        'items' => 'All presets must belong to the selected branch.',
                    ]);
                }

                $presetItems = $preset->items
                    ->filter(fn ($presetItem) => $presetItem->service && (int) $presetItem->service->branch_id === $branchId);

                if ($presetItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => 'The selected preset has no valid services.',
                    ]);
                }

                foreach ($presetItems as $presetItem) {
                    $service = $presetItem->service;
                    $expanded[] = [
                        'laundry_service_id' => $service->id,
                        'service_preset_id' => $preset->id,
                        'description' => $service->name,
                        'quantity' => (float) $item['quantity'] * (float) $presetItem->quantity,
                        'unit_price' => (float) $service->price,
                    ];
                }

                continue;
            }

            if (empty($item['laundry_service_id'])) {
                throw ValidationException::withMessages([
                    'items' => 'Each cart item must be a service or preset.',
                ]);
            }

            $expanded[] = $item;
        }

        return $expanded;
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
        $to = $this->parseDate($request->date_to);

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
