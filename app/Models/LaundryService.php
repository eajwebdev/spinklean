<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaundryService extends Model
{
    use SoftDeletes;

    protected $fillable = ['branch_id', 'service_category_id', 'name', 'report_category', 'add_on_inventory_id', 'sale_inventory_id', 'pricing_type', 'price', 'is_active'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function serviceCategory() { return $this->belongsTo(LaundryServiceCategory::class, 'service_category_id'); }
    public function inventoryUsages() { return $this->hasMany(ServiceInventoryUsage::class); }
    public function addOnInventory() { return $this->belongsTo(Inventory::class, 'add_on_inventory_id'); }
    public function saleInventory() { return $this->belongsTo(Inventory::class, 'sale_inventory_id'); }

    /** Sold under "Add Ons" (goes with laundry, e.g. Downy for the wash). */
    public function isAddOn(): bool
    {
        return (bool) $this->add_on_inventory_id;
    }

    /** Sold under "Sell Products": can be sold on its own as a product sale. */
    public function isSaleProduct(): bool
    {
        return (bool) $this->sale_inventory_id;
    }

    /** Any service that is really an inventory product (managed from Inventory, stock tracked). */
    public function isInventoryProduct(): bool
    {
        return $this->isAddOn() || $this->isSaleProduct();
    }

    /** The inventory item whose stock this product uses. */
    public function productInventory(): ?Inventory
    {
        return $this->addOnInventory ?? $this->saleInventory;
    }
}
