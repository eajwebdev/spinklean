<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baggage Counter: customers leave luggage under a numbered claim tag (01, 02, ...).
 * Each deposit is a paid sale (recorded as a product-sale Job Order so it flows into
 * payments, Z Reading and reports). The tag is free again once the bag is released.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('branches', 'baggage_tag_count')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->unsignedSmallInteger('baggage_tag_count')->default(0)->after('machine_count');
            });
        }

        if (! Schema::hasColumn('laundry_services', 'baggage_size')) {
            Schema::table('laundry_services', function (Blueprint $table) {
                // small / medium / large / overnight: the storage rate services of the Baggage Counter.
                $table->string('baggage_size', 20)->nullable()->after('sale_inventory_id');
            });
        }

        if (! Schema::hasTable('baggage_deposits')) {
            Schema::create('baggage_deposits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('job_order_id')->nullable()->constrained('job_orders')->nullOnDelete();
                $table->unsignedSmallInteger('tag_number');
                $table->string('size', 20);
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('payment_type', 20)->default('cash');
                // dateTime (not timestamp): MySQL strict mode rejects a 2nd NOT NULL timestamp without a default.
                $table->dateTime('deposited_at');
                $table->dateTime('due_at');
                $table->timestamp('released_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->foreignId('deposited_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'tag_number', 'released_at', 'voided_at'], 'baggage_active_tag_index');
                $table->index(['branch_id', 'deposited_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('baggage_deposits');

        if (Schema::hasColumn('laundry_services', 'baggage_size')) {
            Schema::table('laundry_services', function (Blueprint $table) {
                $table->dropColumn('baggage_size');
            });
        }

        if (Schema::hasColumn('branches', 'baggage_tag_count')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('baggage_tag_count');
            });
        }
    }
};
