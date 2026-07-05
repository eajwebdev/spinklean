<?php

use App\Support\ServiceCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laundry_services', function (Blueprint $table) {
            if (! Schema::hasColumn('laundry_services', 'report_category')) {
                $table->string('report_category', 32)->default('other')->after('name')->index();
            }
        });

        Schema::table('job_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('job_order_items', 'service_category')) {
                $table->string('service_category', 32)->default('other')->after('description')->index();
            }
        });

        DB::table('laundry_services')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->each(fn ($service) => DB::table('laundry_services')
                ->where('id', $service->id)
                ->update(['report_category' => ServiceCategories::infer($service->name)]));

        DB::table('job_order_items')
            ->leftJoin('laundry_services', 'laundry_services.id', '=', 'job_order_items.laundry_service_id')
            ->select([
                'job_order_items.id',
                'job_order_items.description',
                'laundry_services.report_category',
            ])
            ->orderBy('job_order_items.id')
            ->each(fn ($item) => DB::table('job_order_items')
                ->where('id', $item->id)
                ->update([
                    'service_category' => $item->report_category ?: ServiceCategories::infer($item->description),
                ]));

        foreach (DB::table('branches')->pluck('id') as $branchId) {
            foreach ([
                ['name' => 'Plastic Small', 'category' => 'small', 'price' => 15, 'sku' => 'PKG-PLASTIC-SMALL', 'cost' => 2],
                ['name' => 'Plastic Big', 'category' => 'big', 'price' => 25, 'sku' => 'PKG-PLASTIC-BIG', 'cost' => 3],
            ] as $definition) {
                $serviceId = DB::table('laundry_services')
                    ->where('branch_id', $branchId)
                    ->where('name', $definition['name'])
                    ->value('id');

                if (! $serviceId) {
                    $serviceId = DB::table('laundry_services')->insertGetId([
                        'branch_id' => $branchId,
                        'name' => $definition['name'],
                        'report_category' => $definition['category'],
                        'pricing_type' => 'custom',
                        'price' => $definition['price'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $inventoryId = DB::table('inventories')
                    ->where('branch_id', $branchId)
                    ->where('sku', $definition['sku'])
                    ->value('id');

                if (! $inventoryId) {
                    $inventoryId = DB::table('inventories')->insertGetId([
                        'branch_id' => $branchId,
                        'name' => $definition['name'],
                        'sku' => $definition['sku'],
                        'unit' => 'pcs',
                        'quantity' => 0,
                        'reorder_level' => 100,
                        'unit_cost' => $definition['cost'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('service_inventory_usages')->updateOrInsert(
                    ['laundry_service_id' => $serviceId, 'inventory_id' => $inventoryId],
                    ['quantity' => 1, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('job_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('job_order_items', 'service_category')) {
                $table->dropIndex(['service_category']);
                $table->dropColumn('service_category');
            }
        });

        Schema::table('laundry_services', function (Blueprint $table) {
            if (Schema::hasColumn('laundry_services', 'report_category')) {
                $table->dropIndex(['report_category']);
                $table->dropColumn('report_category');
            }
        });
    }
};
