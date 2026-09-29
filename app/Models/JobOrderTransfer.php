<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobOrderTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_order_id',
        'job_order_number',
        'tag_number',
        'origin_branch_id',
        'destination_branch_id',
        'transfer_type',
        'transfer_status',
        'transferred_at',
        'transferred_by',
        'received_at',
        'received_by',
        'notes',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function jobOrder()
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function originBranch()
    {
        return $this->belongsTo(Branch::class, 'origin_branch_id');
    }

    public function destinationBranch()
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopePending($query)
    {
        return $query->where('transfer_status', 'pending');
    }

    public function scopeReceived($query)
    {
        return $query->where('transfer_status', 'received');
    }

    public function scopeOutbound($query)
    {
        return $query->where('transfer_type', 'outbound');
    }

    public function scopeReturn($query)
    {
        return $query->where('transfer_type', 'return');
    }

    public function isPending(): bool
    {
        return $this->transfer_status === 'pending';
    }

    public function isReceived(): bool
    {
        return $this->transfer_status === 'received';
    }

    public function isOutbound(): bool
    {
        return $this->transfer_type === 'outbound';
    }

    public function isReturn(): bool
    {
        return $this->transfer_type === 'return';
    }
}
