<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\SmsLog;
use App\Models\SystemSetting;
use App\Support\SmsNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_unisms_sends_sms_with_normalized_philippine_number(): void
    {
        Http::fake([
            'unismsapi.com/*' => Http::response([
                'message' => [
                    'reference_id' => 'msg_12345',
                    'status' => 'sent',
                ],
            ], 201),
        ]);

        [$order] = $this->readyOrder();
        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'sms_enabled' => true,
            'sms_provider' => 'unisms',
            'sms_api_key' => 'unisms-secret',
            'unisms_sender_id' => 'SPINKLEAN',
            'is_completed' => true,
        ]);

        SmsNotifier::jobOrderStatus($order);

        Http::assertSent(fn ($request) => $request->url() === 'https://unismsapi.com/api/sms'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('unisms-secret:'))
            && $request['recipient'] === '+639171234567'
            && $request['sender_id'] === 'SPINKLEAN'
            && $request['metadata']['sms_log_id'] !== null
            && stripos($request['content'], 'ready for pickup') !== false);

        $this->assertDatabaseHas('sms_logs', [
            'recipient' => '09171234567',
            'status' => 'sent',
            'response' => 'UniSMS message accepted (msg_12345)',
        ]);
    }

    public function test_po_customer_never_receives_or_queues_sms(): void
    {
        Http::fake();
        [$order, , $customer] = $this->readyOrder();
        $customer->update(['billing_type' => 'po']);
        $order->setRelation('customer', $customer->fresh());

        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'sms_enabled' => true,
            'sms_provider' => 'unisms',
            'sms_api_key' => 'unisms-secret',
            'is_completed' => true,
        ]);

        SmsNotifier::jobOrderReceived($order);
        SmsNotifier::jobOrderStatus($order);

        Http::assertNothingSent();
        $this->assertDatabaseCount('sms_logs', 0);
    }

    public function test_unisms_failure_is_recorded_without_throwing(): void
    {
        Http::fake([
            'unismsapi.com/*' => Http::response([
                'message' => [
                    'status' => 'failed',
                    'fail_reason' => 'Insufficient account balance.',
                ],
            ], 422),
        ]);

        [$order] = $this->readyOrder();
        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'sms_enabled' => true,
            'sms_provider' => 'unisms',
            'sms_api_key' => 'unisms-secret',
            'is_completed' => true,
        ]);

        SmsNotifier::jobOrderStatus($order);

        $this->assertDatabaseHas('sms_logs', [
            'customer_id' => $order->customer_id,
            'status' => 'failed',
            'response' => 'UniSMS error: Insufficient account balance.',
        ]);
        $this->assertDatabaseHas('job_orders', [
            'id' => $order->id,
            'status' => 'ready_for_pickup',
        ]);
    }

    public function test_unisms_missing_secret_key_keeps_sms_queued_without_throwing(): void
    {
        [$order] = $this->readyOrder();

        SystemSetting::query()->create([
            'business_name' => 'EAJ Laundry',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'sms_enabled' => true,
            'sms_provider' => 'unisms',
            'is_completed' => true,
        ]);

        SmsNotifier::jobOrderStatus($order);

        $this->assertDatabaseHas('sms_logs', [
            'recipient' => '09171234567',
            'status' => 'queued',
            'response' => 'UniSMS is selected but the API secret key is missing.',
        ]);
    }

    public function test_unisms_configured_sends_finish_sms(): void
    {
        Http::fake([
            'unismsapi.com/*' => Http::response([
                'message' => [
                    'reference_id' => 'msg_finish',
                    'status' => 'sent',
                ],
            ], 201),
        ]);

        [$order] = $this->readyOrder();

        SystemSetting::query()->create([
            'business_name' => 'EAJ Laundry',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'sms_enabled' => true,
            'sms_provider' => 'unisms',
            'sms_api_key' => 'secret',
            'is_completed' => true,
        ]);

        SmsNotifier::jobOrderStatus($order);

        Http::assertSent(fn ($request) => $request->url() === 'https://unismsapi.com/api/sms'
            && $request['recipient'] === '+639171234567'
            && stripos($request['content'], 'ready for pickup') !== false);

        $this->assertDatabaseHas('sms_logs', [
            'recipient' => '09171234567',
            'status' => 'sent',
            'response' => 'UniSMS message accepted (msg_finish)',
        ]);
    }

    public function test_walk_in_received_sms_uses_drop_off_message(): void
    {
        [$order] = $this->readyOrder();
        $order->update([
            'status' => 'pending',
            'transaction_type' => 'walk_in',
        ]);

        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'sms_enabled' => true,
            'is_completed' => true,
        ]);

        SmsNotifier::jobOrderReceived($order);

        $message = (string) SmsLog::query()->value('message');
        $this->assertStringContainsString('We received your laundry', $message);
        $this->assertStringNotContainsString('picked up', $message);
        $this->assertStringContainsString('once it is ready', $message);
    }

    public function test_custom_sms_template_replaces_order_placeholders(): void
    {
        [$order] = $this->readyOrder();

        SystemSetting::query()->create([
            'business_name' => 'EAJ Laundry',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'sms_enabled' => true,
            'is_completed' => true,
            'sms_template_ready_for_pickup' => '{customer_name}, claim {job_order_number} at {branch_name}. Balance {balance}.',
        ]);

        SmsNotifier::jobOrderStatus($order);

        $this->assertDatabaseHas('sms_logs', [
            'message' => 'Laundry Customer, claim JO-TEST-001 at Main Branch. Balance PHP 0.00.',
        ]);
    }

    public function test_custom_sms_template_can_skip_customer_name(): void
    {
        [$order] = $this->readyOrder();

        SystemSetting::query()->create([
            'business_name' => 'EAJ Laundry',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'sms_enabled' => true,
            'is_completed' => true,
            'sms_template_ready_for_pickup' => 'Your laundry {job_order_number} is ready for pickup.',
        ]);

        SmsNotifier::jobOrderStatus($order);

        $this->assertDatabaseHas('sms_logs', [
            'message' => 'Your laundry JO-TEST-001 is ready for pickup.',
        ]);
    }

    public function test_each_sms_event_uses_its_matching_template(): void
    {
        [$order] = $this->readyOrder();

        SystemSetting::query()->create([
            'business_name' => 'EAJ Laundry',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'sms_enabled' => true,
            'is_completed' => true,
            'sms_template_order_received' => 'Drop-off received {job_order_number}.',
            'sms_template_delivery_received' => 'Pickup received {job_order_number}.',
            'sms_template_ready_for_pickup' => 'Pickup ready {job_order_number}.',
            'sms_template_ready_for_delivery' => 'Delivery ready {job_order_number}.',
            'sms_template_completed' => 'Completed {job_order_number}.',
        ]);

        $order->update(['status' => 'pending', 'transaction_type' => 'walk_in']);
        SmsNotifier::jobOrderReceived($order->fresh(['branch', 'customer']));

        $order->update(['transaction_type' => 'delivery']);
        SmsNotifier::jobOrderReceived($order->fresh(['branch', 'customer']));

        $order->update(['status' => 'ready_for_pickup']);
        SmsNotifier::jobOrderStatus($order->fresh(['branch', 'customer']));

        $order->update(['status' => 'ready_for_delivery']);
        SmsNotifier::jobOrderStatus($order->fresh(['branch', 'customer']));

        $order->update(['status' => 'completed']);
        SmsNotifier::jobOrderStatus($order->fresh(['branch', 'customer']));

        $messages = SmsLog::query()->orderBy('id')->pluck('message')->all();

        $this->assertSame([
            'Drop-off received JO-TEST-001.',
            'Pickup received JO-TEST-001.',
            'Pickup ready JO-TEST-001.',
            'Delivery ready JO-TEST-001.',
            'Completed JO-TEST-001.',
        ], $messages);
    }

    public function test_unisms_receives_rendered_template_content(): void
    {
        Http::fake([
            'unismsapi.com/*' => Http::response([
                'message' => [
                    'reference_id' => 'msg_rendered',
                    'status' => 'sent',
                ],
            ], 201),
        ]);

        [$order] = $this->readyOrder();

        SystemSetting::query()->create([
            'business_name' => 'EAJ Laundry',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'sms_enabled' => true,
            'sms_provider' => 'unisms',
            'sms_api_key' => 'secret',
            'is_completed' => true,
            'sms_template_ready_for_pickup' => 'Hi {customer_name}, claim {job_order_number} at {branch_name}.',
        ]);

        SmsNotifier::jobOrderStatus($order);

        Http::assertSent(fn ($request) => $request->url() === 'https://unismsapi.com/api/sms'
            && $request['content'] === 'Hi Laundry Customer, claim JO-TEST-001 at Main Branch.'
            && ! str_contains($request['content'], '{customer_name}')
            && ! str_contains($request['content'], '{job_order_number}'));
    }

    private function readyOrder(): array
    {
        $branch = Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Laundry Customer',
            'phone' => '09171234567',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);
        $order = JobOrder::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_number' => 'JO-TEST-001',
            'status' => 'ready_for_pickup',
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'balance' => 0,
        ]);
        $order->load('customer');

        return [$order, $branch, $customer];
    }
}
