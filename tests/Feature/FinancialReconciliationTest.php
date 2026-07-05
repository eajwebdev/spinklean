<?php

namespace Tests\Feature;

use App\Models\AccountsPayable;
use App\Models\AccountsPayablePayment;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchExpense;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\MoneyMovement;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\ZReading;
use App\Support\FinancialReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_activity_logs_render_nested_properties(): void
    {
        SystemSetting::query()->create([
            'business_name' => 'EAJ Laundry',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'primary_color' => '#2E7D32',
            'is_completed' => true,
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        ActivityLog::query()->create([
            'user_id' => $admin->id,
            'branch_id' => $branch->id,
            'action' => 'job_order_deleted',
            'properties' => [
                'job_order_number' => 'JO-ARRAY-001',
                'payments' => [
                    ['type' => 'cash', 'amount' => 100],
                ],
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'date_range' => today()->toDateString().' to '.today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Activity Logs')
            ->assertSee('JO-ARRAY-001')
            ->assertSee('&quot;type&quot;:&quot;cash&quot;', false);
    }

    public function test_all_financial_modules_share_the_authoritative_reconciliation(): void
    {
        $date = '2026-06-14';
        $oldDate = '2026-06-01';
        $admin = User::factory()->create(['role' => 'super_admin']);
        $salesBranch = Branch::query()->create(['name' => 'Sales Branch', 'code' => 'SALE', 'is_active' => true]);
        $collectionBranch = Branch::query()->create(['name' => 'Collection Branch', 'code' => 'COLL', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $salesBranch->id,
            'name' => 'Reconciliation Customer',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);

        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Manila',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'is_completed' => true,
        ]);

        $order = $this->order($salesBranch, $customer, $admin, 'JO-RECON-001', 245, 70, 'pending', $date);
        $cancelled = $this->order($salesBranch, $customer, $admin, 'JO-RECON-CANCELLED', 90, 90, 'cancelled', $date);

        $this->payment($order, $admin, 'PAY-CASH-CROSS', 'cash', 100, $date, $collectionBranch);
        $this->payment($order, $admin, 'PAY-GCASH', 'gcash', 50, $date, $salesBranch);
        $this->payment($order, $admin, 'PAY-BANK', 'bank', 25, $date, $salesBranch);
        $this->payment($order, $admin, 'PAY-PO', 'po', 40, $date, $salesBranch);
        $this->payment($order, $admin, 'PAY-MONTHLY', 'monthly_billing', 30, $date, $salesBranch);
        $this->payment($order, $admin, 'PAY-HISTORICAL', 'cash', 999, $oldDate, $salesBranch);

        BranchExpense::query()->create([
            'branch_id' => $salesBranch->id,
            'category' => 'supplies',
            'expense_type' => 'supplies',
            'title' => 'Store cash expense',
            'amount' => 20,
            'expense_date' => $date,
            'payment_method' => 'cash',
            'paid_from' => 'store_cash',
            'created_by' => $admin->id,
        ]);
        BranchExpense::query()->create([
            'branch_id' => $salesBranch->id,
            'category' => 'utilities',
            'expense_type' => 'utilities',
            'title' => 'Store GCash expense',
            'amount' => 5,
            'expense_date' => $date,
            'payment_method' => 'GCash',
            'paid_from' => 'store_cash',
            'created_by' => $admin->id,
        ]);
        BranchExpense::query()->create([
            'branch_id' => $salesBranch->id,
            'category' => 'utilities',
            'expense_type' => 'utilities',
            'title' => 'Store bank expense',
            'amount' => 7,
            'expense_date' => $date,
            'payment_method' => 'bank',
            'paid_from' => 'store_cash',
            'created_by' => $admin->id,
        ]);
        BranchExpense::query()->create([
            'branch_id' => $salesBranch->id,
            'category' => 'utilities',
            'expense_type' => 'utilities',
            'title' => 'Owner-paid expense',
            'amount' => 30,
            'expense_date' => $date,
            'payment_method' => 'gcash',
            'paid_from' => 'owner',
            'created_by' => $admin->id,
        ]);

        MoneyMovement::query()->create([
            'branch_id' => $salesBranch->id,
            'recorded_by' => $admin->id,
            'movement_date' => $date,
            'type' => 'deposit',
            'direction' => 'in',
            'amount' => 200,
            'description' => 'Owner cash funding',
        ]);
        MoneyMovement::query()->create([
            'branch_id' => $salesBranch->id,
            'recorded_by' => $admin->id,
            'movement_date' => $date,
            'type' => 'withdraw',
            'direction' => 'out',
            'amount' => 60,
            'description' => 'Cash repayment and remittance',
        ]);

        $gcashPayable = $this->payable($salesBranch, $admin, 'AP-GCASH', 'gcash', 100, 80, $date);
        $bankPayable = $this->payable($salesBranch, $admin, 'AP-BANK', 'bank', 80, 70, $date);
        $this->payablePayment($gcashPayable, $salesBranch, $admin, 'APP-GCASH', 'gcash', 20, $date);
        $this->payablePayment($bankPayable, $salesBranch, $admin, 'APP-BANK', 'bank', 10, $date);

        ZReading::query()->create([
            'branch_id' => $salesBranch->id,
            'prepared_by' => $admin->id,
            'reading_number' => 'ZR-RECON-001',
            'business_date' => $date,
            'signature_name' => $admin->name,
            'over_short_amount' => 5,
        ]);

        $summary = FinancialReconciliation::forPeriod($salesBranch->id, $date, $date);

        $this->assertSame(245.0, $summary['sales_owned']);
        $this->assertSame(75.0, $summary['physical_collections']);
        $this->assertSame(0.0, $summary['cash_collections']);
        $this->assertSame(120.0, $summary['expected_cash_drawer']);
        $this->assertSame(125.0, $summary['expected_gcash']);
        $this->assertSame(88.0, $summary['expected_bank']);
        $this->assertSame(62.0, $summary['expenses_total']);
        $this->assertSame(5.0, $summary['store_gcash_expenses']);
        $this->assertSame(7.0, $summary['store_bank_expenses']);
        $this->assertSame(150.0, $summary['accounts_payable']);
        $this->assertSame(70.0, $summary['unpaid_balance']);
        $this->assertSame(5.0, $summary['over_short']);
        $this->assertSame(40.0, $summary['po_collections']);
        $this->assertSame(30.0, $summary['monthly_billing_collections']);
        $this->assertNotEquals($cancelled->balance, $summary['unpaid_balance']);

        $dashboard = $this->actingAs($admin)->getJson(route('dashboard.data', [
            'branch_id' => $salesBranch->id,
            'date_range' => "{$date} to {$date}",
        ]));
        $dashboard->assertOk()
            ->assertJsonPath('stats.sales', 'PHP 245.00')
            ->assertJsonPath('stats.collections', 'PHP 75.00')
            ->assertJsonPath('stats.cash_drawer', 'PHP 120.00')
            ->assertJsonPath('stats.gcash', 'PHP 125.00')
            ->assertJsonMissingPath('stats.bank')
            ->assertJsonPath('stats.expenses', 'PHP 62.00')
            ->assertJsonPath('stats.accounts_payable', 'PHP 150.00')
            ->assertJsonPath('stats.receivables', 'PHP 70.00')
            ->assertJsonPath('stats.over_short', 'PHP 5.00');

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'branch_id' => $salesBranch->id,
                'date_range' => "{$date} to {$date}",
            ]))
            ->assertOk()
            ->assertSee('Financial Reconciliation')
            ->assertSee('PHP 120.00')
            ->assertSee('PHP 125.00')
            ->assertSee('Expected Bank')
            ->assertSee('Legacy billing');

        $this->actingAs($admin)
            ->get(route('admin.z-readings.create', [
                'branch_id' => $salesBranch->id,
                'business_date' => $date,
            ]))
            ->assertOk()
            ->assertSee('PHP 120.00')
            ->assertSee('PHP 125.00')
            ->assertSee('Expected Bank net balance')
            ->assertDontSee('Monthly Billing');

        $collectionSummary = FinancialReconciliation::forPeriod($collectionBranch->id, $date, $date);
        $this->assertSame(100.0, $collectionSummary['cash_collections']);
        $this->assertSame(100.0, $collectionSummary['expected_cash_drawer']);
        $this->assertSame(0.0, $collectionSummary['sales_owned']);
    }

    private function order(Branch $branch, Customer $customer, User $user, string $number, float $total, float $balance, string $status, string $date): JobOrder
    {
        return JobOrder::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'job_order_number' => $number,
            'status' => $status,
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'paid_amount' => $total - $balance,
            'balance' => $balance,
            'created_at' => $date.' 09:00:00',
            'updated_at' => $date.' 09:00:00',
        ]);
    }

    private function payment(JobOrder $order, User $user, string $number, string $type, float $amount, string $date, Branch $collectedBranch): void
    {
        Payment::query()->create([
            'branch_id' => $order->branch_id,
            'collected_branch_id' => $collectedBranch->id,
            'job_order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'received_by' => $user->id,
            'payment_number' => $number,
            'payment_type' => $type,
            'amount' => $amount,
            'paid_at' => $date.' 10:00:00',
        ]);
    }

    private function payable(Branch $branch, User $user, string $number, string $method, float $original, float $balance, string $date): AccountsPayable
    {
        return AccountsPayable::query()->create([
            'branch_id' => $branch->id,
            'created_by' => $user->id,
            'payable_number' => $number,
            'creditor_name' => 'Owner',
            'source_type' => 'owner_funding',
            'funding_method' => $method,
            'description' => "{$method} owner funding",
            'original_amount' => $original,
            'paid_amount' => $original - $balance,
            'balance' => $balance,
            'status' => 'partial',
            'funded_at' => $date,
        ]);
    }

    private function payablePayment(AccountsPayable $payable, Branch $branch, User $user, string $number, string $method, float $amount, string $date): void
    {
        AccountsPayablePayment::query()->create([
            'accounts_payable_id' => $payable->id,
            'branch_id' => $branch->id,
            'recorded_by' => $user->id,
            'payment_number' => $number,
            'payment_date' => $date,
            'payment_method' => $method,
            'amount' => $amount,
        ]);
    }
}
