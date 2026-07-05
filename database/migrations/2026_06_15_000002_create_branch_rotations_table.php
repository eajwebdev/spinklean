<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_rotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_employee_id')->nullable()->constrained('attendance_employees')->nullOnDelete();
            $table->foreignId('home_branch_id')->constrained('branches');
            $table->foreignId('assigned_branch_id')->constrained('branches');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('notes', 500)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'starts_on', 'ends_on'], 'branch_rotations_user_dates_index');
            $table->index(['attendance_employee_id', 'starts_on', 'ends_on'], 'branch_rotations_employee_dates_index');
            $table->index(['assigned_branch_id', 'starts_on', 'ends_on'], 'branch_rotations_branch_dates_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_rotations');
    }
};
