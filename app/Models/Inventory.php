<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use SoftDeletes;

    protected $fillable = ['branch_id', 'supplier_id', 'name', 'sku', 'unit', 'quantity', 'reorder_level', 'unit_cost', 'is_active'];

    protected $casts = ['quantity' => 'decimal:2', 'reorder_level' => 'decimal:2', 'unit_cost' => 'decimal:2', 'is_active' => 'boolean'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function movements() { return $this->hasMany(InventoryMovement::class); }
}
