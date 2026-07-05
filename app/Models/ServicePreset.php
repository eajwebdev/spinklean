<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePreset extends Model
{
    protected $fillable = ['branch_id', 'service_category_id', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function serviceCategory()
    {
        return $this->belongsTo(LaundryServiceCategory::class, 'service_category_id');
    }

    public function items()
    {
        return $this->hasMany(ServicePresetItem::class);
    }
}
