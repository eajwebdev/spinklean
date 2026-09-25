<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTaskCompletion extends Model
{
    protected $fillable = ['daily_task_id', 'branch_id', 'completed_by', 'completed_by_employee_id', 'work_date', 'photo_path', 'remarks', 'cleaned_machines', 'completed_at'];

    protected $casts = [
        'work_date' => 'date',
        'completed_at' => 'datetime',
        'cleaned_machines' => 'array',
    ];

    public function task() { return $this->belongsTo(DailyTask::class, 'daily_task_id'); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function completer() { return $this->belongsTo(User::class, 'completed_by'); }
    public function employeeCompleter() { return $this->belongsTo(AttendanceEmployee::class, 'completed_by_employee_id'); }

    public function cleanedWashMachines(): array
    {
        return array_values(array_map('intval', (array) data_get($this->cleaned_machines, 'wash', [])));
    }

    public function cleanedDryMachines(): array
    {
        return array_values(array_map('intval', (array) data_get($this->cleaned_machines, 'dry', [])));
    }

    public function hasCleanedMachines(): bool
    {
        return ! empty($this->cleanedWashMachines()) || ! empty($this->cleanedDryMachines());
    }

    public function cleanedMachinesSummary(): string
    {
        $parts = [];
        $wash = $this->cleanedWashMachines();
        $dry = $this->cleanedDryMachines();

        if (! empty($wash)) {
            $parts[] = 'Wash: ' . implode(', ', array_map(fn ($m) => "#{$m}", $wash));
        }

        if (! empty($dry)) {
            $parts[] = 'Dry: ' . implode(', ', array_map(fn ($m) => "#{$m}", $dry));
        }

        return implode(' | ', $parts);
    }
}
