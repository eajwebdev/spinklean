<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\BranchExpense;
use App\Models\Customer;
use App\Models\CycleRecord;
use App\Models\InventoryMovement;
use App\Models\JobOrder;
use App\Models\JobOrderItem;
use App\Models\MoneyMovement;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deeper analytics for the dashboard: ratios, trends, aging, staff activity,
 * and audit signals. Date math is done in PHP so it behaves the same on
 * MySQL (production) and SQLite (tests).
 */
class DashboardAnalytics
{
    public function __construct(
        private readonly ?int $branchId,
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly string $currency,
        private readonly array $financial,
    ) {}

    public function build(bool $includeAudit): array
    {
        return [
            'ratios' => $this->ratios(),
            'orders_breakdown' => $this->ordersBreakdown(),
            'customers' => $this->customers(),
            'peak' => $this->peak(),
            'category_sales' => $this->categorySales(),
            'add_ons' => $this->addOns(),
            'trend_7d' => $this->sevenDayTrend(),
            'receivables_aging' => $this->receivablesAging(),
            'top_debtors' => $this->topDebtors(),
            'turnaround' => $this->turnaround(),
            'cycles' => $this->cycles(),
            'inventory_usage' => $this->inventoryUsage(),
            'money_movements' => $this->moneyMovements(),
            'audit' => $includeAudit ? $this->audit() : null,
        ];
    }

    // ---------------------------------------------------------------- sales

    private function ordersInRange()
    {
        return JobOrder::query()
            ->when($this->branchId, fn ($q) => $q->where('job_orders.branch_id', $this->branchId))
            ->whereDate('job_orders.created_at', '>=', $this->dateFrom)
            ->whereDate('job_orders.created_at', '<=', $this->dateTo);
    }

    private function ratios(): array
    {
        $totals = (clone $this->ordersInRange())->financiallyActive()
            ->selectRaw('COALESCE(SUM(total), 0) as billed, COALESCE(SUM(paid_amount), 0) as paid')
            ->first();
        $billed = (float) ($totals->billed ?? 0);
        $paid = (float) ($totals->paid ?? 0);
        $sales = (float) $this->financial['sales_owned'];
        $expenses = (float) $this->financial['expenses_total'];

        return [
            'collection_rate' => $this->percent($paid, $billed),
            'expense_ratio' => $this->percent($expenses, $sales),
            'net_margin' => $sales > 0 ? round((($sales - $expenses) / $sales) * 100, 1) : null,
            'collection_hint' => $this->money($paid).' paid of '.$this->money($billed).' billed',
            'expense_hint' => $this->money($expenses).' of '.$this->money($sales).' sales',
        ];
    }

    private function ordersBreakdown(): array
    {
        $orders = $this->ordersInRange();

        $active = (clone $orders)->financiallyActive();
        $paid = (clone $active)->where('balance', '<=', 0)->count();
        $partial = (clone $active)->where('balance', '>', 0)->where('paid_amount', '>', 0)->count();
        $unpaid = (clone $active)->where('balance', '>', 0)->where('paid_amount', '<=', 0)->count();

        $rush = (clone $active)->where('is_rush', true)
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as total_amount')->first();
        $cancelled = (clone $orders)->where('status', 'cancelled')
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as total_amount')->first();
        $discounted = (clone $active)->where('discount', '>', 0)->count();

        return [
            'paid' => number_format($paid),
            'partial' => number_format($partial),
            'unpaid' => number_format($unpaid),
            'rush_count' => number_format((int) ($rush->orders_count ?? 0)),
            'rush_amount' => $this->money((float) ($rush->total_amount ?? 0)),
            'cancelled_count' => number_format((int) ($cancelled->orders_count ?? 0)),
            'cancelled_amount' => $this->money((float) ($cancelled->total_amount ?? 0)),
            'discounted_count' => number_format($discounted),
            'chart' => [
                'labels' => ['Fully Paid', 'Partially Paid', 'Unpaid'],
                'values' => [$paid, $partial, $unpaid],
            ],
        ];
    }

