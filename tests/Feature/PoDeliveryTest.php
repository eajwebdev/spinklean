<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PoDelivery;
use App\Models\PoDeliveryItem;
use App\Models\PoTransaction;
use App\Models\PoTransactionPayment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PoDeliveries;
use App\Support\StatementOfAccountRows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PoDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private Customer $hotel;

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

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->branch = Branch::query()->create(['name' => 'Branch 1-JULIO PACANA', 'code' => 'B0001', 'is_active' => true, 'branch_type' => 'full_service', 'machine_count' => 4]);
        $this->hotel = $this->poCustomer('DYNASTY HOTEL');
    }

    public function test_a_70_piece_order_is_delivered_in_parts_and_billed_on_each_delivery_date(): void
    {
        $po = $this->poOrder($this->hotel, '2026-10-31', [['Bed sheet', 70, 10]]);
        $key = PoDeliveries::lineKey('Bed sheet', 10);

        // Washed in batches: 8 go out on Nov 1 morning, 8 that afternoon, 10 on Nov 2.
        $this->deliver($po, [$key => 8], '2026-11-01', 'DR-A')->assertRedirect();
        $this->deliver($po, [$key => 8], '2026-11-01', 'DR-B')->assertRedirect();
        $this->deliver($po, [$key => 10], '2026-11-02', 'DR-C')->assertRedirect();

        $po->refresh();
        $this->assertSame(44.0, PoDeliveries::remainingLines($po)->first()['remaining']);
        $this->assertSame('received', $po->jobOrder->fresh()->status, 'A partly delivered order stays open.');
        $this->assertSame('2026-11-02', $po->date_delivered->toDateString());
        $this->assertSame('DR-A, DR-B, DR-C', $po->dr_number);

        // October (the drop-off month) bills nothing; November bills each day's deliveries.
        $this->soa('2026-10-01', '2026-10-31')->assertSee('Nothing was delivered to this customer in this period.');
        $this->soa('2026-11-01', '2026-11-30')
            ->assertSee('DR-A')->assertSee('DR-B')->assertSee('DR-C')
            ->assertSee('PHP 260.00')   // delivered this period: 26 pieces x 10
            ->assertSee('PHP 440.00');  // not yet delivered: 44 pieces x 10

        $rows = $this->pdfRows('2026-11-01', '2026-11-30');
        $this->assertSame(160.0, $rows['days'][0]['amount']);
        $this->assertSame(16.0, $rows['days'][0]['loads']);
        $this->assertSame(100.0, $rows['days'][1]['amount']);
        $this->assertSame(260.0, $rows['total']);

        // The last 44 go out on Nov 5 and complete the job order.
        $this->deliver($po, [$key => 44], '2026-11-05', 'DR-D')->assertRedirect();
        $order = $po->jobOrder->fresh();
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->released_at);
        $this->assertSame(700.0, PoDeliveries::deliveredAmount($po->fresh()));
    }

    public function test_cannot_deliver_more_than_is_left(): void
    {
        $po = $this->poOrder($this->hotel, '2026-11-01', [['Towel', 10, 9]]);
        $key = PoDeliveries::lineKey('Towel', 9);

        $this->deliver($po, [$key => 6], '2026-11-01', 'DR-1')->assertRedirect();
        $this->deliver($po, [$key => 5], '2026-11-02', 'DR-2')->assertSessionHasErrors("quantities.{$po->id}.{$key}");
        $this->deliver($po, [$key => 0], '2026-11-02', 'DR-2')->assertSessionHasErrors('quantities');
        $this->deliver($po, [$key => 1], '2026-11-02', 'DR-1')->assertSessionHasErrors('dr_number');

        $this->assertSame(1, PoDelivery::query()->count());
    }

    public function test_one_dr_can_carry_several_job_orders_and_numbers_itself(): void
    {
        $first = $this->poOrder($this->hotel, '2026-11-01', [['Bed sheet', 20, 10], ['Pillow case', 10, 4]]);
        $second = $this->poOrder($this->hotel, '2026-11-02', [['Towel', 30, 9]]);

        $this->actingAs($this->admin)->post(route('admin.po-deliveries.store'), [
            'customer_id' => $this->hotel->id,
            'delivered_on' => '2026-11-03',
            'quantities' => [
                $first->id => [PoDeliveries::lineKey('Bed sheet', 10) => 20, PoDeliveries::lineKey('Pillow case', 4) => 10],
                $second->id => [PoDeliveries::lineKey('Towel', 9) => 12],
            ],
        ])->assertRedirect();

        $delivery = PoDelivery::query()->with('items')->sole();
        $this->assertSame('00001', $delivery->dr_number);
        $this->assertSame('348.00', $delivery->amount);   // 200 + 40 + 108
        $this->assertCount(3, $delivery->items);
        $this->assertSame('completed', $first->jobOrder->fresh()->status);
        $this->assertSame('received', $second->jobOrder->fresh()->status);
        $this->assertSame('00002', PoDeliveries::nextDrNumber($this->branch->id));

        $this->actingAs($this->admin)->get(route('admin.po-deliveries.print', $delivery))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_discounted_order_bills_proportionally_and_dr_amounts_add_up_to_the_po(): void
    {
        // 3 x 100 = 300, less a 100 discount: the customer owes 200.
        $po = $this->poOrder($this->hotel, '2026-11-01', [['Comforter', 3, 100]], discount: 100);
        $key = PoDeliveries::lineKey('Comforter', 100);

        $this->deliver($po, [$key => 1], '2026-11-01', 'DR-1');
        $this->deliver($po, [$key => 1], '2026-11-02', 'DR-2');
        $this->deliver($po, [$key => 1], '2026-11-03', 'DR-3');

        $this->assertSame(['66.67', '66.67', '66.66'], PoDelivery::query()->orderBy('id')->pluck('amount')->all());
        $this->assertSame(200.0, PoDeliveries::deliveredAmount($po->fresh()));
    }

    public function test_completing_or_releasing_a_po_order_puts_the_rest_on_a_final_dr(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        $po = $this->poOrder($this->hotel, '2026-11-01', [['Bed sheet', 30, 10]]);
        $this->deliver($po, [PoDeliveries::lineKey('Bed sheet', 10) => 10], '2026-11-08', 'DR-1');

        $po->jobOrder->update(['status' => 'ready_for_delivery']);
        $this->actingAs($this->admin)->patch(route('admin.job-orders.release', $po->jobOrder))->assertRedirect();

        $final = PoDelivery::query()->latest('id')->first();
        $this->assertSame('2026-11-10', $final->delivered_on->toDateString());
        $this->assertSame('200.00', $final->amount);
        $this->assertTrue(PoDeliveries::isFullyDelivered($po->fresh()));
        $this->assertSame(2, PoDelivery::query()->count());
    }

    public function test_monthly_payment_pays_delivered_orders_oldest_first_but_not_undelivered_laundry(): void
    {
        $october = $this->poOrder($this->hotel, '2026-10-20', [['Bed sheet', 30, 10]]);
        $november = $this->poOrder($this->hotel, '2026-11-02', [['Towel', 100, 9]]);
        $this->deliver($october, [PoDeliveries::lineKey('Bed sheet', 10) => 30], '2026-10-25', 'DR-1');
        $this->deliver($november, [PoDeliveries::lineKey('Towel', 9) => 50], '2026-11-03', 'DR-2');

        // Due up to Nov 30: 300 (October) + 450 (half of the towels). The other 450 is not delivered yet.
        $this->payStatement(['amount' => 800, 'date_to' => '2026-11-30'])->assertSessionHasErrors('amount');

        $this->payStatement(['amount' => 500, 'date_to' => '2026-11-30', 'reference_no' => 'CHQ-1001'])->assertRedirect();

        $october->refresh();
        $november->refresh();
        $this->assertSame('paid', $october->status);
        $this->assertSame('300.00', $october->paid_amount);
        $this->assertSame('partially_paid', $november->status);
        $this->assertSame('200.00', $november->paid_amount);
        $this->assertSame(250.0, PoDeliveries::dueAmount($november, '2026-11-30'));
        $this->assertSame(['CHQ-1001', 'CHQ-1001'], PoTransactionPayment::query()->pluck('reference_no')->all());
    }

    public function test_voiding_a_dr_reopens_the_order_and_removes_it_from_the_statement(): void
    {
        $po = $this->poOrder($this->hotel, '2026-11-01', [['Duvet', 4, 17]]);
        $this->deliver($po, [PoDeliveries::lineKey('Duvet', 17) => 4], '2026-11-02', 'DR-9');
        $this->assertSame('completed', $po->jobOrder->fresh()->status);

        $delivery = PoDelivery::query()->sole();
        $this->actingAs($this->admin)->delete(route('admin.po-deliveries.destroy', $delivery))->assertRedirect();

        $this->assertSame(0, PoDelivery::query()->count());
        $this->assertSame('ready_for_delivery', $po->jobOrder->fresh()->status);
        $this->assertNull($po->fresh()->date_delivered);
        $this->soa('2026-11-01', '2026-11-30')->assertSee('Nothing was delivered to this customer in this period.');
    }

    public function test_delivered_pieces_cannot_be_edited_away_and_order_cannot_be_cancelled(): void
    {
        $po = $this->poOrder($this->hotel, '2026-11-01', [['Bed sheet', 20, 10]]);
        $this->deliver($po, [PoDeliveries::lineKey('Bed sheet', 10) => 12], '2026-11-02', 'DR-1');
        $order = $po->jobOrder;

        $this->actingAs($this->admin)->patch(route('admin.job-orders.cancel', $order))->assertStatus(422);
        $this->assertSame('received', $order->fresh()->status);

        $this->assertThrows(
            fn () => PoDeliveries::assertItemsCoverDeliveries($order, [['description' => 'Bed sheet', 'unit_price' => 10, 'quantity' => 10]]),
            ValidationException::class,
        );
        PoDeliveries::assertItemsCoverDeliveries($order, [['description' => 'Bed sheet', 'unit_price' => 10, 'quantity' => 12]]);
    }

    public function test_other_branch_cannot_see_print_or_void_a_dr(): void
    {
        $po = $this->poOrder($this->hotel, '2026-11-01', [['Towel', 5, 9]]);
        $this->deliver($po, [PoDeliveries::lineKey('Towel', 9) => 5], '2026-11-02', 'DR-1');
        $delivery = PoDelivery::query()->sole();

        $otherBranch = Branch::query()->create(['name' => 'Branch 2', 'code' => 'B0002', 'is_active' => true, 'branch_type' => 'full_service']);
        $cashier = User::factory()->create(['role' => 'cashier', 'branch_id' => $otherBranch->id, 'access' => ['po_transactions']]);

        $this->actingAs($cashier)->get(route('admin.po-deliveries.print', $delivery))->assertForbidden();
        $this->actingAs($cashier)->delete(route('admin.po-deliveries.destroy', $delivery))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.po-deliveries.index'))->assertOk()->assertDontSee('DR-1');
        $this->actingAs($cashier)->post(route('admin.po-deliveries.store'), [
            'customer_id' => $this->hotel->id,
            'delivered_on' => '2026-11-03',
            'quantities' => [$po->id => [PoDeliveries::lineKey('Towel', 9) => 1]],
        ])->assertForbidden();
    }

    public function test_new_dr_page_lists_open_orders_with_what_is_left(): void
    {
        $po = $this->poOrder($this->hotel, '2026-11-01', [['Bed sheet', 70, 10]]);
        $this->deliver($po, [PoDeliveries::lineKey('Bed sheet', 10) => 8], '2026-11-02', 'DR-1');

        $this->actingAs($this->admin)->get(route('admin.po-deliveries.create', ['customer_id' => $this->hotel->id]))
            ->assertOk()
            ->assertSee($po->jobOrder->job_order_number)
            ->assertSee('Deliver all remaining')
            ->assertViewHas('purchaseOrders', fn ($orders) => $orders->sole()['lines']->sole()['remaining'] === 62.0);
    }

    private function deliver(PoTransaction $po, array $quantities, string $date, string $drNumber)
    {
        return $this->actingAs($this->admin)->post(route('admin.po-deliveries.store'), [
            'customer_id' => $po->customer_id,
            'delivered_on' => $date,
            'dr_number' => $drNumber,
            'quantities' => [$po->id => $quantities],
        ]);
    }

    private function payStatement(array $data)
    {
        return $this->actingAs($this->admin)->post(route('admin.po-transactions.statement-of-account.payments'), [
            'customer_id' => $this->hotel->id,
            'payment_method' => 'cheque',
            ...$data,
        ]);
    }

    private function soa(string $from, string $to)
    {
        return $this->actingAs($this->admin)->get(route('admin.po-transactions.statement-of-account', [
            'customer_id' => $this->hotel->id,
            'date_from' => $from,
            'date_to' => $to,
        ]))->assertOk();
    }

    private function pdfRows(string $from, string $to): array
    {
        $lines = PoDeliveryItem::query()
            ->with('delivery')
            ->whereHas('delivery', fn ($query) => $query->whereDate('delivered_on', '>=', $from)->whereDate('delivered_on', '<=', $to))
            ->get();

        return StatementOfAccountRows::build($lines, $from, $to);
    }

    private function poCustomer(string $name): Customer
    {
        return Customer::query()->create([
            'branch_id' => $this->branch->id,
            'name' => $name,
            'address' => 'Cagayan de Oro',
            'billing_type' => 'po',
            'is_active' => true,
        ]);
    }

    private function poOrder(Customer $customer, string $date, array $items, float $discount = 0): PoTransaction
    {
        $subtotal = collect($items)->sum(fn (array $item) => $item[1] * $item[2]);
        $order = JobOrder::query()->create([
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'job_order_number' => 'JO-'.uniqid(),
            'status' => 'received',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
            'paid_amount' => 0,
            'balance' => $subtotal - $discount,
            'created_at' => $date,
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
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'company_name' => $customer->name,
            'po_number' => 'PO-'.$order->job_order_number,
            'transaction_date' => $date,
            'amount' => $subtotal - $discount,
            'balance' => $subtotal - $discount,
            'status' => 'pending',
        ]);
    }
}
