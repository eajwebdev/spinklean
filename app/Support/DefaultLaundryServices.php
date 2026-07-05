<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\LaundryService;
use App\Models\LaundryServiceCategory;
use App\Models\ServicePreset;

class DefaultLaundryServices
{
    public static function all(): array
    {
        return [
            // Small Machine
            ['name' => 'Wash 7kg',       'pricing_type' => 'load',   'price' => 60,  'category' => 'Small Machine', 'report_category' => 'small'],
            ['name' => 'Dry 7kg',        'pricing_type' => 'load',   'price' => 80,  'category' => 'Small Machine', 'report_category' => 'small'],
            ['name' => 'Fold 7kg',       'pricing_type' => 'load',   'price' => 25,  'category' => 'Small Machine', 'report_category' => 'small'],
            ['name' => 'Detergent 80ml', 'pricing_type' => 'custom', 'price' => 15,  'category' => 'Small Machine', 'report_category' => 'small'],
            ['name' => 'Fabcon 70ml',    'pricing_type' => 'custom', 'price' => 15,  'category' => 'Small Machine', 'report_category' => 'small'],

            // Big Machine
            ['name' => 'Wash 10kg',       'pricing_type' => 'load',   'price' => 100, 'category' => 'Big Machine', 'report_category' => 'big'],
            ['name' => 'Dry 10kg',        'pricing_type' => 'load',   'price' => 120, 'category' => 'Big Machine', 'report_category' => 'big'],
            ['name' => 'Fold 10kg',       'pricing_type' => 'load',   'price' => 35,  'category' => 'Big Machine', 'report_category' => 'big'],
            ['name' => 'Detergent 100ml', 'pricing_type' => 'custom', 'price' => 20,  'category' => 'Big Machine', 'report_category' => 'big'],
            ['name' => 'Fabcon 100ml',    'pricing_type' => 'custom', 'price' => 20,  'category' => 'Big Machine', 'report_category' => 'big'],

            // Delivery
            ['name' => 'Delivery Zone 1', 'pricing_type' => 'custom', 'price' => 20,  'category' => 'Delivery', 'report_category' => 'delivery'],
            ['name' => 'Delivery Zone 2', 'pricing_type' => 'custom', 'price' => 30,  'category' => 'Delivery', 'report_category' => 'delivery'],
            ['name' => 'Delivery Zone 3', 'pricing_type' => 'custom', 'price' => 50,  'category' => 'Delivery', 'report_category' => 'delivery'],
            ['name' => 'Delivery Zone 4', 'pricing_type' => 'custom', 'price' => 100, 'category' => 'Delivery', 'report_category' => 'delivery'],

            // Extra Services
            ['name' => 'Dry Extension',        'pricing_type' => 'custom', 'price' => 20,  'category' => 'Extra Services', 'report_category' => 'other'],
            ['name' => 'Spin',                 'pricing_type' => 'custom', 'price' => 30,  'category' => 'Extra Services', 'report_category' => 'other'],
            ['name' => 'Bleach',               'pricing_type' => 'custom', 'price' => 10,  'category' => 'Extra Services', 'report_category' => 'other'],
            ['name' => 'Handwash',             'pricing_type' => 'piece',  'price' => 50,  'category' => 'Extra Services', 'report_category' => 'other'],
            ['name' => 'Steam/Iron',           'pricing_type' => 'piece',  'price' => 50,  'category' => 'Extra Services', 'report_category' => 'other'],
            ['name' => 'Hard Stain',           'pricing_type' => 'custom', 'price' => 50,  'category' => 'Extra Services', 'report_category' => 'other'],
            ['name' => 'Carpet Cleaning',      'pricing_type' => 'custom', 'price' => 500, 'category' => 'Extra Services', 'report_category' => 'other'],
            ['name' => 'Dry Clean',            'pricing_type' => 'custom', 'price' => 200, 'category' => 'Extra Services', 'report_category' => 'other'],
            ['name' => 'Shoe Cleaning',        'pricing_type' => 'piece',  'price' => 350, 'category' => 'Extra Services', 'report_category' => 'other'],

            // Special Items
            ['name' => 'Comforter',    'pricing_type' => 'piece',  'price' => 0, 'category' => 'Special Items', 'report_category' => 'other'],
            ['name' => 'Bed Sheets',   'pricing_type' => 'piece',  'price' => 0, 'category' => 'Special Items', 'report_category' => 'other'],
            ['name' => 'Thick Linens', 'pricing_type' => 'piece',  'price' => 0, 'category' => 'Special Items', 'report_category' => 'other'],

            // Establishment
            ['name' => 'Dynasty',       'pricing_type' => 'piece', 'price' => 0, 'category' => 'Establishment', 'report_category' => 'other'],
            ['name' => 'Estrella',      'pricing_type' => 'piece', 'price' => 0, 'category' => 'Establishment', 'report_category' => 'other'],
            ['name' => 'Laybare',       'pricing_type' => 'load',  'price' => 0, 'category' => 'Establishment', 'report_category' => 'other'],
            ['name' => 'Tresor',        'pricing_type' => 'load',  'price' => 0, 'category' => 'Establishment', 'report_category' => 'other'],
            ['name' => 'Salon De Rose', 'pricing_type' => 'load',  'price' => 0, 'category' => 'Establishment', 'report_category' => 'other'],
            ['name' => 'Amuma',         'pricing_type' => 'piece', 'price' => 0, 'category' => 'Establishment', 'report_category' => 'other'],
            ['name' => 'Nailaholics',   'pricing_type' => 'load',  'price' => 0, 'category' => 'Establishment', 'report_category' => 'other'],

            // For Sale Items
            ['name' => 'Sachet', 'pricing_type' => 'piece', 'price' => 0, 'category' => 'For Sale Items', 'report_category' => 'other'],
        ];
    }

