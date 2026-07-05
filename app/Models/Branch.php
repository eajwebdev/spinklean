<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'contact_number',
        'branch_type',
        'latitude',
        'longitude',
        'attendance_radius_meters',
        'machine_count',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'attendance_radius_meters' => 'integer',
        'machine_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function isPickupDropoff(): bool
    {
        return $this->branch_type === 'pickup_dropoff';
    }

    public function isFullService(): bool
    {
        return $this->branch_type !== 'pickup_dropoff';
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function setting()
    {
        return $this->hasOne(BranchSetting::class);
    }

    public function billingRecords()
    {
        return $this->hasMany(BranchBillingRecord::class);
    }

    public function expenses()
    {
        return $this->hasMany(BranchExpense::class);
    }

    public function dailyTasks()
    {
        return $this->hasMany(DailyTask::class);
    }
}
