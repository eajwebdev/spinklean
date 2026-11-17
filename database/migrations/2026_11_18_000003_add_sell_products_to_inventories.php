<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Sell Products": an inventory item can be tagged to be sold on its own in Job Orders
 * (a product sale: no laundry, no transfer). It is separate from the Add-On tag, and an
 * item can have both; each tag gets its own linked service and both use the same stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventories', 'is_for_sale')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->boolean('is_for_sale')->default(false)->after('add_on_type');
            });
        }

        if (! Schema::hasColumn('laundry_services', 'sale_inventory_id')) {
            Schema::table('laundry_services', function (Blueprint $table) {
                $table->foreignId('sale_inventory_id')->nullable()->after('add_on_inventory_id')
                    ->constrained('inventories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('laundry_services', 'sale_inventory_id')) {
            Schema::table('laundry_services', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sale_inventory_id');
            });
        }

        if (Schema::hasColumn('inventories', 'is_for_sale')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->dropColumn('is_for_sale');
            });
        }
    }
};
