<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchSetting extends Model
{
    protected $fillable = [
        'branch_id',
        'receipt_header',
        'receipt_footer',
        'operating_hours',
        'default_price_per_kilo',
        'default_price_per_load',
        'default_price_per_piece',
        'job_order_prefix',
        'invoice_prefix',
        'sms_provider',
        'sms_api_key',
        'unisms_sender_id',
        'sms_enabled',
    ];

    protected $casts = [
        'operating_hours' => 'array',
        'default_price_per_kilo' => 'decimal:2',
        'default_price_per_load' => 'decimal:2',
        'default_price_per_piece' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
