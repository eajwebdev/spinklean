<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\LaundryService;
use App\Models\ServiceInventoryUsage;

class ExcelSampleServices
{
    public static function all(): array
    {
        return [
            [
                'name' => 'Regular Wash',
                'report_category' => 'wash',
                'pricing_type' => 'load',
                'price' => 60,
                'inventory' => ['Detergent Powder' => 0.10],
            ],
            [
                'name' => 'Regular Dry',
                'report_category' => 'dry',
                'pricing_type' => 'load',
                'price' => 80,
                'inventory' => ['Dryer Sheet' => 1],
            ],
            [
                'name' => 'Dry Extend 10 Minutes',
                'report_category' => 'dry_extend',
                'pricing_type' => 'custom',
                'price' => 20,
                'inventory' => ['Dryer Sheet' => 0.25],
            ],
            [
                'name' => 'Fold Service',
                'report_category' => 'fold',
                'pricing_type' => 'load',
                'price' => 25,
                'inventory' => ['Plastic Packaging' => 0.10],
            ],
        ];
    }

    public static function seedForBranch(Branch $branch): void
    {
        $inventory = Inventory::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('name');

        foreach (self::all() as $definition) {
            $service = LaundryService::withTrashed()->firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'name' => $definition['name'],
                ],
                [
                    'report_category' => $definition['report_category'],
                    'pricing_type' => $definition['pricing_type'],
                    'price' => $definition['price'],
                    'is_active' => true,
                ]
            );

            if ($service->trashed()) {
                $service->restore();
            }

            foreach ($definition['inventory'] as $inventoryName => $quantity) {
                $stock = $inventory->get($inventoryName);

                if (! $stock) {
                    continue;
                }

                ServiceInventoryUsage::updateOrCreate(
                    [
                        'laundry_service_id' => $service->id,
                        'inventory_id' => $stock->id,
                    ],
                    ['quantity' => $quantity]
                );
            }
        }
    }
}
