<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryServiceCategory extends Model
{
    protected $fillable = ['name', 'visibility', 'branch_id', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function services()
    {
        return $this->hasMany(LaundryService::class, 'service_category_id');
    }

    public function isAvailableFor(int|string $branchId): bool
    {
        return $this->visibility === 'all' || (string) $this->branch_id === (string) $branchId;
    }
}
