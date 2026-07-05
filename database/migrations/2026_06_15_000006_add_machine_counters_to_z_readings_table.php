<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('z_readings', function (Blueprint $table) {
            if (! Schema::hasColumn('z_readings', 'machine_counters')) {
                $table->json('machine_counters')->nullable()->after('expense_breakdown');
            }
        });
    }

    public function down(): void
    {
        Schema::table('z_readings', function (Blueprint $table) {
            if (Schema::hasColumn('z_readings', 'machine_counters')) {
                $table->dropColumn('machine_counters');
            }
        });
    }
};
