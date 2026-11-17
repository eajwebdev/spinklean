<?php

namespace Tests\Feature;

use App\Models\BaggageDeposit;
use App\Models\Branch;
use App\Models\JobOrder;
use App\Models\LaundryService;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use App\Support\BaggageCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BaggageCounterTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    private User $cashier;

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
            'name' => 'Third Branch',
            'code' => 'THIRD',
            'address' => 'Quezon City',
            'contact_number' => '09171234568',
            'branch_type' => 'pickup_dropoff',
            'machine_count' => 0,
            'baggage_tag_count' => 6,
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $this->branch->id,
            'access' => ['job_orders'],
        ]);
        // Existing cashiers only have "job_orders"; that is enough to run the counter.
        $this->cashier = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
            'access' => ['job_orders'],
        ]);
    }

    private function deposit(int $tag, string $size = 'small', array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->cashier)->post(route('admin.baggage.store'), array_merge([
            'tag_number' => $tag,
            'size' => $size,
            'payment_type' => 'cash',
        ], $overrides));
    }

    public function test_cashier_with_job_order_access_sees_the_counter_with_tags(): void
    {
        $this->actingAs($this->cashier)->get(route('admin.baggage.index'))
            ->assertOk()
            ->assertSee('Baggage Counter')
            ->assertViewHas('tags', fn ($tags) => $tags->count() === 6 && $tags->first()['label'] === '01' && $tags->last()['label'] === '06')
            ->assertViewHas('rates', fn ($rates) => $rates->pluck('price', 'size')->all() === ['small' => 20.0, 'medium' => 30.0, 'large' => 50.0, 'overnight' => 100.0]);

        $this->actingAs($this->cashier)->get(route('dashboard'))->assertSee(route('admin.baggage.index'), false);
    }

    public function test_storing_a_bag_records_a_paid_sale_and_occupies_the_tag(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');

        $this->deposit(1, 'small')->assertSessionHasNoErrors()->assertRedirect(route('admin.baggage.index', ['branch_id' => $this->branch->id]));

        $deposit = BaggageDeposit::query()->sole();
        $this->assertSame(1, $deposit->tag_number);
        $this->assertSame('small', $deposit->size);
        $this->assertEquals(20, (float) $deposit->amount);
        $this->assertSame('2026-11-18 12:00:00', $deposit->due_at->toDateTimeString(), 'Due back in 2 hours.');
        $this->assertTrue($deposit->isActive());

        $order = $deposit->jobOrder;
        $this->assertTrue($order->is_product_sale);
        $this->assertSame('completed', $order->status);
        $this->assertEquals(20, (float) $order->total);
        $this->assertEquals(0, (float) $order->balance);
        $this->assertSame('Walk-in Customer', $order->customer->name);
        $this->assertSame(0, $order->transfers()->count());
        $this->assertSame(BaggageCounter::CATEGORY, $order->items->first()->service->serviceCategory->name);

        $payment = Payment::query()->where('job_order_id', $order->id)->sole();
        $this->assertEquals(20, (float) $payment->amount);
        $this->assertSame('cash', $payment->payment_type);
        $this->assertSame($this->branch->id, (int) $payment->collected_branch_id);

        // The same tag cannot be given out twice; another tag can.
        $this->deposit(1, 'medium')->assertSessionHasErrors('tag_number');
        $this->deposit(2, 'medium')->assertSessionHasNoErrors();
        $this->assertSame(2, BaggageDeposit::query()->active()->count());

        Carbon::setTestNow();
    }

    public function test_overnight_rate_is_due_in_24_hours_and_costs_100(): void
    {
        Carbon::setTestNow('2026-11-18 17:00:00');

        $this->deposit(3, 'overnight', ['payment_type' => 'gcash', 'payment_reference_no' => 'GC123'])->assertSessionHasNoErrors();

        $deposit = BaggageDeposit::query()->sole();
        $this->assertEquals(100, (float) $deposit->amount);
        $this->assertSame('2026-11-19 17:00:00', $deposit->due_at->toDateTimeString());
        $this->assertSame('GC123', Payment::query()->sole()->reference_no);

        Carbon::setTestNow();
    }

    public function test_bag_becomes_overdue_after_two_hours(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->deposit(1, 'large')->assertSessionHasNoErrors();
        $deposit = BaggageDeposit::query()->sole();

        Carbon::setTestNow('2026-11-18 11:59:00');
        $this->assertFalse($deposit->fresh()->isOverdue());

        Carbon::setTestNow('2026-11-18 12:01:00');
        $this->assertTrue($deposit->fresh()->isOverdue());

        Carbon::setTestNow();
    }

    public function test_releasing_frees_the_tag_and_keeps_the_sale(): void
    {
        $this->deposit(4, 'small')->assertSessionHasNoErrors();
        $deposit = BaggageDeposit::query()->sole();

        $this->actingAs($this->cashier)->patch(route('admin.baggage.release', $deposit))->assertSessionHasNoErrors();

        $deposit->refresh();
        $this->assertNotNull($deposit->released_at);
        $this->assertSame($this->cashier->id, (int) $deposit->released_by);
        $this->assertSame('completed', $deposit->jobOrder->status);
        $this->assertSame(1, Payment::query()->count());

        // Tag 04 can be used again.
        $this->deposit(4, 'medium')->assertSessionHasNoErrors();
        $this->assertSame(1, BaggageDeposit::query()->active()->where('tag_number', 4)->count());

        // A released bag cannot be released again.
        $this->actingAs($this->cashier)->patch(route('admin.baggage.release', $deposit))->assertStatus(422);
    }

    public function test_voiding_cancels_the_sale_and_frees_the_tag(): void
    {
        $this->deposit(5, 'large')->assertSessionHasNoErrors();
        $deposit = BaggageDeposit::query()->sole();

        $this->actingAs($this->cashier)->patch(route('admin.baggage.void', $deposit))->assertSessionHasNoErrors();

        $deposit->refresh();
        $this->assertNotNull($deposit->voided_at);
        $this->assertSame('cancelled', $deposit->jobOrder->status);
        $this->assertSame(0, Payment::query()->count(), 'Payments of cancelled sales drop out of the totals.');
        $this->deposit(5, 'small')->assertSessionHasNoErrors();
    }

    public function test_cancelling_the_sale_from_job_orders_frees_the_tag(): void
    {
        $this->deposit(6, 'small')->assertSessionHasNoErrors();
        $deposit = BaggageDeposit::query()->sole();

        $this->actingAs($this->manager)->patch(route('admin.job-orders.cancel', $deposit->job_order_id), ['cancel_reason' => 'mistake'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($deposit->fresh()->voided_at);
        $this->assertSame(0, BaggageDeposit::query()->active()->count());
    }

    public function test_baggage_sale_cannot_be_edited_from_job_orders(): void
    {
        $this->deposit(1)->assertSessionHasNoErrors();
        $order = BaggageDeposit::query()->sole()->jobOrder;

        $this->actingAs($this->manager)->get(route('admin.job-orders.edit', $order))->assertStatus(422);
        $this->actingAs($this->manager)->get(route('admin.job-orders.show', $order))
            ->assertOk()
            ->assertSee('Baggage Tag #01');
        $this->actingAs($this->manager)->get(route('admin.job-orders.index'))
            ->assertOk()
            ->assertSee('Baggage Tag #01');
    }

    public function test_baggage_rates_stay_out_of_the_laundry_pos(): void
    {
        BaggageCounter::rates($this->branch);

        $this->actingAs($this->cashier)->get(route('admin.job-orders.create'))
            ->assertOk()
            ->assertViewHas('services', fn ($services) => collect($services)->every(fn ($s) => ! str_starts_with($s['name'], 'Baggage - ')))
            ->assertViewHas('serviceCategories', fn ($categories) => ! $categories->contains('name', BaggageCounter::CATEGORY));
    }

    public function test_changed_rate_price_is_used(): void
    {
        $small = BaggageCounter::rates($this->branch)->get('small');
        $small->update(['price' => 25]);

        $this->deposit(1, 'small')->assertSessionHasNoErrors();
        $this->assertEquals(25, (float) BaggageDeposit::query()->sole()->amount);

        // A rate switched off is not offered.
        $small->update(['is_active' => false]);
        $this->deposit(2, 'small')->assertSessionHasErrors('size');
    }

    public function test_only_managers_change_tag_count_and_tags_must_exist(): void
    {
        $this->actingAs($this->cashier)->patch(route('admin.baggage.settings'), ['baggage_tag_count' => 10])->assertForbidden();

        $this->actingAs($this->manager)->patch(route('admin.baggage.settings'), ['baggage_tag_count' => 10])->assertSessionHasNoErrors();
        $this->assertSame(10, $this->branch->fresh()->baggage_tag_count);

        $this->deposit(10)->assertSessionHasNoErrors();
        $this->deposit(11)->assertSessionHasErrors('tag_number');
    }

    public function test_other_branch_cannot_release_the_bag(): void
    {
        $this->deposit(1)->assertSessionHasNoErrors();
        $deposit = BaggageDeposit::query()->sole();

        $otherBranch = Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => 'Manila',
            'contact_number' => '09171234567',
            'branch_type' => 'full_service',
            'machine_count' => 2,
            'is_active' => true,
        ]);
        $otherCashier = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $otherBranch->id,
            'access' => ['job_orders'],
        ]);

        $this->actingAs($otherCashier)->patch(route('admin.baggage.release', $deposit))->assertForbidden();
        $this->assertTrue($deposit->fresh()->isActive());
    }

    public function test_branch_without_tags_shows_setup_message(): void
    {
        $this->branch->update(['baggage_tag_count' => 0]);

        $this->actingAs($this->manager)->get(route('admin.baggage.index'))
            ->assertOk()
            ->assertViewHas('tags', fn ($tags) => $tags->isEmpty())
            ->assertSee('Number of claim tags');
        $this->deposit(1)->assertSessionHasErrors('tag_number');
    }

    public function test_dashboard_sales_include_baggage_and_show_baggage_panel(): void
    {
        $this->manager->update(['access' => ['job_orders', 'dashboard']]);

        $this->deposit(1, 'small')->assertSessionHasNoErrors();                                // 20 cash
        $this->deposit(2, 'large', ['payment_type' => 'gcash'])->assertSessionHasNoErrors();   // 50 gcash
        $this->deposit(3, 'medium')->assertSessionHasNoErrors();                               // 30, voided below
        $voided = BaggageDeposit::query()->where('tag_number', 3)->sole();
        $this->actingAs($this->cashier)->patch(route('admin.baggage.void', $voided))->assertSessionHasNoErrors();
        $released = BaggageDeposit::query()->where('tag_number', 1)->sole();
        $this->actingAs($this->cashier)->patch(route('admin.baggage.release', $released))->assertSessionHasNoErrors();

        $payload = $this->actingAs($this->manager)->getJson(route('dashboard.data'))->assertOk()->json();

        // Main totals count the baggage sales (voided one excluded).
        $this->assertSame('PHP 70.00', $payload['stats']['sales']);
        $this->assertSame('PHP 20.00', $payload['sales']['cash']);
        $this->assertSame('PHP 50.00', $payload['sales']['gcash']);
        $this->assertContains('Baggage Storage', collect($payload['analytics']['category_sales']['rows'])->pluck('label')->all());

        // Baggage panel.
        $baggage = $payload['analytics']['baggage'];
        $this->assertSame('PHP 70.00', $baggage['total']);
        $this->assertSame('2', $baggage['bags']);
        $this->assertSame('1', $baggage['stored_now'], 'Tag 02 is still at the counter.');
        $this->assertSame('0', $baggage['overdue_now']);
        $this->assertSame(['Small item', 'Large to XL item'], collect($baggage['rows'])->pluck('label')->all());

        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk()->assertSee('Baggage Sales');

        // Baggage tab: full list, counter list, stats and charts.
        $this->assertSame('PHP 20.00', $baggage['cash']);
        $this->assertSame('PHP 50.00', $baggage['gcash']);
        $this->assertSame('PHP 35.00', $baggage['average']);
        $this->assertSame('1', $baggage['released']);
        $this->assertSame('1', $baggage['voided']);
        $this->assertCount(3, $baggage['list'], 'The list shows every bag, voided ones included.');
        $this->assertSame(['Voided', 'Stored', 'Released'], collect($baggage['list'])->sortBy('tag')->reverse()->pluck('status')->values()->all());
        $this->assertSame(['02'], collect($baggage['at_counter'])->pluck('tag')->all());
        $this->assertEquals([20, 50], $payload['charts']['baggage_sizes']['values']);
        $this->assertEquals([70], $payload['charts']['baggage_daily']['values']);
        $this->assertSame(2, array_sum($payload['charts']['baggage_hourly']['values']));

        $this->actingAs($this->manager)->get(route('dashboard'))
            ->assertSee('All Baggage in This Period')
            ->assertSee('At the Counter Now');
    }

    public function test_baggage_tab_hidden_without_baggage_access(): void
    {
        $viewer = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
            'access' => ['dashboard'],
        ]);

        $this->actingAs($viewer)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('All Baggage in This Period');
    }

    public function test_default_branch_seeding_keeps_baggage_rates(): void
    {
        BaggageCounter::rates($this->branch);
        \App\Support\DefaultLaundryServices::seedForBranch($this->branch);

        $this->assertSame(4, LaundryService::query()->where('branch_id', $this->branch->id)->whereNotNull('baggage_size')->count());
    }
}
