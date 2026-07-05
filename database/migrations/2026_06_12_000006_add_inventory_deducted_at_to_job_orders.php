<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('job_orders', 'inventory_deducted_at')) {
                $table->timestamp('inventory_deducted_at')->nullable()->after('production_accepted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            if (Schema::hasColumn('job_orders', 'inventory_deducted_at')) {
                $table->dropColumn('inventory_deducted_at');
            }
        });
    }
};
