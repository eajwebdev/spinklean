<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaggageDeposit extends Model
{
    protected $fillable = [
        'branch_id',
        'job_order_id',
        'ticket_code',
        'tag_number',
        'claim_tag',
        'customer_id',
        'customer_name',
        'customer_phone',
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
        'claim_tag' => 'integer',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** Every cubicle stored under the same claim ticket (this one included). */
    public function ticketDeposits()
    {
        return $this->hasMany(self::class, 'ticket_code', 'ticket_code');
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

    /** Cubicle number, e.g. "03". */
    public function tagLabel(): string
    {
        return self::pad($this->tag_number);
    }

    /** The claim tag the customer holds for the whole ticket, e.g. "01". */
    public function claimLabel(): string
    {
        return self::pad($this->claim_tag ?: $this->tag_number);
    }

    public static function pad(?int $number): string
    {
        return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }
}
