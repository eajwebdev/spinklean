<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOrder extends Model
{
    use SoftDeletes;

    protected $fillable = ['branch_id', 'processing_branch_id', 'current_branch_id', 'release_branch_id', 'customer_id', 'created_by', 'job_order_number', 'status', 'transaction_type', 'is_rush', 'subtotal', 'discount', 'tax', 'total', 'paid_amount', 'balance', 'notes', 'completed_at', 'production_completed_at', 'production_accepted_at', 'inventory_deducted_at', 'returned_to_branch_at', 'released_at'];

    protected $casts = ['is_rush' => 'boolean', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'balance' => 'decimal:2', 'completed_at' => 'datetime', 'production_completed_at' => 'datetime', 'production_accepted_at' => 'datetime', 'inventory_deducted_at' => 'datetime', 'returned_to_branch_at' => 'datetime', 'released_at' => 'datetime'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function processingBranch() { return $this->belongsTo(Branch::class, 'processing_branch_id'); }
    public function currentBranch() { return $this->belongsTo(Branch::class, 'current_branch_id'); }
    public function releaseBranch() { return $this->belongsTo(Branch::class, 'release_branch_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function items() { return $this->hasMany(JobOrderItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function poTransaction() { return $this->hasOne(PoTransaction::class); }
    public function cycles() { return $this->hasMany(CycleRecord::class); }

    public function scopeRegularReceivable($query)
    {
        return $query
            ->whereDoesntHave('poTransaction')
            ->whereDoesntHave('customer', fn ($query) => $query->where('billing_type', 'po'));
    }

    public function allCyclesDone()
    {
        return ! $this->cycles()->whereNull('ended_at')->exists();
    }

    public function endActiveCycles(): int
    {
        return $this->cycles()->whereNull('ended_at')->update(['ended_at' => now()]);
    }
}