    public static function presets(): array
    {
        return [
            [
                'name' => 'Full Service 7kg',
                'category' => 'Small Machine',
                'sort_order' => 1,
                'items' => [
                    'Wash 7kg' => 1,
                    'Dry 7kg' => 1,
                    'Fold 7kg' => 1,
                    'Detergent 80ml' => 1,
                    'Fabcon 70ml' => 1,
                ],
            ],
            [
                'name' => 'Full Service 10kg',
                'category' => 'Big Machine',
                'sort_order' => 2,
                'items' => [
                    'Wash 10kg' => 1,
                    'Dry 10kg' => 1,
                    'Fold 10kg' => 1,
                    'Detergent 100ml' => 1,
                    'Fabcon 100ml' => 1,
                ],
            ],
        ];
    }

    public static function seedForBranch(Branch $branch): void
    {
        self::ensureCategories();

        $categoryMap = LaundryServiceCategory::pluck('id', 'name')->all();
        $names = array_column(self::all(), 'name');

        LaundryService::where('branch_id', $branch->id)
            ->whereNotIn('name', $names)
            ->delete();

        foreach (self::all() as $service) {
            LaundryService::withTrashed()->updateOrCreate(
                ['branch_id' => $branch->id, 'name' => $service['name']],
                [
                    'pricing_type'        => $service['pricing_type'],
                    'service_category_id' => $categoryMap[$service['category']] ?? null,
                    'report_category'     => $service['report_category'],
                    'price'               => $service['price'],
                    'is_active'           => true,
                    'deleted_at'          => null,
                ]
            );
        }

        self::seedPresetsForBranch($branch, $categoryMap);
    }

    private static function ensureCategories(): void
    {
        $categories = collect(self::all())
            ->pluck('category')
            ->unique()
            ->values();

        foreach ($categories as $index => $name) {
            LaundryServiceCategory::updateOrCreate(
                ['name' => $name],
                ['visibility' => 'all', 'sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }

    private static function seedPresetsForBranch(Branch $branch, array $categoryMap): void
    {
        $serviceMap = LaundryService::query()
            ->where('branch_id', $branch->id)
            ->get()
            ->keyBy('name');
        $presetNames = array_column(self::presets(), 'name');

        ServicePreset::query()
            ->where('branch_id', $branch->id)
            ->whereNotIn('name', $presetNames)
            ->delete();

        foreach (self::presets() as $definition) {
            $preset = ServicePreset::updateOrCreate(
                ['branch_id' => $branch->id, 'name' => $definition['name']],
                [
                    'service_category_id' => $categoryMap[$definition['category']] ?? null,
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ]
            );

            $serviceIds = [];

            foreach ($definition['items'] as $serviceName => $quantity) {
                $service = $serviceMap->get($serviceName);

                if (! $service) {
                    continue;
                }

                $serviceIds[] = $service->id;
                $preset->items()->updateOrCreate(
                    ['laundry_service_id' => $service->id],
                    ['quantity' => $quantity]
                );
            }

            $preset->items()->whereNotIn('laundry_service_id', $serviceIds)->delete();
        }
    }
}
