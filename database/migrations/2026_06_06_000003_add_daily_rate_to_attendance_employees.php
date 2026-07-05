<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_employees') && ! Schema::hasColumn('attendance_employees', 'daily_rate')) {
            Schema::table('attendance_employees', function (Blueprint $table) {
                $table->decimal('daily_rate', 10, 2)->default(0)->after('password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_employees') && Schema::hasColumn('attendance_employees', 'daily_rate')) {
            Schema::table('attendance_employees', function (Blueprint $table) {
                $table->dropColumn('daily_rate');
            });
        }
    }
};
