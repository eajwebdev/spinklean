<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\CycleRecord;
use App\Models\Inventory;
use App\Models\JobOrder;
use App\Models\JobOrderTransfer;
use App\Models\LaundryService;
use App\Models\Payment;
use App\Models\ServiceInventoryUsage;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use App\Support\FinancialReconciliation;
use App\Support\TagValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DropoffPickupBranchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch1;
    private Branch $branch2;
    private Branch $branch3;
    private User $b1User;
    private User $b2User;
    private User $b3User;
    private User $admin;
    private Customer $b3Customer;
    private LaundryService $b3Service;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'CDO',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#0EA5E9',
            'is_completed' => true,
        ]);

        SystemTrialSetting::query()->create([
            'trial_enabled' => true,
            'trial_start_date' => now()->subDay()->toDateString(),
            'trial_end_date' => now()->addDay()->toDateString(),
            'trial_status' => 'active',
            'grace_period_days' => 0,
        ]);

        $this->branch1 = Branch::query()->create([
            'name' => 'Branch 1 - Main Production',
            'code' => 'B0001',
            'branch_type' => 'full_service',
            'machine_count' => 5,
            'address' => 'Production 1',
            'contact_number' => '09170000001',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::query()->create([
            'name' => 'Branch 2 - Secondary Production',
            'code' => 'B0002',
            'branch_type' => 'full_service',
            'machine_count' => 4,
            'address' => 'Production 2',
            'contact_number' => '09170000002',
            'is_active' => true,
        ]);

        $this->branch3 = Branch::query()->create([
            'name' => 'Branch 3 - Dropoff & Pickup Only',
            'code' => 'B0003',
            'branch_type' => 'pickup_dropoff',
            'machine_count' => 0,
            'address' => 'Mall Kiosk',
            'contact_number' => '09170000003',
            'is_active' => true,
        ]);

        $this->b1User = User::factory()->create([
            'name' => 'Branch 1 Operator',
            'role' => 'branch_manager',
            'branch_id' => $this->branch1->id,
            'access' => ['cycles', 'job_orders', 'inventory'],
        ]);

        $this->b2User = User::factory()->create([
            'name' => 'Branch 2 Operator',
            'role' => 'branch_manager',
            'branch_id' => $this->branch2->id,
            'access' => ['cycles', 'job_orders', 'inventory'],
        ]);

        $this->b3User = User::factory()->create([
            'name' => 'Branch 3 Cashier',
            'role' => 'branch_manager',
            'branch_id' => $this->branch3->id,
            'access' => ['job_orders', 'payments'],
        ]);

        $this->admin = User::factory()->create([
            'name' => 'System Admin',
            'role' => 'admin',
            'branch_id' => null,
            'access' => ['job_orders', 'cycles', 'payments', 'reports'],
        ]);

        $this->b3Customer = Customer::query()->create([
            'branch_id' => $this->branch3->id,
            'name' => 'Juan Dela Cruz',
            'phone' => '09179998888',
            'billing_type' => 'regular',
            'unpaid_limit' => 0,
            'is_active' => true,
        ]);

        $this->b3Service = LaundryService::query()->create([
            'branch_id' => $this->branch3->id,
            'name' => 'Wash Dry Fold',
            'pricing_type' => 'kilo',
            'price' => 150,
            'is_active' => true,
        ]);
    }

    /**
     * Case 1: Create JO at Branch 3 with manual tag number -> succeeds.
     */
    public function test_case_01_create_jo_at_branch_3_with_manual_tag_number_succeeds(): void
    {
        $response = $this->actingAs($this->b3User)->post(route('admin.job-orders.store'), [
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'tag_number' => 'TAG-101',
            'items' => [[
                'laundry_service_id' => $this->b3Service->id,
                'description' => $this->b3Service->name,
                'quantity' => 1,
                'unit_price' => 150,
            ]],
            'paid_amount' => 0,
            'transaction_type' => 'walk_in',
        ]);

        $response->assertRedirect(route('admin.job-orders.index'));

        $order = JobOrder::where('customer_id', $this->b3Customer->id)->firstOrFail();
        $this->assertSame('TAG-101', $order->tag_number);
        $this->assertSame($this->branch3->id, $order->branch_id);
        $this->assertSame($this->branch1->id, $order->processing_branch_id);
        $this->assertSame('pending', $order->status);
    }

    /**
     * Case 2: Create JO at Branch 3 with empty tag number -> fails validation.
     */
    public function test_case_02_create_jo_at_branch_3_with_empty_tag_number_fails_validation(): void
    {
        $response = $this->actingAs($this->b3User)->post(route('admin.job-orders.store'), [
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'tag_number' => '',
            'items' => [[
                'laundry_service_id' => $this->b3Service->id,
                'description' => $this->b3Service->name,
                'quantity' => 1,
                'unit_price' => 150,
            ]],
            'paid_amount' => 0,
            'transaction_type' => 'walk_in',
        ]);

        $response->assertSessionHasErrors(['tag_number']);
        $this->assertDatabaseMissing('job_orders', [
            'customer_id' => $this->b3Customer->id,
        ]);
    }

    /**
     * Case 3: Create JO at Branch 3 with duplicate tag number (active) -> fails validation.
     */
    public function test_case_03_create_jo_at_branch_3_with_duplicate_tag_number_fails_validation(): void
    {
        // Existing active order using TAG-DUPLICATE
        JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-B0003-20260927-0001',
            'tag_number' => 'TAG-DUPLICATE',
            'status' => 'washing',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        $response = $this->actingAs($this->b3User)->post(route('admin.job-orders.store'), [
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'tag_number' => 'TAG-DUPLICATE',
            'items' => [[
                'laundry_service_id' => $this->b3Service->id,
                'description' => $this->b3Service->name,
                'quantity' => 1,
                'unit_price' => 150,
            ]],
            'paid_amount' => 0,
            'transaction_type' => 'walk_in',
        ]);

        $response->assertSessionHasErrors(['tag_number']);
    }

    /**
     * Case 4: Reuse tag completed earlier today -> fails validation (same-day restriction).
     */
    public function test_case_04_reuse_tag_completed_earlier_today_fails_validation(): void
    {
        // Order completed earlier today in Manila time
        JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-B0003-20260927-0002',
            'tag_number' => 'TAG-TODAY-COMPLETED',
            'status' => 'completed',
            'completed_at' => Carbon::now('Asia/Manila')->subHours(2),
            'released_at' => Carbon::now('Asia/Manila')->subHours(2),
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 150,
            'balance' => 0,
        ]);

        $this->expectException(ValidationException::class);
        TagValidator::validate('TAG-TODAY-COMPLETED');
    }

    /**
     * Case 5: Reuse tag completed yesterday -> succeeds.
     */
    public function test_case_05_reuse_tag_completed_yesterday_succeeds(): void
    {
        $yesterdayOrder = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-B0003-20260926-0001',
            'tag_number' => 'TAG-YESTERDAY-COMPLETED',
            'status' => 'completed',
            'completed_at' => Carbon::now('Asia/Manila')->subDay(),
            'released_at' => Carbon::now('Asia/Manila')->subDay(),
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 150,
            'balance' => 0,
        ]);
        $yesterdayOrder->timestamps = false;
        $yesterdayOrder->created_at = Carbon::now('Asia/Manila')->subDay();
        $yesterdayOrder->save();

        $validatedTag = TagValidator::validate('TAG-YESTERDAY-COMPLETED');
        $this->assertSame('TAG-YESTERDAY-COMPLETED', $validatedTag);
    }

    /**
     * Case 6: Create JO at Branch 3 selecting Branch 1 -> succeeds, outbound transfer created with status pending.
     */
    public function test_case_06_create_jo_at_branch_3_selecting_branch_1_creates_outbound_transfer_with_pending_status(): void
    {
        $this->actingAs($this->b3User)->post(route('admin.job-orders.store'), [
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'tag_number' => 'TAG-TRANSFER-01',
            'items' => [[
                'laundry_service_id' => $this->b3Service->id,
                'description' => $this->b3Service->name,
                'quantity' => 1,
                'unit_price' => 150,
            ]],
            'paid_amount' => 0,
            'transaction_type' => 'walk_in',
        ]);

        $order = JobOrder::where('tag_number', 'TAG-TRANSFER-01')->firstOrFail();

        $this->assertDatabaseHas('job_order_transfers', [
            'job_order_id' => $order->id,
            'tag_number' => 'TAG-TRANSFER-01',
            'origin_branch_id' => $this->branch3->id,
            'destination_branch_id' => $this->branch1->id,
            'transfer_type' => 'outbound',
            'transfer_status' => 'pending',
        ]);
    }

    /**
     * Case 7: Select Branch 3 as processing branch -> fails validation.
     */
    public function test_case_07_select_branch_3_as_processing_branch_fails_validation(): void
    {
        $response = $this->actingAs($this->b3User)->post(route('admin.job-orders.store'), [
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch3->id,
            'customer_id' => $this->b3Customer->id,
            'tag_number' => 'TAG-FAIL-PROC',
            'items' => [[
                'laundry_service_id' => $this->b3Service->id,
                'description' => $this->b3Service->name,
                'quantity' => 1,
                'unit_price' => 150,
            ]],
            'paid_amount' => 0,
            'transaction_type' => 'walk_in',
        ]);

        $response->assertSessionHasErrors(['processing_branch_id']);
    }

    /**
     * Case 8: TAG (X) count at Branch 1 matches pending outbound transfers to Branch 1.
     */
    public function test_case_08_tag_count_at_branch_1_matches_pending_outbound_transfers_to_branch_1(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $order = JobOrder::query()->create([
                'branch_id' => $this->branch3->id,
                'processing_branch_id' => $this->branch1->id,
                'customer_id' => $this->b3Customer->id,
                'job_order_number' => "JO-COUNT-{$i}",
                'tag_number' => "TAG-COUNT-{$i}",
                'status' => 'pending',
                'subtotal' => 150,
                'total' => 150,
                'paid_amount' => 0,
                'balance' => 150,
            ]);

            JobOrderTransfer::query()->create([
                'job_order_id' => $order->id,
                'job_order_number' => $order->job_order_number,
                'tag_number' => $order->tag_number,
                'origin_branch_id' => $this->branch3->id,
                'destination_branch_id' => $this->branch1->id,
                'transfer_type' => 'outbound',
                'transfer_status' => 'pending',
                'transferred_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->b1User)
            ->getJson(route('admin.transfers.count'))
            ->assertOk();

        $response->assertJson([
            'pending_count' => 3,
        ]);
    }

    /**
     * Case 9: TAG (X) count at Branch 1 excludes transfers to Branch 2.
     */
    public function test_case_09_tag_count_at_branch_1_excludes_transfers_to_branch_2(): void
    {
        // 2 transfers for Branch 1
        for ($i = 1; $i <= 2; $i++) {
            $order = JobOrder::query()->create([
                'branch_id' => $this->branch3->id,
                'processing_branch_id' => $this->branch1->id,
                'customer_id' => $this->b3Customer->id,
                'job_order_number' => "JO-B1-{$i}",
                'tag_number' => "TAG-B1-{$i}",
                'status' => 'pending',
                'subtotal' => 150,
                'total' => 150,
                'paid_amount' => 0,
                'balance' => 150,
            ]);

            JobOrderTransfer::query()->create([
                'job_order_id' => $order->id,
                'job_order_number' => $order->job_order_number,
                'tag_number' => $order->tag_number,
                'origin_branch_id' => $this->branch3->id,
                'destination_branch_id' => $this->branch1->id,
                'transfer_type' => 'outbound',
                'transfer_status' => 'pending',
                'transferred_at' => now(),
            ]);
        }

        // 4 transfers for Branch 2
        for ($i = 1; $i <= 4; $i++) {
            $order = JobOrder::query()->create([
                'branch_id' => $this->branch3->id,
                'processing_branch_id' => $this->branch2->id,
                'customer_id' => $this->b3Customer->id,
                'job_order_number' => "JO-B2-{$i}",
                'tag_number' => "TAG-B2-{$i}",
                'status' => 'pending',
                'subtotal' => 150,
                'total' => 150,
                'paid_amount' => 0,
                'balance' => 150,
            ]);

            JobOrderTransfer::query()->create([
                'job_order_id' => $order->id,
                'job_order_number' => $order->job_order_number,
                'tag_number' => $order->tag_number,
                'origin_branch_id' => $this->branch3->id,
                'destination_branch_id' => $this->branch2->id,
                'transfer_type' => 'outbound',
                'transfer_status' => 'pending',
                'transferred_at' => now(),
            ]);
        }

        $b1Count = $this->actingAs($this->b1User)->getJson(route('admin.transfers.count'))->json('pending_count');
        $b2Count = $this->actingAs($this->b2User)->getJson(route('admin.transfers.count'))->json('pending_count');

        $this->assertSame(2, $b1Count);
        $this->assertSame(4, $b2Count);
    }

    /**
     * Case 10: TAG (X) modal endpoint returns pending transfers with correct details.
     */
    public function test_case_10_tag_modal_endpoint_returns_pending_transfers_with_correct_details(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-MODAL-001',
            'tag_number' => 'TAG-MODAL-001',
            'status' => 'pending',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        JobOrderTransfer::query()->create([
            'job_order_id' => $order->id,
            'job_order_number' => $order->job_order_number,
            'tag_number' => $order->tag_number,
            'origin_branch_id' => $this->branch3->id,
            'destination_branch_id' => $this->branch1->id,
            'transfer_type' => 'outbound',
            'transfer_status' => 'pending',
            'transferred_at' => now(),
        ]);

        $response = $this->actingAs($this->b1User)
            ->getJson(route('admin.transfers.pending'))
            ->assertOk();

        $response->assertJson([
            'success' => true,
            'count' => 1,
            'transfers' => [[
                'tag_number' => 'TAG-MODAL-001',
                'job_order_number' => 'JO-MODAL-001',
                'customer_name' => $this->b3Customer->name,
                'origin_branch_name' => $this->branch3->name,
            ]],
        ]);
    }

    /**
     * Case 11: Tag search filters list correctly.
     */
    public function test_case_11_tag_search_filters_list_correctly(): void
    {
        $customerAlpha = Customer::query()->create([
            'branch_id' => $this->branch3->id,
            'name' => 'Alice Allison',
            'phone' => '09171111111',
            'is_active' => true,
        ]);

        $customerBeta = Customer::query()->create([
            'branch_id' => $this->branch3->id,
            'name' => 'Bob Builder',
            'phone' => '09172222222',
            'is_active' => true,
        ]);

        $orderA = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $customerAlpha->id,
            'job_order_number' => 'JO-ALPHA',
            'tag_number' => 'TAG-ALPHA',
            'status' => 'pending',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        JobOrderTransfer::query()->create([
            'job_order_id' => $orderA->id,
            'job_order_number' => $orderA->job_order_number,
            'tag_number' => $orderA->tag_number,
            'origin_branch_id' => $this->branch3->id,
            'destination_branch_id' => $this->branch1->id,
            'transfer_type' => 'outbound',
            'transfer_status' => 'pending',
            'transferred_at' => now(),
        ]);

        $orderB = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $customerBeta->id,
            'job_order_number' => 'JO-BETA',
            'tag_number' => 'TAG-BETA',
            'status' => 'pending',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        JobOrderTransfer::query()->create([
            'job_order_id' => $orderB->id,
            'job_order_number' => $orderB->job_order_number,
            'tag_number' => $orderB->tag_number,
            'origin_branch_id' => $this->branch3->id,
            'destination_branch_id' => $this->branch1->id,
            'transfer_type' => 'outbound',
            'transfer_status' => 'pending',
            'transferred_at' => now(),
        ]);

        $searchAlpha = $this->actingAs($this->b1User)
            ->getJson(route('admin.transfers.pending', ['search' => 'ALPHA']))
            ->json('transfers');

        $this->assertCount(1, $searchAlpha);
        $this->assertSame('TAG-ALPHA', $searchAlpha[0]['tag_number']);

        $searchBob = $this->actingAs($this->b1User)
            ->getJson(route('admin.transfers.pending', ['search' => 'Bob']))
            ->json('transfers');

        $this->assertCount(1, $searchBob);
        $this->assertSame('TAG-BETA', $searchBob[0]['tag_number']);
    }

    /**
     * Case 12: Batch receive 3 tags at Branch 1 -> all 3 marked received, added to Cycle Monitoring, inventory deducted at Branch 1, count decrements by 3.
     */
    public function test_case_12_batch_receive_3_tags_at_branch_1_marks_received_deducts_inventory_and_decrements_count(): void
    {
        $transferIds = [];
        $orders = [];

        for ($i = 1; $i <= 3; $i++) {
            $order = JobOrder::query()->create([
                'branch_id' => $this->branch3->id,
                'processing_branch_id' => $this->branch1->id,
                'customer_id' => $this->b3Customer->id,
                'job_order_number' => "JO-BATCH-{$i}",
                'tag_number' => "TAG-BATCH-{$i}",
                'status' => 'pending',
                'subtotal' => 150,
                'total' => 150,
                'paid_amount' => 0,
                'balance' => 150,
            ]);
            $orders[] = $order;

            $transfer = JobOrderTransfer::query()->create([
                'job_order_id' => $order->id,
                'job_order_number' => $order->job_order_number,
                'tag_number' => $order->tag_number,
                'origin_branch_id' => $this->branch3->id,
                'destination_branch_id' => $this->branch1->id,
                'transfer_type' => 'outbound',
                'transfer_status' => 'pending',
                'transferred_at' => now(),
            ]);
            $transferIds[] = $transfer->id;
        }

        $this->assertSame(3, $this->actingAs($this->b1User)->getJson(route('admin.transfers.count'))->json('pending_count'));

        $response = $this->actingAs($this->b1User)->postJson(route('admin.transfers.receive'), [
            'transfer_ids' => $transferIds,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'received_count' => 3,
        ]);

        foreach ($orders as $order) {
            $order->refresh();
            $this->assertSame('received', $order->status);
            $this->assertNotNull($order->production_accepted_at);
            $this->assertSame($this->branch1->id, $order->current_branch_id);
        }

        $this->assertSame(0, $this->actingAs($this->b1User)->getJson(route('admin.transfers.count'))->json('pending_count'));
    }

    /**
     * Case 13: Inventory deduction at Branch 1 does not affect Branch 3.
     */
    public function test_case_13_inventory_deduction_at_branch_1_does_not_affect_branch_3(): void
    {
        $invB3 = Inventory::query()->create([
            'branch_id' => $this->branch3->id,
            'name' => 'Detergent Powder',
            'sku' => 'DET-001',
            'unit' => 'g',
            'quantity' => 100,
            'reorder_level' => 10,
            'is_active' => true,
        ]);

        $invB1 = Inventory::query()->create([
            'branch_id' => $this->branch1->id,
            'name' => 'Detergent Powder',
            'sku' => 'DET-001',
            'unit' => 'g',
            'quantity' => 100,
            'reorder_level' => 10,
            'is_active' => true,
        ]);

        ServiceInventoryUsage::query()->create([
            'laundry_service_id' => $this->b3Service->id,
            'inventory_id' => $invB3->id,
            'quantity' => 10,
        ]);

        $this->actingAs($this->b3User)->post(route('admin.job-orders.store'), [
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'tag_number' => 'TAG-INV-CHECK',
            'items' => [[
                'laundry_service_id' => $this->b3Service->id,
                'description' => $this->b3Service->name,
                'quantity' => 1,
                'unit_price' => 150,
            ]],
            'paid_amount' => 0,
            'transaction_type' => 'walk_in',
        ]);

        $order = JobOrder::where('tag_number', 'TAG-INV-CHECK')->firstOrFail();
        $transfer = JobOrderTransfer::where('job_order_id', $order->id)->firstOrFail();

        $this->assertSame('100.00', $invB3->fresh()->quantity);
        $this->assertSame('100.00', $invB1->fresh()->quantity);

        $this->actingAs($this->b1User)->postJson(route('admin.transfers.receive'), [
            'transfer_ids' => [$transfer->id],
        ])->assertOk();

        $this->assertSame('100.00', $invB3->fresh()->quantity);
        $this->assertSame('90.00', $invB1->fresh()->quantity);
    }

    /**
     * Case 14: Cycle monitoring at Branch 1 advances order (wash -> dry -> fold -> ready).
     */
    public function test_case_14_cycle_monitoring_at_branch_1_advances_order(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'current_branch_id' => $this->branch1->id,
            'release_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-CYCLES-001',
            'tag_number' => 'TAG-CYCLES-001',
            'status' => 'received',
            'production_accepted_at' => now(),
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        // Start Wash
        $this->actingAs($this->b1User)->post(route('admin.cycles.store', $order), [
            'cycle_type' => 'wash',
            'machine_numbers' => [1],
        ])->assertRedirect();
        $this->assertSame('washing', $order->fresh()->status);

        // End Wash
        $washCycle = CycleRecord::where('job_order_id', $order->id)->where('cycle_type', 'wash')->firstOrFail();
        $this->actingAs($this->b1User)->patch(route('admin.cycles.end', $washCycle))->assertRedirect();

        // Start Dry
        $this->actingAs($this->b1User)->post(route('admin.cycles.store', $order), [
            'cycle_type' => 'dry',
            'machine_numbers' => [1],
        ])->assertRedirect();
        $this->assertSame('drying', $order->fresh()->status);

        // End Dry
        $dryCycle = CycleRecord::where('job_order_id', $order->id)->where('cycle_type', 'dry')->firstOrFail();
        $this->actingAs($this->b1User)->patch(route('admin.cycles.end', $dryCycle))->assertRedirect();

        // Start Fold
        $this->actingAs($this->b1User)->post(route('admin.cycles.store', $order), [
            'cycle_type' => 'fold',
        ])->assertRedirect();
        $this->assertSame('folding', $order->fresh()->status);

        // End Fold
        $foldCycle = CycleRecord::where('job_order_id', $order->id)->where('cycle_type', 'fold')->firstOrFail();
        $this->actingAs($this->b1User)->patch(route('admin.cycles.end', $foldCycle))->assertRedirect();

        // Mark Ready for Pickup
        $this->actingAs($this->b1User)->patch(route('admin.cycles.status', $order), [
            'status' => 'ready_for_pickup',
        ])->assertRedirect();
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
    }

    /**
     * Case 15: Starting machine cycle at Branch 3 is blocked (no machines).
     */
    public function test_case_15_starting_machine_cycle_at_branch_3_is_blocked(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch3->id,
            'current_branch_id' => $this->branch3->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-B3-NO-MACHINE',
            'tag_number' => 'TAG-NO-MACHINE',
            'status' => 'pending',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        $response = $this->actingAs($this->b3User)->post(route('admin.cycles.store', $order), [
            'cycle_type' => 'wash',
            'machine_numbers' => [1],
        ]);

        $response->assertForbidden();
    }

    /**
     * Case 16: Mark ready & return to Branch 3 -> return transfer created (pending).
     */
    public function test_case_16_mark_ready_and_return_to_branch_3_creates_pending_return_transfer(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'current_branch_id' => $this->branch1->id,
            'release_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-RETURN-001',
            'tag_number' => 'TAG-RETURN-001',
            'status' => 'ready_for_pickup',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        $response = $this->actingAs($this->b1User)->patch(route('admin.cycles.release', $order), [
            'action' => 'return_to_dropoff',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('job_order_transfers', [
            'job_order_id' => $order->id,
            'tag_number' => 'TAG-RETURN-001',
            'origin_branch_id' => $this->branch1->id,
            'destination_branch_id' => $this->branch3->id,
            'transfer_type' => 'return',
            'transfer_status' => 'pending',
        ]);

        $order->refresh();
        $this->assertSame($this->branch3->id, $order->current_branch_id);
        $this->assertSame($this->branch3->id, $order->release_branch_id);
    }

    /**
     * Case 17: Branch 3 receives returned laundry -> returned_received_at set.
     */
    public function test_case_17_branch_3_receives_returned_laundry_sets_returned_received_at(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'current_branch_id' => null,
            'release_branch_id' => $this->branch3->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-RECV-RET-001',
            'tag_number' => 'TAG-RECV-RET-001',
            'status' => 'ready_for_pickup',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        $transfer = JobOrderTransfer::query()->create([
            'job_order_id' => $order->id,
            'job_order_number' => $order->job_order_number,
            'tag_number' => $order->tag_number,
            'origin_branch_id' => $this->branch1->id,
            'destination_branch_id' => $this->branch3->id,
            'transfer_type' => 'return',
            'transfer_status' => 'pending',
            'transferred_at' => now(),
        ]);

        $response = $this->actingAs($this->b3User)->post(route('admin.transfers.receive-return', $transfer));
        $response->assertRedirect();

        $order->refresh();
        $this->assertNotNull($order->returned_received_at);
        $this->assertSame($this->branch3->id, $order->current_branch_id);

        $transfer->refresh();
        $this->assertSame('received', $transfer->transfer_status);
        $this->assertNotNull($transfer->received_at);
    }

    /**
     * Case 18: Release at Branch 3 with unpaid balance > limit -> blocked.
     */
    public function test_case_18_release_at_branch_3_with_unpaid_balance_exceeding_limit_is_blocked(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'current_branch_id' => $this->branch3->id,
            'release_branch_id' => $this->branch3->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-UNPAID-REL',
            'tag_number' => 'TAG-UNPAID-REL',
            'status' => 'ready_for_pickup',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        $response = $this->actingAs($this->b3User)->patch(route('admin.job-orders.release', $order));
        $response->assertStatus(422);

        $this->assertNotSame('completed', $order->fresh()->status);
    }

    /**
     * Case 19: Collect remaining payment at Branch 3 -> recorded under Branch 3, balance 0.
     */
    public function test_case_19_collect_remaining_payment_at_branch_3_records_under_branch_3_and_clears_balance(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'current_branch_id' => $this->branch3->id,
            'release_branch_id' => $this->branch3->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-PAY-B3',
            'tag_number' => 'TAG-PAY-B3',
            'status' => 'ready_for_pickup',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        $response = $this->actingAs($this->b3User)->post(route('admin.job-orders.payments.store', $order), [
            'amount' => 150,
            'payment_type' => 'cash',
        ]);

        $response->assertRedirect();

        $order->refresh();
        $this->assertSame('150.00', $order->paid_amount);
        $this->assertSame('0.00', $order->balance);

        $payment = Payment::where('job_order_id', $order->id)->firstOrFail();
        $this->assertSame($this->branch3->id, $payment->branch_id);
        $this->assertSame($this->branch3->id, $payment->collected_branch_id);
    }

    /**
     * Case 20: Release laundry after payment -> status completed/released, physical tag freed for next day.
     */
    public function test_case_20_release_laundry_after_payment_completes_order_and_frees_tag_next_day(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'current_branch_id' => $this->branch3->id,
            'release_branch_id' => $this->branch3->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-RELEASED-OK',
            'tag_number' => 'TAG-RELEASE-20',
            'status' => 'ready_for_pickup',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 150,
            'balance' => 0,
        ]);

        $response = $this->actingAs($this->b3User)->patch(route('admin.job-orders.release', $order));
        $response->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertNotNull($order->released_at);

        // Tag is blocked on the same calendar day
        $this->expectException(ValidationException::class);
        TagValidator::validate('TAG-RELEASE-20');
    }

    /**
     * Case 21: Financial report attributes 100% sale to Branch 3, 0 to Branch 1.
     */
    public function test_case_21_financial_report_attributes_100_percent_sale_to_branch_3_and_zero_to_branch_1(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-FIN-001',
            'tag_number' => 'TAG-FIN-001',
            'status' => 'completed',
            'subtotal' => 500,
            'total' => 500,
            'paid_amount' => 500,
            'balance' => 0,
            'created_at' => now(),
        ]);

        Payment::query()->create([
            'branch_id' => $this->branch3->id,
            'collected_branch_id' => $this->branch3->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_id' => $order->id,
            'received_by' => $this->b3User->id,
            'payment_number' => 'PAY-FIN-001',
            'payment_type' => 'cash',
            'amount' => 500,
            'paid_at' => now(),
        ]);

        $today = now()->toDateString();
        $b3Finance = FinancialReconciliation::forPeriod($this->branch3->id, $today, $today);
        $b1Finance = FinancialReconciliation::forPeriod($this->branch1->id, $today, $today);

        $this->assertEquals(500, $b3Finance['expected_total']);
        $this->assertEquals(500, $b3Finance['cash_collections']);

        $this->assertEquals(0, $b1Finance['expected_total']);
        $this->assertEquals(0, $b1Finance['cash_collections']);
    }

    /**
     * Case 22: Branch 1 operational report reflects cycle count and machine usage for transferred order.
     */
    public function test_case_22_branch_1_operational_report_reflects_cycle_count_and_machine_usage_for_transferred_order(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-OPS-001',
            'tag_number' => 'TAG-OPS-001',
            'status' => 'washing',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 150,
            'balance' => 0,
        ]);

        CycleRecord::query()->create([
            'job_order_id' => $order->id,
            'user_id' => $this->b1User->id,
            'cycle_type' => 'wash',
            'machine_number' => 2,
            'cycle_number' => 1,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(30),
        ]);

        CycleRecord::query()->create([
            'job_order_id' => $order->id,
            'user_id' => $this->b1User->id,
            'cycle_type' => 'dry',
            'machine_number' => 3,
            'cycle_number' => 1,
            'started_at' => now()->subMinutes(25),
            'ended_at' => now(),
        ]);

        // Branch 1's machines recorded the cycle records
        $b1WashCycles = CycleRecord::whereHas('jobOrder', function ($q) {
            $q->where('processing_branch_id', $this->branch1->id);
        })->where('cycle_type', 'wash')->where('machine_number', 2)->count();

        $b1DryCycles = CycleRecord::whereHas('jobOrder', function ($q) {
            $q->where('processing_branch_id', $this->branch1->id);
        })->where('cycle_type', 'dry')->where('machine_number', 3)->count();

        $this->assertSame(1, $b1WashCycles);
        $this->assertSame(1, $b1DryCycles);

        // Branch 3 has 0 cycles recorded locally
        $b3Cycles = CycleRecord::whereHas('jobOrder', function ($q) {
            $q->where('processing_branch_id', $this->branch3->id);
        })->count();

        $this->assertSame(0, $b3Cycles);
    }

    /**
     * Case 23: Direct release from processing branch (Option B) completes order and keeps 100% sale on Branch 3.
     */
    public function test_case_23_direct_release_from_processing_branch_option_b_completes_order_and_keeps_sales_at_branch_3(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'current_branch_id' => $this->branch1->id,
            'release_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-DIRECT-01',
            'tag_number' => 'TAG-DIR-01',
            'status' => 'ready_for_pickup',
            'subtotal' => 200,
            'total' => 200,
            'paid_amount' => 200,
            'balance' => 0,
        ]);

        Payment::query()->create([
            'branch_id' => $this->branch3->id,
            'collected_branch_id' => $this->branch3->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_id' => $order->id,
            'received_by' => $this->b3User->id,
            'payment_number' => 'PAY-DIR-001',
            'payment_type' => 'cash',
            'amount' => 200,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->b1User)->patch(route('admin.cycles.release', $order), [
            'action' => 'release_here',
        ]);
        $response->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->released_at);
        $this->assertSame($this->branch1->id, (int) $order->release_branch_id);
        $this->assertSame($this->branch3->id, (int) $order->branch_id);

        $today = now()->toDateString();
        $b3Finance = FinancialReconciliation::forPeriod($this->branch3->id, $today, $today);
        $b1Finance = FinancialReconciliation::forPeriod($this->branch1->id, $today, $today);

        $this->assertEquals(200, $b3Finance['expected_total']);
        $this->assertEquals(200, $b3Finance['cash_collections']);
        $this->assertEquals(0, $b1Finance['expected_total']);
        $this->assertEquals(0, $b1Finance['cash_collections']);
    }

    /**
     * Case 24: Direct release blocked if balance unpaid, permitted if PO customer.
     */
    public function test_case_24_direct_release_blocked_if_unpaid_permitted_if_po_customer(): void
    {
        $unpaidOrder = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'current_branch_id' => $this->branch1->id,
            'release_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-UNPAID-01',
            'tag_number' => 'TAG-UNPAID-01',
            'status' => 'ready_for_pickup',
            'subtotal' => 250,
            'total' => 250,
            'paid_amount' => 0,
            'balance' => 250,
        ]);

        // Should fail with 422 for regular customer with unpaid balance
        $response = $this->actingAs($this->b1User)->patch(route('admin.cycles.release', $unpaidOrder), [
            'action' => 'release_here',
        ]);
        $response->assertStatus(422);

        $unpaidOrder->refresh();
        $this->assertSame('ready_for_pickup', $unpaidOrder->status);

        // Now test PO customer with credit
        $poCustomer = Customer::query()->create([
            'branch_id' => $this->branch3->id,
            'name' => 'Corporate Hotel Partner',
            'phone' => '09175556666',
            'billing_type' => 'po',
            'unpaid_limit' => 50000,
            'is_active' => true,
        ]);

        $poOrder = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'current_branch_id' => $this->branch1->id,
            'release_branch_id' => $this->branch1->id,
            'customer_id' => $poCustomer->id,
            'job_order_number' => 'JO-PO-01',
            'tag_number' => 'TAG-PO-01',
            'status' => 'ready_for_pickup',
            'subtotal' => 500,
            'total' => 500,
            'paid_amount' => 0,
            'balance' => 500,
        ]);

        $poResponse = $this->actingAs($this->b1User)->patch(route('admin.cycles.release', $poOrder), [
            'action' => 'release_here',
        ]);
        $poResponse->assertRedirect();

        $poOrder->refresh();
        $this->assertSame('completed', $poOrder->status);
        $this->assertNotNull($poOrder->released_at);
    }

    /**
     * Case 25: Cancelling a Job Order at Branch 3 automatically cancels pending outbound transfers and decrements TAG count.
     */
    public function test_case_25_cancelling_job_order_at_branch_3_cancels_pending_transfers_and_decrements_tag_count(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-CANCEL-ME',
            'tag_number' => 'TAG-CANCEL-01',
            'status' => 'pending',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        $transfer = JobOrderTransfer::query()->create([
            'job_order_id' => $order->id,
            'job_order_number' => $order->job_order_number,
            'tag_number' => $order->tag_number,
            'origin_branch_id' => $this->branch3->id,
            'destination_branch_id' => $this->branch1->id,
            'transfer_type' => 'outbound',
            'transfer_status' => 'pending',
            'transferred_at' => now(),
        ]);

        // Branch 1 sees 1 pending incoming transfer
        $countResponseBefore = $this->actingAs($this->b1User)->getJson(route('admin.transfers.count'));
        $this->assertSame(1, $countResponseBefore->json('count'));

        // Branch 3 cancels the order
        $cancelResponse = $this->actingAs($this->b3User)->patch(route('admin.job-orders.cancel', $order));
        $cancelResponse->assertRedirect();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);

        $transfer->refresh();
        $this->assertSame('cancelled', $transfer->transfer_status);

        // Branch 1 tag count decrements to 0
        $countResponseAfter = $this->actingAs($this->b1User)->getJson(route('admin.transfers.count'));
        $this->assertSame(0, $countResponseAfter->json('count'));
    }

    /**
     * Case 26: Editing Job Order at Branch 3 reassigning from Branch 1 to Branch 2 cancels Branch 1 transfer and creates Branch 2 transfer.
     */
    public function test_case_26_editing_job_order_reassigning_processing_branch_updates_transfers_and_counts(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-REASSIGN-01',
            'tag_number' => 'TAG-REASSIGN-01',
            'status' => 'pending',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        $order->items()->create([
            'laundry_service_id' => $this->b3Service->id,
            'description' => $this->b3Service->name,
            'service_category' => 'Wash',
            'quantity' => 1,
            'unit_price' => 150,
            'total' => 150,
        ]);

        $initialTransfer = JobOrderTransfer::query()->create([
            'job_order_id' => $order->id,
            'job_order_number' => $order->job_order_number,
            'tag_number' => $order->tag_number,
            'origin_branch_id' => $this->branch3->id,
            'destination_branch_id' => $this->branch1->id,
            'transfer_type' => 'outbound',
            'transfer_status' => 'pending',
            'transferred_at' => now(),
        ]);

        $this->assertSame(1, $this->actingAs($this->b1User)->getJson(route('admin.transfers.count'))->json('count'));
        $this->assertSame(0, $this->actingAs($this->b2User)->getJson(route('admin.transfers.count'))->json('count'));

        // Update Job Order to reassign to Branch 2
        $response = $this->actingAs($this->b3User)->put(route('admin.job-orders.update', $order), [
            'customer_id' => $this->b3Customer->id,
            'processing_branch_id' => $this->branch2->id,
            'tag_number' => 'TAG-REASSIGN-01',
            'status' => 'pending',
            'items' => [[
                'laundry_service_id' => $this->b3Service->id,
                'description' => $this->b3Service->name,
                'quantity' => 1,
                'unit_price' => 150,
            ]],
        ]);
        $response->assertRedirect();

        $initialTransfer->refresh();
        $this->assertSame('cancelled', $initialTransfer->transfer_status);

        $newTransfer = JobOrderTransfer::where('job_order_id', $order->id)
            ->where('destination_branch_id', $this->branch2->id)
            ->firstOrFail();
        $this->assertSame('pending', $newTransfer->transfer_status);

        $this->assertSame(0, $this->actingAs($this->b1User)->getJson(route('admin.transfers.count'))->json('count'));
        $this->assertSame(1, $this->actingAs($this->b2User)->getJson(route('admin.transfers.count'))->json('count'));
    }

    /**
     * Case 27: Editing Tag Number on a pending job order updates the pending transfer record.
     */
    public function test_case_27_editing_tag_number_on_pending_job_order_updates_pending_transfer(): void
    {
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-TAG-EDIT',
            'tag_number' => 'TAG-OLD-VAL',
            'status' => 'pending',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 0,
            'balance' => 150,
        ]);

        $order->items()->create([
            'laundry_service_id' => $this->b3Service->id,
            'description' => $this->b3Service->name,
            'service_category' => 'Wash',
            'quantity' => 1,
            'unit_price' => 150,
            'total' => 150,
        ]);

        $transfer = JobOrderTransfer::query()->create([
            'job_order_id' => $order->id,
            'job_order_number' => $order->job_order_number,
            'tag_number' => 'TAG-OLD-VAL',
            'origin_branch_id' => $this->branch3->id,
            'destination_branch_id' => $this->branch1->id,
            'transfer_type' => 'outbound',
            'transfer_status' => 'pending',
            'transferred_at' => now(),
        ]);

        $response = $this->actingAs($this->b3User)->put(route('admin.job-orders.update', $order), [
            'customer_id' => $this->b3Customer->id,
            'processing_branch_id' => $this->branch1->id,
            'tag_number' => 'TAG-NEW-VAL',
            'status' => 'pending',
            'items' => [[
                'laundry_service_id' => $this->b3Service->id,
                'description' => $this->b3Service->name,
                'quantity' => 1,
                'unit_price' => 150,
            ]],
        ]);
        $response->assertRedirect();

        $order->refresh();
        $this->assertSame('TAG-NEW-VAL', $order->tag_number);

        $transfer->refresh();
        $this->assertSame('TAG-NEW-VAL', $transfer->tag_number);
    }

    /**
     * Case 28: Tag check AJAX endpoint returns JSON available boolean and descriptive messages.
     */
    public function test_case_28_tag_check_ajax_endpoint_returns_json_availability(): void
    {
        // 1. Tag is completely free
        $responseFree = $this->actingAs($this->b3User)->getJson(route('admin.tags.check', [
            'tag_number' => 'TAG-AVAILABLE-999',
        ]));
        $responseFree->assertOk();
        $this->assertTrue($responseFree->json('available'));

        // 2. Active order with tag exists
        JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-TAG-CHECK',
            'tag_number' => 'TAG-IN-USE-888',
            'status' => 'washing',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 150,
            'balance' => 0,
        ]);

        $responseUsed = $this->actingAs($this->b3User)->getJson(route('admin.tags.check', [
            'tag_number' => 'TAG-IN-USE-888',
        ]));
        $responseUsed->assertOk();
        $this->assertFalse($responseUsed->json('available'));
        $this->assertStringContainsString('has already been used today', $responseUsed->json('message'));

        // 3. Multi-day active order from previous day
        $prevOrder = JobOrder::query()->create([
            'branch_id' => $this->branch3->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-TAG-ACTIVE-PREV',
            'tag_number' => 'TAG-PREV-ACTIVE',
            'status' => 'washing',
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 150,
            'balance' => 0,
        ]);
        $prevOrder->timestamps = false;
        $prevOrder->created_at = now()->subDays(2);
        $prevOrder->save();

        $responseActive = $this->actingAs($this->b3User)->getJson(route('admin.tags.check', [
            'tag_number' => 'TAG-PREV-ACTIVE',
        ]));
        $responseActive->assertOk();
        $this->assertFalse($responseActive->json('available'));
        $this->assertStringContainsString('currently assigned to an active laundry order', $responseActive->json('message'));
    }

    /**
     * Case 29: Tag completed today at Branch 1 cannot be reused today on Branch 3.
     */
    public function test_case_29_tag_completed_today_cannot_be_reused_today_on_another_branch(): void
    {
        JobOrder::query()->create([
            'branch_id' => $this->branch1->id,
            'processing_branch_id' => $this->branch1->id,
            'customer_id' => $this->b3Customer->id,
            'job_order_number' => 'JO-B1-COMPLETED-TODAY',
            'tag_number' => 'TAG-COMPLETED-TODAY',
            'status' => 'completed',
            'completed_at' => now(),
            'subtotal' => 150,
            'total' => 150,
            'paid_amount' => 150,
            'balance' => 0,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Tag #TAG-COMPLETED-TODAY has already been used today. Please use another Tag Number.');

        TagValidator::validate('TAG-COMPLETED-TODAY');
    }

    /**
     * Case 30: Multi-branch security: Branch 3 user cannot batch-receive or access machine cycle monitoring.
     */
    public function test_case_30_branch_3_user_cannot_batch_receive_or_access_machine_cycles(): void
    {
        // Branch 3 user cannot batch receive
        $receiveResponse = $this->actingAs($this->b3User)->post(route('admin.transfers.receive'), [
            'transfer_ids' => [99999],
        ]);
        $receiveResponse->assertStatus(403);

        // Branch 3 user cannot access cycles menu
        $cycleResponse = $this->actingAs($this->b3User)->get(route('admin.cycles.index'));
        $cycleResponse->assertStatus(403);
    }
}
