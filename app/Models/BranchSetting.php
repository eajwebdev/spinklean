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
        'soa_tin',
        'soa_bank_name',
        'soa_account_name',
        'soa_account_number',
        'soa_email',
        'soa_viber',
    ];

    /**
     * Statement of Account payment details used when a branch has not entered its own
     * (taken from the business's existing statement template).
     */
    public const SOA_DEFAULTS = [
        'soa_tin' => '619-588-144-0001',
        'soa_bank_name' => 'Metropolitan bank and trust co (Metrobank)',
        'soa_account_name' => 'SPINKLEAN LAUNDRY SERVICES',
        'soa_account_number' => '0377-0375-5529-7',
        'soa_email' => 'spinkleanlaundrycdo@gmail.com',
        'soa_viber' => '0917-338-7546',
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

    public static function statementDetailsFor(?self $setting): array
    {
        return collect(self::SOA_DEFAULTS)
            ->map(fn (string $default, string $key) => filled($setting?->{$key}) ? trim((string) $setting->{$key}) : $default)
            ->all();
    }
}
