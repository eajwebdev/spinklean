<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('money_movements')) {
            Schema::create('money_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('movement_date');
                $table->string('type');
                $table->string('direction');
                $table->decimal('amount', 12, 2);
                $table->string('reference_no')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'movement_date']);
                $table->index(['type', 'direction']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('money_movements');
    }
};
