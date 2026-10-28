<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Washed-and-dried pieces of a PO job order marked ready for delivery or pickup.
 */
class PoReadyBatch extends Model
{
    public const READY_FOR = ['delivery', 'pickup'];

    protected $fillable = ['job_order_id', 'pieces', 'ready_for', 'user_id'];

    protected $casts = ['pieces' => 'decimal:2'];

    public function jobOrder()
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
