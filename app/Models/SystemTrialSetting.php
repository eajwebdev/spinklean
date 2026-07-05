<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SystemTrialSetting extends Model
{
    protected $fillable = [
        'trial_enabled',
        'trial_start_date',
        'trial_end_date',
        'trial_status',
        'trial_remarks',
        'grace_period_days',
        'updated_by',
    ];

    protected $casts = [
        'trial_enabled' => 'boolean',
        'trial_start_date' => 'date',
        'trial_end_date' => 'date',
        'grace_period_days' => 'integer',
    ];

    public static function current(): self
    {
        if (! Schema::hasTable('system_trial_settings')) {
            return new self([
                'trial_enabled' => false,
                'trial_status' => 'inactive',
                'grace_period_days' => 0,
            ]);
        }

        return self::firstOrCreate(
            ['id' => 1],
            [
                'trial_enabled' => false,
                'trial_status' => 'inactive',
                'grace_period_days' => 0,
            ]
        );
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(?Carbon $date = null): bool
    {
        $date ??= now();

        return $this->trial_start_date
            && $this->trial_end_date
            && $date->toDateString() >= $this->trial_start_date->toDateString()
            && $date->toDateString() <= $this->trial_end_date->toDateString();
    }

    public function shouldEnforceBilling(?Carbon $date = null): bool
    {
        $date ??= now();

        if (! $this->trial_start_date || ! $this->trial_end_date) {
            return false;
        }

        return $date->toDateString() > $this->trial_end_date->toDateString();
    }

    public function computedStatus(?Carbon $date = null): string
    {
        $date ??= now();

        if ($this->isActive($date)) {
            return 'active';
        }

        if ($this->trial_end_date && $date->toDateString() > $this->trial_end_date->toDateString()) {
            return 'expired';
        }

        return 'inactive';
    }
}
