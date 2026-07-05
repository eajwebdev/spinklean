<?php

namespace App\Support;

use App\Models\JobOrder;
use App\Models\SmsLog;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SmsNotifier
{
    public static function jobOrderReceived(JobOrder $order): void
    {
        self::withoutInterruptingOperations(function () use ($order): void {
            $settings = SystemSetting::current();
            $order->loadMissing(['branch', 'customer']);
            $customer = $order->customer;

            if (! $settings->sms_enabled || ! $customer?->canReceiveSms()) {
                return;
            }

            $template = $order->transaction_type === 'delivery'
                ? 'sms_template_delivery_received'
                : 'sms_template_order_received';
            $message = self::renderTemplate($settings, $template, $order);

            self::queue($order, $message, $settings);
        });
    }

    public static function jobOrderStatus(JobOrder $order): void
    {
        self::withoutInterruptingOperations(function () use ($order): void {
            $settings = SystemSetting::current();
            $order->loadMissing(['branch', 'customer']);
            $customer = $order->customer;

            if (! $settings->sms_enabled || ! $customer?->canReceiveSms()) {
                return;
            }

            $template = match ($order->status) {
                'ready_for_pickup' => 'sms_template_ready_for_pickup',
                'ready_for_delivery' => 'sms_template_ready_for_delivery',
                'completed' => 'sms_template_completed',
                default => null,
            };

            if (! $template) {
                return;
            }

            $message = self::renderTemplate($settings, $template, $order);

            self::queue($order, $message, $settings);
        });
    }

    private static function withoutInterruptingOperations(callable $notification): void
    {
        try {
            $notification();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private static function queue(JobOrder $order, string $message, SystemSetting $settings): void
    {
        $customer = $order->customer;
        $log = SmsLog::create([
            'branch_id' => $order->branch_id,
            'customer_id' => $order->customer_id,
            'recipient' => $customer->phone,
            'message' => $message,
            'status' => 'queued',
            'response' => 'Waiting for SMS provider.',
        ]);

        self::send($log, $settings);
    }

    private static function send(SmsLog $log, SystemSetting $settings): void
    {
        match (Str::lower((string) $settings->sms_provider)) {
            'unisms' => self::sendWithUniSms($log, $settings),
            default => $log->update([
                'status' => 'queued',
                'response' => 'UniSMS is not configured for live sending.',
            ]),
        };
    }

    private static function sendWithUniSms(SmsLog $log, SystemSetting $settings): void
    {
        $secretKey = trim((string) $settings->sms_api_key);

        if ($secretKey === '') {
            $log->update([
                'status' => 'queued',
                'response' => 'UniSMS is selected but the API secret key is missing.',
            ]);

            return;
        }

        $payload = [
            'recipient' => self::normalizePhone($log->recipient),
            'content' => self::smsContent($log->message),
            'metadata' => [
                'sms_log_id' => (string) $log->id,
                'branch_id' => (string) $log->branch_id,
                'customer_id' => (string) $log->customer_id,
            ],
        ];
        $senderId = trim((string) $settings->unisms_sender_id);
        if ($senderId !== '') {
            $payload['sender_id'] = $senderId;
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->asJson()
                ->timeout(10)
                ->post('https://unismsapi.com/api/sms', $payload);

            $responsePayload = $response->json();
            $messageResult = is_array($responsePayload) ? ($responsePayload['message'] ?? $responsePayload) : [];
            $referenceId = $messageResult['reference_id'] ?? null;
            $providerStatus = Str::lower((string) ($messageResult['status'] ?? ''));
            $accepted = $response->created() && ! in_array($providerStatus, ['failed'], true);
            $error = $messageResult['fail_reason'] ?? $messageResult['message'] ?? $response->body();

            $log->update([
                'status' => $accepted ? 'sent' : 'failed',
                'response' => $accepted
                    ? 'UniSMS message accepted'.($referenceId ? " ({$referenceId})" : '.')
                    : Str::limit('UniSMS error: '.$error, 1000),
            ]);
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'response' => Str::limit('UniSMS request failed: '.$exception->getMessage(), 1000),
            ]);
        }
    }

    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]/', '', trim($phone)) ?: '';

        if (Str::startsWith($phone, '+')) {
            return $phone;
        }

        if (Str::startsWith($phone, '09')) {
            return '+63'.substr($phone, 1);
        }

        if (Str::startsWith($phone, '63')) {
            return '+'.$phone;
        }

        return $phone;
    }

    private static function smsContent(string $message): string
    {
        // UniSMS accepts up to 670 characters per message (multi-part SMS).
        // Use mb_* so multi-byte characters (emoji, ñ, curly quotes) are
        // counted and cut on character boundaries, not bytes.
        $message = trim($message);

        if (mb_strlen($message) <= 670) {
            return $message;
        }

        return mb_substr($message, 0, 670);
    }

    private static function renderTemplate(SystemSetting $settings, string $templateKey, JobOrder $order): string
    {
        $defaults = SystemSetting::defaultSmsTemplates();
        $template = trim((string) ($settings->{$templateKey} ?? ''));
        if ($template === '') {
            $template = $defaults[$templateKey] ?? '';
        }

        $values = self::templateValues($settings, $order);

        // Case-insensitive so {NAME}, {Name} and {name} all resolve.
        return str_ireplace(array_keys($values), array_values($values), $template);
    }

    private static function firstNamePart(string $name): string
    {
        // Split only on " - " (dash padded by spaces) so a hyphenated
        // name like "Mary-Jane" is preserved, but an address suffix like
        // "EDWIN - APARTMENT 2" is dropped.
        $parts = preg_split('/\s+-\s+/', trim($name), 2);

        return trim($parts[0] ?? $name);
    }

    private static function templateValues(SystemSetting $settings, JobOrder $order): array
    {
        $customer = $order->customer;
        $storeName = $settings->business_name ?: config('app.name');
        $currency = $settings->currency ?: 'PHP';

        // Customer names may carry an address suffix after " - "
        // (e.g. "EDWIN - APARTMENT 2"). For SMS we only want the name part.
        $name = self::firstNamePart((string) ($customer?->name ?? ''));
        $phone = (string) ($customer?->phone ?? '');
        $orderNumber = (string) $order->job_order_number;
        $branchName = (string) ($order->branch?->name ?? $storeName);
        $status = Str::headline((string) $order->status);
        $total = $currency.' '.number_format((float) $order->total, 2);
        $balance = $currency.' '.number_format((float) $order->balance, 2);

        return [
            // Canonical placeholders
            '{customer_name}' => $name,
            '{customer_phone}' => $phone,
            '{job_order_number}' => $orderNumber,
            '{store_name}' => (string) $storeName,
            '{branch_name}' => $branchName,
            '{status}' => $status,
            '{total}' => $total,
            '{balance}' => $balance,
            // Short aliases (case-insensitive variants handled in renderTemplate)
            '{name}' => $name,
            '{phone}' => $phone,
            '{order_no}' => $orderNumber,
            '{order_number}' => $orderNumber,
            '{branch}' => $branchName,
            '{store}' => (string) $storeName,
        ];
    }
}
