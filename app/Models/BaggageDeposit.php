<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaggageDeposit extends Model
{
    protected $fillable = [
        'branch_id',
        'job_order_id',
        'tag_number',
        'size',
        'amount',
        'payment_type',
        'deposited_at',
        'due_at',
        'released_at',
        'voided_at',
        'deposited_by',
        'released_by',
        'notes',
    ];

    protected $casts = [
        'tag_number' => 'integer',
        'amount' => 'decimal:2',
        'deposited_at' => 'datetime',
        'due_at' => 'datetime',
        'released_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function jobOrder()
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function depositor()
    {
        return $this->belongsTo(User::class, 'deposited_by');
    }

    public function releaser()
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /** Bags still at the counter (tag occupied). */
    public function scopeActive($query)
    {
        return $query->whereNull('released_at')->whereNull('voided_at');
    }

    public function isActive(): bool
    {
        return ! $this->released_at && ! $this->voided_at;
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && $this->due_at && now()->greaterThan($this->due_at);
    }

    public function tagLabel(): string
    {
        return str_pad((string) $this->tag_number, 2, '0', STR_PAD_LEFT);
    }
}
