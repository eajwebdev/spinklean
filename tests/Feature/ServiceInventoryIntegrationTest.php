<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\JobOrder;
use App\Models\LaundryService;
use App\Models\LaundryServiceCategory;
use App\Models\Payment;
use App\Models\ServicePreset;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\InventorySeeder;
use Database\Seeders\ExcelSampleServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceInventoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_choose_branch_when_adding_laundry_service(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'Spin Klean Laundry',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#2E7D32',
            'is_completed' => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'access' => ['services']]);
        $branchA = Branch::query()->create(['name' => 'Branch A', 'code' => 'A', 'is_active' => true]);
        $branchB = Branch::query()->create(['name' => 'Branch B', 'code' => 'B', 'is_active' => true]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.services.index', ['branch_id' => $branchA->id]));

        $response
            ->assertOk()
            ->assertSee('<select name="branch_id" required', false)
            ->assertSee('<option value="'.$branchA->id.'" selected>Branch A</option>', false)
            ->assertSee('<option value="'.$branchB->id.'" >Branch B</option>', false);
    }

    public function test_service_preset_can_be_created_and_loaded_in_pos_catalog(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'Spin Klean Laundry',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#2E7D32',
            'is_completed' => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'access' => ['services', 'job_orders']]);
        $branch = Branch::query()->create(['name' => 'Branch A', 'code' => 'A', 'is_active' => true]);
        $category = LaundryServiceCategory::query()->create(['name' => 'Packages', 'visibility' => 'all', 'is_active' => true]);
        $wash = LaundryService::query()->create(['branch_id' => $branch->id, 'service_category_id' => $category->id, 'name' => 'Wash', 'pricing_type' => 'load', 'price' => 80, 'is_active' => true]);
        $dry = LaundryService::query()->create(['branch_id' => $branch->id, 'service_category_id' => $category->id, 'name' => 'Dry', 'pricing_type' => 'load', 'price' => 70, 'is_active' => true]);

        $this
            ->actingAs($admin)
            ->post(route('admin.services.presets.store'), [
                'branch_id' => $branch->id,
                'service_category_id' => $category->id,
                'name' => 'Wash Dry Set',
                'sort_order' => 0,
                'is_active' => 1,
                'items' => [
                    $wash->id => 1,
                    $dry->id => 1,
                ],
            ])
            ->assertRedirect(route('admin.services.index', ['branch_id' => $branch->id]));

        $preset = ServicePreset::query()->where('name', 'Wash Dry Set')->firstOrFail();
        $this->assertSame($branch->id, $preset->branch_id);
        $this->assertSame(2, $preset->items()->count());

        $this
            ->actingAs($admin)
            ->get(route('admin.job-orders.create', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Wash Dry Set')
            ->assertSee('\u0022quantity\u0022:1');
    }

    public function test_service_preset_edit_url_opens_catalog_and_post_fallback_updates_preset(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'Spin Klean Laundry',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#2E7D32',
            'is_completed' => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'access' => ['services']]);
        $branch = Branch::query()->create(['name' => 'Branch A', 'code' => 'A', 'is_active' => true]);
        $category = LaundryServiceCategory::query()->create(['name' => 'Packages', 'visibility' => 'all', 'is_active' => true]);
        $wash = LaundryService::query()->create(['branch_id' => $branch->id, 'service_category_id' => $category->id, 'name' => 'Wash', 'pricing_type' => 'load', 'price' => 80, 'is_active' => true]);
        $dry = LaundryService::query()->create(['branch_id' => $branch->id, 'service_category_id' => $category->id, 'name' => 'Dry', 'pricing_type' => 'load', 'price' => 70, 'is_active' => true]);
        $fold = LaundryService::query()->create(['branch_id' => $branch->id, 'service_category_id' => $category->id, 'name' => 'Fold', 'pricing_type' => 'piece', 'price' => 30, 'is_active' => true]);
        $preset = ServicePreset::query()->create([
            'branch_id' => $branch->id,
            'service_category_id' => $category->id,
            'name' => 'Old Bundle',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $preset->items()->create(['laundry_service_id' => $wash->id, 'quantity' => 1]);

        $this
            ->actingAs($admin)
            ->get(route('admin.services.presets.show', $preset))
            ->assertRedirect(route('admin.services.index', [
                'branch_id' => $branch->id,
                'edit_preset' => $preset->id,
            ]));

        $this
            ->actingAs($admin)
            ->post(route('admin.services.presets.update.post', $preset), [
                'branch_id' => $branch->id,
                'service_category_id' => $category->id,
                'name' => 'Updated Bundle',
                'sort_order' => 2,
                'is_active' => 1,
                'items' => [
                    $dry->id => 2,
                    $fold->id => 1,
                ],
            ])
            ->assertRedirect(route('admin.services.index', ['branch_id' => $branch->id]));

        $preset->refresh()->load('items');

        $this->assertSame('Updated Bundle', $preset->name);
        $this->assertSame(2, $preset->sort_order);
        $this->assertSame([$dry->id, $fold->id], $preset->items->pluck('laundry_service_id')->sort()->values()->all());
        $this->assertDatabaseMissing('service_preset_items', [
            'service_preset_id' => $preset->id,
            'laundry_service_id' => $wash->id,
        ]);
    }

    public function test_pos_preset_cart_line_expands_to_service_sales_when_order_is_saved(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'Spin Klean Laundry',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#2E7D32',
            'is_completed' => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'access' => ['job_orders']]);
        $branch = Branch::query()->create(['name' => 'Branch A', 'code' => 'A', 'branch_type' => 'full_service', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Preset Customer',
            'billing_type' => 'regular',
            'unpaid_limit' => 0,
            'is_active' => true,
        ]);
        $category = LaundryServiceCategory::query()->create(['name' => 'Packages', 'visibility' => 'all', 'is_active' => true]);
        $wash = LaundryService::query()->create(['branch_id' => $branch->id, 'service_category_id' => $category->id, 'name' => 'Wash', 'pricing_type' => 'load', 'price' => 80, 'is_active' => true]);
        $dry = LaundryService::query()->create(['branch_id' => $branch->id, 'service_category_id' => $category->id, 'name' => 'Dry', 'pricing_type' => 'load', 'price' => 70, 'is_active' => true]);
        $fold = LaundryService::query()->create(['branch_id' => $branch->id, 'service_category_id' => $category->id, 'name' => 'Fold', 'pricing_type' => 'load', 'price' => 30, 'is_active' => true]);
        $preset = ServicePreset::query()->create([
            'branch_id' => $branch->id,
            'service_category_id' => $category->id,
            'name' => 'Wash Dry Set',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $preset->items()->create(['laundry_service_id' => $wash->id, 'quantity' => 1]);
        $preset->items()->create(['laundry_service_id' => $dry->id, 'quantity' => 1]);

        $this->actingAs($admin)
            ->post(route('admin.job-orders.store'), [
                'branch_id' => $branch->id,
                'processing_branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'items' => [
                    [
                        'service_preset_id' => $preset->id,
                        'description' => $preset->name,
                        'quantity' => 1,
                        'unit_price' => 150,
                    ],
                    [
                        'laundry_service_id' => $fold->id,
                        'description' => $fold->name,
                        'quantity' => 1,
                        'unit_price' => 30,
                    ],
                ],
                'discount' => 0,
                'paid_amount' => 0,
                'payment_type' => 'unpaid',
                'transaction_type' => 'walk_in',
            ])
            ->assertRedirect(route('admin.job-orders.index'));

        $order = JobOrder::query()->with('items')->firstOrFail();

        $items = $order->items->sortBy('description')->values();

        $this->assertSame('180.00', $order->subtotal);
        $this->assertSame(['Dry', 'Fold', 'Wash'], $items->pluck('description')->all());
        $this->assertSame(['70.00', '30.00', '80.00'], $items->pluck('unit_price')->all());
    }

    public function test_service_category_and_inventory_recipe_drive_job_order_snapshots_and_stock(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'Spin Klean Laundry',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#2E7D32',
            'is_completed' => true,
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::query()->create([
            'name' => 'Branch 1',
            'code' => 'B001',
            'branch_type' => 'full_service',
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Test Customer',
            'billing_type' => 'regular',
            'unpaid_limit' => 1000,
            'is_active' => true,
        ]);
        $inventory = Inventory::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Detergent Powder',
            'sku' => 'DET-TEST',
            'unit' => 'kg',
            'quantity' => 10,
            'reorder_level' => 1,
            'unit_cost' => 80,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.services.store'), [
                'branch_id' => $branch->id,
                'name' => 'Detergent',
                'report_category' => 'detergent',
                'pricing_type' => 'custom',
                'price' => 50,
                'is_active' => 1,
                'inventory_usages' => [$inventory->id => 0.25],
            ])
            ->assertRedirect(route('admin.services.index', ['branch_id' => $branch->id]));

        $service = LaundryService::query()->where('name', 'Detergent')->firstOrFail();
        $this->assertSame('detergent', $service->report_category);
        $this->assertSame('0.2500', $service->inventoryUsages()->firstOrFail()->quantity);

        $this->actingAs($admin)
            ->post(route('admin.job-orders.store'), [
                'branch_id' => $branch->id,
                'processing_branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'items' => [[
                    'laundry_service_id' => $service->id,
                    'description' => $service->name,
                    'quantity' => 2,
                    'unit_price' => 50,
                ]],
                'discount' => 0,
                'paid_amount' => 0,
                'payment_type' => 'unpaid',
                'transaction_type' => 'walk_in',
            ])
            ->assertRedirect(route('admin.job-orders.index'));

        $order = JobOrder::query()->firstOrFail();
        $this->assertSame('detergent', $order->items()->firstOrFail()->service_category);
        $this->assertSame('9.50', $inventory->fresh()->quantity);

        $this->actingAs($admin)
            ->put(route('admin.services.update', $service), [
                'branch_id' => $branch->id,
                'name' => 'Laundry Soap',
                'report_category' => 'detergent',
                'pricing_type' => 'custom',
                'price' => 50,
                'is_active' => 1,
                'inventory_usages' => [$inventory->id => 0.5],
            ])
            ->assertRedirect(route('admin.services.index', ['branch_id' => $branch->id]));

        $this->actingAs($admin)
            ->put(route('admin.job-orders.update', $order), [
                'customer_id' => $customer->id,
                'processing_branch_id' => $branch->id,
                'items' => [[
                    'laundry_service_id' => $service->id,
                    'description' => 'Laundry Soap',
                    'quantity' => 3,
                    'unit_price' => 50,
                ]],
                'discount' => 0,
                'status' => 'pending',
                'transaction_type' => 'walk_in',
            ])
            ->assertRedirect(route('admin.job-orders.show', $order));

        $this->assertSame('detergent', $order->fresh()->items()->firstOrFail()->service_category);
        $this->assertSame('8.50', $inventory->fresh()->quantity);

        $this->actingAs($admin)
            ->get(route('admin.services.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Z Reading Column')
            ->assertSee('Inventory Consumption')
            ->assertSee('View Inventory')
            ->assertSee('8.50 kg in stock')
            ->assertSee('Detergent');
    }

    public function test_inventory_seeder_preserves_used_stock_and_rebuilds_default_recipes(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Branch 1',
            'code' => 'B001',
            'branch_type' => 'full_service',
            'is_active' => true,
        ]);
        $detergent = Inventory::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Detergent Powder',
            'sku' => 'SUP-DETERGENT',
            'unit' => 'kg',
            'quantity' => 12.5,
            'reorder_level' => 1,
            'unit_cost' => 70,
            'is_active' => true,
        ]);
        $detergent->movements()->create([
            'movement_type' => 'out',
            'quantity' => 1,
            'remarks' => 'Existing usage',
        ]);
        $service = LaundryService::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Detergent',
            'report_category' => 'detergent',
            'pricing_type' => 'custom',
            'price' => 15,
            'is_active' => true,
        ]);

        $this->seed(InventorySeeder::class);

        $this->assertSame('12.50', $detergent->fresh()->quantity);
        $this->assertDatabaseHas('inventories', [
            'branch_id' => $branch->id,
            'sku' => 'PKG-PLASTIC-SMALL',
            'quantity' => 500,
        ]);
        $this->assertDatabaseHas('service_inventory_usages', [
            'laundry_service_id' => $service->id,
            'inventory_id' => $detergent->id,
            'quantity' => 0.1,
        ]);
    }

    public function test_inventory_page_shows_the_complete_default_inventory_list_on_one_page(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'Spin Klean Laundry',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#2E7D32',
            'is_completed' => true,
        ]);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::query()->create([
            'name' => 'Branch 1',
            'code' => 'B001',
            'branch_type' => 'full_service',
            'is_active' => true,
        ]);

        $this->seed(InventorySeeder::class);

        $response = $this->actingAs($admin)
            ->get(route('admin.inventory.index', ['branch_id' => $branch->id]))
            ->assertOk();

        foreach (['Bleach', 'Detergent Powder', 'Disinfectant', 'Dryer Sheet', 'Fabric Conditioner', 'Hanger', 'Laundry Bag', 'Plastic Big', 'Plastic Packaging', 'Plastic Small', 'Stain Remover'] as $name) {
            $response->assertSee($name);
        }
    }

    public function test_excel_sample_services_are_seeded_with_inventory_recipes(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Branch 1',
            'code' => 'B001',
            'branch_type' => 'full_service',
            'is_active' => true,
        ]);

        $this->seed(ExcelSampleServiceSeeder::class);

        foreach ([
            ['Regular Wash', 'wash', '60.00', 'Detergent Powder', '0.1000'],
            ['Regular Dry', 'dry', '80.00', 'Dryer Sheet', '1.0000'],
            ['Dry Extend 10 Minutes', 'dry_extend', '20.00', 'Dryer Sheet', '0.2500'],
            ['Fold Service', 'fold', '25.00', 'Plastic Packaging', '0.1000'],
        ] as [$name, $category, $price, $inventoryName, $quantity]) {
            $service = LaundryService::query()
                ->with('inventoryUsages.inventory')
                ->where('branch_id', $branch->id)
                ->where('name', $name)
                ->firstOrFail();

            $this->assertSame($category, $service->report_category);
            $this->assertSame($price, $service->price);

            $usage = $service->inventoryUsages
                ->first(fn ($usage) => $usage->inventory?->name === $inventoryName);

            $this->assertNotNull($usage);
            $this->assertSame($quantity, $usage->quantity);
        }
    }

    public function test_consolidated_z_reading_aggregates_worksheet_categories_by_date_range(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'Spin Klean Laundry',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#2E7D32',
            'is_completed' => true,
        ]);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::query()->create([
            'name' => 'Branch 1',
            'code' => 'B001',
            'branch_type' => 'full_service',
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Test Customer',
            'billing_type' => 'regular',
            'unpaid_limit' => 1000,
            'is_active' => true,
        ]);
        $wash = LaundryService::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Regular Wash',
            'report_category' => 'wash',
            'pricing_type' => 'load',
            'price' => 60,
            'is_active' => true,
        ]);
        $dry = LaundryService::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Regular Dry',
            'report_category' => 'dry',
            'pricing_type' => 'load',
            'price' => 80,
            'is_active' => true,
        ]);

        foreach ([
            ['date' => '2026-06-01 09:00:00', 'number' => 'JO-B001-20260601-0001', 'service' => $wash, 'quantity' => 2, 'total' => 120, 'paid' => 100],
            ['date' => '2026-06-02 09:00:00', 'number' => 'JO-B001-20260602-0001', 'service' => $dry, 'quantity' => 1, 'total' => 80, 'paid' => 80],
        ] as $index => $data) {
            $order = JobOrder::query()->create([
                'branch_id' => $branch->id,
                'processing_branch_id' => $branch->id,
                'current_branch_id' => $branch->id,
                'release_branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'created_by' => $admin->id,
                'job_order_number' => $data['number'],
                'status' => 'pending',
                'transaction_type' => 'walk_in',
                'subtotal' => $data['total'],
                'discount' => 0,
                'tax' => 0,
                'total' => $data['total'],
                'paid_amount' => $data['paid'],
                'balance' => $data['total'] - $data['paid'],
            ]);
            $order->forceFill([
                'created_at' => $data['date'],
                'updated_at' => $data['date'],
            ])->saveQuietly();
            $order->items()->create([
                'laundry_service_id' => $data['service']->id,
                'description' => $data['service']->name,
                'service_category' => $data['service']->report_category,
                'quantity' => $data['quantity'],
                'unit_price' => $data['service']->price,
                'total' => $data['total'],
            ]);
            Payment::query()->create([
                'branch_id' => $branch->id,
                'collected_branch_id' => $branch->id,
                'job_order_id' => $order->id,
                'customer_id' => $customer->id,
                'received_by' => $admin->id,
                'payment_number' => 'PAY-RANGE-'.($index + 1),
                'payment_type' => $index === 0 ? 'cash' : 'gcash',
                'amount' => $data['paid'],
                'paid_at' => $data['date'],
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'branch_id' => $branch->id,
                'date_range' => '2026-06-01 to 2026-06-02',
            ]))
            ->assertOk()
            ->assertSee('Daily Operations by Date')
            ->assertSee('Service Totals by Catalog Category')
            ->assertViewHas('zCategoryTotals', function ($totals) {
                return (float) $totals->get('Wash')->total_amount === 120.0
                    && (float) $totals->get('Dry')->total_amount === 80.0;
            })
            ->assertViewHas('zDailyOperations', function ($rows) {
                return $rows->count() === 2
                    && (float) $rows->sum('sales_amount') === 200.0
                    && (float) $rows->sum('cash_amount') === 100.0
                    && (float) $rows->sum('gcash_amount') === 80.0
                    && (float) $rows->sum('unpaid_amount') === 20.0;
            });

        $pdf = $this->actingAs($admin)
            ->get(route('admin.reports.z-reading.pdf', [
                'branch_id' => $branch->id,
                'date_range' => '2026-06-01 to 2026-06-02',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString('/MediaBox [0.000 0.000 841.890 595.280]', $pdf->getContent());
    }
}
