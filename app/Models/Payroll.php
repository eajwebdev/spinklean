<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $fillable = ['branch_id', 'user_id', 'period_start', 'period_end', 'gross_pay', 'deductions', 'net_pay', 'status'];

    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'gross_pay' => 'decimal:2', 'deductions' => 'decimal:2', 'net_pay' => 'decimal:2'];
}
