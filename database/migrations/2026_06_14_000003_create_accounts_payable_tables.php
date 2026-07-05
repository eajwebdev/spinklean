<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts_payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payable_number')->unique();
            $table->string('creditor_name')->default('Owner');
            $table->string('source_type')->default('owner_funding');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('funding_method')->default('cash');
            $table->string('reference_no')->nullable();
            $table->text('description');
            $table->decimal('original_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->string('status')->default('unpaid');
            $table->date('funded_at');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('accounts_payable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounts_payable_id')->constrained('accounts_payables')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('money_movement_id')->nullable()->constrained('money_movements')->nullOnDelete();
            $table->string('payment_number')->unique();
            $table->date('payment_date');
            $table->string('payment_method')->default('cash');
            $table->decimal('amount', 12, 2);
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'payment_date']);
        });

        Schema::table('branch_expenses', function (Blueprint $table) {
            $table->foreignId('accounts_payable_id')
                ->nullable()
                ->after('source_id')
                ->constrained('accounts_payables')
                ->nullOnDelete();
        });

        DB::table('branch_expenses')
            ->where('expense_type', 'regular')
            ->update(['expense_type' => 'operating']);

        DB::table('branch_expenses')
            ->where('expense_type', 'cash_advance')
            ->update(['expense_type' => 'other']);

        DB::table('branch_expenses')
            ->where('paid_from', 'owner')
            ->whereNull('accounts_payable_id')
            ->orderBy('id')
            ->get()
            ->each(function ($expense): void {
                $payableId = DB::table('accounts_payables')->insertGetId([
                    'branch_id' => $expense->branch_id,
                    'created_by' => $expense->created_by,
                    'payable_number' => 'AP-LEGACY-'.str_pad((string) $expense->id, 6, '0', STR_PAD_LEFT),
                    'creditor_name' => 'Owner',
                    'source_type' => 'owner_paid_expense',
                    'source_id' => $expense->id,
                    'funding_method' => $expense->payment_method ?: 'cash',
                    'reference_no' => $expense->reference_no,
                    'description' => 'Reimbursement for '.$expense->title,
                    'original_amount' => $expense->amount,
                    'paid_amount' => 0,
                    'balance' => $expense->amount,
                    'status' => 'unpaid',
                    'funded_at' => $expense->expense_date,
                    'created_at' => $expense->created_at ?? now(),
                    'updated_at' => now(),
                ]);

                DB::table('branch_expenses')
                    ->where('id', $expense->id)
                    ->update(['accounts_payable_id' => $payableId]);
            });

        DB::table('users')
            ->whereNotNull('access')
            ->orderBy('id')
            ->get(['id', 'access'])
            ->each(function ($user): void {
                $access = json_decode((string) $user->access, true);
                if (! is_array($access) || ! in_array('expenses', $access, true) || in_array('accounts_payable', $access, true)) {
                    return;
                }

                $access[] = 'accounts_payable';
                DB::table('users')->where('id', $user->id)->update([
                    'access' => json_encode(array_values($access)),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('branch_expenses')
            ->where('expense_type', 'operating')
            ->update(['expense_type' => 'regular']);

        Schema::table('branch_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounts_payable_id');
        });

        Schema::dropIfExists('accounts_payable_payments');
        Schema::dropIfExists('accounts_payables');
    }
};
