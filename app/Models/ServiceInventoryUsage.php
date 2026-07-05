<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceInventoryUsage extends Model
{
    protected $fillable = ['laundry_service_id', 'inventory_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:4'];

    public function service() { return $this->belongsTo(LaundryService::class, 'laundry_service_id'); }
    public function inventory() { return $this->belongsTo(Inventory::class); }
}
