<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\LaundryService;
use App\Models\LaundryServiceCategory;

/**
 * Add Ons are inventory products (Ariel, Downy, ...) sold in Job Orders.
 * An item can also (or instead) be tagged "Sell Product": it then shows under
 * "Sell Products" and can be sold on its own as a product sale.
 *
 * The inventory item is the source of truth: it holds the stock, the selling
 * price and the type (detergent / fabcon). Each add-on item owns one linked
 * "Add Ons" laundry service so the POS, receipts, reports and the existing
 * stock deduction / restore logic keep working. That service is managed here
 * and should never be edited from the Services page.
 */
class InventoryAddOns
{
    public const CATEGORY = 'Add Ons';

    /** Products tagged "Sell Product" in Inventory: can be sold on their own (product sale). */
    public const SALE_CATEGORY = 'Sell Products';

    public const TYPES = ['detergent' => 'Detergent', 'fabcon' => 'Fabcon'];

    /** Default branded add-on products created for every branch. */
    public static function defaults(): array
    {
        return [
            ['name' => 'Ariel',          'sku' => 'ADD-ARIEL',        'add_on_type' => 'detergent', 'selling_price' => 20],
            ['name' => 'Tide',           'sku' => 'ADD-TIDE',         'add_on_type' => 'detergent', 'selling_price' => 18],
            ['name' => 'Breeze',         'sku' => 'ADD-BREEZE',       'add_on_type' => 'detergent', 'selling_price' => 18],
            ['name' => 'Surf Detergent', 'sku' => 'ADD-SURF-DET',     'add_on_type' => 'detergent', 'selling_price' => 15],
            ['name' => 'Downy',          'sku' => 'ADD-DOWNY',        'add_on_type' => 'fabcon',    'selling_price' => 15],
            ['name' => 'Del',            'sku' => 'ADD-DEL',          'add_on_type' => 'fabcon',    'selling_price' => 15],
            ['name' => 'Surf Fabcon',    'sku' => 'ADD-SURF-FABCON',  'add_on_type' => 'fabcon',    'selling_price' => 12],
        ];
    }

    public static function categoryId(string $name = self::CATEGORY): int
    {
        $category = LaundryServiceCategory::query()->firstOrCreate(
            ['name' => $name],
            [
                'visibility' => 'all',
                'sort_order' => (int) LaundryServiceCategory::query()->max('sort_order') + 1,
                'is_active' => true,
            ]
        );

        return (int) $category->id;
    }

    /**
     * Create or update the Job Order services that sell this inventory item:
     * one under "Add Ons" when tagged Add-On, one under "Sell Products" when tagged
     * Sell Product. Both use the same stock. Returns the Add-On service.
     */
    public static function sync(Inventory $inventory): ?LaundryService
    {
        $reportCategory = array_key_exists((string) $inventory->add_on_type, self::TYPES) ? $inventory->add_on_type : 'other';

        self::syncLinkedService($inventory, 'sale_inventory_id', (bool) $inventory->is_for_sale, self::SALE_CATEGORY, $inventory->is_add_on ? $reportCategory : 'other');

        return self::syncLinkedService($inventory, 'add_on_inventory_id', (bool) $inventory->is_add_on, self::CATEGORY, $reportCategory);
    }

    private static function syncLinkedService(Inventory $inventory, string $linkColumn, bool $tagged, string $category, string $reportCategory): ?LaundryService
    {
        $service = LaundryService::withTrashed()
            ->where($linkColumn, $inventory->id)
            ->first();

        $sellable = $tagged && ! $inventory->trashed();

        if (! $sellable) {
            if ($service && $service->is_active) {
                $service->update(['is_active' => false]);
            }

            return $service;
        }

        $attributes = [
            'branch_id' => $inventory->branch_id,
            'service_category_id' => self::categoryId($category),
            'name' => $inventory->name,
            'report_category' => $reportCategory,
            'pricing_type' => 'piece',
            'price' => (float) ($inventory->selling_price ?? 0),
            'is_active' => (bool) $inventory->is_active,
        ];

        if ($service) {
            if ($service->trashed()) {
                $service->restore();
            }
            $service->update($attributes);
        } else {
            $service = LaundryService::query()->create($attributes + [$linkColumn => $inventory->id]);
        }

        // One sale of the add-on uses exactly one unit of its inventory item.
        $service->inventoryUsages()->where('inventory_id', '!=', $inventory->id)->delete();
        $service->inventoryUsages()->updateOrCreate(['inventory_id' => $inventory->id], ['quantity' => 1]);

        return $service;
    }

    /** Make sure a branch has the default add-on products in its inventory. */
    public static function seedForBranch(Branch $branch): void
    {
        foreach (self::defaults() as $default) {
            $inventory = Inventory::withTrashed()
                ->where('branch_id', $branch->id)
                ->where(fn ($query) => $query->where('sku', $default['sku'])->orWhere('name', $default['name']))
                ->first();

            if (! $inventory) {
                $inventory = Inventory::query()->create($default + [
                    'branch_id' => $branch->id,
                    'unit' => 'pcs',
                    'quantity' => 0,
                    'reorder_level' => 5,
                    'unit_cost' => 0,
                    'is_active' => true,
                    'is_add_on' => true,
                ]);
            } elseif (! $inventory->is_add_on) {
                $inventory->update([
                    'is_add_on' => true,
                    'add_on_type' => $inventory->add_on_type ?: $default['add_on_type'],
                    'selling_price' => $inventory->selling_price ?? $default['selling_price'],
                ]);
            }

            self::sync($inventory);
        }
    }
}
