<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTask extends Model
{
    protected $fillable = ['branch_id', 'name', 'requires_photo', 'affects_machine_counter', 'is_active'];

    protected $casts = ['requires_photo' => 'boolean', 'is_active' => 'boolean'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function completions() { return $this->hasMany(DailyTaskCompletion::class); }

    public function affectsMachineCounter(): bool
    {
        return in_array($this->affects_machine_counter, ['wash', 'dry', 'both'], true);
    }

    public function affectsWash(): bool
    {
        return in_array($this->affects_machine_counter, ['wash', 'both'], true);
    }

    public function affectsDry(): bool
    {
        return in_array($this->affects_machine_counter, ['dry', 'both'], true);
    }

    public function machineImpactLabel(): string
    {
        return match ($this->affects_machine_counter) {
            'wash' => 'Washers (+1 wash cycle)',
            'dry' => 'Dryers (+1 dry cycle)',
            'both' => 'Washers & Dryers (+1 cycle)',
            default => 'None',
        };
    }
}
