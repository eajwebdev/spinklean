<?php

use App\Models\Branch;
use App\Support\InventoryAddOns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add Ons are inventory products sold in Job Orders. Inventory items can be
 * flagged as add-ons (with a type and selling price) and each one gets a
 * linked "Add Ons" laundry service that deducts 1 unit per sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->boolean('is_add_on')->default(false)->after('is_active');
            $table->string('add_on_type', 20)->nullable()->after('is_add_on');
            $table->decimal('selling_price', 12, 2)->nullable()->after('add_on_type');
        });

        Schema::table('laundry_services', function (Blueprint $table) {
            $table->foreignId('add_on_inventory_id')->nullable()->after('report_category')
                ->constrained('inventories')->nullOnDelete();
        });

        Branch::query()->each(fn (Branch $branch) => InventoryAddOns::seedForBranch($branch));
    }

    public function down(): void
    {
        $usedServiceIds = DB::table('job_order_items')->whereNotNull('laundry_service_id')->pluck('laundry_service_id');
        $addOnServices = DB::table('laundry_services')->whereNotNull('add_on_inventory_id');

        DB::table('service_inventory_usages')
            ->whereIn('laundry_service_id', (clone $addOnServices)->whereNotIn('id', $usedServiceIds)->pluck('id'))
            ->delete();
        (clone $addOnServices)->whereNotIn('id', $usedServiceIds)->delete();

        $skus = array_column(InventoryAddOns::defaults(), 'sku');
        DB::table('inventories')
            ->whereIn('sku', $skus)
            ->where('is_add_on', true)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('inventory_movements')->whereColumn('inventory_movements.inventory_id', 'inventories.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw(1)->from('service_inventory_usages')->whereColumn('service_inventory_usages.inventory_id', 'inventories.id'))
            ->delete();

        Schema::table('laundry_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('add_on_inventory_id');
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn(['is_add_on', 'add_on_type', 'selling_price']);
        });
    }
};
