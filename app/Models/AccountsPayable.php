<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountsPayable extends Model
{
    protected $fillable = [
        'branch_id',
        'created_by',
        'payable_number',
        'creditor_name',
        'source_type',
        'source_id',
        'funding_method',
        'reference_no',
        'description',
        'original_amount',
        'paid_amount',
        'balance',
        'status',
        'funded_at',
        'due_date',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'funded_at' => 'date',
        'due_date' => 'date',
    ];

    public static function nextNumber(): string
    {
        return 'AP-'.now()->format('Ymd').'-'.str_pad((string) (static::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    public function branch() { return $this->belongsTo(Branch::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function payments() { return $this->hasMany(AccountsPayablePayment::class); }
    public function expense() { return $this->hasOne(BranchExpense::class); }
}
