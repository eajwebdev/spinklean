<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoTransaction extends Model
{
    public const STATUSES = ['pending', 'billed', 'partially_paid', 'paid'];

    protected $fillable = [
        'branch_id',
        'customer_id',
        'job_order_id',
        'company_name',
        'po_number',
        'transaction_date',
        'amount',
        'paid_amount',
        'balance',
        'status',
        'billed_at',
        'paid_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'billed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function jobOrder() { return $this->belongsTo(JobOrder::class); }
    public function payments() { return $this->hasMany(PoTransactionPayment::class); }
}
