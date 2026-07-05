<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('job_orders', 'transaction_type')) {
                $table->string('transaction_type')->default('walk_in')->after('status');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'reference_no')) {
                $table->string('reference_no')->nullable()->after('payment_type');
            }
        });

        Schema::table('branch_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('branch_expenses', 'expense_type')) {
                $table->string('expense_type')->default('regular')->after('category');
            }
        });

        if (! Schema::hasTable('daily_tasks')) {
            Schema::create('daily_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
                $table->string('name');
                $table->boolean('requires_photo')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['branch_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('daily_task_completions')) {
            Schema::create('daily_task_completions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('daily_task_id')->constrained('daily_tasks')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('work_date');
                $table->string('photo_path');
                $table->text('remarks')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['daily_task_id', 'branch_id', 'work_date'], 'daily_task_branch_date_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_task_completions');
        Schema::dropIfExists('daily_tasks');

        foreach ([
            ['branch_expenses', 'expense_type'],
            ['payments', 'reference_no'],
            ['job_orders', 'transaction_type'],
        ] as [$tableName, $column]) {
            if (Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
