<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'contact_number', 'email', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function inventories() { return $this->hasMany(Inventory::class); }
}
