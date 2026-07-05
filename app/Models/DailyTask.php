<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTask extends Model
{
    protected $fillable = ['branch_id', 'name', 'requires_photo', 'is_active'];

    protected $casts = ['requires_photo' => 'boolean', 'is_active' => 'boolean'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function completions() { return $this->hasMany(DailyTaskCompletion::class); }
}
