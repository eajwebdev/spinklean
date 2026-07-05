<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_billing_records')) {
            return;
        }

        Schema::table('branch_billing_records', function (Blueprint $table) {
            $table->index(['branch_id', 'billing_month', 'billing_year'], 'branch_billing_period_index');
            $table->dropUnique('branch_billing_period_unique');

            if (! Schema::hasColumn('branch_billing_records', 'subscription_start_date')) {
                $table->date('subscription_start_date')->nullable()->after('billing_year');
            }

            if (! Schema::hasColumn('branch_billing_records', 'subscription_end_date')) {
                $table->date('subscription_end_date')->nullable()->after('subscription_start_date');
            }

            $table->index(['branch_id', 'subscription_start_date', 'subscription_end_date'], 'branch_billing_subscription_dates_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('branch_billing_records')) {
            return;
        }

        Schema::table('branch_billing_records', function (Blueprint $table) {
            $table->dropIndex('branch_billing_period_index');
            $table->dropIndex('branch_billing_subscription_dates_index');

            if (Schema::hasColumn('branch_billing_records', 'subscription_start_date')) {
                $table->dropColumn('subscription_start_date');
            }

            if (Schema::hasColumn('branch_billing_records', 'subscription_end_date')) {
                $table->dropColumn('subscription_end_date');
            }

            $table->unique(['branch_id', 'billing_month', 'billing_year'], 'branch_billing_period_unique');
        });
    }
};
