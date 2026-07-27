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
        'paymongo_checkout_id',
        'paymongo_checkout_url',
        'paymongo_payment_id',
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

    public function isPayable(): bool
    {
        return in_array($this->status, ['unpaid', 'overdue', 'suspended'], true);
    }

    /**
     * Whole days the record is past its due date (0 when not yet due).
     */
    public function daysPastDue(?Carbon $date = null): int
    {
        $date ??= now();

        if (! $this->due_date) {
            return 0;
        }

        $diff = (int) $this->due_date->copy()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        return max(0, $diff);
    }

    /**
     * The branch is hard-locked once it is overdue by at least the grace days.
     */
    public function isLocked(int $lockGraceDays, ?Carbon $date = null): bool
    {
        if ($this->status === 'paid') {
            return false;
        }

        if ($this->status === 'suspended') {
            return true;
        }

        return $this->daysPastDue($date) >= $lockGraceDays;
    }
}