    private function customers(): array
    {
        $served = (clone $this->ordersInRange())->financiallyActive()
            ->whereNotNull('customer_id')
            ->distinct()
            ->count('customer_id');

        $new = Customer::query()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->count();

        $returning = (clone $this->ordersInRange())->financiallyActive()
            ->whereNotNull('customer_id')
            ->whereExists(fn ($q) => $q->selectRaw(1)
                ->from('job_orders as earlier')
                ->whereColumn('earlier.customer_id', 'job_orders.customer_id')
                ->whereNull('earlier.deleted_at')
                ->whereDate('earlier.created_at', '<', $this->dateFrom))
            ->distinct()
            ->count('customer_id');

        return [
            'served' => number_format($served),
            'new' => number_format($new),
            'returning' => number_format($returning),
            'returning_rate' => $this->percent($returning, $served),
        ];
    }

    private function peak(): array
    {
        $payments = Payment::query()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereDate('paid_at', '>=', $this->dateFrom)
            ->whereDate('paid_at', '<=', $this->dateTo)
            ->get(['paid_at', 'amount']);

        $byHour = [];
        $byDay = [];
        foreach ($payments as $payment) {
            $hour = (int) $payment->paid_at->format('G');
            $day = $payment->paid_at->format('l');
            $byHour[$hour] = ($byHour[$hour] ?? 0) + (float) $payment->amount;
            $byDay[$day] = ($byDay[$day] ?? 0) + (float) $payment->amount;
        }

        arsort($byHour);
        arsort($byDay);
        $topHour = array_key_first($byHour);
        $topDay = array_key_first($byDay);

        return [
            'hour' => $topHour !== null ? Carbon::createFromTime($topHour)->format('g A').' – '.Carbon::createFromTime(($topHour + 1) % 24)->format('g A') : '—',
            'hour_amount' => $topHour !== null ? $this->money($byHour[$topHour]) : $this->money(0),
            'day' => $topDay ?? '—',
            'day_amount' => $topDay !== null ? $this->money($byDay[$topDay]) : $this->money(0),
        ];
    }

    private function itemsInRange()
    {
        return JobOrderItem::query()
            ->join('job_orders', 'job_orders.id', '=', 'job_order_items.job_order_id')
            ->leftJoin('laundry_services', 'laundry_services.id', '=', 'job_order_items.laundry_service_id')
            ->whereNull('job_orders.deleted_at')
            ->where('job_orders.status', '!=', 'cancelled')
            ->when($this->branchId, fn ($q) => $q->where('job_orders.branch_id', $this->branchId))
            ->whereDate('job_orders.created_at', '>=', $this->dateFrom)
            ->whereDate('job_orders.created_at', '<=', $this->dateTo);
    }

    private function categorySales(): array
    {
        $rows = (clone $this->itemsInRange())
            ->leftJoin('laundry_service_categories', 'laundry_service_categories.id', '=', 'laundry_services.service_category_id')
            ->groupBy('label')
            ->orderByDesc('total_amount')
            ->limit(8)
            ->get([
                DB::raw("COALESCE(laundry_service_categories.name, NULLIF(job_order_items.service_category, ''), 'Uncategorized') as label"),
                DB::raw('COUNT(*) as lines_count'),
                DB::raw('COALESCE(SUM(job_order_items.total), 0) as total_amount'),
            ]);

        $grand = max((float) $rows->sum('total_amount'), 0.0);

        return [
            'rows' => $rows->map(fn ($row) => [
                'label' => $row->label,
                'lines' => number_format((int) $row->lines_count),
                'amount' => $this->money((float) $row->total_amount),
                'share' => $this->percent((float) $row->total_amount, $grand),
            ])->values(),
            'chart' => [
                'labels' => $rows->pluck('label')->values(),
                'values' => $rows->map(fn ($row) => round((float) $row->total_amount, 2))->values(),
            ],
        ];
    }

