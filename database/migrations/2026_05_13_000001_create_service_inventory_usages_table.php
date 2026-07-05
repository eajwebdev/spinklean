<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_inventory_usages')) {
            Schema::create('service_inventory_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('laundry_service_id')->constrained('laundry_services')->cascadeOnDelete();
                $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
                $table->decimal('quantity', 12, 4)->default(0);
                $table->timestamps();

                $table->unique(['laundry_service_id', 'inventory_id'], 'service_inventory_usage_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_inventory_usages');
    }
};
