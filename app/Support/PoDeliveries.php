<?php

namespace App\Support;

use App\Models\JobOrder;
use App\Models\PoDelivery;
use App\Models\PoTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Delivery Receipts (DRs) for PO job orders.
 *
 * A PO job order can be delivered in parts: each DR records how many pieces of each item went
 * out on one trip, and a DR can carry pieces from several of the customer's job orders. The
 * Statement of Account bills each DR on the day it was delivered. A job order is completed once
 * everything on it has been delivered.
 */
class PoDeliveries
{
    private const EPSILON = 0.001;

    /**
     * Items are matched by description and price, because editing a job order recreates its items.
     */
    public static function lineKey(string $description, float|string $unitPrice): string
    {
        return substr(md5(Str::lower(trim($description)).'|'.number_format((float) $unitPrice, 2, '.', '')), 0, 12);
    }

    /**
     * Ordered, delivered and remaining quantity for each item of a PO job order.
     *
     * @return Collection<string, array{key: string, description: string, service_category: ?string, unit_price: float, ordered: float, delivered: float, remaining: float}>
     */
    public static function remainingLines(PoTransaction $po): Collection
    {
        $po->loadMissing(['jobOrder.items', 'deliveryItems']);

        $delivered = $po->deliveryItems
            ->groupBy(fn ($item) => self::lineKey($item->description, $item->unit_price))
            ->map(fn (Collection $items) => (float) $items->sum('quantity'));

        return ($po->jobOrder?->items ?? collect())
            ->groupBy(fn ($item) => self::lineKey($item->description, $item->unit_price))
            ->map(function (Collection $items, string $key) use ($delivered) {
                $first = $items->first();
                $ordered = round((float) $items->sum('quantity'), 2);
                $done = round((float) ($delivered[$key] ?? 0), 2);

                return [
                    'key' => $key,
                    'description' => $first->description,
                    'service_category' => $first->service_category,
                    'unit_price' => (float) $first->unit_price,
                    'ordered' => $ordered,
                    'delivered' => $done,
                    'remaining' => max(round($ordered - $done, 2), 0),
                ];
            });
    }

    public static function isFullyDelivered(PoTransaction $po): bool
    {
        return self::remainingLines($po)->every(fn (array $line) => $line['remaining'] <= self::EPSILON);
    }

