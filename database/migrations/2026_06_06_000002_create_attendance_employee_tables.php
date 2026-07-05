<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_employees')) {
            Schema::create('attendance_employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('phone')->nullable();
                $table->string('username')->unique();
                $table->string('password');
                $table->decimal('daily_rate', 10, 2)->default(0);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('employee_attendance_records')) {
            Schema::create('employee_attendance_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_employee_id')->constrained('attendance_employees')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->date('work_date');
                $table->json('clock_in')->nullable();
                $table->json('clock_out')->nullable();
                $table->json('clock_in_photos')->nullable();
                $table->json('clock_out_photos')->nullable();
                $table->json('clock_in_locations')->nullable();
                $table->json('clock_out_locations')->nullable();
                $table->timestamps();

                $table->unique(['attendance_employee_id', 'work_date'], 'employee_attendance_work_date_unique');
                $table->index(['branch_id', 'work_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_records');
        Schema::dropIfExists('attendance_employees');
    }
};
