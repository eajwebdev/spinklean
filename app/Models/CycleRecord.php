<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CycleRecord extends Model
{
    protected $fillable = ['job_order_id', 'user_id', 'cycle_type', 'machine_number', 'cycle_number', 'pieces', 'started_at', 'ended_at', 'notes'];

    protected $casts = ['pieces' => 'decimal:2', 'started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function jobOrder() { return $this->belongsTo(JobOrder::class); }
    public function user() { return $this->belongsTo(User::class); }
}
