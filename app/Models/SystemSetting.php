<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'business_name',
        'business_logo',
        'business_email',
        'contact_number',
        'business_address',
        'receipt_header',
        'receipt_footer',
        'currency',
        'vat_enabled',
        'vat_rate',
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
        'sms_template_order_received',
        'sms_template_delivery_received',
        'sms_template_ready_for_pickup',
        'sms_template_ready_for_delivery',
        'sms_template_completed',
        'primary_color',
        'dark_mode_default',
        'is_completed',
    ];

    protected $casts = [
        'vat_enabled' => 'boolean',
        'vat_rate' => 'decimal:2',
        'operating_hours' => 'array',
        'default_price_per_kilo' => 'decimal:2',
        'default_price_per_load' => 'decimal:2',
        'default_price_per_piece' => 'decimal:2',
        'sms_enabled' => 'boolean',
        'dark_mode_default' => 'boolean',
        'is_completed' => 'boolean',
    ];

    public static function current(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'business_name' => 'SPIN KLEAN LAUNDRY',
                'currency' => 'PHP',
                'job_order_prefix' => 'JO',
                'invoice_prefix' => 'INV',
                'sms_provider' => 'unisms',
                'primary_color' => '#2E7D32',
            ]
        );
    }

    /**
     * Default SMS templates. Kept short and detailed so each notification
     * stays close to a single 160-character SMS segment (1 credit). All
     * placeholders resolve case-insensitively via SmsNotifier.
     */
    public static function defaultSmsTemplates(): array
    {
        return [
            'sms_template_order_received' => "Hi {name}! Recv JO #{order_no} at {branch}. Total: {price}. Pay: https://spinklean.online/pay. We'll msg when ready. Tnk u!",
            'sms_template_delivery_received' => "Hi {name}! Picked up JO #{order_no} for delivery ({branch}). Total: {price}. Pay: https://spinklean.online/pay. We'll msg when ready!",
            'sms_template_ready_for_pickup' => "Hi {name}! JO #{order_no} is READY FOR PICKUP at {branch}. Bal: {balance}. Pay: https://spinklean.online/pay. Pls bring JO #. Tnk u!",
            'sms_template_ready_for_delivery' => "Hi {name}! JO #{order_no} is READY FOR DELIVERY. Bal: {balance}. Pay: https://spinklean.online/pay. Tnk u - {store}!",
            'sms_template_completed' => "Hi {name}! JO #{order_no} is COMPLETED. Bal: {balance}. Pay: https://spinklean.online/pay. Tnk u for choosing {store}!",
        ];
    }

    public function isComplete(): bool
    {
        return filled($this->business_name)
            && filled($this->contact_number)
            && filled($this->business_address)
            && filled($this->currency)
            && filled($this->job_order_prefix)
            && filled($this->invoice_prefix);
    }
}