    private function addOns(): array
    {
        $rows = (clone $this->itemsInRange())
            ->whereNotNull('laundry_services.add_on_inventory_id')
            ->groupBy('laundry_services.id', 'laundry_services.name')
            ->orderByDesc('total_amount')
            ->get([
                'laundry_services.name as label',
                DB::raw('COALESCE(SUM(job_order_items.quantity), 0) as quantity'),
                DB::raw('COALESCE(SUM(job_order_items.total), 0) as total_amount'),
            ]);

        return [
            'total' => $this->money((float) $rows->sum('total_amount')),
            'units' => number_format((float) $rows->sum('quantity')),
            'rows' => $rows->take(6)->map(fn ($row) => [
                'label' => $row->label,
                'quantity' => number_format((float) $row->quantity),
                'amount' => $this->money((float) $row->total_amount),
            ])->values(),
        ];
    }

    /** Always the 7 days ending on the selected end date, for context. */
    private function sevenDayTrend(): array
    {
        $end = Carbon::parse($this->dateTo);
        $start = $end->copy()->subDays(6);
        [$from, $to] = [$start->toDateString(), $end->toDateString()];

        $sales = Payment::query()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereDate('paid_at', '>=', $from)->whereDate('paid_at', '<=', $to)
            ->selectRaw('DATE(paid_at) as d, COALESCE(SUM(amount), 0) as total')
            ->groupBy('d')->pluck('total', 'd');

        $expenses = BranchExpense::query()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to)
            ->selectRaw('DATE(expense_date) as d, COALESCE(SUM(amount), 0) as total')
            ->groupBy('d')->pluck('total', 'd');

        $orders = JobOrder::query()->financiallyActive()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as total')
            ->groupBy('d')->pluck('total', 'd');

