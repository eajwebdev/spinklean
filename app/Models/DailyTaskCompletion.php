<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTaskCompletion extends Model
{
    protected $fillable = ['daily_task_id', 'branch_id', 'completed_by', 'completed_by_employee_id', 'work_date', 'photo_path', 'remarks', 'completed_at'];

    protected $casts = ['work_date' => 'date', 'completed_at' => 'datetime'];

    public function task() { return $this->belongsTo(DailyTask::class, 'daily_task_id'); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function completer() { return $this->belongsTo(User::class, 'completed_by'); }
    public function employeeCompleter() { return $this->belongsTo(AttendanceEmployee::class, 'completed_by_employee_id'); }
}
