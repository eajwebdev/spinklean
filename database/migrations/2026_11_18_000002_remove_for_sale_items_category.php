<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "For Sale Items" (with its PHP 0 "Sachet" service) is replaced by inventory Add Ons,
     * which track stock and are sold as product sales. Remove the old category.
     *
     * The Sachet service is soft-deleted so past job orders keep their history.
     * Any other service someone put in the category is kept (only uncategorized).
     */
    public function up(): void
    {
        if (! Schema::hasTable('laundry_service_categories') || ! Schema::hasTable('laundry_services')) {
            return;
        }

        $categoryId = DB::table('laundry_service_categories')->where('name', 'For Sale Items')->value('id');

        if (! $categoryId) {
            return;
        }

        DB::table('laundry_services')
            ->where('service_category_id', $categoryId)
            ->where('name', 'Sachet')
            ->whereNull('add_on_inventory_id')
            ->whereNull('deleted_at')
            ->update(['is_active' => false, 'deleted_at' => now(), 'updated_at' => now()]);

        // Services and presets point at the category with nullOnDelete; null them explicitly
        // so this also works on databases without enforced foreign keys.
        DB::table('laundry_services')->where('service_category_id', $categoryId)->update(['service_category_id' => null]);

        if (Schema::hasTable('service_presets')) {
            DB::table('service_presets')->where('service_category_id', $categoryId)->update(['service_category_id' => null]);
        }

        DB::table('laundry_service_categories')->where('id', $categoryId)->delete();
    }

    public function down(): void
    {
        // Not restored: Add Ons replace this category.
    }
};
