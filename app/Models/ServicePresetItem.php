<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePresetItem extends Model
{
    protected $fillable = ['service_preset_id', 'laundry_service_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function preset()
    {
        return $this->belongsTo(ServicePreset::class, 'service_preset_id');
    }

    public function service()
    {
        return $this->belongsTo(LaundryService::class, 'laundry_service_id');
    }
}
