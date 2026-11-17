<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use SoftDeletes;

    protected $fillable = ['branch_id', 'supplier_id', 'name', 'sku', 'unit', 'quantity', 'reorder_level', 'unit_cost', 'is_active', 'is_add_on', 'add_on_type', 'is_for_sale', 'selling_price'];

    protected $casts = ['quantity' => 'decimal:2', 'reorder_level' => 'decimal:2', 'unit_cost' => 'decimal:2', 'selling_price' => 'decimal:2', 'is_active' => 'boolean', 'is_add_on' => 'boolean', 'is_for_sale' => 'boolean'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function addOnService() { return $this->hasOne(LaundryService::class, 'add_on_inventory_id'); }
    public function saleService() { return $this->hasOne(LaundryService::class, 'sale_inventory_id'); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function movements() { return $this->hasMany(InventoryMovement::class); }
}
