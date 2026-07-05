<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'branch_id', 'action', 'subject_type', 'subject_id', 'properties', 'ip_address'];

    protected $casts = ['properties' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
}
