<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\LaundryService;
use App\Models\ServiceInventoryUsage;

class DefaultServiceInventoryUsages
{
    public static function rules(): array
    {
        return [
            'Wash Dry Fold' => ['Detergent Powder' => 0.08, 'Fabric Conditioner' => 0.05, 'Plastic Packaging' => 0.20],
            'Wash Only' => ['Detergent Powder' => 0.08],
            'Dry Only' => ['Dryer Sheet' => 0.20],
            'Fold Only' => ['Plastic Packaging' => 0.10],
            'Ironing' => ['Hanger' => 1],
            'Pressing' => ['Hanger' => 1],
            'Full Service Load' => ['Detergent Powder' => 0.50, 'Fabric Conditioner' => 0.25, 'Laundry Bag' => 1, 'Plastic Packaging' => 1],
            'Self Service Wash Load' => ['Detergent Powder' => 0.35],
            'Self Service Dry Load' => ['Dryer Sheet' => 1],
            'Heavy Load Wash' => ['Detergent Powder' => 0.75, 'Fabric Conditioner' => 0.30],
            'Heavy Load Dry' => ['Dryer Sheet' => 1],
            'Extra Dry 10 Minutes' => ['Dryer Sheet' => 0.25],
            'Extra Dry 20 Minutes' => ['Dryer Sheet' => 0.50],
            'Extra Dry 30 Minutes' => ['Dryer Sheet' => 0.75],
            'Detergent' => ['Detergent Powder' => 0.10],
            'Fabric Conditioner' => ['Fabric Conditioner' => 0.08],
            'Plastic Small' => ['Plastic Small' => 1],
            'Plastic Big' => ['Plastic Big' => 1],
            'Bleach' => ['Bleach' => 0.10],
            'Stain Treatment' => ['Stain Remover' => 0.05],
            'Deep Cleaning' => ['Detergent Powder' => 0.12, 'Stain Remover' => 0.03, 'Disinfectant' => 0.03],
            'Sanitize Wash' => ['Detergent Powder' => 0.08, 'Disinfectant' => 0.05],
            'Delicate Wash' => ['Detergent Powder' => 0.05, 'Fabric Conditioner' => 0.03],
            'Hand Wash' => ['Detergent Powder' => 0.05, 'Fabric Conditioner' => 0.03],
            'Dry Cleaning' => ['Plastic Packaging' => 1, 'Hanger' => 1],
            'Bedsheet Single' => ['Detergent Powder' => 0.08, 'Fabric Conditioner' => 0.04, 'Plastic Packaging' => 1],
            'Bedsheet Double' => ['Detergent Powder' => 0.10, 'Fabric Conditioner' => 0.05, 'Plastic Packaging' => 1],
            'Comforter Single' => ['Detergent Powder' => 0.20, 'Fabric Conditioner' => 0.10, 'Plastic Packaging' => 1],
            'Comforter Double' => ['Detergent Powder' => 0.25, 'Fabric Conditioner' => 0.12, 'Plastic Packaging' => 1],
            'Comforter Queen' => ['Detergent Powder' => 0.30, 'Fabric Conditioner' => 0.15, 'Plastic Packaging' => 1],
            'Comforter King' => ['Detergent Powder' => 0.35, 'Fabric Conditioner' => 0.18, 'Plastic Packaging' => 1],
            'Blanket Small' => ['Detergent Powder' => 0.15, 'Fabric Conditioner' => 0.08, 'Plastic Packaging' => 1],
            'Blanket Large' => ['Detergent Powder' => 0.25, 'Fabric Conditioner' => 0.12, 'Plastic Packaging' => 1],
            'Duvet' => ['Detergent Powder' => 0.30, 'Fabric Conditioner' => 0.15, 'Plastic Packaging' => 1],
            'Pillow Case' => ['Detergent Powder' => 0.03, 'Fabric Conditioner' => 0.02],
            'Pillow' => ['Detergent Powder' => 0.10, 'Fabric Conditioner' => 0.05, 'Plastic Packaging' => 1],
            'Towel Small' => ['Detergent Powder' => 0.03, 'Fabric Conditioner' => 0.02],
            'Towel Bath' => ['Detergent Powder' => 0.06, 'Fabric Conditioner' => 0.03],
            'Curtain Small' => ['Detergent Powder' => 0.15, 'Fabric Conditioner' => 0.07, 'Plastic Packaging' => 1],
            'Curtain Large' => ['Detergent Powder' => 0.25, 'Fabric Conditioner' => 0.12, 'Plastic Packaging' => 1],
            'Rug Small' => ['Detergent Powder' => 0.20, 'Stain Remover' => 0.05],
            'Rug Large' => ['Detergent Powder' => 0.35, 'Stain Remover' => 0.08],
            'Jacket' => ['Detergent Powder' => 0.08, 'Fabric Conditioner' => 0.04, 'Hanger' => 1],
            'Coat' => ['Detergent Powder' => 0.10, 'Fabric Conditioner' => 0.05, 'Hanger' => 1],
            'Gown' => ['Detergent Powder' => 0.08, 'Fabric Conditioner' => 0.04, 'Hanger' => 1, 'Plastic Packaging' => 1],
            'Suit Set' => ['Detergent Powder' => 0.10, 'Fabric Conditioner' => 0.05, 'Hanger' => 1, 'Plastic Packaging' => 1],
            'Uniform Set' => ['Detergent Powder' => 0.08, 'Fabric Conditioner' => 0.04, 'Hanger' => 1],
            'Shoes Cleaning' => ['Stain Remover' => 0.08, 'Disinfectant' => 0.05],
            'Bag Cleaning' => ['Stain Remover' => 0.08, 'Disinfectant' => 0.05],
        ];
    }

    public static function seedForBranch(Branch $branch): void
    {
        $services = LaundryService::query()
            ->where('branch_id', $branch->id)
            ->get()
            ->keyBy('name');

        $inventory = Inventory::query()
            ->where('branch_id', $branch->id)
            ->get()
            ->keyBy('name');

        foreach (self::rules() as $serviceName => $items) {
            $service = $services->get($serviceName);

            if (! $service) {
                continue;
            }

            foreach ($items as $inventoryName => $quantity) {
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

            $validInventoryIds = collect($items)
                ->keys()
                ->map(fn (string $inventoryName) => $inventory->get($inventoryName)?->id)
                ->filter()
                ->values();

            $service->inventoryUsages()
                ->whereNotIn('inventory_id', $validInventoryIds)
                ->delete();
        }
    }
}
