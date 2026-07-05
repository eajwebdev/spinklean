<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneyMovement extends Model
{
    public const TYPES = [
        'withdraw' => ['label' => 'Withdraw', 'direction' => 'out'],
        'deposit' => ['label' => 'Deposit', 'direction' => 'in'],
    ];

    public const LEGACY_TYPES = [
        'owner_cash_in' => ['label' => 'Deposit', 'direction' => 'in'],
        'cash_float_in' => ['label' => 'Deposit', 'direction' => 'in'],
        'sales_withdrawal' => ['label' => 'Withdraw', 'direction' => 'out'],
        'bank_deposit' => ['label' => 'Withdraw', 'direction' => 'out'],
        'cash_out' => ['label' => 'Withdraw', 'direction' => 'out'],
    ];

    protected $fillable = [
        'branch_id',
        'recorded_by',
        'movement_date',
        'type',
        'direction',
        'amount',
        'reference_no',
        'description',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label']
            ?? self::LEGACY_TYPES[$this->type]['label']
            ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public static function typeOptions(): array
    {
        return self::TYPES;
    }
}
