<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained('laundry_service_categories')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_preset_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_preset_id')->constrained('service_presets')->cascadeOnDelete();
            $table->foreignId('laundry_service_id')->constrained('laundry_services')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->timestamps();

            $table->unique(['service_preset_id', 'laundry_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_preset_items');
        Schema::dropIfExists('service_presets');
    }
};
