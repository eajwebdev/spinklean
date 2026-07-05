<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BranchBillingRecord extends Model
{
    protected $fillable = [
        'branch_id',
        'billing_month',
        'billing_year',
        'subscription_start_date',
        'subscription_end_date',
        'amount',
        'due_date',
        'status',
        'payment_date',
        'payment_method',
        'reference_no',
        'remarks',
        'paid_by',
        'generated_by',
        'expense_id',
    ];

    protected $casts = [
        'billing_month' => 'integer',
        'billing_year' => 'integer',
        'subscription_start_date' => 'date',
        'subscription_end_date' => 'date',
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_date' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function expense()
    {
        return $this->belongsTo(BranchExpense::class, 'expense_id');
    }

    public function periodLabel(): string
    {
        if ($this->subscription_start_date && $this->subscription_end_date) {
            return $this->subscription_start_date->format('M d, Y').' - '.$this->subscription_end_date->format('M d, Y');
        }

        return Carbon::create($this->billing_year, $this->billing_month, 1)->format('F Y');
    }

    public function graceEndsAt(int $graceDays): Carbon
    {
        return $this->due_date->copy()->addDays($graceDays);
    }

    public function isWithinGrace(int $graceDays, ?Carbon $date = null): bool
    {
        $date ??= now();

        return $date->startOfDay()->lessThanOrEqualTo($this->graceEndsAt($graceDays)->startOfDay());
    }
}
