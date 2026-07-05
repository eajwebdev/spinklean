<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_task_completions') && ! Schema::hasColumn('daily_task_completions', 'completed_by_employee_id')) {
            Schema::table('daily_task_completions', function (Blueprint $table) {
                $table->foreignId('completed_by_employee_id')
                    ->nullable()
                    ->after('completed_by')
                    ->constrained('attendance_employees')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('daily_task_completions') && Schema::hasColumn('daily_task_completions', 'completed_by_employee_id')) {
            Schema::table('daily_task_completions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('completed_by_employee_id');
            });
        }
    }
};
