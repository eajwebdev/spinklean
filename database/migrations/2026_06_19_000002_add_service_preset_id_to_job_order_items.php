<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('job_order_items', 'service_preset_id')) {
                $table->foreignId('service_preset_id')
                    ->nullable()
                    ->after('laundry_service_id')
                    ->constrained('service_presets')
                    ->nullOnDelete();

                $table->index(['service_preset_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('job_order_items', 'service_preset_id')) {
                $table->dropConstrainedForeignId('service_preset_id');
            }
        });
    }
};
