<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_settings')) {
            Schema::create('branch_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();
                $table->text('receipt_header')->nullable();
                $table->text('receipt_footer')->nullable();
                $table->json('operating_hours')->nullable();
                $table->decimal('default_price_per_kilo', 12, 2)->nullable();
                $table->decimal('default_price_per_load', 12, 2)->nullable();
                $table->decimal('default_price_per_piece', 12, 2)->nullable();
                $table->string('job_order_prefix')->nullable();
                $table->string('invoice_prefix')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_settings');
    }
};
