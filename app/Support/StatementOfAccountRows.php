<?php

namespace App\Support;

use App\Models\JobOrderItem;
use App\Models\PoTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the day-by-day lines of a PO Statement of Account:
 * one row per calendar day with total loads, price per load, dry extension, delivery and amount.
 */
class StatementOfAccountRows
{
    /**
     * @param  Collection<int, PoTransaction>  $transactions  with jobOrder.items loaded
     */
    public static function build(Collection $transactions, string $dateFrom, string $dateTo): array
    {
        $byDate = $transactions->groupBy(fn (PoTransaction $po) => $po->transaction_date->toDateString());
        $days = [];

        foreach (Carbon::parse($dateFrom)->daysUntil(Carbon::parse($dateTo)) as $day) {
            $date = $day->toDateString();
            $loads = 0.0;
            $loadAmount = 0.0;
            $prices = [];
            $dryExtension = 0.0;
            $delivery = 0.0;
            $amount = 0.0;

            foreach ($byDate->get($date, collect()) as $po) {
                $breakdown = self::orderBreakdown($po->jobOrder?->items ?? collect());
                $loads += $breakdown['loads'];
                $loadAmount += $breakdown['load_amount'];
                $prices = array_merge($prices, $breakdown['prices']);
                $dryExtension += $breakdown['dry_extension'];
                $delivery += $breakdown['delivery'];
                // The PO amount is what the customer owes (it already reflects any discount).
                $amount += (float) $po->amount;
            }

            $days[] = [
                'date' => $day->copy(),
                'has_orders' => $byDate->has($date),
                'loads' => round($loads, 2),
                'prices' => array_values(array_unique(array_map(fn ($price) => round($price, 2), $prices))),
                'dry_extension' => round($dryExtension, 2),
                'delivery' => round($delivery, 2),
                'amount' => round($amount, 2),
            ];
        }

        $usualPrice = collect($days)->flatMap(fn (array $day) => $day['prices'])
            ->filter(fn ($price) => $price > 0)
            ->countBy(fn ($price) => number_format($price, 2, '.', ''))
            ->sortDesc()
            ->keys()
            ->first();

        return [
            'days' => $days,
            'usual_price' => $usualPrice !== null ? (float) $usualPrice : null,
            'has_delivery' => collect($days)->contains(fn (array $day) => $day['delivery'] > 0),
            'total' => round(collect($days)->sum('amount'), 2),
        ];
    }

    /**
     * Split one job order's items into loads, dry extension and delivery.
     *
     * - "Delivery" category items are delivery charges.
     * - "Dry Extension" items are dry extension charges.
     * - Small/big machine items (wash, dry, fold, detergent, fabcon) form one load per wash.
     * - Any other item (e.g. a customer package such as "Laybare") is a load per quantity.
     */
    public static function orderBreakdown(Collection $items): array
    {
        $loads = 0.0;
        $loadAmount = 0.0;
        $prices = [];
        $dryExtension = 0.0;
        $delivery = 0.0;
        $machineGroups = [];

        foreach ($items as $item) {
            /** @var JobOrderItem $item */
            $quantity = (float) $item->quantity;
            $lineTotal = (float) ($item->total ?? $quantity * (float) $item->unit_price);
            $category = Str::lower((string) $item->service_category);
            $description = Str::lower((string) $item->description);

            if ($category === 'delivery' || Str::startsWith($description, 'delivery')) {
                $delivery += $lineTotal;
            } elseif (Str::contains($description, 'dry extension')) {
                $dryExtension += $lineTotal;
            } elseif (in_array($category, ['small', 'big'], true)) {
                $machineGroups[$category][] = ['description' => $description, 'quantity' => $quantity, 'total' => $lineTotal];
            } else {
                $loads += $quantity;
                $loadAmount += $lineTotal;
                $prices[] = (float) $item->unit_price;
            }
        }

        foreach ($machineGroups as $group) {
            $wash = collect($group)->first(fn (array $line) => Str::startsWith($line['description'], 'wash'));
            $groupLoads = $wash['quantity'] ?? collect($group)->max('quantity');
            $groupTotal = collect($group)->sum('total');

            if ($groupLoads > 0) {
                $loads += $groupLoads;
                $loadAmount += $groupTotal;
                $prices[] = $groupTotal / $groupLoads;
            }
        }

        return [
            'loads' => $loads,
            'load_amount' => $loadAmount,
            'prices' => $prices,
            'dry_extension' => $dryExtension,
            'delivery' => $delivery,
        ];
    }
}