    /**
     * The customer's PO job orders that still have pieces to deliver.
     */
    public static function openPurchaseOrders(int $customerId, ?int $branchId = null): Collection
    {
        return PoTransaction::query()
            ->with(['jobOrder.items', 'deliveryItems'])
            ->where('customer_id', $customerId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->reject(fn (PoTransaction $po) => self::isFullyDelivered($po))
            ->values();
    }

    /**
     * Billed amount of everything delivered for a PO, optionally only up to a date.
     */
    public static function deliveredAmount(PoTransaction $po, ?string $upTo = null): float
    {
        $po->loadMissing('deliveryItems.delivery');

        return round((float) $po->deliveryItems
            ->filter(fn ($item) => ! $upTo || $item->delivery?->delivered_on?->toDateString() <= $upTo)
            ->sum('amount'), 2);
    }

    /**
     * What the customer owes now for a PO: delivered, but not yet paid.
     */
    public static function dueAmount(PoTransaction $po, ?string $upTo = null): float
    {
        $delivered = min(self::deliveredAmount($po, $upTo), (float) $po->amount);

        return max(round($delivered - (float) $po->paid_amount, 2), 0);
    }

    public static function nextDrNumber(int $branchId): string
    {
        $last = PoDelivery::query()
            ->where('branch_id', $branchId)
            ->pluck('dr_number')
            ->filter(fn ($number) => ctype_digit((string) $number))
            ->map(fn ($number) => (int) $number)
            ->max() ?? 0;

        return str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Record one DR.
     *
     * @param  array<int|string, array<string, float|string>>  $quantities  [po_transaction_id => [line key => quantity delivered]]
     */
    public static function record(
        int $branchId,
        int $customerId,
        array $quantities,
        string $deliveredOn,
        ?string $drNumber = null,
        ?string $notes = null,
        ?int $userId = null,
    ): PoDelivery {
        return DB::transaction(function () use ($branchId, $customerId, $quantities, $deliveredOn, $drNumber, $notes, $userId) {
            $purchaseOrders = PoTransaction::query()
                ->with(['jobOrder.items', 'deliveryItems'])
                ->where('branch_id', $branchId)
                ->where('customer_id', $customerId)
                ->whereIn('id', array_map('intval', array_keys($quantities)))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $linesByPo = [];

            foreach ($quantities as $poId => $byKey) {
                $po = $purchaseOrders->get((int) $poId);

                if (! $po) {
                    throw ValidationException::withMessages(['quantities' => 'One of the job orders is not an open PO of this customer.']);
                }

                $remaining = self::remainingLines($po);

                foreach ((array) $byKey as $key => $quantity) {
                    $quantity = round((float) $quantity, 2);

                    if ($quantity <= 0) {
                        continue;
                    }

                    $line = $remaining->get($key);

                    if (! $line) {
                        throw ValidationException::withMessages(["quantities.{$poId}.{$key}" => 'This item is no longer on the job order.']);
                    }

                    if ($quantity > $line['remaining'] + self::EPSILON) {
                        throw ValidationException::withMessages([
                            "quantities.{$poId}.{$key}" => 'Only '.self::number($line['remaining']).' '.$line['description'].' left to deliver for '.$po->jobOrder?->job_order_number.'.',
                        ]);
                    }

                    $linesByPo[$po->id][] = [...$line, 'quantity' => $quantity];
                }
            }

            if ($linesByPo === []) {
                throw ValidationException::withMessages(['quantities' => 'Enter how many pieces go out on this DR.']);
            }

            $drNumber = filled($drNumber) ? trim($drNumber) : self::nextDrNumber($branchId);

            if (PoDelivery::query()->where('branch_id', $branchId)->where('dr_number', $drNumber)->exists()) {
                throw ValidationException::withMessages(['dr_number' => "DR #{$drNumber} is already used."]);
            }

            $delivery = PoDelivery::query()->create([
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'dr_number' => $drNumber,
                'delivered_on' => $deliveredOn,
                'notes' => filled($notes) ? $notes : null,
                'created_by' => $userId,
            ]);

            foreach ($linesByPo as $poId => $lines) {
                $po = $purchaseOrders->get($poId);
                $itemsTotal = (float) $po->jobOrder->items->sum('total');
                // Lines are billed at the order's price after its discount and tax.
                $ratio = $itemsTotal > 0 ? (float) $po->amount / $itemsTotal : 0;
                $rows = collect($lines)->map(function (array $line) use ($ratio) {
                    $total = round($line['quantity'] * $line['unit_price'], 2);

                    return [...$line, 'total' => $total, 'amount' => round($total * $ratio, 2)];
                });

                $completesOrder = self::remainingLines($po)->every(function (array $line) use ($rows) {
                    $sent = (float) $rows->where('key', $line['key'])->sum('quantity');

                    return $line['remaining'] - $sent <= self::EPSILON;
                });

                if ($completesOrder) {
                    // The last DR absorbs rounding so the DRs add up to exactly the PO amount.
                    $alreadyBilled = (float) $po->deliveryItems->sum('amount');
                    $lastIndex = $rows->keys()->last();
                    $othersOnThisDr = (float) $rows->except($lastIndex)->sum('amount');
                    $rows[$lastIndex] = [...$rows[$lastIndex], 'amount' => round((float) $po->amount - $alreadyBilled - $othersOnThisDr, 2)];
                }

                foreach ($rows as $row) {
                    $delivery->items()->create([
                        'po_transaction_id' => $po->id,
                        'job_order_id' => $po->job_order_id,
                        'description' => $row['description'],
                        'service_category' => $row['service_category'],
                        'unit_price' => $row['unit_price'],
                        'quantity' => $row['quantity'],
                        'total' => $row['total'],
                        'amount' => $row['amount'],
                    ]);
                }
            }

            $delivery->update(['amount' => round((float) $delivery->items()->sum('amount'), 2)]);

            foreach (array_keys($linesByPo) as $poId) {
                self::sync($purchaseOrders->get($poId));
            }

            return $delivery;
        });
    }

    /**
     * Put everything not yet delivered on a final DR dated today. Used when a PO job order is
     * completed or released without the pieces having been put on DRs.
     */
    public static function deliverRemaining(JobOrder $order, ?int $userId = null): ?PoDelivery
    {
        $po = PoTransaction::query()->with(['jobOrder.items', 'deliveryItems'])->where('job_order_id', $order->id)->first();

        if (! $po) {
            return null;
        }

        $remaining = self::remainingLines($po)->filter(fn (array $line) => $line['remaining'] > self::EPSILON);

        if ($remaining->isEmpty()) {
            return null;
        }

        return self::record(
            (int) $po->branch_id,
            (int) $po->customer_id,
            [$po->id => $remaining->mapWithKeys(fn (array $line) => [$line['key'] => $line['remaining']])->all()],
            today()->toDateString(),
            null,
            'Remaining pieces delivered when '.$order->job_order_number.' was completed.',
            $userId ?? auth()->id(),
        );
    }

    /**
     * Void a DR. Its pieces go back to "not yet delivered" and a job order it had completed is
     * reopened as Ready for Delivery.
     */
    public static function void(PoDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $poIds = $delivery->items()->pluck('po_transaction_id')->unique();
            $delivery->delete();

            PoTransaction::query()->whereIn('id', $poIds)->get()->each(fn (PoTransaction $po) => self::sync($po, reopen: true));
        });
    }

