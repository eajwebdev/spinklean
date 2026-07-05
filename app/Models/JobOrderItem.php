<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobOrderItem extends Model
{
    protected $fillable = ['job_order_id', 'laundry_service_id', 'service_preset_id', 'description', 'service_category', 'quantity', 'unit_price', 'total', 'instructions'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total' => 'decimal:2'];

    public function jobOrder() { return $this->belongsTo(JobOrder::class); }
    public function service() { return $this->belongsTo(LaundryService::class, 'laundry_service_id'); }
    public function preset() { return $this->belongsTo(ServicePreset::class, 'service_preset_id'); }
}
