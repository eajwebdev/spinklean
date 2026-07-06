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
            'sms_template_order_received' => 'Hi {name}! We received your laundry at {branch}. Order #{order_no}. Keep this no. for claiming/inquiries. We will text you once it is ready. Thank you!',
            'sms_template_delivery_received' => 'Hi {name}! We picked up your laundry for delivery ({branch}). Order #{order_no}. Keep this no. for inquiries. We will text you once ready. Thank you!',
            'sms_template_ready_for_pickup' => 'Hi {name}! Your laundry Order #{order_no} is ready for PICKUP at {branch}. Total: {total}. Please bring this order no. Thank you!',
            'sms_template_ready_for_delivery' => 'Hi {name}! Your laundry Order #{order_no} is ready and out for DELIVERY. Total: {total}, Balance: {balance}. Thank you!',
            'sms_template_completed' => 'Hi {name}! Your laundry Order #{order_no} is now completed. Total: {total}, Balance: {balance}. Thank you for choosing {store}!',
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
