<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\JobOrder;
use App\Models\LaundryService;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAddOnTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

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

        $this->branch = Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => 'Manila',
            'contact_number' => '09171234567',
            'branch_type' => 'full_service',
            'machine_count' => 2,
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $this->branch->id,
            'access' => ['job_orders', 'inventory', 'services'],
        ]);
    }

    private function createAddOn(array $overrides = []): Inventory
    {
        $this->actingAs($this->manager)->post(route('admin.inventory.store'), array_merge([
            'branch_id' => $this->branch->id,
            'name' => 'Downy',
            'unit' => 'pcs',
            'quantity' => 3,
            'reorder_level' => 1,
            'unit_cost' => 10,
            'is_active' => 1,
            'is_add_on' => 1,
            'add_on_type' => 'fabcon',
            'selling_price' => 15,
        ], $overrides))->assertSessionHasNoErrors();

        return Inventory::query()->where('name', $overrides['name'] ?? 'Downy')->firstOrFail();
    }

    private function orderAddOn(LaundryService $service, int $quantity)
    {
        $customer = Customer::query()->firstOrCreate(
            ['branch_id' => $this->branch->id, 'name' => 'Laundry Customer'],
            ['phone' => '09171234567', 'billing_type' => 'regular', 'is_active' => true]
        );

        return $this->actingAs($this->manager)->post(route('admin.job-orders.store'), [
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'items' => [[
                'laundry_service_id' => $service->id,
                'description' => $service->name,
                'quantity' => $quantity,
                'unit_price' => (float) $service->price,
            ]],
            'discount' => 0,
            'paid_amount' => 0,
            'transaction_type' => 'walk_in',
        ]);
    }

    public function test_flagging_inventory_item_as_add_on_creates_linked_job_order_service(): void
    {
        $inventory = $this->createAddOn();

        $service = $inventory->addOnService;
        $this->assertNotNull($service);
        $this->assertSame('Downy', $service->name);
        $this->assertSame('fabcon', $service->report_category);
        $this->assertSame('piece', $service->pricing_type);
        $this->assertEquals(15, (float) $service->price);
        $this->assertTrue($service->is_active);
        $this->assertSame('Add Ons', $service->serviceCategory->name);
        $this->assertEquals(1, (float) $service->inventoryUsages()->where('inventory_id', $inventory->id)->value('quantity'));

        $this->actingAs($this->manager)->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('Sell as Add-On in Job Orders')
            ->assertSee('Add-On · Fabcon');

        // Shows in the Job Order POS with its stock.
        $this->actingAs($this->manager)->get(route('admin.job-orders.create'))
            ->assertOk()
            ->assertViewHas('services', fn ($services) => collect($services)->contains(fn ($s) => $s['id'] === $service->id
                && $s['is_add_on'] === true
                && $s['stock'] === 3.0
                && $s['low_stock'] === false));

        // Hidden from the Services page and cannot be edited there.
        $this->actingAs($this->manager)->get(route('admin.services.index'))
            ->assertOk()
            ->assertDontSee('services/'.$service->id, false);
        $this->actingAs($this->manager)->delete(route('admin.services.destroy', $service))
            ->assertSessionHasErrors('name');
        $this->assertNotSoftDeleted($service);
    }

    public function test_editing_inventory_updates_add_on_and_unflagging_stops_selling_it(): void
    {
        $inventory = $this->createAddOn();

        $payload = [
            'branch_id' => $this->branch->id,
            'name' => 'Downy Sunrise',
            'unit' => 'pcs',
            'quantity' => 3,
            'reorder_level' => 1,
            'unit_cost' => 10,
            'is_active' => 1,
            'is_add_on' => 1,
            'add_on_type' => 'fabcon',
            'selling_price' => 18,
        ];
        $this->actingAs($this->manager)->put(route('admin.inventory.update', $inventory), $payload)->assertSessionHasNoErrors();

        $service = $inventory->fresh()->addOnService;
        $this->assertSame('Downy Sunrise', $service->name);
        $this->assertEquals(18, (float) $service->price);

        $this->actingAs($this->manager)->put(route('admin.inventory.update', $inventory), array_merge($payload, ['is_add_on' => 0]))->assertSessionHasNoErrors();
        $this->assertFalse($service->fresh()->is_active);
        $this->assertEquals(18, (float) $inventory->fresh()->selling_price, 'Price is kept for re-enabling.');

        $this->actingAs($this->manager)->delete(route('admin.inventory.destroy', $inventory));
        $this->assertFalse($service->fresh()->is_active);
    }

    public function test_add_on_requires_type_and_selling_price(): void
    {
        $this->actingAs($this->manager)->post(route('admin.inventory.store'), [
            'branch_id' => $this->branch->id,
            'name' => 'Ariel',
            'unit' => 'pcs',
            'quantity' => 3,
            'reorder_level' => 1,
            'unit_cost' => 10,
            'is_add_on' => 1,
        ])->assertSessionHasErrors(['add_on_type', 'selling_price']);
    }

    public function test_selling_add_on_deducts_inventory_and_blocks_when_insufficient(): void
    {
        $inventory = $this->createAddOn(['quantity' => 3]);
        $service = $inventory->addOnService;

        $this->orderAddOn($service, 2)->assertSessionHasNoErrors();
        $this->assertEquals(1, (float) $inventory->fresh()->quantity);

        // Only 1 left: an order for 2 is refused and nothing is deducted.
        $this->orderAddOn($service, 2)->assertSessionHasErrors('items');
        $this->assertEquals(1, (float) $inventory->fresh()->quantity);
        $this->assertSame(1, JobOrder::query()->count());

        // Cancelling the order returns the stock.
        $order = JobOrder::query()->firstOrFail();
        $this->actingAs($this->manager)->patch(route('admin.job-orders.cancel', $order), ['cancel_reason' => 'Customer changed mind']);
        $this->assertEquals(3, (float) $inventory->fresh()->quantity);
    }

    public function test_default_add_on_products_are_seeded_into_branch_inventory(): void
    {
        \App\Support\InventoryAddOns::seedForBranch($this->branch);

        $names = Inventory::query()->where('branch_id', $this->branch->id)->where('is_add_on', true)->pluck('name')->sort()->values()->all();
        $this->assertSame(['Ariel', 'Breeze', 'Del', 'Downy', 'Surf Detergent', 'Surf Fabcon', 'Tide'], $names);
        $this->assertSame(7, LaundryService::query()->where('branch_id', $this->branch->id)->whereNotNull('add_on_inventory_id')->count());

        // Running the default service seeder must not remove the add-ons.
        \App\Support\DefaultLaundryServices::seedForBranch($this->branch);
        $this->assertSame(7, LaundryService::query()->where('branch_id', $this->branch->id)->whereNotNull('add_on_inventory_id')->count());
    }
}
