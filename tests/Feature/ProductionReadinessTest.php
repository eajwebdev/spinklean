<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DailyTask;
use App\Models\DailyTaskCompletion;
use App\Models\JobOrder;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Menu;
use App\Support\StatusBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_badges_have_distinct_identifiable_colors(): void
    {
        $statuses = [
            'pending',
            'washing',
            'drying',
            'folding',
            'ready_for_pickup',
            'ready_for_delivery',
            'completed',
            'cancelled',
            'unpaid',
            'paid',
            'overdue',
            'suspended',
            'queued',
            'sent',
            'failed',
        ];

        $classes = collect($statuses)
            ->mapWithKeys(fn (string $status) => [$status => StatusBadge::classes($status)]);

        $this->assertStringContainsString('amber', $classes['pending']);
        $this->assertStringContainsString('blue', $classes['washing']);
        $this->assertStringContainsString('cyan', $classes['drying']);
        $this->assertStringContainsString('purple', $classes['folding']);
        $this->assertStringContainsString('teal', $classes['ready_for_pickup']);
        $this->assertStringContainsString('orange', $classes['ready_for_delivery']);
        $this->assertStringContainsString('green', $classes['completed']);
        $this->assertStringContainsString('red', $classes['cancelled']);
        $this->assertStringContainsString('orange', $classes['unpaid']);
        $this->assertStringContainsString('slate', $classes['suspended']);
    }

    public function test_menu_routes_are_real(): void
    {
        $missing = collect(Menu::items())
            ->pluck('route')
            ->reject(fn (string $route) => Route::has($route))
            ->all();

        $this->assertSame([], $missing);
    }

    public function test_core_admin_pages_render_for_super_admin(): void
    {
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

        Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => 'Manila',
            'contact_number' => '09171234567',
            'is_active' => true,
        ]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        foreach ([
            'dashboard',
            'admin.branches.index',
            'admin.users.index',
            'admin.customers.index',
            'admin.services.index',
            'admin.job-orders.index',
            'admin.cycles.index',
            'admin.payments.index',
            'admin.receivables.index',
            'admin.expenses.index',
            'admin.accounts-payable.index',
            'admin.petty-cash.index',
            'admin.z-readings.index',
            'admin.inventory.index',
            'admin.employees.index',
            'admin.attendance.index',
            'admin.daily-tasks.index',
            'admin.reports.index',
            'admin.sms-logs.index',
            'admin.billing.index',
            'admin.settings.edit',
        ] as $route) {
            $this->actingAs($superAdmin)
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_sidebar_renders_open_by_default_and_closed_on_pos(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'is_completed' => true,
        ]);
        $branch = Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('xl:translate-x-0', false)
            ->assertSee('xl:pl-64', false);

        $this->actingAs($admin)
            ->get(route('admin.job-orders.create'))
            ->assertOk()
            ->assertSee('xl:-translate-x-full', false)
            ->assertSee('xl:pl-0', false)
            ->assertSee("sidebarVisible ? '!translate-x-0' : '!-translate-x-full'", false)
            ->assertSee("desktopSidebarOpen ? 'xl:!pl-64' : 'xl:!pl-0'", false);
    }

    public function test_hidden_legacy_payment_options_are_not_rendered_in_operational_ui(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#0EA5E9',
            'is_completed' => true,
        ]);

        Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);

        foreach ([
            'admin.job-orders.create',
            'admin.payments.index',
            'admin.receivables.index',
            'admin.customers.index',
            'admin.accounts-payable.index',
            'admin.z-readings.create',
        ] as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk()
                ->assertDontSee('value="bank"', false)
                ->assertDontSee("value='bank'", false)
                ->assertDontSee('value="monthly_billing"', false)
                ->assertDontSee("value='monthly_billing'", false)
                ->assertDontSee('Monthly Billing');
        }
    }

    public function test_filterable_admin_modules_apply_search_status_and_branch_filters(): void
    {
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

        $mainBranch = Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => 'Manila',
            'contact_number' => '09171234567',
            'is_active' => true,
        ]);
        $hubBranch = Branch::query()->create([
            'name' => 'Delivery Hub',
            'code' => 'HUB',
            'address' => 'Makati',
            'contact_number' => '09170000000',
            'is_active' => true,
        ]);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $mainUser = User::factory()->create([
            'name' => 'Alice Filter',
            'role' => 'cashier',
            'branch_id' => $mainBranch->id,
            'status' => 'active',
        ]);
        $hubUser = User::factory()->create([
            'name' => 'Bob Hidden',
            'role' => 'cashier',
            'branch_id' => $hubBranch->id,
            'status' => 'active',
        ]);
        $mainCustomer = Customer::query()->create([
            'branch_id' => $mainBranch->id,
            'name' => 'Main Customer',
            'phone' => '09171111111',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);
        $hubCustomer = Customer::query()->create([
            'branch_id' => $hubBranch->id,
            'name' => 'Hub Customer',
            'phone' => '09172222222',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);

        JobOrder::query()->create([
            'branch_id' => $mainBranch->id,
            'customer_id' => $mainCustomer->id,
            'job_order_number' => 'JO-FILTER-MAIN',
            'status' => 'pending',
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'balance' => 0,
        ]);
        JobOrder::query()->create([
            'branch_id' => $hubBranch->id,
            'customer_id' => $hubCustomer->id,
            'job_order_number' => 'JO-FILTER-HUB',
            'status' => 'pending',
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'balance' => 0,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.branches.index', ['search' => 'Delivery']))
            ->assertOk()
            ->assertSee('Delivery Hub')
            ->assertDontSee('Main Branch');

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index', ['branch_id' => $mainBranch->id, 'search' => 'Alice']))
            ->assertOk()
            ->assertSee($mainUser->email)
            ->assertDontSee($hubUser->email);

        $this->actingAs($superAdmin)
            ->get(route('admin.cycles.index', ['branch_id' => $mainBranch->id, 'search' => 'FILTER']))
            ->assertOk()
            ->assertSee('JO-FILTER-MAIN')
            ->assertDontSee('JO-FILTER-HUB');
    }

    public function test_customer_module_shows_laundry_visit_count_and_loyal_status(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'is_completed' => true,
        ]);

        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Ten Visit Customer',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['role' => 'super_admin']);

        foreach (range(1, 10) as $orderNumber) {
            JobOrder::query()->create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'job_order_number' => "JO-VISIT-{$orderNumber}",
                'status' => 'completed',
                'subtotal' => 0,
                'discount' => 0,
                'tax' => 0,
                'total' => 0,
                'paid_amount' => 0,
                'balance' => 0,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Laundry Visits')
            ->assertSee('Ten Visit Customer')
            ->assertSee('10')
            ->assertSee('Loyal');
    }

    public function test_system_assistant_is_role_and_branch_scoped(): void
    {
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

        $branchA = Branch::query()->create(['name' => 'Branch A', 'code' => 'A', 'is_active' => true]);
        $branchB = Branch::query()->create(['name' => 'Branch B', 'code' => 'B', 'is_active' => true]);
        $customerA = Customer::query()->create(['branch_id' => $branchA->id, 'name' => 'A Customer', 'billing_type' => 'regular', 'is_active' => true]);
        $customerB = Customer::query()->create(['branch_id' => $branchB->id, 'name' => 'B Customer', 'billing_type' => 'regular', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branchA->id]);
        $cashier = User::factory()->create(['role' => 'cashier', 'branch_id' => $branchA->id]);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        Payment::query()->create([
            'branch_id' => $branchA->id,
            'customer_id' => $customerA->id,
            'received_by' => $manager->id,
            'payment_number' => 'PAY-A-001',
            'payment_type' => 'cash',
            'amount' => 100,
            'paid_at' => today(),
        ]);
        Payment::query()->create([
            'branch_id' => $branchB->id,
            'customer_id' => $customerB->id,
            'received_by' => $superAdmin->id,
            'payment_number' => 'PAY-B-001',
            'payment_type' => 'cash',
            'amount' => 900,
            'paid_at' => today(),
        ]);

        $payload = [
            'preset' => 'daily_sales',
            'branch_id' => $branchB->id,
            'date_range' => today()->toDateString().' to '.today()->toDateString(),
        ];

        $this->actingAs($manager)
            ->postJson(route('dashboard.assistant'), $payload)
            ->assertOk()
            ->assertJsonPath('scope', 'Branch A')
            ->assertJsonPath('metrics.0.value', 'PHP 100.00');

        $this->actingAs($superAdmin)
            ->postJson(route('dashboard.assistant'), $payload)
            ->assertOk()
            ->assertJsonPath('scope', 'Branch B')
            ->assertJsonPath('metrics.0.value', 'PHP 900.00');

        $this->actingAs($superAdmin)
            ->postJson(route('dashboard.assistant'), [
                'question' => 'What is the payment method breakdown?',
                'branch_id' => $branchB->id,
                'date_range' => today()->toDateString().' to '.today()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('preset', 'payment_mix')
            ->assertJsonPath('scope', 'Branch B');

        $this->actingAs($cashier)
            ->postJson(route('dashboard.assistant'), $payload)
            ->assertForbidden();
    }

    public function test_dashboard_lists_trusted_customers_with_branch_scoping(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#0EA5E9',
            'is_completed' => true,
        ]);

        $branchA = Branch::query()->create(['name' => 'Branch A', 'code' => 'A', 'is_active' => true]);
        $branchB = Branch::query()->create(['name' => 'Branch B', 'code' => 'B', 'is_active' => true]);
        $customerA = Customer::query()->create(['branch_id' => $branchA->id, 'name' => 'Trusted Customer A', 'billing_type' => 'regular', 'is_active' => true]);
        $customerBelowThreshold = Customer::query()->create(['branch_id' => $branchA->id, 'name' => 'Nine Order Customer', 'billing_type' => 'regular', 'is_active' => true]);
        $customerB = Customer::query()->create(['branch_id' => $branchB->id, 'name' => 'Trusted Customer B', 'billing_type' => 'regular', 'is_active' => true]);

        foreach ([[$branchA, $customerA, 'A', 10], [$branchA, $customerBelowThreshold, 'NINE', 9], [$branchB, $customerB, 'B', 10]] as [$branch, $customer, $number, $orderCount]) {
            foreach (range(1, $orderCount) as $orderNumber) {
                JobOrder::query()->create([
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'job_order_number' => "JO-TRUST-{$number}-{$orderNumber}",
                    'status' => 'washing',
                    'subtotal' => 100,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => 100,
                    'paid_amount' => 100,
                    'balance' => 0,
                ]);
            }
        }

        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branchA->id,
            'access' => ['dashboard'],
        ]);

        $this->actingAs($manager)
            ->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('trusted_customers.0.name', 'Trusted Customer A')
            ->assertJsonPath('trusted_customers.0.orders_count', '10')
            ->assertJsonMissing(['name' => 'Nine Order Customer'])
            ->assertJsonMissing(['name' => 'Trusted Customer B']);
    }

    public function test_assistant_active_cycles_include_processing_branch_work(): void
    {
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

        $dropoffBranch = Branch::query()->create(['name' => 'Pickup Branch', 'code' => 'PICK', 'branch_type' => 'pickup_dropoff', 'is_active' => true]);
        $productionBranch = Branch::query()->create(['name' => 'Production Branch', 'code' => 'PROD', 'branch_type' => 'full_service', 'is_active' => true]);
        $customer = Customer::query()->create(['branch_id' => $dropoffBranch->id, 'name' => 'Pickup Customer', 'billing_type' => 'regular', 'is_active' => true]);
        JobOrder::query()->create([
            'branch_id' => $dropoffBranch->id,
            'processing_branch_id' => $productionBranch->id,
            'production_accepted_at' => now(),
            'customer_id' => $customer->id,
            'job_order_number' => 'JO-PROCESSING-001',
            'status' => 'washing',
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'balance' => 0,
        ]);
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $productionBranch->id,
            'access' => ['dashboard'],
        ]);

        $this->actingAs($manager)
            ->postJson(route('dashboard.assistant'), [
                'preset' => 'active_cycles',
                'date_range' => today()->toDateString().' to '.today()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('scope', 'Production Branch')
            ->assertJsonPath('metrics.0.value', '1');
    }

    public function test_assistant_daily_tasks_include_global_branch_tasks(): void
    {
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

        $branch = Branch::query()->create(['name' => 'Branch A', 'code' => 'A', 'is_active' => true]);
        $globalTask = DailyTask::query()->create(['branch_id' => null, 'name' => 'Clean front desk', 'requires_photo' => true, 'is_active' => true]);
        DailyTask::query()->create(['branch_id' => $branch->id, 'name' => 'Count cash drawer', 'requires_photo' => true, 'is_active' => true]);
        DailyTaskCompletion::query()->create([
            'daily_task_id' => $globalTask->id,
            'branch_id' => $branch->id,
            'work_date' => today()->toDateString(),
            'photo_path' => 'daily-tasks/proof.jpg',
            'completed_at' => now(),
        ]);
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
            'access' => ['dashboard'],
        ]);

        $this->actingAs($manager)
            ->postJson(route('dashboard.assistant'), [
                'preset' => 'eod_tasks',
                'date_range' => today()->toDateString().' to '.today()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('scope', 'Branch A')
            ->assertJsonPath('metrics.0.value', '2')
            ->assertJsonPath('metrics.1.value', '1')
            ->assertJsonPath('metrics.2.value', '1');
    }
}
