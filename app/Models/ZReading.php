<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZReading extends Model
{
    protected $fillable = [
        'branch_id',
        'prepared_by',
        'reading_number',
        'business_date',
        'cash_count',
        'payment_breakdown',
        'expense_breakdown',
        'machine_counters',
        'expected_cash_amount',
        'cash_expense_amount',
        'expected_cash_drawer_amount',
        'actual_cash_amount',
        'expected_gcash_amount',
        'actual_gcash_amount',
        'expected_bank_amount',
        'actual_bank_amount',
        'expected_total_amount',
        'actual_total_amount',
        'over_short_amount',
        'transaction_count',
        'first_job_order_number',
        'last_job_order_number',
        'signature_name',
        'remarks',
        'closed_at',
    ];

    protected $casts = [
        'business_date' => 'date',
        'cash_count' => 'array',
        'payment_breakdown' => 'array',
        'expense_breakdown' => 'array',
        'machine_counters' => 'array',
        'expected_cash_amount' => 'decimal:2',
        'cash_expense_amount' => 'decimal:2',
        'expected_cash_drawer_amount' => 'decimal:2',
        'actual_cash_amount' => 'decimal:2',
        'expected_gcash_amount' => 'decimal:2',
        'actual_gcash_amount' => 'decimal:2',
        'expected_bank_amount' => 'decimal:2',
        'actual_bank_amount' => 'decimal:2',
        'expected_total_amount' => 'decimal:2',
        'actual_total_amount' => 'decimal:2',
        'over_short_amount' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
