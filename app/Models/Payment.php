<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['branch_id', 'collected_branch_id', 'job_order_id', 'customer_id', 'received_by', 'payment_number', 'payment_type', 'reference_no', 'amount', 'remarks', 'settlement_status', 'paid_at'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function collectedBranch() { return $this->belongsTo(Branch::class, 'collected_branch_id'); }
    public function jobOrder() { return $this->belongsTo(JobOrder::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            $payment->collected_branch_id ??= $payment->branch_id;
            $payment->settlement_status ??= (int) $payment->collected_branch_id === (int) $payment->branch_id ? 'local' : 'pending';
        });
    }
}
