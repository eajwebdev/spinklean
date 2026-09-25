<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_tasks') && ! Schema::hasColumn('daily_tasks', 'affects_machine_counter')) {
            Schema::table('daily_tasks', function (Blueprint $table) {
                $table->string('affects_machine_counter', 20)->default('none')->after('requires_photo');
            });
        }

        if (Schema::hasTable('daily_task_completions') && ! Schema::hasColumn('daily_task_completions', 'cleaned_machines')) {
            Schema::table('daily_task_completions', function (Blueprint $table) {
                $table->json('cleaned_machines')->nullable()->after('remarks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('daily_tasks') && Schema::hasColumn('daily_tasks', 'affects_machine_counter')) {
            Schema::table('daily_tasks', function (Blueprint $table) {
                $table->dropColumn('affects_machine_counter');
            });
        }

        if (Schema::hasTable('daily_task_completions') && Schema::hasColumn('daily_task_completions', 'cleaned_machines')) {
            Schema::table('daily_task_completions', function (Blueprint $table) {
                $table->dropColumn('cleaned_machines');
            });
        }
    }
};
