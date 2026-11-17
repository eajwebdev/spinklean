<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\LaundryService;
use App\Models\LaundryServiceCategory;
use Illuminate\Support\Collection;

/**
 * Baggage Counter rates. Each size is a "Baggage Storage" service per branch so the
 * price can be changed on the Services page; the sale itself is a product-sale Job Order.
 */
class BaggageCounter
{
    public const CATEGORY = 'Baggage Storage';

    /** size => [label, default price, hours before it is overdue] */
    public const SIZES = [
        'small' => ['label' => 'Small item', 'price' => 20, 'hours' => 2],
        'medium' => ['label' => 'Medium item', 'price' => 30, 'hours' => 2],
        'large' => ['label' => 'Large to XL item', 'price' => 50, 'hours' => 2],
        'overnight' => ['label' => 'Overnight / per day', 'price' => 100, 'hours' => 24],
    ];

    public const MAX_TAGS = 200;

    public static function label(string $size): string
    {
        return self::SIZES[$size]['label'] ?? ucfirst($size);
    }

    public static function hours(string $size): int
    {
        return (int) (self::SIZES[$size]['hours'] ?? 2);
    }

    /**
     * The branch's rate services keyed by size, created with the default prices the
     * first time. A rate turned off (inactive) on the Services page is not offered.
     *
     * @return Collection<string, LaundryService>
     */
    public static function rates(Branch $branch): Collection
    {
        $categoryId = LaundryServiceCategory::query()->firstOrCreate(
            ['name' => self::CATEGORY],
            [
                'visibility' => 'all',
                'sort_order' => (int) LaundryServiceCategory::query()->max('sort_order') + 1,
                'is_active' => true,
            ]
        )->id;

        $existing = LaundryService::withTrashed()
            ->where('branch_id', $branch->id)
            ->whereNotNull('baggage_size')
            ->get()
            ->keyBy('baggage_size');

        foreach (self::SIZES as $size => $definition) {
            $service = $existing->get($size);

            if (! $service) {
                $existing->put($size, LaundryService::query()->create([
                    'branch_id' => $branch->id,
                    'service_category_id' => $categoryId,
                    'name' => 'Baggage - '.$definition['label'],
                    'report_category' => 'other',
                    'baggage_size' => $size,
                    'pricing_type' => 'piece',
                    'price' => $definition['price'],
                    'is_active' => true,
                ]));
            } elseif ($service->trashed()) {
                // The counter needs its rates; a deleted rate comes back (switch it off to hide it).
                $service->restore();
            }
        }

        return $existing
            ->filter(fn (LaundryService $service) => $service->is_active)
            ->sortBy(fn (LaundryService $service, string $size) => array_search($size, array_keys(self::SIZES), true));
    }
}
