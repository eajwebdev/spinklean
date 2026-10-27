<?php

namespace App\Support;

use App\Models\PoTransactionPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Money received from PO (corporate) customers.
 *
 * PO laundry is billed on a Statement of Account and usually paid once a month for many job
 * orders together. It counts as sales on the day the customer pays, for the branch that owns
 * the PO, alongside regular payments. Cash goes to the drawer, GCash to GCash, and bank
 * transfers and cheques (which are deposited) to bank.
 */
class PoCollections
{
    public const BUCKETS = ['cash' => 'cash', 'gcash' => 'gcash', 'bank' => 'bank', 'cheque' => 'bank'];

    public static function bucket(?string $method): string
    {
        return self::BUCKETS[$method] ?? 'bank';
    }

    /**
     * PO payments received in a period. Payments on canceled job orders are left out.
     */
    public static function query(?int $branchId, string $dateFrom, string $dateTo): Builder
    {
        return PoTransactionPayment::query()
            ->whereHas('poTransaction')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo);
    }

    /**
     * @return array{cash: float, gcash: float, bank: float, cheque: float, total: float, count: int}
     */
    public static function totals(?int $branchId, string $dateFrom, string $dateTo): array
    {
        $totals = ['cash' => 0.0, 'gcash' => 0.0, 'bank' => 0.0, 'cheque' => 0.0, 'total' => 0.0, 'count' => 0];

        $rows = self::query($branchId, $dateFrom, $dateTo)
            ->selectRaw('payment_method, COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as payments_count')
            ->groupBy('payment_method')
            ->get();

        foreach ($rows as $row) {
            $amount = (float) $row->total_amount;
            $totals[self::bucket($row->payment_method)] += $amount;
            $totals['total'] += $amount;
            $totals['count'] += (int) $row->payments_count;

            if ($row->payment_method === 'cheque') {
                $totals['cheque'] += $amount;
            }
        }

        return array_map(fn ($value) => is_float($value) ? round($value, 2) : $value, $totals);
    }

    /**
     * PO payments as lines for the Z Reading "previous payments" list.
     */
    public static function paymentItems(?int $branchId, string $dateFrom, string $dateTo): array
    {
        return self::query($branchId, $dateFrom, $dateTo)
            ->with(['customer:id,name', 'poTransaction:id,po_number'])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get()
            ->map(fn (PoTransactionPayment $payment) => [
                'paid_at' => $payment->paid_at?->toDateTimeString(),
                'payment_number' => $payment->payment_number,
                'job_order_number' => 'PO '.$payment->poTransaction?->po_number,
                'customer_name' => $payment->customer?->name,
                'type' => self::bucket($payment->payment_method),
                'reference_no' => trim(($payment->payment_method === 'cheque' ? 'Cheque ' : '').($payment->reference_no ?? '')),
                'amount' => round((float) $payment->amount, 2),
                'is_po' => true,
            ])
            ->all();
    }

    /**
     * What PO customers owe right now: delivered but unpaid, and not yet delivered (not billed yet).
     *
     * @return array{delivered_unpaid: float, not_yet_delivered: float}
     */
    public static function balances(?int $branchId = null, ?int $customerId = null): array
    {
        $rows = self::purchaseOrderRows($branchId, $customerId);

        return [
            'delivered_unpaid' => round((float) $rows->sum('delivered_unpaid'), 2),
            'not_yet_delivered' => round((float) $rows->sum('not_yet_delivered'), 2),
        ];
    }

    /**
     * The same balances per PO customer, largest amount owed first.
     */
    public static function balancesByCustomer(?int $branchId = null): Collection
    {
        return self::purchaseOrderRows($branchId)
            ->groupBy('customer_id')
            ->map(fn (Collection $rows, $customerId) => [
                'customer_id' => (int) $customerId,
                'delivered_unpaid' => round((float) $rows->sum('delivered_unpaid'), 2),
                'not_yet_delivered' => round((float) $rows->sum('not_yet_delivered'), 2),
                'open_orders' => $rows->filter(fn ($row) => $row->delivered_unpaid > 0 || $row->not_yet_delivered > 0)->count(),
            ])
            ->filter(fn (array $row) => $row['delivered_unpaid'] > 0 || $row['not_yet_delivered'] > 0)
            ->sortByDesc(fn (array $row) => $row['delivered_unpaid'] + $row['not_yet_delivered'])
            ->values();
    }

    private static function purchaseOrderRows(?int $branchId = null, ?int $customerId = null): Collection
    {
        $delivered = DB::table('po_delivery_items')
            ->selectRaw('po_transaction_id, SUM(amount) as delivered_amount')
            ->groupBy('po_transaction_id');

        return DB::table('po_transactions')
            ->join('job_orders', 'job_orders.id', '=', 'po_transactions.job_order_id')
            ->leftJoinSub($delivered, 'delivered', 'delivered.po_transaction_id', '=', 'po_transactions.id')
            ->whereNull('job_orders.deleted_at')
            ->where('job_orders.status', '!=', 'cancelled')
            ->when($branchId, fn ($query) => $query->where('po_transactions.branch_id', $branchId))
            ->when($customerId, fn ($query) => $query->where('po_transactions.customer_id', $customerId))
            ->get([
                'po_transactions.customer_id',
                'po_transactions.amount',
                'po_transactions.paid_amount',
                DB::raw('COALESCE(delivered.delivered_amount, 0) as delivered_amount'),
            ])
            ->map(function ($row) {
                $row->delivered_unpaid = max(min((float) $row->delivered_amount, (float) $row->amount) - (float) $row->paid_amount, 0);
                $row->not_yet_delivered = max((float) $row->amount - (float) $row->delivered_amount, 0);

                return $row;
            });
    }
}
