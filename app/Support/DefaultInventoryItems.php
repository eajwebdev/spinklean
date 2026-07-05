<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Inventory;

class DefaultInventoryItems
{
    public static function all(): array
    {
        return [
            ['name' => 'Detergent Powder', 'sku' => 'SUP-DETERGENT', 'unit' => 'kg', 'quantity' => 100, 'reorder_level' => 20, 'unit_cost' => 80],
            ['name' => 'Fabric Conditioner', 'sku' => 'SUP-CONDITIONER', 'unit' => 'liter', 'quantity' => 80, 'reorder_level' => 15, 'unit_cost' => 95],
            ['name' => 'Bleach', 'sku' => 'SUP-BLEACH', 'unit' => 'liter', 'quantity' => 30, 'reorder_level' => 8, 'unit_cost' => 70],
            ['name' => 'Stain Remover', 'sku' => 'SUP-STAIN', 'unit' => 'liter', 'quantity' => 20, 'reorder_level' => 5, 'unit_cost' => 140],
            ['name' => 'Disinfectant', 'sku' => 'SUP-DISINFECT', 'unit' => 'liter', 'quantity' => 30, 'reorder_level' => 8, 'unit_cost' => 120],
            ['name' => 'Dryer Sheet', 'sku' => 'SUP-DRYER-SHEET', 'unit' => 'pcs', 'quantity' => 300, 'reorder_level' => 60, 'unit_cost' => 3],
            ['name' => 'Laundry Bag', 'sku' => 'PKG-LAUNDRY-BAG', 'unit' => 'pcs', 'quantity' => 500, 'reorder_level' => 100, 'unit_cost' => 4],
            ['name' => 'Plastic Packaging', 'sku' => 'PKG-PLASTIC', 'unit' => 'pcs', 'quantity' => 500, 'reorder_level' => 100, 'unit_cost' => 2],
            ['name' => 'Plastic Small', 'sku' => 'PKG-PLASTIC-SMALL', 'unit' => 'pcs', 'quantity' => 500, 'reorder_level' => 100, 'unit_cost' => 2],
            ['name' => 'Plastic Big', 'sku' => 'PKG-PLASTIC-BIG', 'unit' => 'pcs', 'quantity' => 500, 'reorder_level' => 100, 'unit_cost' => 3],
            ['name' => 'Hanger', 'sku' => 'PKG-HANGER', 'unit' => 'pcs', 'quantity' => 200, 'reorder_level' => 50, 'unit_cost' => 5],
        ];
    }

    public static function seedForBranch(Branch $branch): void
    {
        foreach (self::all() as $item) {
            $inventory = Inventory::withTrashed()
                ->where('branch_id', $branch->id)
                ->where(function ($query) use ($item) {
                    $query->where('sku', $item['sku'])
                        ->orWhere('name', $item['name']);
                })
                ->first();

            if (! $inventory) {
                Inventory::query()->create($item + [
                    'branch_id' => $branch->id,
                    'is_active' => true,
                ]);

                continue;
            }

            $attributes = [
                'name' => $item['name'],
                'sku' => $item['sku'],
                'unit' => $item['unit'],
                'reorder_level' => $item['reorder_level'],
                'unit_cost' => $item['unit_cost'],
                'is_active' => true,
                'deleted_at' => null,
            ];

            // Initialize records created without opening stock, but never overwrite
            // quantities that have already participated in inventory movements.
            if ((float) $inventory->quantity === 0.0 && ! $inventory->movements()->exists()) {
                $attributes['quantity'] = $item['quantity'];
            }

            $inventory->update($attributes);
        }
    }
}
