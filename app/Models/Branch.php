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
        'subscription_price',
        'qr_pay_url',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'attendance_radius_meters' => 'integer',
        'machine_count' => 'integer',
        'subscription_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function isPickupDropoff(): bool
    {
        return in_array($this->branch_type, ['pickup_dropoff', 'no_machine'], true);
    }

    public function isFullService(): bool
    {
        return ! $this->isPickupDropoff();
    }

    public function isMachineEquipped(): bool
    {
        return $this->isFullService();
    }

    public function isNoMachine(): bool
    {
        return $this->isPickupDropoff();
    }

    public function scopeMachineEquipped($query)
    {
        return $query->where('branch_type', 'full_service')->where('machine_count', '>', 0);
    }

    public function scopeNoMachine($query)
    {
        return $query->whereIn('branch_type', ['pickup_dropoff', 'no_machine']);
    }

    public function transfersIn()
    {
        return $this->hasMany(JobOrderTransfer::class, 'destination_branch_id');
    }

    public function transfersOut()
    {
        return $this->hasMany(JobOrderTransfer::class, 'origin_branch_id');
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