    /**
     * Refuse job order edits that would drop or reduce items already delivered on a DR.
     *
     * @param  array<int, array{description: string, unit_price: float|string, quantity: float|string}>  $items
     */
    public static function assertItemsCoverDeliveries(JobOrder $order, array $items): void
    {
        $po = PoTransaction::query()->with('deliveryItems')->where('job_order_id', $order->id)->first();

        if (! $po || $po->deliveryItems->isEmpty()) {
            return;
        }

        $ordered = collect($items)
            ->groupBy(fn (array $item) => self::lineKey($item['description'], $item['unit_price']))
            ->map(fn (Collection $group) => (float) $group->sum('quantity'));

        foreach ($po->deliveryItems->groupBy(fn ($item) => self::lineKey($item->description, $item->unit_price)) as $key => $delivered) {
            $deliveredQuantity = (float) $delivered->sum('quantity');

            if ($deliveredQuantity > (float) ($ordered[$key] ?? 0) + self::EPSILON) {
                throw ValidationException::withMessages([
                    'items' => self::number($deliveredQuantity).' '.$delivered->first()->description.' (₱'.number_format((float) $delivered->first()->unit_price, 2).') were already delivered on a DR. Void that DR first to remove or reduce this item.',
                ]);
            }
        }
    }

    public static function hasDeliveries(JobOrder $order): bool
    {
        return PoTransaction::query()->where('job_order_id', $order->id)->whereHas('deliveryItems')->exists();
    }

    /**
     * Keep the PO's delivery summary in step with its DRs, and complete (or reopen) the job order.
     */
    private static function sync(PoTransaction $po, bool $reopen = false): void
    {
        $po->unsetRelation('deliveryItems');
        $po->load(['deliveryItems.delivery', 'jobOrder.items']);

        $deliveries = $po->deliveryItems->pluck('delivery')->filter()->unique('id')->sortBy(fn ($delivery) => $delivery->delivered_on->toDateString().'-'.$delivery->id);

        $po->update([
            'date_delivered' => $deliveries->last()?->delivered_on,
            'dr_number' => Str::limit($deliveries->pluck('dr_number')->implode(', '), 100, '') ?: null,
        ]);

        $order = $po->jobOrder;

        if (! $order || $order->status === 'cancelled') {
            return;
        }

        $fullyDelivered = $deliveries->isNotEmpty() && self::isFullyDelivered($po);

        if ($fullyDelivered && $order->status !== 'completed') {
            $order->endActiveCycles();
            $order->update(['status' => 'completed', 'completed_at' => now(), 'released_at' => $order->released_at ?: now()]);
        } elseif ($reopen && ! $fullyDelivered && $order->status === 'completed') {
            $order->update(['status' => 'ready_for_delivery', 'completed_at' => null, 'released_at' => null]);
        }
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
