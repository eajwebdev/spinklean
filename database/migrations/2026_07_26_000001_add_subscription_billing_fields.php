<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'subscription_price')) {
            Schema::table('branches', function (Blueprint $table) {
                // Fixed monthly subscription price used to auto-generate future billing cycles.
                $table->decimal('subscription_price', 12, 2)->nullable()->after('machine_count');
            });
        }

        if (Schema::hasTable('branch_billing_records')) {
            Schema::table('branch_billing_records', function (Blueprint $table) {
                if (! Schema::hasColumn('branch_billing_records', 'paymongo_checkout_id')) {
                    $table->string('paymongo_checkout_id')->nullable()->after('reference_no');
                }
                if (! Schema::hasColumn('branch_billing_records', 'paymongo_checkout_url')) {
                    $table->text('paymongo_checkout_url')->nullable()->after('paymongo_checkout_id');
                }
                if (! Schema::hasColumn('branch_billing_records', 'paymongo_payment_id')) {
                    $table->string('paymongo_payment_id')->nullable()->after('paymongo_checkout_url');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'subscription_price')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('subscription_price');
            });
        }

        if (Schema::hasTable('branch_billing_records')) {
            Schema::table('branch_billing_records', function (Blueprint $table) {
                foreach (['paymongo_checkout_id', 'paymongo_checkout_url', 'paymongo_payment_id'] as $column) {
                    if (Schema::hasColumn('branch_billing_records', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
