<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A product sale is a Job Order that only sells add-on products (Ariel, Downy,
     * plastic bottles, ...). It is sold from the branch's own stock, never transferred
     * to a processing branch, and is completed/released the moment it is saved.
     */
    public function up(): void
    {
        if (Schema::hasColumn('job_orders', 'is_product_sale')) {
            return;
        }

        Schema::table('job_orders', function (Blueprint $table) {
            $table->boolean('is_product_sale')->default(false)->after('transaction_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('job_orders', 'is_product_sale')) {
            return;
        }

        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('is_product_sale');
        });
    }
};
