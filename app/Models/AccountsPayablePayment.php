<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountsPayablePayment extends Model
{
    protected $fillable = [
        'accounts_payable_id',
        'branch_id',
        'recorded_by',
        'money_movement_id',
        'payment_number',
        'payment_date',
        'payment_method',
        'amount',
        'reference_no',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function payable() { return $this->belongsTo(AccountsPayable::class, 'accounts_payable_id'); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
    public function moneyMovement() { return $this->belongsTo(MoneyMovement::class); }
}
