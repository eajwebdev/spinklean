<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\JobOrder;
use App\Models\Payment;
use App\Models\PoTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\FinancialReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_payment_and_balances_are_recalculated(): void
    {
        [$branch, $customer, $order, $payment] = $this->fixture();

        $debit = CustomerLedger::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'entry_type' => 'debit',
            'amount' => 100,
            'running_balance' => 100,
            'description' => 'Order debit',
        ]);
        CustomerLedger::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'payment_id' => $payment->id,
            'entry_type' => 'credit',
            'amount' => 40,
            'running_balance' => 60,
            'description' => 'Order payment',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['payments'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Delete payment?');

        $this->actingAs($admin)
            ->delete(route('admin.payments.destroy', $payment))
            ->assertRedirect();

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('customer_ledgers', ['payment_id' => $payment->id]);
        $this->assertSame('0.00', $order->fresh()->paid_amount);
        $this->assertSame('100.00', $order->fresh()->balance);
        $this->assertSame('100.00', $debit->fresh()->running_balance);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'payment_deleted',
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
        ]);
    }

    public function test_non_admin_cannot_delete_payment(): void
    {
        [$branch, , , $payment] = $this->fixture();
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $branch->id,
            'access' => ['payments'],
        ]);

        $this->actingAs($cashier)
            ->delete(route('admin.payments.destroy', $payment))
            ->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_cancelled_job_order_is_excluded_from_all_financial_records(): void
    {
        [$branch, $customer, $order, $payment] = $this->fixture();

        $inventory = Inventory::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Detergent',
            'unit' => 'ml',
            'quantity' => 8,
            'is_active' => true,
        ]);
        InventoryMovement::query()->create([
            'inventory_id' => $inventory->id,
            'movement_type' => 'out',
            'quantity' => 2,
            'remarks' => "Auto deducted for {$order->job_order_number}",
        ]);
        $order->update(['inventory_deducted_at' => now()]);

        CustomerLedger::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'entry_type' => 'debit',
            'amount' => 100,
            'running_balance' => 100,
        ]);
        CustomerLedger::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'payment_id' => $payment->id,
            'entry_type' => 'credit',
            'amount' => 40,
            'running_balance' => 60,
        ]);
        PoTransaction::withoutGlobalScope('financially_active')->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'company_name' => 'Test Company',
            'po_number' => 'PO-CANCEL-001',
            'transaction_date' => today(),
            'amount' => 100,
            'paid_amount' => 0,
            'balance' => 100,
            'status' => 'pending',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['job_orders', 'payments', 'receivables', 'po_transactions', 'reports'],
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.job-orders.cancel', $order))
            ->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('0.00', $order->fresh()->balance);
        $this->assertNull($order->fresh()->inventory_deducted_at);
        $this->assertSame('10.00', $inventory->fresh()->quantity);
        $this->assertDatabaseMissing('inventory_movements', ['remarks' => "Auto deducted for {$order->job_order_number}"]);
        $this->assertSame(0, Payment::query()->whereKey($payment->id)->count());
        $this->assertSame(1, Payment::withoutGlobalScope('financially_active')->whereKey($payment->id)->count());
        $this->assertSame(0, PoTransaction::query()->where('job_order_id', $order->id)->count());
        $this->assertDatabaseMissing('customer_ledgers', ['job_order_id' => $order->id]);

        $financial = FinancialReconciliation::forPeriod($branch->id, today()->toDateString(), today()->toDateString());
        $this->assertSame(0.0, $financial['sales_owned']);
        $this->assertSame(0.0, $financial['physical_collections']);
        $this->assertSame(0.0, $financial['unpaid_balance']);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertDontSee($payment->payment_number);
        $this->actingAs($admin)
            ->get(route('admin.receivables.index'))
            ->assertOk()
            ->assertDontSee($order->job_order_number);
        $this->actingAs($admin)
            ->get(route('admin.po-transactions.index'))
            ->assertOk()
            ->assertDontSee('PO-CANCEL-001');
        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertDontSee($payment->payment_number);
        $this->actingAs($admin)
            ->get(route('dashboard.data', [
                'branch_id' => $branch->id,
                'date_from' => today()->toDateString(),
                'date_to' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('stats.sales', 'PHP 0.00')
            ->assertJsonPath('stats.receivables', 'PHP 0.00');

        $log = ActivityLog::query()->where('action', 'job_order_cancelled')->firstOrFail();
        $this->assertSame(1, $log->properties['excluded_payments_count']);
    }

    private function fixture(): array
    {
        SystemSetting::query()->create([
            'business_name' => 'EAJ Laundry',
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
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Test Customer',
            'phone' => '09171234567',
            'is_active' => true,
        ]);
        $order = JobOrder::query()->create([
            'branch_id' => $branch->id,
            'processing_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'release_branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_number' => 'JO-FINANCE-001',
            'status' => 'pending',
            'transaction_type' => 'walk_in',
            'subtotal' => 100,
            'discount' => 0,
            'tax' => 0,
            'total' => 100,
            'paid_amount' => 40,
            'balance' => 60,
        ]);
        $payment = Payment::query()->create([
            'branch_id' => $branch->id,
            'collected_branch_id' => $branch->id,
            'job_order_id' => $order->id,
            'customer_id' => $customer->id,
            'payment_number' => 'PAY-FINANCE-001',
            'payment_type' => 'cash',
            'amount' => 40,
            'settlement_status' => 'local',
            'paid_at' => now(),
        ]);

        return [$branch, $customer, $order, $payment];
    }
}
