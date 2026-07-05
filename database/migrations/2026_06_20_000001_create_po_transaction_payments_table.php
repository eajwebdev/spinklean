<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('po_transaction_payments')) {
            return;
        }

        Schema::create('po_transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_transaction_id')->constrained('po_transactions')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('job_order_id')->nullable()->constrained('job_orders')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_number')->unique();
            $table->enum('payment_method', ['cash', 'gcash', 'bank', 'cheque']);
            $table->string('reference_no')->nullable();
            $table->decimal('amount', 12, 2);
            $table->text('remarks')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['po_transaction_id', 'paid_at']);
            $table->index(['branch_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_transaction_payments');
    }
};
