<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchExpense;
use App\Models\CustomerLedger;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\JobOrder;
use App\Models\JobOrderItem;
use App\Models\LaundryServiceCategory;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\ZReading;
use App\Models\AccountsPayable;
use App\Models\AccountsPayablePayment;
use App\Models\MoneyMovement;
use App\Models\SmsLog;
use App\Support\FinancialReconciliation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const DENOMINATIONS = [
        '1000' => 'PHP 1,000', '500' => 'PHP 500', '200' => 'PHP 200',
        '100' => 'PHP 100', '50' => 'PHP 50', '20' => 'PHP 20',
        '10' => 'PHP 10', '5' => 'PHP 5', '1' => 'PHP 1', '0.25' => 'PHP 0.25',
    ];

    public function index(Request $request)
    {
        return view('admin.reports.index', $this->reportData($request));
    }

    public function pdf(Request $request)
    {
        $data = $this->reportData($request);
        $branchName = $data['selectedBranchId']
            ? $data['branches']->firstWhere('id', $data['selectedBranchId'])?->name
            : 'All branches';

        $pdf = Pdf::loadView('admin.reports.pdf', [
            ...$data,
            'branchName' => $branchName,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('reports-'.$data['dateFrom'].'-to-'.$data['dateTo'].'.pdf');
    }

    public function zReadingPdf(Request $request)
    {
        $data = $this->consolidatedZReadingData($request);
        $pdf = Pdf::loadView('admin.z-readings.pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->stream('z-reading-'.$data['dateFrom'].'-to-'.$data['dateTo'].'.pdf');
    }

    private function consolidatedZReadingData(Request $request): array
    {
        $user = $request->user();
        [$dateFrom, $dateTo] = $this->dateRange($request);
        $branchId = $user->isAdmin() ? ($request->integer('branch_id') ?: null) : $user->branch_id;
        $financial = FinancialReconciliation::forPeriod($branchId, $dateFrom, $dateTo);
        $branch = $branchId
            ? Branch::query()->findOrFail($branchId)
            : new Branch(['name' => 'All Branches', 'code' => 'ALL', 'machine_count' => Branch::query()->max('machine_count')]);

        $orders = JobOrder::query()
            ->with([
                'customer:id,name,address,billing_type',
                'poTransaction:id,job_order_id',
                'items:id,job_order_id,laundry_service_id,description,service_category,quantity,unit_price,total',
                'items.service:id,name,report_category,service_category_id',
                'items.service.serviceCategory:id,name',
                'payments' => fn ($query) => $query
                    ->whereIn('payment_type', ['cash', 'gcash', 'bank'])
                    ->whereDate('paid_at', '>=', $dateFrom)
                    ->whereDate('paid_at', '<=', $dateTo)
                    ->orderBy('paid_at'),
            ])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $payments = Payment::query()
            ->with(['customer:id,name', 'jobOrder:id,job_order_number,created_at'])
            ->when($branchId, fn ($query) => $query->where('collected_branch_id', $branchId))
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo)
            ->whereIn('payment_type', ['cash', 'gcash', 'bank'])
            ->orderBy('paid_at')
            ->get();
        $currentPayments = $payments->filter(fn (Payment $payment) => $payment->jobOrder?->created_at?->betweenIncluded($dateFrom, Carbon::parse($dateTo)->endOfDay()));
        $previousPayments = $payments->diff($currentPayments);
        $paymentTotals = fn ($items) => $items->groupBy('payment_type')
            ->map(fn ($group) => round((float) $group->sum('amount'), 2))
            ->all();
        $regularUnpaidTotal = fn ($items) => round((float) $items
            ->reject(fn (JobOrder $order) => $order->poTransaction || $order->customer?->billing_type === 'po')
            ->sum('balance'), 2);

        $expenses = BranchExpense::query()
            ->with('creator:id,name')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get();

        $serviceTotals = JobOrderItem::query()
            ->join('job_orders', 'job_orders.id', '=', 'job_order_items.job_order_id')
            ->leftJoin('laundry_services', 'laundry_services.id', '=', 'job_order_items.laundry_service_id')
            ->leftJoin('laundry_service_categories', 'laundry_service_categories.id', '=', 'laundry_services.service_category_id')
            ->whereNull('job_orders.deleted_at')
            ->where('job_orders.status', '!=', 'cancelled')
            ->when($branchId, fn ($query) => $query->where('job_orders.branch_id', $branchId))
            ->whereDate('job_orders.created_at', '>=', $dateFrom)
            ->whereDate('job_orders.created_at', '<=', $dateTo)
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
            ->with('inventory:id,branch_id,name,unit')
            ->where('movement_type', 'out')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn ($query) => $query->whereHas('inventory', fn ($query) => $query->where('branch_id', $branchId)))
            ->get()
            ->groupBy(fn (InventoryMovement $movement) => $movement->inventory_id)
            ->map(fn ($group) => [
                'item_name' => $group->first()->inventory?->name,
                'quantity' => round((float) $group->sum('quantity'), 4),
                'unit' => $group->first()->inventory?->unit,
            ])
            ->values();

        $machineCycles = DB::table('cycle_records')
            ->join('job_orders', 'job_orders.id', '=', 'cycle_records.job_order_id')
            ->whereNull('job_orders.deleted_at')
            ->whereIn('cycle_records.cycle_type', ['wash', 'dry'])
            ->whereNotNull('cycle_records.machine_number')
            ->when($branchId, fn ($query) => $query->whereRaw('COALESCE(job_orders.processing_branch_id, job_orders.branch_id) = ?', [$branchId]))
            ->whereDate('cycle_records.started_at', '>=', $dateFrom)
            ->whereDate('cycle_records.started_at', '<=', $dateTo)
            ->groupBy('cycle_records.machine_number', 'cycle_records.cycle_type')
            ->orderBy('cycle_records.machine_number')
            ->get([
                'cycle_records.machine_number',
                'cycle_records.cycle_type',
                DB::raw('COUNT(*) as cycle_count'),
            ]);

        $readings = ZReading::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('business_date', '>=', $dateFrom)
            ->whereDate('business_date', '<=', $dateTo)
            ->orderBy('business_date')
            ->get();
        $cashCount = collect(self::DENOMINATIONS)->mapWithKeys(fn ($label, $value) => [
            $value => $readings->sum(fn (ZReading $reading) => (int) data_get($reading->cash_count, $value, 0)),
        ])->all();
        $machineCounters = $this->consolidatedMachineCounters($readings);

        $reading = new ZReading([
            'reading_number' => 'ZR-'.($branch->code ?: 'ALL').'-'.Carbon::parse($dateFrom)->format('Ymd').'-'.Carbon::parse($dateTo)->format('Ymd'),
            'business_date' => $dateTo,
            'cash_count' => $cashCount,
            'machine_counters' => $machineCounters,
            'expected_cash_amount' => $financial['cash_collections'],
            'cash_expense_amount' => $financial['store_cash_expenses'],
            'expected_cash_drawer_amount' => $financial['expected_cash_drawer'],
            'actual_cash_amount' => $readings->sum('actual_cash_amount'),
            'expected_gcash_amount' => $financial['expected_gcash'],
            'actual_gcash_amount' => $readings->sum('actual_gcash_amount'),
            'expected_bank_amount' => $financial['expected_bank'],
            'actual_bank_amount' => $readings->sum('actual_bank_amount'),
            'expected_total_amount' => $financial['expected_total'],
            'actual_total_amount' => (float) $readings->sum('actual_cash_amount') + (float) $readings->sum('actual_gcash_amount') + (float) $readings->sum('actual_bank_amount'),
            'over_short_amount' => $readings->sum('over_short_amount'),
            'transaction_count' => $orders->count(),
            'signature_name' => $user->name,
            'closed_at' => now(),
        ]);
        $reading->setRelation('branch', $branch);
        $reading->setRelation('preparer', $user);

        $orderItems = $orders->map(fn (JobOrder $order) => [
            'job_order_number' => $order->job_order_number,
            'customer_name' => $order->customer?->name,
            'address' => $order->customer?->address,
            'created_at' => $order->created_at?->toDateTimeString(),
            'total' => round((float) $order->total, 2),
            'balance' => round((float) $order->balance, 2),
            'notes' => $order->notes,
            'service_amounts' => $order->items
                ->groupBy(fn ($item) => $this->serviceCategoryLabel($item))
                ->map(fn ($items) => round((float) $items->sum('total'), 2))
                ->all(),
            'payments' => $order->payments->map(fn (Payment $payment) => [
                'type' => $payment->payment_type,
                'amount' => round((float) $payment->amount, 2),
                'reference_no' => $payment->reference_no,
            ])->values()->all(),
        ])->values()->all();

        $details = [
            'job_order_items' => $orderItems,
            'daily_total_sales' => round((float) $orders->sum('total'), 2),
            'daily_unpaid_amount' => $regularUnpaidTotal($orders),
            'payment_breakdown' => [
                'current_sales' => $paymentTotals($currentPayments),
                'previous_payment_items' => $previousPayments->map(fn (Payment $payment) => [
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'job_order_number' => $payment->jobOrder?->job_order_number,
                    'customer_name' => $payment->customer?->name,
                    'type' => $payment->payment_type,
                    'reference_no' => $payment->reference_no,
                    'amount' => round((float) $payment->amount, 2),
                ])->values()->all(),
            ],
            'expense_breakdown' => [
                'store_cash' => $financial['store_cash_expenses'],
                'store_gcash' => $financial['store_gcash_expenses'],
                'store_bank' => $financial['store_bank_expenses'],
                'owner' => $financial['owner_paid_expenses'],
                'money_movements' => ['cash_in' => $financial['cash_in'], 'cash_out' => $financial['cash_out']],
                'items' => $expenses->map(fn (BranchExpense $expense) => [
                    'title' => $expense->title, 'category' => $expense->category,
                    'payment_method' => $expense->payment_method, 'paid_from' => $expense->paid_from,
                    'reference_no' => $expense->reference_no, 'remarks' => $expense->remarks,
                    'amount' => round((float) $expense->amount, 2),
                ])->all(),
            ],
            'service_totals' => $serviceTotals->map(fn ($row) => [
                'category_name' => $this->humanCategoryLabel($row->category_name),
                'service_name' => $row->service_name,
                'quantity' => round((float) $row->quantity, 2),
                'total_amount' => round((float) $row->total_amount, 2),
            ])->all(),
            'sales_columns' => $this->salesColumns($serviceTotals),
            'inventory_usage' => $inventoryUsage->all(),
            'machine_cycles' => $machineCycles->map(fn ($row) => [
                'machine_number' => (int) $row->machine_number,
                'cycle_type' => $row->cycle_type,
                'cycle_count' => (int) $row->cycle_count,
            ])->all(),
        ];

        $signatories = User::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('role', 'branch_manager')
            ->where('status', 'active')
            ->pluck('name')
            ->all();

        return [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dateRangeLabel' => Carbon::parse($dateFrom)->format('M d, Y').' to '.Carbon::parse($dateTo)->format('M d, Y'),
            'documentTitle' => 'Daily Z Reading',
            'documentNumber' => $reading->reading_number,
            'generatedAt' => now(),
            'denominations' => self::DENOMINATIONS,
            'details' => $details,
            'reading' => $reading,
            'settings' => SystemSetting::current(),
            'signatories' => ['branch_manager' => $signatories, 'cashier' => []],
        ];
    }

    private function consolidatedMachineCounters($readings): array
    {
        $counters = [];

        foreach ($readings as $reading) {
            foreach ($reading->machine_counters ?? [] as $machine => $types) {
                foreach (['wash', 'dry'] as $type) {
                    $beginning = data_get($types, "{$type}.beginning");
                    $ending = data_get($types, "{$type}.ending");
                    $total = data_get($types, "{$type}.total");
                    $counters[$machine][$type]['beginning'] ??= $beginning;
                    if ($ending !== null) {
                        $counters[$machine][$type]['ending'] = $ending;
                    }
                    $counters[$machine][$type]['total'] = ($counters[$machine][$type]['total'] ?? 0) + (int) ($total ?? 0);
                }
            }
        }

        return $counters;
    }

    private function reportData(Request $request): array
    {
        $user = $request->user();
        $canChooseBranch = $user->isAdmin();
        [$dateFrom, $dateTo] = $this->dateRange($request);
        $branchId = $canChooseBranch ? ($request->integer('branch_id') ?: null) : $user->branch_id;
        $financialSummary = FinancialReconciliation::forPeriod($branchId, $dateFrom, $dateTo);

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $payments = Payment::query()
            ->with(['branch', 'collectedBranch', 'customer', 'jobOrder', 'receiver'])
            ->whereIn('payment_type', ['cash', 'gcash', 'bank', 'unpaid', 'po', 'monthly_billing'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo);

        $collections = Payment::query()
            ->with(['branch', 'collectedBranch', 'customer', 'jobOrder', 'receiver'])
            ->when($branchId, fn ($query) => $query->where('collected_branch_id', $branchId))
            ->whereIn('payment_type', ['cash', 'gcash', 'bank'])
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo);

        $salesByDate = (clone $payments)
            ->selectRaw("DATE(paid_at) as report_date, COALESCE(SUM(amount), 0) as total_amount, COALESCE(SUM(CASE WHEN payment_type = 'cash' THEN amount ELSE 0 END), 0) as cash_amount, COALESCE(SUM(CASE WHEN payment_type = 'gcash' THEN amount ELSE 0 END), 0) as gcash_amount, COALESCE(SUM(CASE WHEN payment_type = 'bank' THEN amount ELSE 0 END), 0) as bank_amount, COUNT(*) as payments_count")
            ->groupBy('report_date')
            ->orderBy('report_date')
            ->get();

        $salesByBranch = (clone $payments)
            ->join('branches', 'payments.branch_id', '=', 'branches.id')
            ->selectRaw("branches.name as branch_name, COALESCE(SUM(payments.amount), 0) as total_amount, COALESCE(SUM(CASE WHEN payments.payment_type = 'cash' THEN payments.amount ELSE 0 END), 0) as cash_amount, COALESCE(SUM(CASE WHEN payments.payment_type = 'gcash' THEN payments.amount ELSE 0 END), 0) as gcash_amount, COALESCE(SUM(CASE WHEN payments.payment_type = 'bank' THEN payments.amount ELSE 0 END), 0) as bank_amount, COUNT(*) as payments_count")
            ->groupBy('branches.name')
            ->orderByDesc('total_amount')
            ->get();

        $paymentTypes = (clone $payments)
            ->selectRaw('payment_type, COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as payments_count')
            ->groupBy('payment_type')
            ->orderByDesc('total_amount')
            ->get();

        $gcashPayments = (clone $payments)
            ->where('payment_type', 'gcash')
            ->latest('paid_at')
            ->limit(80)
            ->get();

        $collectionsByBranch = (clone $collections)
            ->join('branches', 'payments.collected_branch_id', '=', 'branches.id')
            ->selectRaw("branches.name as branch_name, COALESCE(SUM(payments.amount), 0) as total_amount, COALESCE(SUM(CASE WHEN payments.payment_type = 'cash' THEN payments.amount ELSE 0 END), 0) as cash_amount, COALESCE(SUM(CASE WHEN payments.payment_type = 'gcash' THEN payments.amount ELSE 0 END), 0) as gcash_amount, COALESCE(SUM(CASE WHEN payments.payment_type = 'bank' THEN payments.amount ELSE 0 END), 0) as bank_amount, COUNT(*) as payments_count")
            ->groupBy('branches.name')
            ->orderByDesc('total_amount')
            ->get();

        $crossBranchCollections = (clone $collections)
            ->whereColumn('collected_branch_id', '!=', 'branch_id')
            ->latest('paid_at')
            ->limit(80)
            ->get();

        $receivables = JobOrder::query()
            ->with(['branch', 'customer'])
            ->where('balance', '>', 0)
            ->where('status', '!=', 'cancelled')
            ->regularReceivable()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->latest()
            ->limit(30)
            ->get();

        $inventoryUsage = InventoryMovement::query()
            ->with(['inventory.branch', 'user'])
            ->where('movement_type', 'out')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn ($query) => $query->whereHas('inventory', fn ($query) => $query->where('branch_id', $branchId)))
            ->latest()
            ->limit(40)
            ->get();

        $customerLedger = CustomerLedger::query()
            ->with(['customer', 'branch'])
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->latest()
            ->limit(40)
            ->get();

        $activityLogs = ActivityLog::query()
            ->with(['user', 'branch'])
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->latest()
            ->limit(40)
            ->get();

        $expenses = BranchExpense::query()
            ->with(['branch', 'creator'])
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->latest('expense_date')
            ->latest()
            ->limit(60)
            ->get();

        $expenseSummary = BranchExpense::query()
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw("COALESCE(SUM(amount), 0) as total_expenses, COALESCE(SUM(CASE WHEN paid_from = 'store_cash' THEN amount ELSE 0 END), 0) as store_cash_expenses, COALESCE(SUM(CASE WHEN paid_from = 'owner' THEN amount ELSE 0 END), 0) as owner_expenses")
            ->first();

        $accountsPayables = AccountsPayable::query()
            ->with(['branch', 'payments'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('funded_at', '>=', $dateFrom)
            ->whereDate('funded_at', '<=', $dateTo)
            ->latest('funded_at')
            ->limit(60)
            ->get();

        $accountsPayableSummary = AccountsPayable::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('funded_at', '>=', $dateFrom)
            ->whereDate('funded_at', '<=', $dateTo)
            ->selectRaw('COALESCE(SUM(original_amount), 0) as original_total, COALESCE(SUM(paid_amount), 0) as paid_total, COALESCE(SUM(balance), 0) as balance_total')
            ->first();

        $accountsPayablePayments = AccountsPayablePayment::query()
            ->with(['payable', 'branch', 'recorder'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateTo)
            ->latest('payment_date')
            ->limit(60)
            ->get();

        $moneyMovements = MoneyMovement::query()
            ->with(['branch', 'recorder'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('movement_date', '>=', $dateFrom)
            ->whereDate('movement_date', '<=', $dateTo)
            ->latest('movement_date')
            ->limit(60)
            ->get();

        $smsSummary = SmsLog::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed, SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued")
            ->first();

        $jobOrderSummary = JobOrder::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw("COUNT(*) as total_orders, SUM(CASE WHEN is_rush = 1 THEN 1 ELSE 0 END) as rush_orders, COALESCE(SUM(total), 0) as order_value, COALESCE(SUM(CASE WHEN balance > 0 AND NOT EXISTS (SELECT 1 FROM po_transactions WHERE po_transactions.job_order_id = job_orders.id) AND NOT EXISTS (SELECT 1 FROM customers WHERE customers.id = job_orders.customer_id AND customers.billing_type = 'po') THEN balance ELSE 0 END), 0) as unpaid_balance")
            ->first();

        $loyalCustomerCount = Customer::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->has('jobOrders', '>=', 10)
            ->count();

        $zReadings = ZReading::query()
            ->with(['branch:id,name', 'preparer:id,name'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('business_date', '>=', $dateFrom)
            ->whereDate('business_date', '<=', $dateTo)
            ->orderBy('business_date')
            ->orderBy('branch_id')
            ->get();

        $zReadingSummary = (object) [
            'reading_count' => $zReadings->count(),
            'transaction_count' => (int) ($jobOrderSummary->total_orders ?? 0),
            'expected_total' => $financialSummary['expected_total'],
            'actual_total' => round((float) $zReadings->sum(fn (ZReading $reading) => (float) $reading->actual_cash_amount + (float) $reading->actual_gcash_amount + (float) $reading->actual_bank_amount), 2),
            'over_short' => round((float) $zReadings->sum(fn (ZReading $reading) => (float) $reading->actual_cash_amount + (float) $reading->actual_gcash_amount + (float) $reading->actual_bank_amount) - (float) $financialSummary['expected_total'], 2),
            'expected_cash' => $financialSummary['expected_cash_drawer'],
            'actual_cash' => round((float) $zReadings->sum('actual_cash_amount'), 2),
            'expected_gcash' => $financialSummary['expected_gcash'],
            'actual_gcash' => round((float) $zReadings->sum('actual_gcash_amount'), 2),
            'expected_bank' => $financialSummary['expected_bank'],
            'actual_bank' => round((float) $zReadings->sum('actual_bank_amount'), 2),
        ];

        $zPaymentSummary = Payment::query()
            ->when($branchId, fn ($query) => $query->where('collected_branch_id', $branchId))
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo)
            ->whereIn('payment_type', ['cash', 'gcash', 'bank'])
            ->selectRaw('payment_type, COUNT(*) as payments_count, COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('payment_type')
            ->orderBy('payment_type')
            ->get();

        $zPreviousPaymentTotal = Payment::query()
            ->join('job_orders', 'job_orders.id', '=', 'payments.job_order_id')
            ->when($branchId, fn ($query) => $query->where('payments.collected_branch_id', $branchId))
            ->whereDate('payments.paid_at', '>=', $dateFrom)
            ->whereDate('payments.paid_at', '<=', $dateTo)
            ->whereIn('payments.payment_type', ['cash', 'gcash', 'bank'])
            ->whereRaw('DATE(job_orders.created_at) < DATE(payments.paid_at)')
            ->sum('payments.amount');

        $zCategorySourceTotals = JobOrderItem::query()
            ->join('job_orders', 'job_orders.id', '=', 'job_order_items.job_order_id')
            ->leftJoin('laundry_services', 'laundry_services.id', '=', 'job_order_items.laundry_service_id')
            ->leftJoin('laundry_service_categories', 'laundry_service_categories.id', '=', 'laundry_services.service_category_id')
            ->whereNull('job_orders.deleted_at')
            ->where('job_orders.status', '!=', 'cancelled')
            ->when($branchId, fn ($query) => $query->where('job_orders.branch_id', $branchId))
            ->whereDate('job_orders.created_at', '>=', $dateFrom)
            ->whereDate('job_orders.created_at', '<=', $dateTo)
            ->groupByRaw('COALESCE(laundry_service_categories.name, job_order_items.service_category, laundry_services.report_category, "Uncategorized")')
            ->orderByRaw('COALESCE(laundry_service_categories.name, job_order_items.service_category, laundry_services.report_category, "Uncategorized")')
            ->get([
                DB::raw('COALESCE(laundry_service_categories.name, job_order_items.service_category, laundry_services.report_category, "Uncategorized") as category_name'),
                DB::raw('SUM(job_order_items.quantity) as quantity'),
                DB::raw('SUM(job_order_items.total) as total_amount'),
            ]);
        $zCategoryLabels = collect($this->salesColumns($zCategorySourceTotals))
            ->mapWithKeys(fn (string $label) => [$label => $label])
            ->all();
        $zCategoryTotals = collect($zCategoryLabels)
            ->mapWithKeys(fn (string $label) => [$label => (object) [
                'category_name' => $label,
                'quantity' => 0.0,
                'total_amount' => 0.0,
            ]]);

        foreach ($zCategorySourceTotals as $row) {
            $label = $this->humanCategoryLabel($row->category_name);
            $zCategoryTotals[$label] = (object) [
                'category_name' => $label,
                'quantity' => round((float) $row->quantity, 2),
                'total_amount' => round((float) $row->total_amount, 2),
            ];
        }

        $zDailyCategoryRows = JobOrderItem::query()
            ->join('job_orders', 'job_orders.id', '=', 'job_order_items.job_order_id')
            ->join('branches', 'branches.id', '=', 'job_orders.branch_id')
            ->leftJoin('laundry_services', 'laundry_services.id', '=', 'job_order_items.laundry_service_id')
            ->leftJoin('laundry_service_categories', 'laundry_service_categories.id', '=', 'laundry_services.service_category_id')
            ->whereNull('job_orders.deleted_at')
            ->where('job_orders.status', '!=', 'cancelled')
            ->when($branchId, fn ($query) => $query->where('job_orders.branch_id', $branchId))
            ->whereDate('job_orders.created_at', '>=', $dateFrom)
            ->whereDate('job_orders.created_at', '<=', $dateTo)
            ->groupByRaw('DATE(job_orders.created_at), job_orders.branch_id, branches.name, COALESCE(laundry_service_categories.name, job_order_items.service_category, laundry_services.report_category, "Uncategorized")')
            ->orderByRaw('DATE(job_orders.created_at), branches.name')
            ->get([
                DB::raw('DATE(job_orders.created_at) as business_date'),
                'job_orders.branch_id',
                'branches.name as branch_name',
                DB::raw('COALESCE(laundry_service_categories.name, job_order_items.service_category, laundry_services.report_category, "Uncategorized") as category_name'),
                DB::raw('SUM(job_order_items.total) as total_amount'),
            ]);

        $zDailyOrders = JobOrder::query()
            ->join('branches', 'branches.id', '=', 'job_orders.branch_id')
            ->leftJoin('customers', 'customers.id', '=', 'job_orders.customer_id')
            ->when($branchId, fn ($query) => $query->where('job_orders.branch_id', $branchId))
            ->where('job_orders.status', '!=', 'cancelled')
            ->whereDate('job_orders.created_at', '>=', $dateFrom)
            ->whereDate('job_orders.created_at', '<=', $dateTo)
            ->leftJoin('po_transactions', 'po_transactions.job_order_id', '=', 'job_orders.id')
            ->selectRaw("DATE(job_orders.created_at) as business_date, job_orders.branch_id, branches.name as branch_name, COUNT(*) as order_count, COALESCE(SUM(job_orders.total), 0) as sales_amount, COALESCE(SUM(CASE WHEN job_orders.balance > 0 AND po_transactions.id IS NULL AND COALESCE(customers.billing_type, '') <> 'po' THEN job_orders.balance ELSE 0 END), 0) as unpaid_amount")
            ->groupByRaw('DATE(job_orders.created_at), job_orders.branch_id, branches.name')
            ->orderByRaw('DATE(job_orders.created_at), branches.name')
            ->get()
            ->keyBy(fn ($row) => $row->business_date.'-'.$row->branch_id);

        $zDailyPayments = Payment::query()
            ->join('job_orders', 'job_orders.id', '=', 'payments.job_order_id')
            ->when($branchId, fn ($query) => $query->where('job_orders.branch_id', $branchId))
            ->whereDate('job_orders.created_at', '>=', $dateFrom)
            ->whereDate('job_orders.created_at', '<=', $dateTo)
            ->whereIn('payments.payment_type', ['cash', 'gcash', 'bank'])
            ->whereRaw('DATE(payments.paid_at) = DATE(job_orders.created_at)')
            ->selectRaw("DATE(job_orders.created_at) as business_date, job_orders.branch_id, COALESCE(SUM(CASE WHEN payments.payment_type = 'cash' THEN payments.amount ELSE 0 END), 0) as cash_amount, COALESCE(SUM(CASE WHEN payments.payment_type = 'gcash' THEN payments.amount ELSE 0 END), 0) as gcash_amount, COALESCE(SUM(CASE WHEN payments.payment_type = 'bank' THEN payments.amount ELSE 0 END), 0) as bank_amount")
            ->groupByRaw('DATE(job_orders.created_at), job_orders.branch_id')
            ->get()
            ->keyBy(fn ($row) => $row->business_date.'-'.$row->branch_id);

        $zDailyCategoryAmounts = $zDailyCategoryRows
            ->groupBy(fn ($row) => $row->business_date.'-'.$row->branch_id)
            ->map(fn ($rows) => $rows
                ->mapWithKeys(fn ($row) => [
                    $this->humanCategoryLabel($row->category_name) => round((float) $row->total_amount, 2),
                ])
                ->all());

        $zDailyOperations = $zDailyOrders->values()->map(function ($row) use ($zDailyCategoryAmounts, $zCategoryLabels, $zDailyPayments) {
            $key = $row->business_date.'-'.$row->branch_id;
            $payment = $zDailyPayments->get($key);
            $categoryAmounts = $zDailyCategoryAmounts->get($key, []);

            foreach (array_keys($zCategoryLabels) as $label) {
                $row->{$label.'_amount'} = round((float) ($categoryAmounts[$label] ?? 0), 2);
            }

            $row->order_count = (int) $row->order_count;
            $row->sales_amount = round((float) $row->sales_amount, 2);
            $row->unpaid_amount = round((float) $row->unpaid_amount, 2);
            $row->cash_amount = round((float) ($payment?->cash_amount ?? 0), 2);
            $row->gcash_amount = round((float) ($payment?->gcash_amount ?? 0), 2);
            $row->bank_amount = round((float) ($payment?->bank_amount ?? 0), 2);

            return $row;
        });

        $zMachineCycles = DB::table('cycle_records')
            ->join('job_orders', 'job_orders.id', '=', 'cycle_records.job_order_id')
            ->join('branches', 'branches.id', '=', DB::raw('COALESCE(job_orders.processing_branch_id, job_orders.branch_id)'))
            ->whereNull('job_orders.deleted_at')
            ->whereIn('cycle_records.cycle_type', ['wash', 'dry'])
            ->whereNotNull('cycle_records.machine_number')
            ->when($branchId, fn ($query) => $query->whereRaw('COALESCE(job_orders.processing_branch_id, job_orders.branch_id) = ?', [$branchId]))
            ->whereDate('cycle_records.started_at', '>=', $dateFrom)
            ->whereDate('cycle_records.started_at', '<=', $dateTo)
            ->groupBy('branches.name', 'cycle_records.machine_number', 'cycle_records.cycle_type')
            ->orderBy('branches.name')
            ->orderBy('cycle_records.machine_number')
            ->get([
                'branches.name as branch_name',
                'cycle_records.machine_number',
                'cycle_records.cycle_type',
                DB::raw('COUNT(*) as cycle_count'),
            ]);

        return [
            'activityLogs' => $activityLogs,
            'branches' => $branches,
            'canChooseBranch' => $canChooseBranch,
            'customerLedger' => $customerLedger,
            'dateFrom' => $dateFrom,
            'dateRangeValue' => $dateFrom.' to '.$dateTo,
            'dateTo' => $dateTo,
            'expenses' => $expenses,
            'expenseSummary' => $expenseSummary,
            'accountsPayables' => $accountsPayables,
            'accountsPayableSummary' => $accountsPayableSummary,
            'accountsPayablePayments' => $accountsPayablePayments,
            'moneyMovements' => $moneyMovements,
            'smsSummary' => $smsSummary,
            'jobOrderSummary' => $jobOrderSummary,
            'loyalCustomerCount' => $loyalCustomerCount,
            'financialSummary' => $financialSummary,
            'collectionsByBranch' => $collectionsByBranch,
            'crossBranchCollections' => $crossBranchCollections,
            'gcashPayments' => $gcashPayments,
            'inventoryUsage' => $inventoryUsage,
            'paymentTypes' => $paymentTypes,
            'receivables' => $receivables,
            'salesByBranch' => $salesByBranch,
            'salesByDate' => $salesByDate,
            'selectedBranchId' => $branchId,
            'settings' => SystemSetting::current(),
            'zMachineCycles' => $zMachineCycles,
            'zCategoryLabels' => $zCategoryLabels,
            'zCategoryTotals' => $zCategoryTotals,
            'zDailyOperations' => $zDailyOperations,
            'zPaymentSummary' => $zPaymentSummary,
            'zPreviousPaymentTotal' => round((float) $zPreviousPaymentTotal, 2),
            'zReadings' => $zReadings,
            'zReadingSummary' => $zReadingSummary,
            'zServiceTotals' => $zCategorySourceTotals,
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

    private function dateRange(Request $request): array
    {
        if ($request->filled('date_range')) {
            $parts = preg_split('/\s+to\s+/', $request->date_range);

            return [
                $this->parseDate($parts[0] ?? null, today()->subDays(6)->toDateString()),
                $this->parseDate($parts[1] ?? $parts[0] ?? null, today()->toDateString()),
            ];
        }

        return [today()->subDays(6)->toDateString(), today()->toDateString()];
    }

    private function parseDate(?string $date, string $fallback): string
    {
        if (! $date) {
            return $fallback;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