        $labels = $salesValues = $expenseValues = $orderValues = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            $key = $date->toDateString();
            $labels[] = $date->format('D M d');
            $salesValues[] = round((float) ($sales[$key] ?? 0), 2);
            $expenseValues[] = round((float) ($expenses[$key] ?? 0), 2);
            $orderValues[] = (int) ($orders[$key] ?? 0);
        }

        $days = max(count($salesValues), 1);

        return [
            'labels' => $labels,
            'sales' => $salesValues,
            'expenses' => $expenseValues,
            'orders' => $orderValues,
            'average_sales' => $this->money(array_sum($salesValues) / $days),
            'average_orders' => number_format(array_sum($orderValues) / $days, 1),
            'total_sales' => $this->money(array_sum($salesValues)),
            'total_expenses' => $this->money(array_sum($expenseValues)),
        ];
    }

    // ------------------------------------------------------------ balances

    private function openReceivables()
    {
        return JobOrder::query()
            ->when($this->branchId, fn ($q) => $q->where('job_orders.branch_id', $this->branchId))
            ->where('job_orders.balance', '>', 0)
            ->financiallyActive()
            ->regularReceivable();
    }

    private function receivablesAging(): array
    {
        $buckets = [
            '0–7 days' => [0, 7],
            '8–30 days' => [8, 30],
            '31–60 days' => [31, 60],
            'Over 60 days' => [61, PHP_INT_MAX],
        ];
        $result = array_fill_keys(array_keys($buckets), ['count' => 0, 'amount' => 0.0]);
        $today = today();

        (clone $this->openReceivables())->get(['job_orders.id', 'job_orders.created_at', 'job_orders.balance'])
            ->each(function (JobOrder $order) use (&$result, $buckets, $today) {
                $age = (int) $order->created_at->copy()->startOfDay()->diffInDays($today);
                foreach ($buckets as $label => [$min, $max]) {
                    if ($age >= $min && $age <= $max) {
                        $result[$label]['count']++;
                        $result[$label]['amount'] += (float) $order->balance;
                        break;
                    }
                }
            });

        return [
            'rows' => collect($result)->map(fn ($row, $label) => [
                'label' => $label,
                'count' => number_format($row['count']),
                'amount' => $this->money($row['amount']),
            ])->values(),
            'chart' => [
                'labels' => array_keys($result),
                'values' => array_map(fn ($row) => round($row['amount'], 2), array_values($result)),
            ],
        ];
    }

    private function topDebtors(): array
    {
        return (clone $this->openReceivables())
            ->join('customers', 'customers.id', '=', 'job_orders.customer_id')
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->orderByDesc('total_balance')
            ->limit(6)
            ->get([
                'customers.id',
                'customers.name',
                'customers.phone',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(job_orders.balance), 0) as total_balance'),
                DB::raw('MIN(job_orders.created_at) as oldest_at'),
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'phone' => $row->phone ?: '—',
                'orders' => number_format((int) $row->orders_count),
                'balance' => $this->money((float) $row->total_balance),
                'oldest' => $row->oldest_at ? Carbon::parse($row->oldest_at)->format('M d, Y') : '—',
            ])
            ->values()
            ->all();
    }

    // ---------------------------------------------------------- operations

    private function turnaround(): array
    {
        $orders = JobOrder::query()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereNotNull('completed_at')
            ->whereDate('completed_at', '>=', $this->dateFrom)
            ->whereDate('completed_at', '<=', $this->dateTo)
            ->get(['created_at', 'completed_at', 'production_completed_at']);

        $hours = $orders->map(fn (JobOrder $o) => max(0, $o->created_at->diffInMinutes($o->completed_at)) / 60);
        $productionHours = $orders->filter(fn (JobOrder $o) => $o->production_completed_at)
            ->map(fn (JobOrder $o) => max(0, $o->created_at->diffInMinutes($o->production_completed_at)) / 60);

        return [
            'completed' => number_format($orders->count()),
            'average' => $this->hours($hours->avg()),
            'fastest' => $this->hours($hours->min()),
            'slowest' => $this->hours($hours->max()),
            'production_average' => $this->hours($productionHours->avg()),
        ];
    }

    private function cycles(): array
    {
        $rows = CycleRecord::query()
            ->join('job_orders', 'job_orders.id', '=', 'cycle_records.job_order_id')
            ->when($this->branchId, fn ($q) => $q->where(fn ($q) => $q
                ->where('job_orders.branch_id', $this->branchId)
                ->orWhere('job_orders.processing_branch_id', $this->branchId)))
            ->whereDate('cycle_records.started_at', '>=', $this->dateFrom)
            ->whereDate('cycle_records.started_at', '<=', $this->dateTo)
            ->selectRaw('cycle_records.cycle_type, COUNT(*) as total')
            ->groupBy('cycle_records.cycle_type')
            ->pluck('total', 'cycle_type');

        $labels = ['wash' => 'Wash', 'dry' => 'Dry', 'iron' => 'Iron', 'fold' => 'Fold'];
        $types = collect($labels)->keys()->merge($rows->keys())->unique()->values();

        return [
            'total' => number_format((int) $rows->sum()),
            'chart' => [
                'labels' => $types->map(fn ($t) => $labels[$t] ?? ucfirst((string) $t))->values(),
                'values' => $types->map(fn ($t) => (int) ($rows[$t] ?? 0))->values(),
            ],
        ];
    }

    private function inventoryUsage(): array
    {
        return InventoryMovement::query()
            ->join('inventories', 'inventories.id', '=', 'inventory_movements.inventory_id')
            ->when($this->branchId, fn ($q) => $q->where('inventories.branch_id', $this->branchId))
            ->where('inventory_movements.movement_type', 'out')
            ->whereDate('inventory_movements.created_at', '>=', $this->dateFrom)
            ->whereDate('inventory_movements.created_at', '<=', $this->dateTo)
            ->groupBy('inventories.id', 'inventories.name', 'inventories.unit', 'inventories.unit_cost')
            ->orderByDesc('used')
            ->limit(8)
            ->get([
                'inventories.name',
                'inventories.unit',
                DB::raw('COALESCE(SUM(ABS(inventory_movements.quantity)), 0) as used'),
                DB::raw('COALESCE(SUM(ABS(inventory_movements.quantity)), 0) * COALESCE(inventories.unit_cost, 0) as cost'),
            ])
            ->map(fn ($row) => [
                'name' => $row->name,
                'used' => rtrim(rtrim(number_format((float) $row->used, 2), '0'), '.').' '.$row->unit,
                'cost' => $this->money((float) $row->cost),
            ])
            ->values()
            ->all();
    }

    private function moneyMovements(): array
    {
        return MoneyMovement::query()
            ->with(['branch', 'recorder'])
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereDate('movement_date', '>=', $this->dateFrom)
            ->whereDate('movement_date', '<=', $this->dateTo)
            ->latest('movement_date')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (MoneyMovement $m) => [
                'id' => $m->id,
                'date' => $m->movement_date?->format('M d, Y') ?? '',
                'direction' => $m->direction,
                'label' => $m->direction === 'in' ? 'Deposit' : 'Withdrawal',
                'description' => $m->description ?: ($m->reference_no ?: '—'),
                'by' => $m->recorder?->name ?? '—',
                'amount' => $this->money((float) $m->amount),
            ])
            ->values()
            ->all();
    }

    // --------------------------------------------------------------- audit

    private function audit(): array
    {
        $staff = $this->staffActivity();

        $logs = ActivityLog::query()
            ->with('user')
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo);

        $deletedPayments = (clone $logs)->where('action', 'payment_deleted')->get();
        $deletedPaymentsAmount = $deletedPayments->sum(fn (ActivityLog $log) => (float) ($log->properties['amount'] ?? 0));

        $counts = (clone $logs)
            ->whereIn('action', ['job_order_cancelled', 'job_order_deleted', 'payment_deleted', 'inventory_movement_recorded', 'cycle_removed', 'accounts_payable_deleted'])
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action');

        $discounts = (clone $this->ordersInRange())->financiallyActive()->where('discount', '>', 0)
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(discount), 0) as s')->first();

        $flags = [
            ['label' => 'Cancelled job orders', 'value' => number_format((int) ($counts['job_order_cancelled'] ?? 0)), 'tone' => ($counts['job_order_cancelled'] ?? 0) > 0 ? 'warn' : 'ok'],
            ['label' => 'Deleted job orders', 'value' => number_format((int) ($counts['job_order_deleted'] ?? 0)), 'tone' => ($counts['job_order_deleted'] ?? 0) > 0 ? 'bad' : 'ok'],
            ['label' => 'Deleted payments', 'value' => number_format($deletedPayments->count()).' · '.$this->money($deletedPaymentsAmount), 'tone' => $deletedPayments->isNotEmpty() ? 'bad' : 'ok'],
            ['label' => 'Orders with discount', 'value' => number_format((int) ($discounts->c ?? 0)).' · '.$this->money((float) ($discounts->s ?? 0)), 'tone' => ($discounts->c ?? 0) > 0 ? 'warn' : 'ok'],
            ['label' => 'Manual stock adjustments', 'value' => number_format((int) ($counts['inventory_movement_recorded'] ?? 0)), 'tone' => 'info'],
            ['label' => 'Removed cycles', 'value' => number_format((int) ($counts['cycle_removed'] ?? 0)), 'tone' => ($counts['cycle_removed'] ?? 0) > 0 ? 'warn' : 'ok'],
            ['label' => 'Z Reading over / short', 'value' => $this->money((float) $this->financial['over_short']), 'tone' => (float) $this->financial['over_short'] < 0 ? 'bad' : ((float) $this->financial['over_short'] > 0 ? 'warn' : 'ok')],
        ];

        $activity = (clone $logs)->latest('id')->limit(15)->get()->map(fn (ActivityLog $log) => [
            'id' => $log->id,
            'time' => $log->created_at?->format($this->dateFrom === $this->dateTo ? 'h:i A' : 'M d, h:i A') ?? '',
            'user' => $log->user?->name ?? 'System',
            'action' => ucfirst(str_replace('_', ' ', $log->action)),
            'detail' => $this->activityDetail($log),
            'tone' => match (true) {
                str_contains($log->action, 'deleted'), str_contains($log->action, 'removed') => 'bad',
                str_contains($log->action, 'cancelled') => 'warn',
                default => 'info',
            },
        ])->values()->all();

        return ['staff' => $staff, 'flags' => $flags, 'activity' => $activity];
    }

    private function staffActivity(): array
    {
        $orders = (clone $this->ordersInRange())
            ->whereNotNull('created_by')
            ->selectRaw("created_by as user_id,
                SUM(CASE WHEN status != 'cancelled' THEN 1 ELSE 0 END) as orders_count,
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END), 0) as billed,
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN discount ELSE 0 END), 0) as discounts,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->groupBy('created_by')
            ->get()
            ->keyBy('user_id');

        $payments = Payment::query()
            ->when($this->branchId, fn ($q) => $q->where('collected_branch_id', $this->branchId))
            ->whereNotNull('received_by')
            ->whereDate('paid_at', '>=', $this->dateFrom)
            ->whereDate('paid_at', '<=', $this->dateTo)
            ->selectRaw('received_by as user_id, COUNT(*) as payments_count, COALESCE(SUM(amount), 0) as collected')
            ->groupBy('received_by')
            ->get()
            ->keyBy('user_id');

        $userIds = $orders->keys()->merge($payments->keys())->unique()->filter();
        $users = User::query()->whereIn('id', $userIds)->get(['id', 'name', 'role'])->keyBy('id');

        return $userIds
            ->map(function ($id) use ($orders, $payments, $users) {
                $o = $orders->get($id);
                $p = $payments->get($id);

                return [
                    'id' => (int) $id,
                    'name' => $users->get($id)?->name ?? 'Unknown user',
                    'role' => ucwords(str_replace('_', ' ', (string) $users->get($id)?->role)),
                    'orders' => (int) ($o->orders_count ?? 0),
                    'billed_raw' => (float) ($o->billed ?? 0),
                    'billed' => $this->money((float) ($o->billed ?? 0)),
                    'discounts' => $this->money((float) ($o->discounts ?? 0)),
                    'cancelled' => (int) ($o->cancelled ?? 0),
                    'payments' => (int) ($p->payments_count ?? 0),
                    'collected_raw' => (float) ($p->collected ?? 0),
                    'collected' => $this->money((float) ($p->collected ?? 0)),
                ];
            })
            ->sortByDesc(fn ($row) => $row['collected_raw'] + $row['billed_raw'])
            ->values()
            ->all();
    }

    private function activityDetail(ActivityLog $log): string
    {
        $p = $log->properties ?? [];

        $parts = array_filter([
            $p['job_order_number'] ?? null,
            $p['payment_number'] ?? null,
            $p['name'] ?? null,
            isset($p['amount']) && is_numeric($p['amount']) ? $this->money((float) $p['amount']) : null,
            $p['status'] ?? null,
        ]);

        return $parts ? implode(' · ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $parts)) : '';
    }

    // ------------------------------------------------------------- helpers

    private function percent(float $part, float $whole): ?float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : null;
    }

    private function hours(?float $hours): string
    {
        if ($hours === null) {
            return '—';
        }

        return $hours >= 24
            ? number_format($hours / 24, 1).' days'
            : number_format($hours, 1).' hrs';
    }

    private function money(float $value): string
    {
        return $this->currency.' '.number_format($value, 2);
    }
}
