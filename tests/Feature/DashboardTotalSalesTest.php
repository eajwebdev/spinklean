<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchExpense;
use App\Models\Customer;
use App\Models\LaundryService;
use App\Models\PoTransaction;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTotalSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_splits_total_sales_into_job_order_and_po_sales(): void
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
        SystemTrialSetting::query()->create([
            'trial_enabled' => true,
            'trial_start_date' => now()->subDay()->toDateString(),
            'trial_end_date' => now()->addDay()->toDateString(),
            'trial_status' => 'active',
            'grace_period_days' => 0,
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $service = LaundryService::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Wash Dry Fold',
            'pricing_type' => 'kilo',
            'price' => 100,
            'is_active' => true,
        ]);
        $regular = Customer::query()->create(['branch_id' => $branch->id, 'name' => 'Juan', 'billing_type' => 'regular', 'is_active' => true]);
        $company = Customer::query()->create(['branch_id' => $branch->id, 'name' => 'Acme Corp', 'billing_type' => 'po', 'is_active' => true]);

        $item = fn (int $kilos) => [[
            'laundry_service_id' => $service->id,
            'description' => 'Wash Dry Fold',
            'quantity' => $kilos,
            'unit_price' => 100,
        ]];

        // Normal job order paid in cash: PHP 200.
        $this->actingAs($admin)->post(route('admin.job-orders.store'), [
            'branch_id' => $branch->id,
            'customer_id' => $regular->id,
            'payment_type' => 'cash',
            'paid_amount' => 200,
            'items' => $item(2),
        ])->assertSessionHasNoErrors();

        // PO job order: PHP 300 billed, PHP 100 collected so far.
        $this->actingAs($admin)->post(route('admin.job-orders.store'), [
            'branch_id' => $branch->id,
            'customer_id' => $company->id,
            'payment_type' => 'po',
            'payment_reference_no' => 'PO-ACME-1',
            'items' => $item(3),
        ])->assertSessionHasNoErrors();

        $po = PoTransaction::query()->where('po_number', 'PO-ACME-1')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.po-transactions.update', $po), [
            'status' => 'billed',
            'payment_method' => 'cash',
            'paid_amount' => 100,
        ])->assertSessionHasNoErrors();

        // Expenses: PHP 120 from store cash + PHP 30 paid by the owner = PHP 150 in all.
        BranchExpense::query()->create(['branch_id' => $branch->id, 'category' => 'Supplies', 'title' => 'Detergent', 'amount' => 120, 'expense_date' => today(), 'payment_method' => 'cash', 'paid_from' => 'store_cash', 'created_by' => $admin->id]);
        BranchExpense::query()->create(['branch_id' => $branch->id, 'category' => 'Utilities', 'title' => 'Water', 'amount' => 30, 'expense_date' => today(), 'payment_method' => 'cash', 'paid_from' => 'owner', 'created_by' => $admin->id]);

        $sales = $this->actingAs($admin)->getJson(route('dashboard.data'))->assertOk()->json('sales');

        // Net = (200 + 300) - 150.
        $this->assertSame('PHP 150.00', $sales['all_expenses']);
        $this->assertSame('2', $sales['expenses_count']);
        $this->assertSame('PHP 350.00', $sales['net_after_expenses']);
        $this->assertFalse($sales['net_is_negative']);

        $this->assertSame('PHP 200.00', $sales['job_order_sales']);
        $this->assertSame('PHP 300.00', $sales['po_sales']);
        $this->assertSame('PHP 500.00', $sales['combined_sales']);
        $this->assertSame('1', $sales['po_count']);
        $this->assertSame('PHP 100.00', $sales['po_collected']);
        $this->assertSame('PHP 200.00', $sales['po_balance']);

        // Filtered to a branch with nothing: all zero.
        $other = Branch::query()->create(['name' => 'Other', 'code' => 'OTH', 'is_active' => true]);
        $empty = $this->actingAs($admin)->getJson(route('dashboard.data', ['branch_id' => $other->id]))->json('sales');
        $this->assertSame('PHP 0.00', $empty['combined_sales']);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Job Order Sales')
            ->assertSee('PO Sales')
            ->assertSee('Total Sales (Job Order + PO)')
            ->assertSee('Net Sales After All Expenses');
    }
}
