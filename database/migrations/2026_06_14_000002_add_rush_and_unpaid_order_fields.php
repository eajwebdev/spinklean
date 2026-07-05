<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('job_orders', 'is_rush')) {
                $table->boolean('is_rush')->default(false)->after('transaction_type')->index();
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'credit_limit') && ! Schema::hasColumn('customers', 'unpaid_limit')) {
                $table->renameColumn('credit_limit', 'unpaid_limit');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('cash', 'gcash', 'bank', 'credit', 'unpaid', 'po', 'monthly_billing') NOT NULL");
        }

        DB::table('payments')
            ->where('payment_type', 'credit')
            ->update(['payment_type' => 'unpaid']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('cash', 'gcash', 'bank', 'unpaid', 'po', 'monthly_billing') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('cash', 'gcash', 'bank', 'credit', 'unpaid', 'po', 'monthly_billing') NOT NULL");
        }

        DB::table('payments')
            ->where('payment_type', 'unpaid')
            ->update(['payment_type' => 'credit']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('cash', 'gcash', 'bank', 'credit', 'po', 'monthly_billing') NOT NULL");
        }

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'unpaid_limit') && ! Schema::hasColumn('customers', 'credit_limit')) {
                $table->renameColumn('unpaid_limit', 'credit_limit');
            }
        });

        Schema::table('job_orders', function (Blueprint $table) {
            if (Schema::hasColumn('job_orders', 'is_rush')) {
                $table->dropIndex(['is_rush']);
                $table->dropColumn('is_rush');
            }
        });
    }
};
