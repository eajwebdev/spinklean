<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchExpense extends Model
{
    protected $fillable = [
        'branch_id',
        'category',
        'expense_type',
        'title',
        'amount',
        'expense_date',
        'payment_method',
        'paid_from',
        'reference_no',
        'remarks',
        'source',
        'source_id',
        'accounts_payable_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accountsPayable()
    {
        return $this->belongsTo(AccountsPayable::class);
    }
}
