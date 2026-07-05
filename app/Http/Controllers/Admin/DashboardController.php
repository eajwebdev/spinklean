<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchExpense;
use App\Models\Customer;
use App\Models\DailyTask;
use App\Models\DailyTaskCompletion;
use App\Models\EmployeeAttendanceRecord;
use App\Models\Inventory;
use App\Models\JobOrder;
use App\Models\JobOrderItem;
use App\Models\MoneyMovement;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\ZReading;
use App\Models\AccountsPayable;
use App\Support\StatusBadge;
use App\Support\FinancialReconciliation;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->isAdmin();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        return view('dashboard', [
            'branches' => $branches,
            'canChooseBranch' => $canChooseBranch,
            'dashboardData' => $this->payload($request),
            'dateRangeValue' => $this->dateRangeValue($request),
            'selectedBranchId' => $this->branchId($request),
            'settings' => SystemSetting::current(),
        ]);
    }

    public function data(Request $request)
    {
        return response()->json($this->payload($request));
    }

    public function assistant(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['super_admin', 'admin', 'branch_manager'], true), 403);

        $validated = $request->validate([
            'preset' => ['nullable', 'string'],
            'question' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date_range' => ['nullable', 'string', 'max:50'],
        ]);

        $preset = $validated['preset'] ?? $this->inferAssistantPreset($validated['question'] ?? '');
        abort_unless(array_key_exists($preset, $this->assistantPresets()), 422);

        return response()->json($this->assistantAnswer($request, $preset, $validated['question'] ?? null));
    }

    public function assistantOptions(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['super_admin', 'admin', 'branch_manager'], true), 403);

        $canChooseBranch = $request->user()->canManageAllBranches();

        return response()->json([
            'can_choose_branch' => $canChooseBranch,
            'branches' => Branch::query()
                ->where('is_active', true)
                ->when(! $canChooseBranch, fn ($query) => $query->whereKey($request->user()->branch_id))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch) => ['id' => $branch->id, 'name' => $branch->name])
                ->values(),
        ]);
    }

    private function payload(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);
        $branchId = $this->branchId($request);
        $currency = SystemSetting::current()->currency ?: 'PHP';
        $financial = FinancialReconciliation::forPeriod($branchId, $dateFrom, $dateTo);

        $orders = JobOrder::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        $ordersInRange = (clone $orders)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        $payments = Payment::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo);

        $collections = Payment::query()
            ->when($branchId, fn ($query) => $query->where('collected_branch_id', $branchId))
            ->whereIn('payment_type', ['cash', 'gcash', 'bank'])
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo);

        $salesTotal = (float) (clone $payments)->sum('amount');
        $collectionsTotal = $financial['physical_collections'];
        $ordersCount = (clone $ordersInRange)->count();
        $openOrders = (clone $orders)->whereNotIn('status', ['completed', 'cancelled'])->count();
        $readyForPickup = (clone $orders)->where('status', 'ready_for_pickup')->count();
        $readyForDelivery = (clone $orders)->where('status', 'ready_for_delivery')->count();
        $receivables = $financial['unpaid_balance'];
        $lowStock = Inventory::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->count();
        $accountsPayable = $financial['accounts_payable'];

        $salesByDate = (clone $payments)
            ->selectRaw('DATE(paid_at) as paid_date, COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('paid_date')
            ->pluck('total_amount', 'paid_date');

        $salesLabels = [];
        $salesValues = [];
        foreach (CarbonPeriod::create($dateFrom, $dateTo) as $date) {
            $key = $date->toDateString();
            $salesLabels[] = $date->format('M d');
            $salesValues[] = round((float) ($salesByDate[$key] ?? 0), 2);
        }

        $statusRows = (clone $ordersInRange)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = ['pending', 'washing', 'drying', 'folding', 'ready_for_pickup', 'ready_for_delivery', 'completed', 'cancelled'];
        $statusLabels = array_map(fn ($status) => StatusBadge::label($status), $statuses);
        $statusValues = array_map(fn ($status) => (int) ($statusRows[$status] ?? 0), $statuses);

        $paymentMixRows = (clone $collections)
            ->selectRaw('payment_type, COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('payment_type')
            ->orderByDesc('total_amount')
            ->get();

        $topServices = JobOrderItem::query()
            ->join('job_orders', 'job_orders.id', '=', 'job_order_items.job_order_id')
            ->leftJoin('laundry_services', 'laundry_services.id', '=', 'job_order_items.laundry_service_id')
            ->whereNull('job_orders.deleted_at')
            ->where('job_orders.status', '!=', 'cancelled')
            ->when($branchId, fn ($query) => $query->where('job_orders.branch_id', $branchId))
            ->whereDate('job_orders.created_at', '>=', $dateFrom)
            ->whereDate('job_orders.created_at', '<=', $dateTo)
            ->groupByRaw('COALESCE(laundry_services.name, job_order_items.description)')
            ->orderByDesc('total_amount')
            ->limit(8)
            ->get([
                DB::raw('COALESCE(laundry_services.name, job_order_items.description) as label'),
                DB::raw('COALESCE(SUM(job_order_items.quantity), 0) as quantity'),
                DB::raw('COALESCE(SUM(job_order_items.total), 0) as total_amount'),
            ]);

        $topPresets = JobOrderItem::query()
            ->join('job_orders', 'job_orders.id', '=', 'job_order_items.job_order_id')
            ->join('service_presets', 'service_presets.id', '=', 'job_order_items.service_preset_id')
            ->whereNull('job_orders.deleted_at')
            ->where('job_orders.status', '!=', 'cancelled')
            ->when($branchId, fn ($query) => $query->where('job_orders.branch_id', $branchId))
            ->whereDate('job_orders.created_at', '>=', $dateFrom)
            ->whereDate('job_orders.created_at', '<=', $dateTo)
            ->groupBy('service_presets.id', 'service_presets.name')
            ->orderByDesc('total_amount')
            ->limit(8)
            ->get([
                'service_presets.name as label',
                DB::raw('COUNT(DISTINCT job_orders.id) as orders_count'),
                DB::raw('COALESCE(SUM(job_order_items.total), 0) as total_amount'),
            ]);

        $transactionRows = (clone $ordersInRange)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('transaction_type, COUNT(*) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $branchSales = Payment::query()
            ->join('branches', 'payments.branch_id', '=', 'branches.id')
            ->when($branchId, fn ($query) => $query->where('payments.branch_id', $branchId))
            ->whereDate('payments.paid_at', '>=', $dateFrom)
            ->whereDate('payments.paid_at', '<=', $dateTo)
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('total_amount')
            ->limit(8)
            ->get([
                'branches.name as label',
                DB::raw('COALESCE(SUM(payments.amount), 0) as total_amount'),
            ]);

        $recentOrders = (clone $orders)
            ->with(['customer', 'branch'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (JobOrder $order) => [
                'id' => $order->id,
                'number' => $order->job_order_number,
                'customer' => $order->customer?->name ?? 'Walk-in',
                'branch' => $order->branch?->name ?? 'N/A',
                'status' => StatusBadge::label($order->status),
                'status_badge' => StatusBadge::classes($order->status),
                'total' => $this->money($currency, (float) $order->total),
                'url' => route('admin.job-orders.show', $order),
            ])
            ->values();

        $trustedCustomers = Customer::query()
            ->with('branch')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas(
                'jobOrders',
                fn ($query) => $query->when($branchId, fn ($query) => $query->where('branch_id', $branchId)),
                '>=',
                10
            )
            ->withCount(['jobOrders as orders_count' => fn ($query) => $query
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))])
            ->withMax(['jobOrders as latest_order_at' => fn ($query) => $query
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))], 'created_at')
            ->orderByDesc('orders_count')
            ->orderByDesc('latest_order_at')
            ->limit(6)
            ->get()
            ->map(function (Customer $customer) use ($branchId) {
                $latestOrder = $customer->jobOrders()
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->latest()
                    ->first();

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone ?: 'No phone provided',
                    'branch' => $customer->branch?->name ?? 'N/A',
                    'orders_count' => number_format($customer->orders_count),
                    'latest_order' => $latestOrder?->created_at?->format('M d, Y') ?? 'N/A',
                    'status' => $latestOrder ? StatusBadge::label($latestOrder->status) : 'No orders',
                    'status_badge' => $latestOrder ? StatusBadge::classes($latestOrder->status) : StatusBadge::classes('pending'),
                ];
            })
            ->values();

        return [
            'currency' => $currency,
            'generated_at' => now()->format('M d, Y h:i:s A'),
            'stats' => [
                'sales' => $this->money($currency, $financial['sales_owned']),
                'collections' => $this->money($currency, $collectionsTotal),
                'cash_drawer' => $this->money($currency, $financial['expected_cash_drawer']),
                'gcash' => $this->money($currency, $financial['expected_gcash']),
                'expenses' => $this->money($currency, $financial['expenses_total']),
                'orders' => number_format($ordersCount),
                'open_orders' => number_format($openOrders),
                'ready_for_pickup' => number_format($readyForPickup),
                'ready_for_delivery' => number_format($readyForDelivery),
                'receivables' => $this->money($currency, $receivables),
                'low_stock' => number_format($lowStock),
                'accounts_payable' => $this->money($currency, $accountsPayable),
                'over_short' => $this->money($currency, $financial['over_short']),
            ],
            'charts' => [
                'sales' => [
                    'labels' => $salesLabels,
                    'values' => $salesValues,
                ],
                'status' => [
                    'labels' => $statusLabels,
                    'values' => $statusValues,
                ],
                'payment_mix' => [
                    'labels' => $paymentMixRows->map(fn ($row) => StatusBadge::label($row->payment_type))->values(),
                    'values' => $paymentMixRows->map(fn ($row) => round((float) $row->total_amount, 2))->values(),
                ],
                'top_services' => [
                    'labels' => $topServices->pluck('label')->values(),
                    'values' => $topServices->map(fn ($row) => round((float) $row->total_amount, 2))->values(),
                ],
                'top_presets' => [
                    'labels' => $topPresets->pluck('label')->values(),
                    'values' => $topPresets->map(fn ($row) => round((float) $row->total_amount, 2))->values(),
                ],
                'transaction_types' => [
                    'labels' => ['Walk-in / Drop Off', 'Delivery / Pick-up'],
                    'values' => [(int) ($transactionRows['walk_in'] ?? 0), (int) ($transactionRows['delivery'] ?? 0)],
                ],
                'branch_sales' => [
                    'labels' => $branchSales->pluck('label')->values(),
                    'values' => $branchSales->map(fn ($row) => round((float) $row->total_amount, 2))->values(),
                ],
                'financial_snapshot' => [
                    'labels' => ['Collections', 'Expenses', 'Receivables', 'Accounts Payable'],
                    'values' => [
                        round((float) $financial['physical_collections'], 2),
                        round((float) $financial['expenses_total'], 2),
                        round((float) $financial['unpaid_balance'], 2),
                        round((float) $financial['accounts_payable'], 2),
                    ],
                ],
            ],
            'top_services' => $topServices->map(fn ($row) => [
                'label' => $row->label,
                'quantity' => number_format((float) $row->quantity, 2),
                'amount' => $this->money($currency, (float) $row->total_amount),
            ])->values(),
            'top_presets' => $topPresets->map(fn ($row) => [
                'label' => $row->label,
                'orders_count' => number_format((int) $row->orders_count),
                'amount' => $this->money($currency, (float) $row->total_amount),
            ])->values(),
            'recent_orders' => $recentOrders,
            'trusted_customers' => $trustedCustomers,
        ];
    }

    private function assistantPresets(): array
    {
        return [
            'daily_sales' => 'Daily sales summary',
            'payment_mix' => 'Payment method mix',
            'expenses' => 'Expenses summary',
            'accounts_payable' => 'Accounts payable summary',
            'cash_drawer' => 'Expected cash drawer',
            'petty_cash' => 'Petty cash movement',
            'receivables' => 'Receivables risk',
            'unpaid_orders' => 'Unpaid job orders',
            'active_cycles' => 'Active laundry cycles',
            'ready_pickup' => 'Ready for pickup',
            'low_stock' => 'Low stock items',
            'top_customers' => 'Top customers',
            'branch_compare' => 'Branch comparison',
            'attendance_today' => 'Attendance today',
            'eod_tasks' => 'End-of-day tasks',
            'z_reading' => 'Latest Z Reading variance',
        ];
    }

    private function assistantAnswer(Request $request, string $preset, ?string $question = null): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);
        $branchId = $this->assistantBranchId($request);
        $currency = SystemSetting::current()->currency ?: 'PHP';
        $scope = $this->assistantScopeLabel($branchId);
        $period = Carbon::parse($dateFrom)->format('M d, Y').' to '.Carbon::parse($dateTo)->format('M d, Y');

        $orders = JobOrder::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        $payments = Payment::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo);

        $collections = Payment::query()
            ->when($branchId, fn ($query) => $query->where('collected_branch_id', $branchId))
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo);

        $expenses = BranchExpense::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo);

        $movements = MoneyMovement::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('movement_date', '>=', $dateFrom)
            ->whereDate('movement_date', '<=', $dateTo);

        $answer = match ($preset) {
            'daily_sales' => $this->salesAssistant($payments, $collections, $orders, $currency),
            'payment_mix' => $this->paymentMixAssistant($collections, $currency),
            'expenses' => $this->expenseAssistant($expenses, $currency),
            'accounts_payable' => $this->accountsPayableAssistant($branchId, $currency),
            'cash_drawer' => $this->cashDrawerAssistant($branchId, $dateFrom, $dateTo, $currency),
            'petty_cash' => $this->pettyCashAssistant($movements, $currency),
            'receivables' => $this->receivablesAssistant($branchId, $currency),
            'unpaid_orders' => $this->unpaidOrdersAssistant($branchId, $currency),
            'active_cycles' => $this->activeCyclesAssistant($branchId),
            'ready_pickup' => $this->readyPickupAssistant($branchId),
            'low_stock' => $this->lowStockAssistant($branchId),
            'top_customers' => $this->topCustomersAssistant($branchId, $dateFrom, $dateTo, $currency),
            'branch_compare' => $this->branchCompareAssistant($request, $dateFrom, $dateTo, $currency),
            'attendance_today' => $this->attendanceAssistant($branchId),
            'eod_tasks' => $this->dailyTaskAssistant($branchId),
            'z_reading' => $this->zReadingAssistant($branchId, $currency),
        };

        return $answer + [
            'scope' => $scope,
            'period' => $period,
            'preset' => $preset,
            'question' => $question,
            'generated_at' => now()->format('M d, Y h:i A'),
        ];
    }

    private function inferAssistantPreset(string $question): string
    {
        $question = str($question)->lower()->toString();

        return match (true) {
            str_contains($question, 'payment') || str_contains($question, 'gcash') || str_contains($question, 'bank') => 'payment_mix',
            str_contains($question, 'expense') || str_contains($question, 'cash advance') => 'expenses',
            str_contains($question, 'payable') || str_contains($question, 'owe owner') || str_contains($question, 'owner funding') => 'accounts_payable',
            str_contains($question, 'drawer') || str_contains($question, 'cash count') || str_contains($question, 'cash drawer') => 'cash_drawer',
            str_contains($question, 'petty') || str_contains($question, 'deposit') || str_contains($question, 'withdraw') => 'petty_cash',
            str_contains($question, 'receivable') || str_contains($question, 'balance') || str_contains($question, 'utang') => 'receivables',
            str_contains($question, 'unpaid') => 'unpaid_orders',
            str_contains($question, 'cycle') || str_contains($question, 'washing') || str_contains($question, 'drying') => 'active_cycles',
            str_contains($question, 'pickup') || str_contains($question, 'ready') => 'ready_pickup',
            str_contains($question, 'stock') || str_contains($question, 'inventory') => 'low_stock',
            str_contains($question, 'customer') || str_contains($question, 'top') => 'top_customers',
            str_contains($question, 'branch') || str_contains($question, 'compare') => 'branch_compare',
            str_contains($question, 'attendance') || str_contains($question, 'clock') => 'attendance_today',
            str_contains($question, 'task') || str_contains($question, 'cleaning') || str_contains($question, 'end of day') => 'eod_tasks',
            str_contains($question, 'z reading') || str_contains($question, 'over') || str_contains($question, 'short') => 'z_reading',
            default => 'daily_sales',
        };
    }

    private function salesAssistant($payments, $collections, $orders, string $currency): array
    {
        $sales = (float) (clone $payments)->sum('amount');
        $collectionsTotal = (float) (clone $collections)->sum('amount');
        $count = (clone $collections)->count();
        $ordersCount = (clone $orders)->count();

        return [
            'title' => 'Daily Sales Summary',
            'summary' => "Sales ownership is {$this->money($currency, $sales)}. Physical collections counted in this branch are {$this->money($currency, $collectionsTotal)} from {$count} payment(s), with {$ordersCount} job order(s) created in the selected period.",
            'metrics' => [
                ['label' => 'Sales Owned', 'value' => $this->money($currency, $sales)],
                ['label' => 'Physical Collections', 'value' => $this->money($currency, $collectionsTotal)],
                ['label' => 'Collection Count', 'value' => number_format($count)],
                ['label' => 'Job Orders', 'value' => number_format($ordersCount)],
            ],
        ];
    }

    private function paymentMixAssistant($payments, string $currency): array
    {
        $rows = (clone $payments)
            ->selectRaw('payment_type, COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as payments_count')
            ->groupBy('payment_type')
            ->orderByDesc('total_amount')
            ->get();
        $top = $rows->first();

        return [
            'title' => 'Physical Collection Mix',
            'summary' => $top ? StatusBadge::label($top->payment_type).' is the leading collected payment method at '.$this->money($currency, (float) $top->total_amount).'.' : 'No payments found for the selected period.',
            'metrics' => $rows->map(fn ($row) => [
                'label' => StatusBadge::label($row->payment_type).' ('.$row->payments_count.')',
                'value' => $this->money($currency, (float) $row->total_amount),
            ])->values()->all(),
        ];
    }

    private function expenseAssistant($expenses, string $currency): array
    {
        $total = (float) (clone $expenses)->sum('amount');
        $storeCash = (float) (clone $expenses)->where('paid_from', 'store_cash')->sum('amount');
        $owner = (float) (clone $expenses)->where('paid_from', 'owner')->sum('amount');

        return [
            'title' => 'Expenses Summary',
            'summary' => "Recorded {$this->money($currency, $total)} in expenses. New expense records are store-funded and affect the drawer; owner-paid totals are legacy records only.",
            'metrics' => [
                ['label' => 'Total Expenses', 'value' => $this->money($currency, $total)],
                ['label' => 'Store Cash', 'value' => $this->money($currency, $storeCash)],
                ['label' => 'Legacy Owner-Paid Records', 'value' => $this->money($currency, $owner)],
            ],
        ];
    }

    private function accountsPayableAssistant(?int $branchId, string $currency): array
    {
        $query = AccountsPayable::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $original = (float) (clone $query)->sum('original_amount');
        $paid = (float) (clone $query)->sum('paid_amount');
        $balance = (float) (clone $query)->sum('balance');
        $open = (clone $query)->where('balance', '>', 0)->count();

        return [
            'title' => 'Accounts Payable Summary',
            'summary' => "{$open} payable(s) remain open. Cash repayments reduce the branch drawer; cashless repayments do not.",
            'metrics' => [
                ['label' => 'Total Obligations', 'value' => $this->money($currency, $original)],
                ['label' => 'Repaid', 'value' => $this->money($currency, $paid)],
                ['label' => 'Outstanding', 'value' => $this->money($currency, $balance)],
            ],
        ];
    }

    private function cashDrawerAssistant(?int $branchId, string $dateFrom, string $dateTo, string $currency): array
    {
        $financial = FinancialReconciliation::forPeriod($branchId, $dateFrom, $dateTo);

        return [
            'title' => 'Expected Cash Drawer',
            'summary' => 'Expected drawer uses cash physically collected in this branch, minus store-cash expenses, plus deposits, minus withdrawals/remittances.',
            'metrics' => [
                ['label' => 'Cash Collected Here', 'value' => '+ '.$this->money($currency, $financial['cash_collections'])],
                ['label' => 'Cash Deposits / Owner Funding', 'value' => '+ '.$this->money($currency, $financial['cash_in'])],
                ['label' => 'Store-Cash Expenses', 'value' => '- '.$this->money($currency, $financial['store_cash_expenses'])],
                ['label' => 'Withdrawals / Repayments', 'value' => '- '.$this->money($currency, $financial['cash_out'])],
                ['label' => 'Expected Drawer', 'value' => $this->money($currency, $financial['expected_cash_drawer'])],
            ],
        ];
    }

    private function pettyCashAssistant($movements, string $currency): array
    {
        $cashIn = (float) (clone $movements)->where('direction', 'in')->sum('amount');
        $cashOut = (float) (clone $movements)->where('direction', 'out')->sum('amount');

        return [
            'title' => 'Petty Cash Movement',
            'summary' => 'Deposits increase branch cash; withdrawals reduce branch cash.',
            'metrics' => [
                ['label' => 'Deposits', 'value' => '+ '.$this->money($currency, $cashIn)],
                ['label' => 'Withdrawals', 'value' => '- '.$this->money($currency, $cashOut)],
                ['label' => 'Net Movement', 'value' => $this->money($currency, $cashIn - $cashOut)],
            ],
        ];
    }

    private function receivablesAssistant(?int $branchId, string $currency): array
    {
        $query = JobOrder::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('balance', '>', 0)->where('status', '!=', 'cancelled')->regularReceivable();
        $balance = (float) (clone $query)->sum('balance');
        $count = (clone $query)->count();

        return [
            'title' => 'Receivables Risk',
            'summary' => "There are {$count} job order(s) with remaining balance.",
            'metrics' => [
                ['label' => 'Open Receivables', 'value' => number_format($count)],
                ['label' => 'Total Balance', 'value' => $this->money($currency, $balance)],
            ],
        ];
    }

    private function unpaidOrdersAssistant(?int $branchId, string $currency): array
    {
        $query = JobOrder::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('balance', '>', 0)->where('status', '!=', 'cancelled')->regularReceivable()->latest();

        return [
            'title' => 'Unpaid Job Orders',
            'summary' => 'Newest unpaid job orders are listed below for collection follow-up.',
            'metrics' => $query->limit(5)->get()->map(fn (JobOrder $order) => [
                'label' => $order->job_order_number,
                'value' => $this->money($currency, (float) $order->balance),
            ])->values()->all(),
        ];
    }

    private function activeCyclesAssistant(?int $branchId): array
    {
        $rows = JobOrder::query()
            ->when($branchId, fn ($query) => $query->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhere(fn ($query) => $query
                    ->where('processing_branch_id', $branchId)
                    ->whereNotNull('production_accepted_at'))))
            ->whereIn('status', ['washing', 'drying', 'folding'])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'title' => 'Active Laundry Cycles',
            'summary' => 'Active cycle counts show current work in progress.',
            'metrics' => collect(['washing', 'drying', 'folding'])->map(fn ($status) => [
                'label' => StatusBadge::label($status),
                'value' => number_format((int) ($rows[$status] ?? 0)),
            ])->all(),
        ];
    }

    private function readyPickupAssistant(?int $branchId): array
    {
        $count = JobOrder::query()
            ->when($branchId, fn ($query) => $query->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhere('release_branch_id', $branchId)
                ->orWhere('current_branch_id', $branchId)))
            ->whereIn('status', ['ready_for_pickup', 'ready_for_delivery'])
            ->count();

        return [
            'title' => 'Ready for Pickup or Delivery',
            'summary' => "{$count} job order(s) are ready and should be released or followed up.",
            'metrics' => [['label' => 'Ready Orders', 'value' => number_format($count)]],
        ];
    }

    private function lowStockAssistant(?int $branchId): array
    {
        $items = Inventory::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->orderBy('quantity')
            ->limit(6)
            ->get();

        return [
            'title' => 'Low Stock Items',
            'summary' => $items->count().' item(s) are at or below reorder level.',
            'metrics' => $items->map(fn (Inventory $item) => [
                'label' => $item->name,
                'value' => number_format((float) $item->quantity, 2).' '.$item->unit,
            ])->values()->all(),
        ];
    }

    private function topCustomersAssistant(?int $branchId, string $dateFrom, string $dateTo, string $currency): array
    {
        $rows = JobOrder::query()
            ->join('customers', 'job_orders.customer_id', '=', 'customers.id')
            ->when($branchId, fn ($query) => $query->where('job_orders.branch_id', $branchId))
            ->whereDate('job_orders.created_at', '>=', $dateFrom)
            ->whereDate('job_orders.created_at', '<=', $dateTo)
            ->selectRaw('customers.name, COALESCE(SUM(job_orders.total), 0) as total_amount')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        return [
            'title' => 'Top Customers',
            'summary' => 'Top customers are ranked by job order total in the selected period.',
            'metrics' => $rows->map(fn ($row) => [
                'label' => $row->name,
                'value' => $this->money($currency, (float) $row->total_amount),
            ])->values()->all(),
        ];
    }

    private function branchCompareAssistant(Request $request, string $dateFrom, string $dateTo, string $currency): array
    {
        if (! $request->user()->canManageAllBranches()) {
            return $this->salesAssistant(
                Payment::query()->where('branch_id', $request->user()->branch_id)->whereDate('paid_at', '>=', $dateFrom)->whereDate('paid_at', '<=', $dateTo),
                Payment::query()->where('collected_branch_id', $request->user()->branch_id)->whereIn('payment_type', ['cash', 'gcash', 'bank'])->whereDate('paid_at', '>=', $dateFrom)->whereDate('paid_at', '<=', $dateTo),
                JobOrder::query()->where('branch_id', $request->user()->branch_id)->whereDate('created_at', '>=', $dateFrom)->whereDate('created_at', '<=', $dateTo),
                $currency
            );
        }

        $rows = Payment::query()
            ->join('branches', 'payments.branch_id', '=', 'branches.id')
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo)
            ->selectRaw('branches.name, COALESCE(SUM(payments.amount), 0) as total_amount')
            ->groupBy('branches.name')
            ->orderByDesc('total_amount')
            ->limit(6)
            ->get();

        return [
            'title' => 'Branch Comparison',
            'summary' => 'Branches are ranked by sales-owner payments in the selected period. Use Payment Audit or Z Reading for physical collections.',
            'metrics' => $rows->map(fn ($row) => [
                'label' => $row->name,
                'value' => $this->money($currency, (float) $row->total_amount),
            ])->values()->all(),
        ];
    }

    private function attendanceAssistant(?int $branchId): array
    {
        $records = EmployeeAttendanceRecord::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('work_date', today())
            ->get();
        $clockIns = $records->sum(fn (EmployeeAttendanceRecord $record) => count($record->clock_in ?? []));
        $clockOuts = $records->sum(fn (EmployeeAttendanceRecord $record) => count($record->clock_out ?? []));

        return [
            'title' => 'Attendance Today',
            'summary' => 'Attendance is based on employee kiosk clock-in and clock-out records for today.',
            'metrics' => [
                ['label' => 'Employees With Logs', 'value' => number_format($records->count())],
                ['label' => 'Clock Ins', 'value' => number_format($clockIns)],
                ['label' => 'Clock Outs', 'value' => number_format($clockOuts)],
            ],
        ];
    }

    private function dailyTaskAssistant(?int $branchId): array
    {
        $tasks = DailyTask::query()
            ->when($branchId, fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $branchId)))
            ->where('is_active', true)
            ->count();
        $completed = DailyTaskCompletion::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->whereDate('work_date', today())->count();

        return [
            'title' => 'End-of-Day Tasks',
            'summary' => 'Completions are counted for today only.',
            'metrics' => [
                ['label' => 'Required Active Tasks', 'value' => number_format($tasks)],
                ['label' => 'Completed Today', 'value' => number_format($completed)],
                ['label' => 'Remaining', 'value' => number_format(max(0, $tasks - $completed))],
            ],
        ];
    }

    private function zReadingAssistant(?int $branchId, string $currency): array
    {
        $reading = ZReading::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->latest('business_date')->latest()->first();

        return [
            'title' => 'Latest Z Reading Variance',
            'summary' => $reading ? 'Latest cash count variance from '.$reading->business_date?->format('M d, Y').'.' : 'No Z Reading has been submitted yet.',
            'metrics' => $reading ? [
                ['label' => 'Expected Total', 'value' => $this->money($currency, (float) $reading->expected_total_amount)],
                ['label' => 'Actual Total', 'value' => $this->money($currency, (float) $reading->actual_total_amount)],
                ['label' => 'Over / Short', 'value' => $this->money($currency, (float) $reading->over_short_amount)],
            ] : [],
        ];
    }

    private function assistantBranchId(Request $request): ?int
    {
        if (! $request->user()->canManageAllBranches()) {
            return $request->user()->branch_id;
        }

        return $request->filled('branch_id') ? (int) $request->branch_id : null;
    }

    private function assistantScopeLabel(?int $branchId): string
    {
        if (! $branchId) {
            return 'All branches';
        }

        return Branch::query()->whereKey($branchId)->value('name') ?: 'Selected branch';
    }

    private function branchId(Request $request): ?int
    {
        if (! $request->user()->isAdmin()) {
            return $request->user()->branch_id;
        }

        return $request->filled('branch_id') ? (int) $request->branch_id : null;
    }

    private function dateRange(Request $request): array
    {
        if ($request->filled('date_range')) {
            $parts = preg_split('/\s+to\s+/', $request->date_range);
            $from = $this->parseDate($parts[0] ?? null);
            $to = $this->parseDate($parts[1] ?? $parts[0] ?? null);

            return [$from, $to];
        }

        return [today()->toDateString(), today()->toDateString()];
    }

    private function dateRangeValue(Request $request): string
    {
        [$from, $to] = $this->dateRange($request);

        return $from.' to '.$to;
    }

    private function parseDate(?string $date): string
    {
        if (! $date) {
            return today()->toDateString();
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return today()->toDateString();
        }
    }

    private function money(string $currency, float $value): string
    {
        return $currency.' '.number_format($value, 2);
    }
}
