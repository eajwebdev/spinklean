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
     * Default SMS templates. These are written in a more formal tone so
     * customer notifications feel professional and polished. All placeholders
     * resolve case-insensitively via SmsNotifier.
     */
    public static function defaultSmsTemplates(): array
    {
        return [
            'sms_template_order_received' => "Dear {name}, thank you for choosing {store}. We have received your laundry order #{order_no} at {branch} for a total of {price}. You may pay online via https://spinklean.online/pay. We will notify you once your order is ready.",
            'sms_template_delivery_received' => "Dear {name}, thank you for choosing {store}. We have picked up your laundry order #{order_no} ({branch}) for a total of {price}. You may pay online via https://spinklean.online/pay. We will notify you once it is ready for delivery.",
            'sms_template_ready_for_pickup' => "Dear {name}, your laundry order #{order_no} is now READY FOR PICKUP at {branch}. Remaining balance: {balance}. You may pay online via https://spinklean.online/pay. Please present your order number upon pickup. Thank you!",
            'sms_template_ready_for_delivery' => "Dear {name}, your laundry order #{order_no} is now READY AND OUT FOR DELIVERY. Remaining balance: {balance}. You may pay online via https://spinklean.online/pay. Thank you for choosing {store}!",
            'sms_template_completed' => "Dear {name}, your laundry order #{order_no} has been COMPLETED. Total: {price}, Balance: {balance}. You may pay online via https://spinklean.online/pay. Thank you for choosing {store}! We appreciate your business.",
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
