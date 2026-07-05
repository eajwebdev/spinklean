<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchExpense;
use App\Models\Branch;
use App\Models\InventoryMovement;
use App\Support\FinancialReconciliation;
use App\Models\JobOrder;
use App\Models\JobOrderItem;
use App\Models\LaundryServiceCategory;
use App\Models\MoneyMovement;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\ZReading;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ZReadingController extends Controller
{
    private const DENOMINATIONS = [
        '1000' => 'PHP 1,000',
        '500' => 'PHP 500',
        '200' => 'PHP 200',
        '100' => 'PHP 100',
        '50' => 'PHP 50',
        '20' => 'PHP 20',
        '10' => 'PHP 10',
        '5' => 'PHP 5',
        '1' => 'PHP 1',
        '0.25' => 'PHP 0.25',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->canManageAllBranches();
        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $branchId = $canChooseBranch
            ? ($request->integer('branch_id') ?: $branches->first()?->id)
            : $user->branch_id;
        $businessDate = $request->date('business_date')?->toDateString() ?: today()->toDateString();

        abort_unless($branchId, 403);

        $branch = $branches->firstWhere('id', $branchId) ?: Branch::query()->findOrFail($branchId);
        if (! $canChooseBranch) {
            abort_unless((int) $user->branch_id === (int) $branch->id, 403);
        }

        $readings = ZReading::query()
            ->with(['branch', 'preparer'])
            ->when(! $canChooseBranch, fn ($query) => $query->where('branch_id', $user->branch_id))
            ->when($canChooseBranch && $request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->branch_id))
            ->latest('business_date')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.z-readings.index', [
            'branch' => $branch,
            'branches' => $branches,
            'businessDate' => $businessDate,
            'canChooseBranch' => $canChooseBranch,
            'readings' => $readings,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->canManageAllBranches();
        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $branchId = $canChooseBranch
            ? ($request->integer('branch_id') ?: $branches->first()?->id)
            : $user->branch_id;
        $businessDate = $request->date('business_date')?->toDateString() ?: today()->toDateString();

        abort_unless($branchId, 403);

        $branch = $branches->firstWhere('id', $branchId) ?: Branch::query()->findOrFail($branchId);
        if (! $canChooseBranch) {
            abort_unless((int) $user->branch_id === (int) $branch->id, 403);
        }

        $summary = $this->summary((int) $branch->id, $businessDate);
        $reading = ZReading::query()
            ->with(['branch', 'preparer'])
            ->where('branch_id', $branch->id)
            ->whereDate('business_date', $businessDate)
            ->first();
        $machineCount = max(
            1,
            (int) $branch->machine_count,
            (int) collect($summary['machine_cycles'])->max('machine_number'),
            (int) collect(array_keys($reading?->machine_counters ?? []))->max()
        );
        $machineCounters = $this->machineCountersForDate((int) $branch->id, $businessDate, $machineCount, $summary, $reading);

        return view('admin.z-readings.create', [
            'branch' => $branch,
            'branches' => $branches,
            'businessDate' => $businessDate,
            'canChooseBranch' => $canChooseBranch,
            'denominations' => self::DENOMINATIONS,
            'reading' => $reading,
            'summary' => $summary,
            'machineCount' => $machineCount,
            'machineCounters' => $machineCounters,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->canManageAllBranches();

        $validated = $request->validate([
            'branch_id' => [$canChooseBranch ? 'required' : 'nullable', 'exists:branches,id'],
            'business_date' => ['required', 'date'],
            'cash_count' => ['nullable', 'array'],
            'cash_count.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'actual_gcash_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'actual_bank_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'machine_counters' => ['nullable', 'array'],
            'machine_counters.*.wash.beginning' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'machine_counters.*.wash.ending' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'machine_counters.*.dry.beginning' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'machine_counters.*.dry.ending' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ]);

        $branchId = $canChooseBranch ? (int) $validated['branch_id'] : (int) $user->branch_id;
        abort_unless($branchId, 403);

        $businessDate = Carbon::parse($validated['business_date'])->toDateString();
        $cashCount = $this->normalizedCashCount($validated['cash_count'] ?? []);
        $actualCash = $this->cashCountTotal($cashCount);
        $actualGcash = round((float) ($validated['actual_gcash_amount'] ?? 0), 2);
        $actualBank = round((float) ($validated['actual_bank_amount'] ?? 0), 2);
        $summary = $this->summary($branchId, $businessDate);
        $machineCount = max(
            1,
            (int) Branch::query()->whereKey($branchId)->value('machine_count'),
            (int) collect($summary['machine_cycles'])->max('machine_number'),
            (int) collect(array_keys($validated['machine_counters'] ?? []))->max()
        );
        $machineCounters = $this->normalizedMachineCounters(
            $validated['machine_counters'] ?? $this->machineCountersForDate($branchId, $businessDate, $machineCount, $summary)
        );
        $actualTotal = round($actualCash + $actualGcash + $actualBank, 2);
        $overShort = round($actualTotal - (float) $summary['expected_total_amount'], 2);

        $reading = DB::transaction(function () use ($branchId, $businessDate, $cashCount, $machineCounters, $actualCash, $actualGcash, $actualBank, $actualTotal, $overShort, $summary, $user): ZReading {
            $reading = ZReading::query()
                ->where('branch_id', $branchId)
                ->whereDate('business_date', $businessDate)
                ->lockForUpdate()
                ->first();

            if (! $reading) {
                $reading = new ZReading([
                    'branch_id' => $branchId,
                    'business_date' => $businessDate,
                    'reading_number' => $this->nextReadingNumber($branchId, $businessDate),
                ]);
            }

            $reading->fill([
                'prepared_by' => $user->id,
                'cash_count' => $cashCount,
                'payment_breakdown' => $summary['payment_breakdown'],
                'expense_breakdown' => $summary['expense_breakdown'],
                'machine_counters' => $machineCounters,
                'expected_cash_amount' => $summary['expected_cash_amount'],
                'cash_expense_amount' => $summary['cash_expense_amount'],
                'expected_cash_drawer_amount' => $summary['expected_cash_drawer_amount'],
                'actual_cash_amount' => $actualCash,
                'expected_gcash_amount' => $summary['expected_gcash_amount'],
                'actual_gcash_amount' => $actualGcash,
                'expected_bank_amount' => $summary['expected_bank_amount'],
                'actual_bank_amount' => $actualBank,
                'expected_total_amount' => $summary['expected_total_amount'],
                'actual_total_amount' => $actualTotal,
                'over_short_amount' => $overShort,
                'transaction_count' => $summary['transaction_count'],
                'first_job_order_number' => $summary['first_job_order_number'],
                'last_job_order_number' => $summary['last_job_order_number'],
                'signature_name' => $user->name,
                'remarks' => null,
                'closed_at' => now(),
            ]);
            $reading->save();

            return $reading;
        });

        return redirect()
            ->route('admin.z-readings.index', ['branch_id' => $branchId, 'business_date' => $businessDate])
            ->with('success', "Z Reading {$reading->reading_number} saved successfully.");
    }

    public function pdf(Request $request, ZReading $zReading)
    {
        $this->authorizeReading($request, $zReading);

        $zReading->load(['branch', 'preparer']);
        $details = $this->summary((int) $zReading->branch_id, $zReading->business_date->toDateString());
        $printReading = $zReading->replicate();
        $printReading->exists = true;
        $printReading->setRelation('branch', $zReading->branch);
        $printReading->setRelation('preparer', $zReading->preparer);
        $actualTotal = round((float) $zReading->actual_cash_amount + (float) $zReading->actual_gcash_amount + (float) $zReading->actual_bank_amount, 2);
        $printReading->forceFill([
            'id' => $zReading->id,
            'reading_number' => $zReading->reading_number,
            'expected_cash_amount' => $details['expected_cash_amount'],
            'cash_expense_amount' => $details['cash_expense_amount'],
            'expected_cash_drawer_amount' => $details['expected_cash_drawer_amount'],
            'expected_gcash_amount' => $details['expected_gcash_amount'],
            'expected_bank_amount' => $details['expected_bank_amount'],
            'expected_total_amount' => $details['expected_total_amount'],
            'actual_total_amount' => $actualTotal,
            'over_short_amount' => round($actualTotal - (float) $details['expected_total_amount'], 2),
            'transaction_count' => $details['transaction_count'],
            'first_job_order_number' => $details['first_job_order_number'],
            'last_job_order_number' => $details['last_job_order_number'],
        ]);

        $pdf = Pdf::loadView('admin.z-readings.pdf', [
            'denominations' => self::DENOMINATIONS,
            'details' => $details,
            'reading' => $printReading,
            'settings' => SystemSetting::current(),
            'signatories' => $this->signatories((int) $zReading->branch_id),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream($zReading->reading_number.'.pdf');
    }

    private function summary(int $branchId, string $businessDate): array
    {
        $financial = FinancialReconciliation::forPeriod($branchId, $businessDate, $businessDate);
        $moneyMovements = MoneyMovement::query()
            ->with('recorder')
            ->where('branch_id', $branchId)
            ->whereDate('movement_date', $businessDate)
            ->latest()
            ->get();

        $jobOrders = JobOrder::query()
            ->with([
                'customer:id,name,address,billing_type',
                'poTransaction:id,job_order_id',
                'items:id,job_order_id,laundry_service_id,description,service_category,quantity,unit_price,total',
                'items.service:id,name,report_category,service_category_id',
                'items.service.serviceCategory:id,name',
                'payments' => fn ($query) => $query
                    ->where('collected_branch_id', $branchId)
                    ->whereDate('paid_at', $businessDate)
                    ->orderBy('paid_at'),
            ])
            ->where('branch_id', $branchId)
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', $businessDate)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $payments = Payment::query()
            ->with(['customer:id,name', 'jobOrder:id,job_order_number,created_at'])
            ->where('collected_branch_id', $branchId)
            ->whereDate('paid_at', $businessDate)
            ->whereIn('payment_type', ['cash', 'gcash', 'bank'])
            ->orderBy('paid_at')
            ->get();

        $currentSalesPayments = $payments
            ->filter(fn (Payment $payment) => $payment->jobOrder?->created_at?->toDateString() === $businessDate)
            ->values();
        $previousPayments = $payments
            ->reject(fn (Payment $payment) => $payment->jobOrder?->created_at?->toDateString() === $businessDate)
            ->values();

        $paymentTotals = fn ($items) => $items
            ->groupBy('payment_type')
            ->map(fn ($group) => round((float) $group->sum('amount'), 2))
            ->all();
        $regularUnpaidTotal = fn ($items) => round((float) $items
            ->reject(fn (JobOrder $order) => $order->poTransaction || $order->customer?->billing_type === 'po')
            ->sum('balance'), 2);

        $expenses = BranchExpense::query()
            ->with('creator:id,name')
            ->where('branch_id', $branchId)
            ->whereDate('expense_date', $businessDate)
            ->orderBy('id')
            ->get();

        $serviceTotals = JobOrderItem::query()
            ->join('job_orders', 'job_orders.id', '=', 'job_order_items.job_order_id')
            ->leftJoin('laundry_services', 'laundry_services.id', '=', 'job_order_items.laundry_service_id')
            ->leftJoin('laundry_service_categories', 'laundry_service_categories.id', '=', 'laundry_services.service_category_id')
            ->whereNull('job_orders.deleted_at')
            ->where('job_orders.status', '!=', 'cancelled')
            ->where('job_orders.branch_id', $branchId)
            ->whereDate('job_orders.created_at', $businessDate)
            ->groupByRaw('COALESCE(laundry_service_categories.name, job_order_items.service_category, laundry_services.report_category, "Uncategorized")')
            ->groupByRaw('COALESCE(laundry_services.name, job_order_items.description)')
            ->orderByRaw('COALESCE(laundry_service_categories.name, job_order_items.service_category, laundry_services.report_category, "Uncategorized")')
            ->orderByRaw('COALESCE(laundry_services.name, job_order_items.description)')
            ->get([
                DB::raw('COALESCE(laundry_service_categories.name, job_order_items.service_category, laundry_services.report_category, "Uncategorized") as category_name'),
                DB::raw('COALESCE(laundry_services.name, job_order_items.description) as service_name'),
                DB::raw('SUM(job_order_items.quantity) as quantity'),
                DB::raw('SUM(job_order_items.total) as total_amount'),
            ]);

        $inventoryUsage = InventoryMovement::query()
            ->with(['inventory:id,branch_id,name,unit', 'user:id,name'])
            ->where('movement_type', 'out')
            ->whereDate('created_at', $businessDate)
            ->whereHas('inventory', fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('id')
            ->get();

        $machineCycles = DB::table('cycle_records')
            ->join('job_orders', 'job_orders.id', '=', 'cycle_records.job_order_id')
            ->whereNull('job_orders.deleted_at')
            ->whereIn('cycle_records.cycle_type', ['wash', 'dry'])
            ->whereNotNull('cycle_records.machine_number')
            ->whereRaw('COALESCE(job_orders.processing_branch_id, job_orders.branch_id) = ?', [$branchId])
            ->whereDate('cycle_records.started_at', $businessDate)
            ->groupBy('cycle_records.machine_number', 'cycle_records.cycle_type')
            ->orderBy('cycle_records.machine_number')
            ->get([
                'cycle_records.machine_number',
                'cycle_records.cycle_type',
                DB::raw('COUNT(*) as cycle_count'),
            ]);

        $jobOrderItems = $jobOrders->map(fn (JobOrder $order) => [
            'job_order_number' => $order->job_order_number,
            'customer_name' => $order->customer?->name,
            'address' => $order->customer?->address,
            'billing_type' => $order->customer?->billing_type,
            'transaction_type' => $order->transaction_type,
            'created_at' => $order->created_at?->toDateTimeString(),
            'total' => round((float) $order->total, 2),
            'balance' => round((float) $order->balance, 2),
            'notes' => $order->notes,
            'services' => $order->items->map(fn ($item) => [
                'name' => $item->service?->name ?: $item->description,
                'quantity' => round((float) $item->quantity, 2),
                'unit_price' => round((float) $item->unit_price, 2),
                'total' => round((float) $item->total, 2),
            ])->values()->all(),
            'service_amounts' => $order->items
                ->groupBy(fn ($item) => $this->serviceCategoryLabel($item))
                ->map(fn ($items) => round((float) $items->sum('total'), 2))
                ->all(),
            'payments' => $order->payments
                ->whereIn('payment_type', ['cash', 'gcash', 'bank'])
                ->map(fn (Payment $payment) => [
                'type' => $payment->payment_type,
                'amount' => round((float) $payment->amount, 2),
                'reference_no' => $payment->reference_no,
            ])->values()->all(),
        ])->values()->all();

        return [
            'expected_cash_amount' => $financial['cash_collections'],
            'cash_expense_amount' => $financial['store_cash_expenses'],
            'expected_cash_drawer_amount' => $financial['expected_cash_drawer'],
            'expected_gcash_amount' => $financial['expected_gcash'],
            'expected_bank_amount' => $financial['expected_bank'],
            'expected_total_amount' => $financial['expected_total'],
            'payment_breakdown' => [
                'amounts' => collect($financial['payment_amounts'])->only(['cash', 'gcash', 'bank', 'unpaid', 'po', 'monthly_billing'])->all(),
                'counts' => collect($financial['payment_counts'])->only(['cash', 'gcash', 'bank', 'unpaid', 'po', 'monthly_billing'])->all(),
                'current_sales' => $paymentTotals($currentSalesPayments),
                'previous_payments' => $paymentTotals($previousPayments),
                'previous_payment_items' => $previousPayments->map(fn (Payment $payment) => [
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'payment_number' => $payment->payment_number,
                    'job_order_number' => $payment->jobOrder?->job_order_number,
                    'customer_name' => $payment->customer?->name,
                    'type' => $payment->payment_type,
                    'reference_no' => $payment->reference_no,
                    'amount' => round((float) $payment->amount, 2),
                ])->all(),
                'unpaid_amount' => 0,
                'po_amount' => $financial['po_collections'],
                'monthly_billing_amount' => $financial['monthly_billing_collections'],
            ],
            'expense_breakdown' => [
                'store_cash' => $financial['store_cash_expenses'],
                'store_gcash' => $financial['store_gcash_expenses'],
                'store_bank' => $financial['store_bank_expenses'],
                'owner' => $financial['owner_paid_expenses'],
                'items' => $expenses->map(fn (BranchExpense $expense) => [
                    'category' => $expense->category,
                    'title' => $expense->title,
                    'payment_method' => $expense->payment_method,
                    'paid_from' => $expense->paid_from,
                    'reference_no' => $expense->reference_no,
                    'remarks' => $expense->remarks,
                    'amount' => round((float) $expense->amount, 2),
                    'created_by' => $expense->creator?->name,
                ])->all(),
                'accounts_payable' => [
                    'gcash_funding' => $financial['gcash_owner_funding'],
                    'gcash_repayments' => $financial['gcash_payable_repayments'],
                    'bank_funding' => $financial['bank_owner_funding'],
                    'bank_repayments' => $financial['bank_payable_repayments'],
                ],
                'money_movements' => [
                    'cash_in' => $financial['cash_in'],
                    'cash_out' => $financial['cash_out'],
                    'items' => $moneyMovements->map(fn (MoneyMovement $movement) => [
                        'id' => $movement->id,
                        'type' => $movement->type,
                        'label' => $movement->type_label,
                        'direction' => $movement->direction,
                        'amount' => round((float) $movement->amount, 2),
                        'reference_no' => $movement->reference_no,
                        'description' => $movement->description,
                        'recorded_by' => $movement->recorder?->name,
                    ])->values()->all(),
                ],
            ],
            'daily_total_sales' => round((float) $jobOrders->sum('total'), 2),
            'daily_unpaid_amount' => $regularUnpaidTotal($jobOrders),
            'current_sales_payment_total' => round((float) $currentSalesPayments->sum('amount'), 2),
            'previous_payment_total' => round((float) $previousPayments->sum('amount'), 2),
            'job_order_items' => $jobOrderItems,
            'service_totals' => $serviceTotals->map(fn ($row) => [
                'category_name' => $this->humanCategoryLabel($row->category_name),
                'service_name' => $row->service_name,
                'quantity' => round((float) $row->quantity, 2),
                'total_amount' => round((float) $row->total_amount, 2),
            ])->all(),
            'sales_columns' => $this->salesColumns($serviceTotals),
            'inventory_usage' => $inventoryUsage->map(fn (InventoryMovement $movement) => [
                'item_name' => $movement->inventory?->name,
                'quantity' => round((float) $movement->quantity, 4),
                'unit' => $movement->inventory?->unit,
                'remarks' => $movement->remarks,
                'recorded_by' => $movement->user?->name,
            ])->all(),
            'machine_cycles' => $machineCycles->map(fn ($row) => [
                'machine_number' => (int) $row->machine_number,
                'cycle_type' => $row->cycle_type,
                'cycle_count' => (int) $row->cycle_count,
            ])->all(),
            'transaction_count' => $jobOrders->count(),
            'first_job_order_number' => $jobOrders->first()?->job_order_number,
            'last_job_order_number' => $jobOrders->last()?->job_order_number,
        ];
    }

    private function salesColumns($serviceTotals): array
    {
        $categories = LaundryServiceCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $used = collect($serviceTotals)
            ->pluck('category_name')
            ->map(fn ($value) => $this->humanCategoryLabel($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return collect($categories)
            ->merge($used)
            ->unique()
            ->values()
            ->all();
    }

    private function serviceCategoryLabel($item): string
    {
        return $this->humanCategoryLabel(
            $item->service?->serviceCategory?->name
            ?: $item->service_category
            ?: $item->service?->report_category
            ?: 'Uncategorized'
        );
    }

    private function humanCategoryLabel(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'Uncategorized';
        }

        return str($value)->replace('_', ' ')->title()->toString();
    }

    private function normalizedCashCount(array $cashCount): array
    {
        return collect(self::DENOMINATIONS)
            ->mapWithKeys(fn (string $label, string $value) => [$value => max(0, (int) ($cashCount[$value] ?? 0))])
            ->all();
    }

    private function machineCountersForDate(int $branchId, string $businessDate, int $machineCount, array $summary, ?ZReading $reading = null): array
    {
        if ($reading?->machine_counters) {
            return $reading->machine_counters;
        }

        $previousReading = ZReading::query()
            ->where('branch_id', $branchId)
            ->whereDate('business_date', '<', $businessDate)
            ->latest('business_date')
            ->latest('id')
            ->first(['machine_counters']);
        $previousCounters = $previousReading?->machine_counters ?? [];

        $cycleCounts = collect($summary['machine_cycles'] ?? [])
            ->groupBy('machine_number')
            ->map(fn ($rows) => collect($rows)->mapWithKeys(fn ($row) => [
                $row['cycle_type'] => (int) $row['cycle_count'],
            ])->all());

        return collect(range(1, $machineCount))
            ->mapWithKeys(function (int $machine) use ($previousCounters, $cycleCounts) {
                $types = [];

                foreach (['wash', 'dry'] as $type) {
                    $beginning = (int) (data_get($previousCounters, "{$machine}.{$type}.ending") ?? 0);
                    $total = (int) data_get($cycleCounts, "{$machine}.{$type}", 0);
                    $ending = $beginning + $total;

                    $types[$type] = [
                        'beginning' => $beginning,
                        'ending' => $ending,
                        'total' => $total,
                    ];
                }

                return [$machine => $types];
            })
            ->all();
    }

    private function cashCountTotal(array $cashCount): float
    {
        $total = 0.0;

        foreach ($cashCount as $value => $quantity) {
            $total += (float) $value * (int) $quantity;
        }

        return round($total, 2);
    }

    private function normalizedMachineCounters(array $counters): array
    {
        return collect($counters)
            ->mapWithKeys(function ($types, $machineNumber) {
                $machineNumber = (int) $machineNumber;
                if ($machineNumber < 1) {
                    return [];
                }

                $normalized = [];
                foreach (['wash', 'dry'] as $type) {
                    $beginning = data_get($types, "{$type}.beginning");
                    $ending = data_get($types, "{$type}.ending");
                    $normalized[$type] = [
                        'beginning' => is_numeric($beginning) ? (int) $beginning : null,
                        'ending' => is_numeric($ending) ? (int) $ending : null,
                    ];
                    $normalized[$type]['total'] = $normalized[$type]['beginning'] !== null && $normalized[$type]['ending'] !== null
                        ? max(0, $normalized[$type]['ending'] - $normalized[$type]['beginning'])
                        : null;
                }

                return [$machineNumber => $normalized];
            })
            ->sortKeys()
            ->all();
    }

    private function nextReadingNumber(int $branchId, string $businessDate): string
    {
        $branchCode = Branch::query()->whereKey($branchId)->value('code') ?: 'BR'.$branchId;
        $count = ZReading::query()
            ->whereDate('created_at', today())
            ->count() + 1;

        return 'ZR-'.$branchCode.'-'.Carbon::parse($businessDate)->format('Ymd').'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function signatories(int $branchId): array
    {
        $users = User::query()
            ->where('branch_id', $branchId)
            ->whereIn('role', ['branch_manager', 'cashier'])
            ->where('status', 'active')
            ->orderByRaw("CASE role WHEN 'branch_manager' THEN 0 WHEN 'cashier' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get(['name', 'role']);

        return [
            'branch_manager' => $users->where('role', 'branch_manager')->pluck('name')->values()->all(),
            'cashier' => $users->where('role', 'cashier')->pluck('name')->values()->all(),
        ];
    }

    private function authorizeReading(Request $request, ZReading $reading): void
    {
        if ($request->user()->canManageAllBranches()) {
            return;
        }

        abort_unless((int) $request->user()->branch_id === (int) $reading->branch_id, 403);
    }
}
