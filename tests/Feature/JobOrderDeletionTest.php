<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\CycleRecord;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\JobOrder;
use App\Models\Payment;
use App\Models\PoTransaction;
use App\Models\PoTransactionPayment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PoDeliveries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobOrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_button_is_visible_to_admin_and_super_admin(): void
    {
        [$branch, $order] = $this->jobOrderFixture();

        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['job_orders'],
        ]);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.job-orders.index'))
            ->assertOk()
            ->assertSee('Delete job order?');

        $this->actingAs($superAdmin)
            ->get(route('admin.job-orders.index'))
            ->assertOk()
            ->assertSee('Delete job order?');
    }

    public function test_admin_can_delete_job_order_and_connected_records_are_removed_with_log(): void
    {
        [$branch, $order, $customer] = $this->jobOrderFixture();

        $payment = Payment::query()->create([
            'branch_id' => $branch->id,
            'collected_branch_id' => $branch->id,
            'job_order_id' => $order->id,
            'customer_id' => $customer->id,
            'received_by' => null,
            'payment_number' => 'PAY-DELETE-001',
            'payment_type' => 'cash',
            'amount' => 40,
            'settlement_status' => 'local',
            'paid_at' => now(),
        ]);

        CustomerLedger::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'entry_type' => 'debit',
            'amount' => 100,
            'running_balance' => 100,
            'description' => 'Deleted order debit',
        ]);

        CustomerLedger::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'payment_id' => $payment->id,
            'entry_type' => 'credit',
            'amount' => 40,
            'running_balance' => 60,
            'description' => 'Deleted order payment',
        ]);

        $remainingLedger = CustomerLedger::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'entry_type' => 'debit',
            'amount' => 25,
            'running_balance' => 85,
            'description' => 'Remaining balance',
        ]);

        $order->items()->create([
            'description' => 'Wash',
            'service_category' => 'wash',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        CycleRecord::query()->create([
            'job_order_id' => $order->id,
            'cycle_type' => 'wash',
            'machine_number' => 1,
            'cycle_number' => 1,
            'started_at' => now(),
        ]);

        PoTransaction::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'company_name' => 'ACME',
            'po_number' => 'PO-DELETE-001',
            'transaction_date' => today(),
            'amount' => 100,
            'paid_amount' => 0,
            'balance' => 100,
            'status' => 'pending',
        ]);

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

        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['job_orders'],
        ]);
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $branch->id,
            'access' => ['job_orders'],
        ]);

        $this->actingAs($cashier)
            ->delete(route('admin.job-orders.destroy', $order))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('admin.job-orders.destroy', $order))
            ->assertRedirect(route('admin.job-orders.index'));

        $this->assertSoftDeleted('job_orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('job_order_items', ['job_order_id' => $order->id]);
        $this->assertDatabaseMissing('cycle_records', ['job_order_id' => $order->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('customer_ledgers', ['job_order_id' => $order->id]);
        $this->assertDatabaseMissing('po_transactions', ['job_order_id' => $order->id]);
        $this->assertDatabaseMissing('inventory_movements', ['remarks' => "Auto deducted for {$order->job_order_number}"]);

        $this->assertSame('10.00', $inventory->fresh()->quantity);
        $this->assertSame('25.00', $remainingLedger->fresh()->running_balance);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'job_order_deleted',
            'subject_type' => JobOrder::class,
            'subject_id' => $order->id,
        ]);

        $log = ActivityLog::query()->where('action', 'job_order_deleted')->firstOrFail();
        $this->assertSame($order->job_order_number, $log->properties['job_order_number']);
        $this->assertSame(1, $log->properties['payments_count']);
        $this->assertTrue($log->properties['had_po_transaction']);
    }

    public function test_deleting_a_po_job_order_leaves_nothing_behind_and_keeps_shared_drs_correct(): void
    {
        [$branch] = $this->jobOrderFixture();
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => $branch->id, 'access' => ['job_orders']]);
        $hotel = Customer::query()->create(['branch_id' => $branch->id, 'name' => 'DYNASTY HOTEL', 'billing_type' => 'po', 'is_active' => true]);

        $po = $this->poOrder($hotel, 'JO-PO-DELETE', 'Bed sheet', 10, 10);
        $otherPo = $this->poOrder($hotel, 'JO-PO-KEEP', 'Towel', 5, 20);
        $order = $po->jobOrder;
        $sheet = PoDeliveries::lineKey('Bed sheet', 10);

        // Batches, a transfer, a payment, a DR of its own and a DR shared with the other PO.
        $order->cycles()->create(['cycle_type' => 'wash', 'machine_number' => 1, 'cycle_number' => 1, 'pieces' => 10, 'started_at' => now(), 'ended_at' => now()]);
        $order->poReadyBatches()->create(['pieces' => 10, 'ready_for' => 'delivery', 'user_id' => $admin->id]);
        $order->transfers()->create(['job_order_number' => $order->job_order_number, 'origin_branch_id' => $branch->id, 'destination_branch_id' => $branch->id, 'transfer_type' => 'return', 'transfer_status' => 'pending', 'transferred_at' => now()]);
        PoTransactionPayment::query()->create(['po_transaction_id' => $po->id, 'branch_id' => $branch->id, 'customer_id' => $hotel->id, 'job_order_id' => $order->id, 'payment_number' => 'POPAY-1', 'payment_method' => 'cash', 'amount' => 30, 'paid_at' => now()]);
        $ownDr = PoDeliveries::record($branch->id, $hotel->id, [$po->id => [$sheet => 4]], today()->toDateString(), 'DR-OWN');
        $sharedDr = PoDeliveries::record($branch->id, $hotel->id, [
            $po->id => [$sheet => 3],
            $otherPo->id => [PoDeliveries::lineKey('Towel', 20) => 2],
        ], today()->toDateString(), 'DR-SHARED');
        $this->assertSame(70.0, (float) $sharedDr->amount);

        $this->actingAs($admin)
            ->delete(route('admin.job-orders.destroy', $order))
            ->assertRedirect(route('admin.job-orders.index'));

        $this->assertSoftDeleted('job_orders', ['id' => $order->id]);
        foreach (['job_order_items', 'cycle_records', 'po_ready_batches', 'job_order_transfers', 'po_transaction_payments', 'po_delivery_items', 'payments', 'customer_ledgers'] as $table) {
            $this->assertDatabaseMissing($table, ['job_order_id' => $order->id]);
        }
        $this->assertDatabaseMissing('po_transactions', ['id' => $po->id]);
        $this->assertDatabaseMissing('po_transaction_payments', ['po_transaction_id' => $po->id]);
        $this->assertDatabaseMissing('po_delivery_items', ['po_transaction_id' => $po->id]);

        // Its own DR is gone; the shared DR keeps only the other PO's line, re-totalled.
        $this->assertDatabaseMissing('po_deliveries', ['id' => $ownDr->id]);
        $this->assertSame(40.0, (float) $sharedDr->fresh()->amount);
        $this->assertSame(1, $sharedDr->items()->count());
        $this->assertDatabaseHas('po_transactions', ['id' => $otherPo->id]);
        $this->assertSame(3.0, PoDeliveries::remainingLines($otherPo->fresh())->first()['remaining']);

        $log = ActivityLog::query()->where('action', 'job_order_deleted')->firstOrFail();
        $this->assertSame(['payments' => 1, 'delivery_lines' => 2, 'deliveries_removed' => 1, 'deliveries_updated' => 1], $log->properties['po_cleanup']);
    }

    private function poOrder(Customer $customer, string $number, string $item, float $quantity, float $price): PoTransaction
    {
        $total = $quantity * $price;
        $order = JobOrder::query()->create([
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'job_order_number' => $number,
            'status' => 'received',
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => 0,
            'balance' => $total,
        ]);
        $order->items()->create(['description' => $item, 'service_category' => 'other', 'quantity' => $quantity, 'unit_price' => $price, 'total' => $total]);

        return PoTransaction::query()->create([
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'company_name' => $customer->name,
            'po_number' => 'PO-'.$number,
            'transaction_date' => today(),
            'amount' => $total,
            'balance' => $total,
            'status' => 'pending',
        ]);
    }

    private function jobOrderFixture(): array
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
            'name' => 'Juan Customer',
            'phone' => '09171234567',
            'is_active' => true,
        ]);

        $order = JobOrder::query()->create([
            'branch_id' => $branch->id,
            'processing_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'release_branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_number' => 'JO-DELETE-001',
            'status' => 'pending',
            'transaction_type' => 'walk_in',
            'subtotal' => 100,
            'discount' => 0,
            'tax' => 0,
            'total' => 100,
            'paid_amount' => 40,
            'balance' => 60,
        ]);

        return [$branch, $order, $customer];
    }
}
