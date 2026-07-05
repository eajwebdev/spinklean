<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_trial_settings')) {
            Schema::create('system_trial_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('trial_enabled')->default(false);
                $table->date('trial_start_date')->nullable();
                $table->date('trial_end_date')->nullable();
                $table->enum('trial_status', ['inactive', 'active', 'expired'])->default('inactive');
                $table->text('trial_remarks')->nullable();
                $table->unsignedInteger('grace_period_days')->default(0);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('branch_expenses')) {
            Schema::create('branch_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('category');
                $table->string('title');
                $table->decimal('amount', 12, 2);
                $table->date('expense_date');
                $table->string('payment_method')->nullable();
                $table->string('paid_from')->default('store_cash');
                $table->string('reference_no')->nullable();
                $table->text('remarks')->nullable();
                $table->string('source')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['branch_id', 'expense_date']);
                $table->unique(['source', 'source_id']);
            });
        }

        if (! Schema::hasTable('branch_billing_records')) {
            Schema::create('branch_billing_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->unsignedTinyInteger('billing_month');
                $table->unsignedSmallInteger('billing_year');
                $table->decimal('amount', 12, 2);
                $table->date('due_date');
                $table->enum('status', ['unpaid', 'paid', 'overdue', 'suspended'])->default('unpaid');
                $table->date('payment_date')->nullable();
                $table->string('payment_method')->nullable();
                $table->string('reference_no')->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('expense_id')->nullable()->constrained('branch_expenses')->nullOnDelete();
                $table->timestamps();

                $table->unique(['branch_id', 'billing_month', 'billing_year'], 'branch_billing_period_unique');
                $table->index(['billing_year', 'billing_month', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_billing_records');
        Schema::dropIfExists('branch_expenses');
        Schema::dropIfExists('system_trial_settings');
    }
};
