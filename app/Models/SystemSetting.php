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
        'maintenance_mode',
        'maintenance_message',
        'maintenance_started_at',
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
        'maintenance_mode' => 'boolean',
        'maintenance_started_at' => 'datetime',
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
            'sms_template_order_received' => "Hi {name}. We've received your laundry at Spin Klean {branch}. Order #: {order_no}. Please keep this order number. Present it when claiming your laundry. If you requested delivery, please wait for our confirmation SMS once your order is ready.\n\nFor inquiries or booking:\nFacebook: Spin Klean Laundry CDO\n{branch_number}\n\nThis is an automated message. Please do not reply.",
            'sms_template_delivery_received' => "Hi {name}. We've picked up your laundry for Spin Klean {branch}. Order #: {order_no}. Please keep this order number. We'll send a confirmation SMS once your order is ready for delivery.\n\nFor inquiries or booking:\nFacebook: Spin Klean Laundry CDO\n{branch_number}\n\nThis is an automated message. Please do not reply.",
            'sms_template_ready_for_pickup' => "Hi {name}! 👋 Your laundry is READY. Order #: {order_no}\nTotal: {total}\n\nFor Pick-up: present your order number during pick up.\n\nFor Delivery: Please reply with your preferred delivery time. Payment must be made before delivery via GCash payment link or exact cash.\n{pay_link}\n\n{branch_number}\n\nThis is an automated message. Please do not reply.",
            'sms_template_ready_for_delivery' => "Hi {name}! 👋 Your laundry is READY for delivery. Order #: {order_no}\nTotal: {total}\n\nPlease reply with your preferred delivery time. Payment must be made before delivery via GCash payment link or exact cash.\n{pay_link}\n\n{branch_number}\n\nThis is an automated message. Please do not reply.",
            'sms_template_completed' => "Hi {name}. Your laundry order #: {order_no} is COMPLETED.\nTotal: {total} | Balance: {balance}\n\nThank you for choosing Spin Klean {branch}!\nFacebook: Spin Klean Laundry CDO\n{branch_number}\n\nThis is an automated message. Please do not reply.",
        ];
    }

    public function isUnderMaintenance(): bool
    {
        return (bool) ($this->maintenance_mode ?? false);
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
