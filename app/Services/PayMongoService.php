<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\BranchBillingRecord;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayMongoService
{
    private string $baseUrl = 'https://api.paymongo.com/v1';

    private function secretKey(): string
    {
        $key = config('services.paymongo.secret');

        if (empty($key)) {
            throw new RuntimeException('PayMongo secret key is not configured. Set PAYMONGO_SECRET_KEY in your environment.');
        }

        return $key;
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->secretKey(), '')
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    /**
     * Create a hosted checkout session for a subscription record.
     *
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(BranchBillingRecord $record): array
    {
        $record->loadMissing('branch');

        $amount = (int) round(((float) $record->amount) * 100);
        $minimum = (int) round(((float) config('billing.min_amount', 20)) * 100);

        if ($amount < $minimum) {
            throw new RuntimeException('Subscription amount is below the PayMongo minimum of PHP '.number_format((float) config('billing.min_amount', 20), 2).'.');
        }

        $branchName = $record->branch?->name ?: 'Branch';

        $payload = [
            'data' => [
                'attributes' => [
                    'billing' => $this->billingDetails($branchName),
                    'send_email_receipt' => false,
                    'show_description' => true,
                    'show_line_items' => true,
                    'description' => 'System Subscription - '.$record->periodLabel(),
                    'line_items' => [[
                        'name' => 'System Subscription - '.$branchName,
                        'quantity' => 1,
                        'amount' => $amount,
                        'currency' => 'PHP',
                        'description' => $record->periodLabel(),
                    ]],
                    'payment_method_types' => ['gcash', 'card', 'paymaya', 'grab_pay'],
                    'success_url' => route('admin.billing.pay.return', $record),
                    'cancel_url' => route('admin.billing.pay.cancel', $record),
                    'reference_number' => 'BILL-'.$record->id,
                    'metadata' => [
                        'record_id' => (string) $record->id,
                        'branch_id' => (string) $record->branch_id,
                    ],
                ],
            ],
        ];

        $response = $this->client()->post($this->baseUrl.'/checkout_sessions', $payload);

        if ($response->failed()) {
            throw new RuntimeException('PayMongo checkout could not be created: '.$this->errorMessage($response->json()));
        }

        $id = $response->json('data.id');
        $url = $response->json('data.attributes.checkout_url');

        if (! $id || ! $url) {
            throw new RuntimeException('PayMongo returned an unexpected checkout response.');
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * Create a QR Ph payment for a subscription record: create a Payment Intent,
     * create a qrph Payment Method, attach it, and return the scannable QR image.
     *
     * @return array{intent_id: string, qr_image: string, status: string}
     */
    public function createQrPhPayment(BranchBillingRecord $record): array
    {
        $record->loadMissing('branch');

        $amount = (int) round(((float) $record->amount) * 100);

        if ($amount < 100) {
            throw new RuntimeException('Subscription amount must be at least PHP 1.00 to generate a QR.');
        }

        $branchName = $record->branch?->name ?: 'Branch';

        // 1) Payment Intent that allows QR Ph.
        $intentResponse = $this->client()->post($this->baseUrl.'/payment_intents', [
            'data' => [
                'attributes' => [
                    'amount' => $amount,
                    'payment_method_allowed' => ['qrph'],
                    'currency' => 'PHP',
                    'capture_type' => 'automatic',
                    'description' => 'System Subscription - '.$record->periodLabel(),
                    'statement_descriptor' => 'Subscription',
                    'metadata' => [
                        'record_id' => (string) $record->id,
                        'branch_id' => (string) $record->branch_id,
                    ],
                ],
            ],
        ]);

        if ($intentResponse->failed()) {
            throw new RuntimeException('PayMongo QR could not be created: '.$this->errorMessage($intentResponse->json()));
        }

        $intentId = $intentResponse->json('data.id');

        // 2) Payment Method of type qrph.
        $methodResponse = $this->client()->post($this->baseUrl.'/payment_methods', [
            'data' => [
                'attributes' => [
                    'type' => 'qrph',
                    'billing' => $this->billingDetails($branchName),
                ],
            ],
        ]);

        if ($methodResponse->failed()) {
            throw new RuntimeException('PayMongo QR could not be created: '.$this->errorMessage($methodResponse->json()));
        }

        $methodId = $methodResponse->json('data.id');

        // 3) Attach the method to the intent -> returns the QR image.
        $attachResponse = $this->client()->post($this->baseUrl.'/payment_intents/'.$intentId.'/attach', [
            'data' => [
                'attributes' => [
                    'payment_method' => $methodId,
                ],
            ],
        ]);

        if ($attachResponse->failed()) {
            throw new RuntimeException('PayMongo QR could not be generated: '.$this->errorMessage($attachResponse->json()));
        }

        $image = $attachResponse->json('data.attributes.next_action.code.image_url')
            ?? $attachResponse->json('data.attributes.next_action.code.qr_image')
            ?? $attachResponse->json('data.attributes.next_action.code.data');

        if (! $image) {
            throw new RuntimeException('PayMongo did not return a QR code. Make sure QR Ph is activated on your account.');
        }

        return [
            'intent_id' => $intentId,
            'qr_image' => $this->normalizeQrImage($image),
            'status' => $attachResponse->json('data.attributes.status', 'awaiting_next_action'),
        ];
    }

    /**
     * Poll a Payment Intent and report whether it has been paid.
     *
     * @return array{paid: bool, status: ?string, payment_id: ?string}
     */
    public function paymentIntentStatus(string $intentId): array
    {
        $response = $this->client()->get($this->baseUrl.'/payment_intents/'.$intentId);

        if ($response->failed()) {
            throw new RuntimeException('PayMongo intent could not be retrieved: '.$this->errorMessage($response->json()));
        }

        $status = $response->json('data.attributes.status');
        $paymentId = null;

        foreach ($response->json('data.attributes.payments') ?? [] as $payment) {
            if (data_get($payment, 'attributes.status') === 'paid') {
                $paymentId = data_get($payment, 'id');
                break;
            }
        }

        return [
            'paid' => $status === 'succeeded' || $paymentId !== null,
            'status' => $status,
            'payment_id' => $paymentId,
        ];
    }

    private function normalizeQrImage(string $image): string
    {
        if (str_starts_with($image, 'data:') || str_starts_with($image, 'http')) {
            return $image;
        }

        return 'data:image/png;base64,'.$image;
    }

    private function billingDetails(string $name): array
    {
        $settings = SystemSetting::current();
        $email = $this->validEmail($settings->business_email ?? null);

        if (! $email) {
            throw new RuntimeException('PayMongo requires a billing email. Add a valid Business Email in Admin Settings.');
        }

        return [
            'name' => $name,
            'email' => $email,
        ];
    }

    private function validEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Inspect a checkout session and report whether it has been paid.
     *
     * @return array{paid: bool, payment_id: ?string, method: ?string}
     */
    public function checkoutPaymentStatus(string $checkoutId): array
    {
        $response = $this->client()->get($this->baseUrl.'/checkout_sessions/'.$checkoutId);

        if ($response->failed()) {
            throw new RuntimeException('PayMongo checkout could not be retrieved: '.$this->errorMessage($response->json()));
        }

        foreach ($response->json('data.attributes.payments') ?? [] as $payment) {
            if (data_get($payment, 'attributes.status') === 'paid') {
                return [
                    'paid' => true,
                    'payment_id' => data_get($payment, 'id'),
                    'method' => data_get($payment, 'attributes.source.type', 'paymongo'),
                ];
            }
        }

        return ['paid' => false, 'payment_id' => null, 'method' => null];
    }

    /**
     * Verify the signature of an incoming PayMongo webhook. When no webhook
     * secret is configured verification is skipped (returns true).
     */
    public function verifyWebhook(Request $request): bool
    {
        $secret = config('services.paymongo.webhook_secret');

        if (empty($secret)) {
            return true;
        }

        $header = $request->header('Paymongo-Signature');

        if (! $header) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['li'] ?? ($parts['te'] ?? '');

        if (! $timestamp || ! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, (string) $signature);
    }

    /**
     * Resolve the billing record referenced by a webhook event.
     */
    public function resolveRecordFromEvent(array $data): ?BranchBillingRecord
    {
        $recordId = data_get($data, 'attributes.data.attributes.metadata.record_id')
            ?? data_get($data, 'attributes.data.attributes.reference_number');

        if ($recordId) {
            $recordId = (int) preg_replace('/[^0-9]/', '', (string) $recordId);
            if ($recordId && $record = BranchBillingRecord::find($recordId)) {
                return $record;
            }
        }

        // Match by the stored PayMongo id — checkout session id or QR payment intent id.
        foreach ([
            data_get($data, 'attributes.data.attributes.payment_intent_id'),
            data_get($data, 'attributes.data.id'),
        ] as $reference) {
            if ($reference && $record = BranchBillingRecord::where('paymongo_checkout_id', $reference)->first()) {
                return $record;
            }
        }

        return null;
    }

    private function errorMessage(?array $body): string
    {
        $detail = data_get($body, 'errors.0.detail');

        return $detail ?: 'Unknown error from payment gateway.';
    }
}
