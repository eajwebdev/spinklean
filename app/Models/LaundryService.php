<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaundryService extends Model
{
    use SoftDeletes;

    protected $fillable = ['branch_id', 'service_category_id', 'name', 'report_category', 'pricing_type', 'price', 'is_active'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function serviceCategory() { return $this->belongsTo(LaundryServiceCategory::class, 'service_category_id'); }
    public function inventoryUsages() { return $this->hasMany(ServiceInventoryUsage::class); }
}
