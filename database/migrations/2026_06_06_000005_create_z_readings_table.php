<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('z_readings')) {
            Schema::create('z_readings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reading_number')->unique();
                $table->date('business_date');
                $table->json('cash_count')->nullable();
                $table->json('payment_breakdown')->nullable();
                $table->json('expense_breakdown')->nullable();
                $table->decimal('expected_cash_amount', 12, 2)->default(0);
                $table->decimal('cash_expense_amount', 12, 2)->default(0);
                $table->decimal('expected_cash_drawer_amount', 12, 2)->default(0);
                $table->decimal('actual_cash_amount', 12, 2)->default(0);
                $table->decimal('expected_gcash_amount', 12, 2)->default(0);
                $table->decimal('actual_gcash_amount', 12, 2)->default(0);
                $table->decimal('expected_bank_amount', 12, 2)->default(0);
                $table->decimal('actual_bank_amount', 12, 2)->default(0);
                $table->decimal('expected_total_amount', 12, 2)->default(0);
                $table->decimal('actual_total_amount', 12, 2)->default(0);
                $table->decimal('over_short_amount', 12, 2)->default(0);
                $table->unsignedInteger('transaction_count')->default(0);
                $table->string('first_job_order_number')->nullable();
                $table->string('last_job_order_number')->nullable();
                $table->string('signature_name');
                $table->text('remarks')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'business_date'], 'z_reading_branch_date_unique');
                $table->index(['business_date', 'branch_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('z_readings');
    }
};
