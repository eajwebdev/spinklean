<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PoTransaction;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use App\Support\PoDeliveries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CyclePoMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Main Office',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'is_completed' => true,
        ]);
        SystemTrialSetting::query()->create([
            'trial_enabled' => true,
            'trial_start_date' => now()->subDay()->toDateString(),
            'trial_end_date' => now()->addDay()->toDateString(),
            'trial_status' => 'active',
            'grace_period_days' => 0,
        ]);

        $this->branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true, 'branch_type' => 'full_service', 'machine_count' => 2]);
        $this->admin = User::factory()->create(['role' => 'admin', 'branch_id' => $this->branch->id, 'access' => ['cycles', 'po_transactions']]);
    }

    public function test_po_orders_show_badge_and_delivery_progress_and_can_be_filtered(): void
    {
        $po = $this->poOrder('received', [['Bed sheet', 20, 10]]);
        $regular = $this->regularOrder('received');
        PoDeliveries::record($this->branch->id, $po->customer_id, [$po->id => [PoDeliveries::lineKey('Bed sheet', 10) => 8]], today()->toDateString(), 'DR-1');

        $this->actingAs($this->admin)
            ->get(route('admin.cycles.index'))
            ->assertOk()
            ->assertSee('PO #'.$po->po_number)
            ->assertSee('PO batch progress · 20 pcs')
            ->assertSee('DR DR-1')
            ->assertSee($regular->job_order_number);

        $this->actingAs($this->admin)
            ->get(route('admin.cycles.index', ['order_type' => 'po']))
            ->assertSee($po->jobOrder->job_order_number)
            ->assertDontSee($regular->job_order_number);

        $this->actingAs($this->admin)
            ->get(route('admin.cycles.index', ['order_type' => 'regular']))
            ->assertSee($regular->job_order_number)
            ->assertDontSee($po->jobOrder->job_order_number);
    }

    public function test_po_filter_keeps_orders_waiting_for_delivery(): void
    {
        $po = $this->poOrder('ready_for_delivery', [['Towel', 10, 5]]);

        $this->actingAs($this->admin)
            ->get(route('admin.cycles.index'))
            ->assertDontSee($po->jobOrder->job_order_number);

        $this->actingAs($this->admin)
            ->get(route('admin.cycles.index', ['order_type' => 'po']))
            ->assertSee($po->jobOrder->job_order_number)
            ->assertSee('Create DR')
            ->assertSee('Deliver All Remaining')
            ->assertDontSee('Direct Release');
    }

    public function test_deliver_all_remaining_puts_the_rest_on_a_final_dr(): void
    {
        $po = $this->poOrder('ready_for_delivery', [['Towel', 10, 5]]);
        PoDeliveries::record($this->branch->id, $po->customer_id, [$po->id => [PoDeliveries::lineKey('Towel', 5) => 4]], today()->toDateString(), 'DR-1');

        $this->actingAs($this->admin)
            ->patch(route('admin.cycles.release', $po->jobOrder), ['action' => 'release_here'])
            ->assertSessionHasNoErrors();

        $po->refresh();
        $this->assertSame('completed', $po->jobOrder->fresh()->status);
        $this->assertTrue(PoDeliveries::isFullyDelivered($po));
        $this->assertSame(6.0, (float) $po->deliveryItems()->whereHas('delivery', fn ($q) => $q->where('dr_number', '!=', 'DR-1'))->sum('quantity'));
    }

    public function test_a_po_without_batches_can_be_marked_ready_for_pickup_or_delivery(): void
    {
        $po = $this->poOrder('received', [['Towel', 10, 5]]);

        $this->actingAs($this->admin)
            ->get(route('admin.cycles.index'))
            ->assertSee('Ready for Pickup?')
            ->assertSee('Ready for Delivery?');

        $this->actingAs($this->admin)
            ->patch(route('admin.cycles.status', $po->jobOrder), ['status' => 'ready_for_pickup'])
            ->assertSessionHasNoErrors();
        $this->assertSame('ready_for_pickup', $po->jobOrder->fresh()->status);
    }

    public function test_a_70_piece_po_is_tracked_through_wash_dry_ready_and_delivery_batches(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(8, 0));
        $po = $this->poOrder('received', [['Bed sheet', 70, 10]]);
        $order = $po->jobOrder;
        $key = PoDeliveries::lineKey('Bed sheet', 10);

        // Day 1: a washing batch needs its piece count; 10 go in over two machines.
        $this->cycle($order, 'wash', [1])->assertSessionHasErrors('pieces');
        $this->cycle($order, 'wash', [1, 2], 10)->assertSessionHasNoErrors();

        // Drying waits for the wash to finish, and takes no more than was washed.
        $this->cycle($order, 'dry', [1], 10)->assertSessionHasErrors('pieces');
        $this->endCycles($order, 'wash');
        $this->cycle($order, 'dry', [1], 11)->assertSessionHasErrors('pieces');
        $this->cycle($order, 'dry', [1])->assertSessionHasErrors('pieces');
        $this->cycle($order, 'dry', [1], 10)->assertSessionHasNoErrors();

        // Ready waits for the dry to finish.
        $this->ready($order, 10)->assertSessionHasErrors('pieces');
        $this->endCycles($order, 'dry');

        $progress = PoDeliveries::batchProgress([$order->id])[$order->id];
        $this->assertSame(10.0, $progress['washed'], 'A batch on two machines counts once.');
        $this->assertSame(10.0, $progress['dried_done']);

        $this->actingAs($this->admin)
            ->get(route('admin.cycles.index'))
            ->assertSee('PO batch progress')
            ->assertSee('Batch: 10 pcs')
            ->assertSee('60 left to wash')
            ->assertSee('10 pcs washed and dried, not yet marked ready');

        $this->ready($order, 11)->assertSessionHasErrors('pieces');
        $this->ready($order, 10)->assertSessionHasNoErrors();
        $this->assertSame('drying', $order->fresh()->status, 'Production continues until every piece is ready.');

        // Day 2: still on today's board; the next batch of 10 is washed, dried and marked ready.
        $this->travelTo(now()->addDay());
        $this->cycle($order, 'wash', [1], 61)->assertSessionHasErrors('pieces');
        $this->cycle($order, 'wash', [1], 10)->assertSessionHasNoErrors();
        $this->endCycles($order, 'wash');
        $this->cycle($order, 'dry', [1], 10)->assertSessionHasNoErrors();
        $this->endCycles($order, 'dry');
        $this->ready($order, 10)->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('admin.cycles.index'))
            ->assertSee($order->job_order_number)
            ->assertSee('50 left to wash')
            ->assertSee('20 of 70 pcs ready')
            ->assertSee('Create DR (20 pcs)');

        // Only the 20 pieces marked ready can go out on a DR.
        $this->actingAs($this->admin)
            ->post(route('admin.po-deliveries.store'), $this->drPayload($po, [$key => 21], 'DR-1'))
            ->assertSessionHasErrors("quantities.{$po->id}");
        $this->actingAs($this->admin)
            ->post(route('admin.po-deliveries.store'), $this->drPayload($po, [$key => 20], 'DR-1'))
            ->assertSessionHasNoErrors();

        $po->refresh();
        $this->assertSame(50.0, PoDeliveries::remainingLines($po)->first()['remaining']);
        $this->assertSame('drying', $order->fresh()->status, 'A partly delivered PO stays in production.');
        $this->actingAs($this->admin)
            ->get(route('admin.cycles.index'))
            ->assertSee('DR DR-1')
            ->assertSee('0 ready to deliver');

        // A batch-tracked PO can't skip the batches with a plain status change.
        $this->actingAs($this->admin)
            ->patch(route('admin.cycles.status', $order), ['status' => 'ready_for_delivery'])
            ->assertSessionHasErrors('status');

        // The last 50 finish production.
        $this->cycle($order, 'wash', [1], 50)->assertSessionHasNoErrors();
        $this->endCycles($order, 'wash');
        $this->cycle($order, 'dry', [1], 50)->assertSessionHasNoErrors();
        $this->endCycles($order, 'dry');
        $this->ready($order, 50, 'ready_for_pickup')->assertSessionHasNoErrors();
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
        $this->assertSame([10.0, 10.0, 50.0], $order->poReadyBatches()->orderBy('id')->pluck('pieces')->map(fn ($p) => (float) $p)->all());
    }

    public function test_a_dry_cycle_without_pieces_dries_what_had_finished_washing(): void
    {
        $po = $this->poOrder('drying', [['Bed sheet', 70, 10]]);
        $order = $po->jobOrder;

        // Washed 10, then dried before Drying asked for pieces.
        $order->cycles()->create(['cycle_type' => 'wash', 'machine_number' => 2, 'cycle_number' => 1, 'pieces' => 10, 'started_at' => now()->subHour(), 'ended_at' => now()->subMinutes(30)]);
        $order->cycles()->create(['cycle_type' => 'dry', 'machine_number' => 3, 'cycle_number' => 1, 'started_at' => now()->subMinutes(20), 'ended_at' => now()]);

        $progress = PoDeliveries::batchProgress([$order->id])[$order->id];
        $this->assertSame(10.0, $progress['dried']);
        $this->assertSame(10.0, $progress['dried_done']);

        $this->actingAs($this->admin)
            ->get(route('admin.cycles.index'))
            ->assertSee('0 waiting to dry')
            ->assertSee('10 pcs washed and dried, not yet marked ready');

        // A dry batch on two machines counts its pieces once.
        $this->cycle($order, 'wash', [1], 20)->assertSessionHasNoErrors();
        $this->endCycles($order, 'wash');
        $this->cycle($order, 'dry', [1, 2], 20)->assertSessionHasNoErrors();
        $this->assertSame(30.0, PoDeliveries::batchProgress([$order->id])[$order->id]['dried']);
    }

    private function cycle(JobOrder $order, string $type, array $machines, ?float $pieces = null)
    {
        return $this->actingAs($this->admin)->post(route('admin.cycles.store', $order), array_filter([
            'cycle_type' => $type,
            'machine_numbers' => $machines,
            'pieces' => $pieces,
        ], fn ($value) => $value !== null));
    }

    private function endCycles(JobOrder $order, string $type): void
    {
        $order->cycles()->where('cycle_type', $type)->whereNull('ended_at')->update(['ended_at' => now()]);
    }

    private function ready(JobOrder $order, float $pieces, string $status = 'ready_for_delivery')
    {
        return $this->actingAs($this->admin)->post(route('admin.cycles.po-ready', $order), ['pieces' => $pieces, 'status' => $status]);
    }

    private function drPayload(PoTransaction $po, array $quantities, string $drNumber): array
    {
        return [
            'customer_id' => $po->customer_id,
            'delivered_on' => today()->toDateString(),
            'dr_number' => $drNumber,
            'quantities' => [$po->id => $quantities],
        ];
    }

    private function regularOrder(string $status): JobOrder
    {
        $customer = Customer::query()->create(['branch_id' => $this->branch->id, 'name' => 'Walk-in Juan', 'billing_type' => 'regular', 'is_active' => true]);

        return JobOrder::query()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'job_order_number' => 'JO-REG-'.uniqid(),
            'status' => $status,
            'subtotal' => 100,
            'total' => 100,
            'paid_amount' => 100,
            'balance' => 0,
        ]);
    }

    private function poOrder(string $status, array $items): PoTransaction
    {
        $customer = Customer::query()->create(['branch_id' => $this->branch->id, 'name' => 'DYNASTY HOTEL', 'billing_type' => 'po', 'is_active' => true]);
        $subtotal = collect($items)->sum(fn (array $item) => $item[1] * $item[2]);
        $order = JobOrder::query()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'job_order_number' => 'JO-PO-'.uniqid(),
            'status' => $status,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'paid_amount' => 0,
            'balance' => $subtotal,
        ]);

        foreach ($items as [$description, $quantity, $price]) {
            $order->items()->create([
                'description' => $description,
                'service_category' => 'other',
                'quantity' => $quantity,
                'unit_price' => $price,
                'total' => $quantity * $price,
            ]);
        }

        return PoTransaction::query()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'company_name' => $customer->name,
            'po_number' => 'PO-'.$order->id,
            'transaction_date' => today(),
            'amount' => $subtotal,
            'balance' => $subtotal,
            'status' => 'pending',
        ]);
    }
}
