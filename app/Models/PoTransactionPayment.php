<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoTransactionPayment extends Model
{
    protected $fillable = [
        'po_transaction_id',
        'branch_id',
        'customer_id',
        'job_order_id',
        'received_by',
        'payment_number',
        'payment_method',
        'reference_no',
        'amount',
        'remarks',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function poTransaction() { return $this->belongsTo(PoTransaction::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function jobOrder() { return $this->belongsTo(JobOrder::class); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
}
