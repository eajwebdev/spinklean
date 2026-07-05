<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = ['inventory_id', 'user_id', 'movement_type', 'quantity', 'remarks'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function inventory() { return $this->belongsTo(Inventory::class); }
    public function user() { return $this->belongsTo(User::class); }
}
