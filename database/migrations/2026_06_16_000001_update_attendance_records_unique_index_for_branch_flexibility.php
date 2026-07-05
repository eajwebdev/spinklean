<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_attendance_records', function (Blueprint $table) {
            if (! $this->hasIndex('employee_attendance_records', 'employee_attendance_branch_work_date_unique')) {
                $table->unique(['attendance_employee_id', 'branch_id', 'work_date'], 'employee_attendance_branch_work_date_unique');
            }

            if ($this->hasIndex('employee_attendance_records', 'employee_attendance_work_date_unique')) {
                $table->dropUnique('employee_attendance_work_date_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_attendance_records', function (Blueprint $table) {
            if (! $this->hasIndex('employee_attendance_records', 'employee_attendance_work_date_unique')) {
                $table->unique(['attendance_employee_id', 'work_date'], 'employee_attendance_work_date_unique');
            }

            if ($this->hasIndex('employee_attendance_records', 'employee_attendance_branch_work_date_unique')) {
                $table->dropUnique('employee_attendance_branch_work_date_unique');
            }
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $name);
    }
};
