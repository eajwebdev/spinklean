<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\LaundryService;
use App\Models\PoTransaction;
use App\Models\PoTransactionPayment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\FinancialReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_po_job_order_saves_without_cashier_payment_and_is_excluded_from_receivables(): void
    {
        $this->settings();

        $user = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Acme Corporation',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);
        $service = LaundryService::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Wash Dry Fold',
            'pricing_type' => 'kilo',
            'price' => 150,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('admin.job-orders.store'), [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'payment_type' => 'po',
            'payment_reference_no' => 'PO-ACME-001',
            'items' => [[
                'laundry_service_id' => $service->id,
                'description' => 'Wash Dry Fold',
                'quantity' => 2,
                'unit_price' => 150,
            ]],
        ]);

        $response->assertRedirect(route('admin.job-orders.index'));

        $order = JobOrder::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(300.0, (float) $order->balance);
        $this->assertDatabaseMissing('payments', ['job_order_id' => $order->id]);
        $this->assertDatabaseMissing('customer_ledgers', ['job_order_id' => $order->id]);
        $this->assertDatabaseHas('po_transactions', [
            'job_order_id' => $order->id,
            'customer_id' => $customer->id,
            'po_number' => 'PO-ACME-001',
            'amount' => 300,
            'balance' => 300,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('admin.receivables.index'))
            ->assertOk()
            ->assertDontSee($order->job_order_number);

        $summary = FinancialReconciliation::forPeriod($branch->id, now()->toDateString(), now()->toDateString());
        $this->assertSame(0.0, $summary['unpaid_balance']);
    }

    public function test_po_module_lists_summary_and_can_mark_po_paid_without_regular_payment(): void
    {
        $this->settings();

        $user = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Corporate Customer',
            'billing_type' => 'po',
            'is_active' => true,
        ]);
        $order = JobOrder::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'job_order_number' => 'JO-PO-001',
            'status' => 'completed',
            'subtotal' => 500,
            'total' => 500,
            'paid_amount' => 0,
            'balance' => 500,
        ]);
        $po = PoTransaction::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'company_name' => $customer->name,
            'po_number' => 'PO-CORP-001',
            'transaction_date' => now()->toDateString(),
            'amount' => 500,
            'balance' => 500,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('admin.po-transactions.index'))
            ->assertOk()
            ->assertSee('PO-CORP-001')
            ->assertSee('PHP 500.00');

        $this->actingAs($user)
            ->patch(route('admin.po-transactions.update', $po), [
                'status' => 'billed',
                'payment_method' => 'cheque',
                'paid_amount' => 500,
                'reference_no' => 'CHK-001',
            ])
            ->assertRedirect();

        $po->refresh();
        $this->assertSame('paid', $po->status);
        $this->assertSame(500.0, (float) $po->paid_amount);
        $this->assertSame(0.0, (float) $po->balance);
        $this->assertDatabaseMissing('payments', ['job_order_id' => $order->id]);
        $this->assertDatabaseHas('po_transaction_payments', [
            'po_transaction_id' => $po->id,
            'payment_method' => 'cheque',
            'reference_no' => 'CHK-001',
            'amount' => 500,
        ]);
    }

    public function test_po_transactions_index_defaults_to_today_only_and_can_filter_date_range(): void
    {
        $this->settings();

        $user = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Corporate Customer',
            'billing_type' => 'po',
            'is_active' => true,
        ]);

        $todayOrder = $this->poOrder($branch, $customer, $user, 'JO-PO-TODAY', 400);
        PoTransaction::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $todayOrder->id,
            'company_name' => $customer->name,
            'po_number' => 'PO-TODAY',
            'transaction_date' => today()->toDateString(),
            'amount' => 400,
            'balance' => 400,
            'status' => 'pending',
        ]);

        $olderOrder = $this->poOrder($branch, $customer, $user, 'JO-PO-OLDER', 700);
        PoTransaction::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $olderOrder->id,
            'company_name' => $customer->name,
            'po_number' => 'PO-OLDER',
            'transaction_date' => today()->subDay()->toDateString(),
            'amount' => 700,
            'balance' => 700,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('admin.po-transactions.index'))
            ->assertOk()
            ->assertSee('PO-TODAY')
            ->assertDontSee('PO-OLDER');

        $this->actingAs($user)
            ->get(route('admin.po-transactions.index', [
                'date_range' => today()->subDay()->toDateString().' to '.today()->subDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('PO-OLDER')
            ->assertDontSee('PO-TODAY');
    }

    public function test_po_transaction_can_be_marked_billed_without_payment(): void
    {
        $this->settings();

        $user = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Corporate Customer',
            'billing_type' => 'po',
            'is_active' => true,
        ]);
        $order = $this->poOrder($branch, $customer, $user, 'JO-PO-BILLED', 650);
        $po = PoTransaction::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'company_name' => $customer->name,
            'po_number' => 'PO-BILLED',
            'transaction_date' => today()->toDateString(),
            'amount' => 650,
            'balance' => 650,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->patch(route('admin.po-transactions.update', $po), [
                'status' => 'billed',
                'paid_amount' => 0,
            ])
            ->assertRedirect();

        $po->refresh();
        $this->assertSame('billed', $po->status);
        $this->assertSame(0.0, (float) $po->paid_amount);
        $this->assertSame(650.0, (float) $po->balance);
        $this->assertNotNull($po->billed_at);
        $this->assertNull($po->paid_at);
        $this->assertDatabaseMissing('po_transaction_payments', ['po_transaction_id' => $po->id]);
    }

    public function test_po_payments_are_incremental_trackable_and_cannot_exceed_remaining_balance(): void
    {
        $this->settings();

        $user = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Corporate Customer',
            'billing_type' => 'po',
            'is_active' => true,
        ]);
        $order = $this->poOrder($branch, $customer, $user, 'JO-PO-PARTIAL', 1000);
        $po = PoTransaction::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'company_name' => $customer->name,
            'po_number' => 'PO-PARTIAL',
            'transaction_date' => today()->toDateString(),
            'amount' => 1000,
            'balance' => 1000,
            'status' => 'billed',
            'billed_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('admin.po-transactions.update', $po), [
                'status' => 'billed',
                'payment_method' => 'bank',
                'paid_amount' => 400,
                'reference_no' => 'BANK-001',
            ])
            ->assertRedirect();

        $po->refresh();
        $this->assertSame('partially_paid', $po->status);
        $this->assertSame(400.0, (float) $po->paid_amount);
        $this->assertSame(600.0, (float) $po->balance);

        $this->actingAs($user)
            ->patch(route('admin.po-transactions.update', $po), [
                'status' => 'partially_paid',
                'payment_method' => 'cash',
                'paid_amount' => 700,
            ])
            ->assertSessionHasErrors('paid_amount');

        $po->refresh();
        $this->assertSame(400.0, (float) $po->paid_amount);
        $this->assertSame(600.0, (float) $po->balance);
        $this->assertSame(1, PoTransactionPayment::query()->where('po_transaction_id', $po->id)->count());

        $this->actingAs($user)
            ->get(route('admin.po-transactions.index'))
            ->assertOk()
            ->assertSee('PO Payment History')
            ->assertSee('BANK-001')
            ->assertSee('PHP 1,000.00')
            ->assertSee('PHP 400.00')
            ->assertSee('PHP 600.00');
    }

    public function test_po_job_order_cannot_be_paid_through_regular_payment_route(): void
    {
        $this->settings();

        $user = User::factory()->create(['role' => 'cashier']);
        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $user->update(['branch_id' => $branch->id, 'access' => ['job_orders', 'receivables']]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Corporate Customer',
            'billing_type' => 'po',
            'is_active' => true,
        ]);
        $order = $this->poOrder($branch, $customer, $user, 'JO-PO-NO-CASHIER-PAY', 850);
        PoTransaction::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'company_name' => $customer->name,
            'po_number' => 'PO-NO-CASHIER-PAY',
            'transaction_date' => today()->toDateString(),
            'amount' => 850,
            'balance' => 850,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('admin.job-orders.payments.store', $order), [
                'payment_type' => 'cash',
                'amount' => 850,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('payments', ['job_order_id' => $order->id]);
        $this->assertSame(850.0, (float) $order->fresh()->balance);
    }

    private function settings(): void
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
    }

    private function poOrder(Branch $branch, Customer $customer, User $user, string $number, float $amount): JobOrder
    {
        return JobOrder::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'job_order_number' => $number,
            'status' => 'completed',
            'subtotal' => $amount,
            'total' => $amount,
            'paid_amount' => 0,
            'balance' => $amount,
        ]);
    }
}
