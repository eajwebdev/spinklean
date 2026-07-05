<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLedger extends Model
{
    protected $fillable = ['branch_id', 'customer_id', 'job_order_id', 'payment_id', 'entry_type', 'amount', 'running_balance', 'description'];

    protected $casts = ['amount' => 'decimal:2', 'running_balance' => 'decimal:2'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function jobOrder() { return $this->belongsTo(JobOrder::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
}
