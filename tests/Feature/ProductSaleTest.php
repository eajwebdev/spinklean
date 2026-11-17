<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\JobOrder;
use App\Models\JobOrderTransfer;
use App\Models\LaundryService;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product sales: a cart with only add-on products (Ariel, Downy, plastic bottles, ...)
 * is sold from the selling branch's own stock, is never transferred to a processing
 * branch, and is completed/released right away.
 */
class ProductSaleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $dropoff;

    private Branch $production;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::query()->create([
            'business_name' => 'EAJ Laundry',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
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

        $this->production = Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => 'Manila',
            'contact_number' => '09171234567',
            'branch_type' => 'full_service',
            'machine_count' => 2,
            'is_active' => true,
        ]);

        $this->dropoff = Branch::query()->create([
            'name' => 'Third Branch',
            'code' => 'THIRD',
            'address' => 'Quezon City',
            'contact_number' => '09171234568',
            'branch_type' => 'pickup_dropoff',
            'machine_count' => 0,
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $this->dropoff->id,
            'access' => ['job_orders', 'inventory', 'services'],
        ]);
    }

    private function addOn(string $name, string $type = 'fabcon', float $price = 15, float $stock = 5, ?User $as = null): LaundryService
    {
        $user = $as ?? $this->cashier;

        $this->actingAs($user)->post(route('admin.inventory.store'), [
            'branch_id' => $user->branch_id,
            'name' => $name,
            'unit' => 'pcs',
            'quantity' => $stock,
            'reorder_level' => 1,
            'unit_cost' => 5,
            'is_active' => 1,
            'is_add_on' => 1,
            'add_on_type' => $type,
            'selling_price' => $price,
        ])->assertSessionHasNoErrors();

        return Inventory::query()->where('branch_id', $user->branch_id)->where('name', $name)->firstOrFail()->addOnService;
    }

    private function laundryService(Branch $branch): LaundryService
    {
        return LaundryService::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Wash 7kg',
            'report_category' => 'small',
            'pricing_type' => 'load',
            'price' => 60,
            'is_active' => true,
        ]);
    }

    private function customer(Branch $branch, array $overrides = []): Customer
    {
        return Customer::query()->create(array_merge([
            'branch_id' => $branch->id,
            'name' => 'Juan Dela Cruz',
            'phone' => '09171234567',
            'billing_type' => 'regular',
            'unpaid_limit' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function line(LaundryService $service, float $quantity): array
    {
        return [
            'laundry_service_id' => $service->id,
            'description' => $service->name,
            'quantity' => $quantity,
            'unit_price' => (float) $service->price,
        ];
    }

    private function sell(array $items, array $overrides = [], ?User $as = null)
    {
        $user = $as ?? $this->cashier;

        return $this->actingAs($user)->post(route('admin.job-orders.store'), array_merge([
            'branch_id' => $user->branch_id,
            'items' => $items,
            'discount' => 0,
            'paid_amount' => 0,
            'payment_type' => 'cash',
            'transaction_type' => 'walk_in',
        ], $overrides));
    }

    public function test_dropoff_branch_sells_add_ons_without_processing_branch_or_transfer(): void
    {
        $downy = $this->addOn('Downy', 'fabcon', 15, 5);
        $ariel = $this->addOn('Ariel', 'detergent', 20, 5);

        $this->sell([$this->line($downy, 2), $this->line($ariel, 1)])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.job-orders.index'));

        $order = JobOrder::query()->sole();
        $this->assertTrue($order->is_product_sale);
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertNotNull($order->released_at);
        $this->assertNotNull($order->inventory_deducted_at);
        $this->assertNull($order->tag_number);
        $this->assertSame($this->dropoff->id, (int) $order->processing_branch_id);
        $this->assertSame($this->dropoff->id, (int) $order->release_branch_id);
        $this->assertFalse($order->isTransferred());
        $this->assertSame(0, JobOrderTransfer::query()->count());

        // Stock comes out of the selling branch's own inventory.
        $this->assertEquals(3, (float) $downy->addOnInventory->fresh()->quantity);
        $this->assertEquals(4, (float) $ariel->addOnInventory->fresh()->quantity);

        // Paid in full and recorded as a normal payment for sales reports.
        $this->assertEquals(50, (float) $order->total);
        $this->assertEquals(0, (float) $order->balance);
        $this->assertEquals(50, (float) Payment::query()->where('job_order_id', $order->id)->sum('amount'));

        // No customer chosen: the branch's Walk-in Customer is used.
        $this->assertSame('Walk-in Customer', $order->customer->name);
        $this->assertSame($this->dropoff->id, (int) $order->customer->branch_id);
    }

    public function test_walk_in_customer_is_reused_across_sales(): void
    {
        $downy = $this->addOn('Downy', 'fabcon', 15, 5);

        $this->sell([$this->line($downy, 1)])->assertSessionHasNoErrors();
        $this->sell([$this->line($downy, 1)])->assertSessionHasNoErrors();

        $this->assertSame(1, Customer::query()->where('name', 'Walk-in Customer')->count());
        $this->assertSame(2, JobOrder::query()->where('is_product_sale', true)->count());
    }

    public function test_full_service_branch_can_also_sell_products_only(): void
    {
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $this->production->id,
            'access' => ['job_orders', 'inventory', 'services'],
        ]);
        $bottle = $this->addOn('Plastic Bottle', 'other', 10, 10, $manager);
        $customer = $this->customer($this->production);

        $this->sell([$this->line($bottle, 3)], ['customer_id' => $customer->id], $manager)
            ->assertSessionHasNoErrors();

        $order = JobOrder::query()->sole();
        $this->assertTrue($order->is_product_sale);
        $this->assertSame('completed', $order->status);
        $this->assertSame($customer->id, (int) $order->customer_id);
        $this->assertEquals(7, (float) $bottle->addOnInventory->fresh()->quantity);
        $this->assertSame('other', $order->items->first()->service_category);
    }

    public function test_other_add_on_type_can_be_saved_for_items_like_plastic_bottles(): void
    {
        $bottle = $this->addOn('Plastic Bottle', 'other', 10, 10);

        $this->assertTrue($bottle->isAddOn());
        $this->assertSame('other', $bottle->report_category);
        $this->assertSame('other', $bottle->addOnInventory->add_on_type);
    }

    public function test_cart_with_laundry_service_still_requires_processing_branch_and_transfers(): void
    {
        $downy = $this->addOn('Downy', 'fabcon', 15, 5);
        $wash = $this->laundryService($this->dropoff);
        $customer = $this->customer($this->dropoff);

        // Laundry in the cart: not a product sale, a processing branch is still required.
        $this->sell([$this->line($wash, 1), $this->line($downy, 1)], ['customer_id' => $customer->id])
            ->assertSessionHasErrors('processing_branch_id');
        $this->assertSame(0, JobOrder::query()->count());

        $this->sell([$this->line($wash, 1), $this->line($downy, 1)], [
            'customer_id' => $customer->id,
            'processing_branch_id' => $this->production->id,
        ])->assertSessionHasNoErrors();

        $order = JobOrder::query()->sole();
        $this->assertFalse($order->is_product_sale);
        $this->assertSame('pending', $order->status);
        $this->assertNull($order->released_at);
        $this->assertNotNull($order->tag_number);
        $this->assertSame($this->production->id, (int) $order->processing_branch_id);
        $this->assertSame(1, JobOrderTransfer::query()->where('job_order_id', $order->id)->count());
    }

    public function test_laundry_order_still_requires_a_customer(): void
    {
        $wash = $this->laundryService($this->dropoff);

        $this->sell([$this->line($wash, 1)], ['processing_branch_id' => $this->production->id])
            ->assertSessionHasErrors('customer_id');

        $this->assertSame(0, JobOrder::query()->count());
        $this->assertSame(0, Customer::query()->count());
    }

    public function test_walk_in_product_sale_must_be_paid_in_full(): void
    {
        $downy = $this->addOn('Downy', 'fabcon', 15, 5);

        $this->sell([$this->line($downy, 2)], ['payment_type' => 'unpaid'])
            ->assertSessionHasErrors('paid_amount');
        $this->sell([$this->line($downy, 2)], ['payment_type' => 'cash', 'paid_amount' => 10])
            ->assertSessionHasErrors('paid_amount');

        $this->assertSame(0, JobOrder::query()->count());
        $this->assertSame(0, Customer::query()->count(), 'The walk-in customer is rolled back with the failed sale.');
        $this->assertEquals(5, (float) $downy->addOnInventory->fresh()->quantity);
    }

    public function test_named_customer_can_take_product_sale_unpaid_within_unpaid_limit(): void
    {
        $downy = $this->addOn('Downy', 'fabcon', 15, 5);
        $trusted = $this->customer($this->dropoff, ['name' => 'Suki', 'unpaid_limit' => 100]);
        $regular = $this->customer($this->dropoff, ['name' => 'New Customer', 'unpaid_limit' => 0]);

        $this->sell([$this->line($downy, 1)], ['customer_id' => $regular->id, 'payment_type' => 'unpaid'])
            ->assertSessionHasErrors('paid_amount');

        $this->sell([$this->line($downy, 2)], ['customer_id' => $trusted->id, 'payment_type' => 'unpaid'])
            ->assertSessionHasNoErrors();

        $order = JobOrder::query()->sole();
        $this->assertEquals(30, (float) $order->balance);
        $this->assertSame('completed', $order->status);
        $this->assertEquals(3, (float) $downy->addOnInventory->fresh()->quantity);
    }

    public function test_product_sale_is_blocked_when_stock_is_insufficient(): void
    {
        $downy = $this->addOn('Downy', 'fabcon', 15, 1);

        $this->sell([$this->line($downy, 2)])->assertSessionHasErrors('items');

        $this->assertSame(0, JobOrder::query()->count());
        $this->assertEquals(1, (float) $downy->addOnInventory->fresh()->quantity);
    }

    public function test_product_sale_can_be_voided_and_stock_is_returned(): void
    {
        $downy = $this->addOn('Downy', 'fabcon', 15, 5);
        $this->sell([$this->line($downy, 2)])->assertSessionHasNoErrors();
        $order = JobOrder::query()->sole();

        $this->actingAs($this->cashier)
            ->patch(route('admin.job-orders.cancel', $order), ['cancel_reason' => 'Wrong item'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNull($order->released_at);
        $this->assertEquals(5, (float) $downy->addOnInventory->fresh()->quantity);
    }

    public function test_editing_product_sale_adjusts_stock_and_stays_completed_locally(): void
    {
        $downy = $this->addOn('Downy', 'fabcon', 15, 5);
        $this->sell([$this->line($downy, 2)])->assertSessionHasNoErrors();
        $order = JobOrder::query()->sole();

        $this->actingAs($this->cashier)->get(route('admin.job-orders.edit', $order))
            ->assertOk()
            ->assertSee('Product Sale');

        $payload = [
            'customer_id' => $order->customer_id,
            'items' => [$this->line($downy, 3)],
            'discount' => 0,
            'status' => 'pending',
            'transaction_type' => 'walk_in',
        ];

        $this->actingAs($this->cashier)->put(route('admin.job-orders.update', $order), $payload)
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('completed', $order->status, 'A product sale cannot be moved back into the laundry workflow.');
        $this->assertSame($this->dropoff->id, (int) $order->processing_branch_id);
        $this->assertEquals(2, (float) $downy->addOnInventory->fresh()->quantity);
        $this->assertSame(0, JobOrderTransfer::query()->count());

        // Laundry cannot be added to a product sale.
        $wash = $this->laundryService($this->dropoff);
        $this->actingAs($this->cashier)->put(route('admin.job-orders.update', $order), array_merge($payload, [
            'items' => [$this->line($downy, 3), $this->line($wash, 1)],
        ]))->assertSessionHasErrors('items');
        $this->assertEquals(2, (float) $downy->addOnInventory->fresh()->quantity);
    }

    public function test_new_branch_defaults_no_longer_include_for_sale_items_or_sachet(): void
    {
        \App\Support\DefaultLaundryServices::seedForBranch($this->production);

        $this->assertFalse(\App\Models\LaundryServiceCategory::query()->where('name', 'For Sale Items')->exists());
        $this->assertFalse(LaundryService::query()->where('branch_id', $this->production->id)->where('name', 'Sachet')->exists());
        $this->assertTrue(LaundryService::query()->where('branch_id', $this->production->id)->where('name', 'Wash 7kg')->exists());
    }

    public function test_product_sales_show_in_lists_and_pos_page_loads(): void
    {
        $downy = $this->addOn('Downy', 'fabcon', 15, 5);
        $this->sell([$this->line($downy, 1)])->assertSessionHasNoErrors();
        $order = JobOrder::query()->sole();

        $this->actingAs($this->cashier)->get(route('admin.job-orders.create'))->assertOk();
        $this->actingAs($this->cashier)->get(route('admin.job-orders.index'))
            ->assertOk()
            ->assertSee('Product Sale');
        $this->actingAs($this->cashier)->get(route('admin.job-orders.show', $order))
            ->assertOk()
            ->assertSee('Product Sale');
        $this->actingAs($this->cashier)->get(route('admin.job-orders.receipt', $order))->assertOk();
    }
}
