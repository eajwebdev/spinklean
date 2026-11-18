<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CycleRecord;
use App\Models\JobOrder;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use App\Models\ZReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZReadingMachineCounterReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $productionBranch;

    private Branch $dropoffBranch;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'business_name' => 'Spin Klean Laundry',
                'business_email' => 'admin@spinklean.test',
                'contact_number' => '09171234567',
                'business_address' => 'Test Address',
                'currency' => 'PHP',
                'timezone' => 'Asia/Manila',
                'is_completed' => true,
            ]
        );

        SystemTrialSetting::query()->create([
            'trial_start_date' => now()->subDay()->toDateString(),
            'trial_end_date' => now()->addDay()->toDateString(),
            'trial_status' => 'active',
            'grace_period_days' => 0,
        ]);

        $this->productionBranch = Branch::query()->create([
            'name' => 'Branch 1',
            'code' => 'B0001',
            'branch_type' => 'full_service',
            'machine_count' => 2,
            'is_active' => true,
        ]);
        $this->dropoffBranch = Branch::query()->create([
            'name' => 'Branch 3',
            'code' => 'B0003',
            'branch_type' => 'pickup_dropoff',
            'machine_count' => 0,
            'is_active' => true,
        ]);
    }

    public function test_production_branch_counts_cycles_for_dropoff_laundry_and_shows_breakdown(): void
    {
        $user = $this->userFor($this->productionBranch);
        $this->createOrderWithCycles($this->productionBranch, $this->productionBranch, 'JO-OWN', ['wash' => 2]);
        $this->createOrderWithCycles($this->dropoffBranch, $this->productionBranch, 'JO-DROP', ['wash' => 1, 'dry' => 1]);

        $response = $this->actingAs($user)->get(route('admin.z-readings.create', ['business_date' => today()->toDateString()]));

        $response->assertOk()->assertSee('laundry from drop-off branches');
        $counters = $response->viewData('machineCounters');
        $this->assertSame(3, $counters[1]['wash']['system_total']);
        $this->assertSame(3, $counters[1]['wash']['ending']);
        $this->assertSame(1, $counters[1]['dry']['system_total']);
        $this->assertSame(0, $counters[1]['wash']['difference']);

        $dropoff = $response->viewData('summary')['dropoff_cycles'];
        $this->assertCount(1, $dropoff);
        $this->assertSame('Branch 3', $dropoff[0]['branch_name']);
        $this->assertSame(1, $dropoff[0]['wash']);
        $this->assertSame(1, $dropoff[0]['dry']);
    }

    public function test_manually_corrected_ending_is_saved_with_difference_from_cycle_monitoring(): void
    {
        $user = $this->userFor($this->productionBranch);
        $this->createOrderWithCycles($this->productionBranch, $this->productionBranch, 'JO-OWN', ['wash' => 3]);

        ZReading::query()->create($this->readingAttributes($this->productionBranch, $user, today()->subDay()->toDateString(), [
            1 => ['wash' => ['beginning' => 90, 'ending' => 100], 'dry' => ['beginning' => 0, 'ending' => 0]],
            2 => ['wash' => ['beginning' => 0, 'ending' => 0], 'dry' => ['beginning' => 0, 'ending' => 0]],
        ]));

        // Machine 1 was started once by accident: counter reads 104 but Cycle Monitoring has 3 cycles.
        $this->actingAs($user)->post(route('admin.z-readings.store'), [
            'business_date' => today()->toDateString(),
            'machine_counters' => [
                1 => ['wash' => ['beginning' => 100, 'ending' => 104], 'dry' => ['beginning' => 0, 'ending' => 0]],
                2 => ['wash' => ['beginning' => 0, 'ending' => 0], 'dry' => ['beginning' => 0, 'ending' => 0]],
            ],
            'remarks' => 'Wash 1 started by accident once.',
        ])->assertRedirect();

        $counters = ZReading::query()->whereDate('business_date', today())->firstOrFail()->machine_counters;
        $this->assertSame(104, $counters[1]['wash']['ending']);
        $this->assertSame(4, $counters[1]['wash']['total']);
        $this->assertSame(3, $counters[1]['wash']['system_total']);
        $this->assertSame(103, $counters[1]['wash']['system_ending']);
        $this->assertSame(1, $counters[1]['wash']['difference']);
        $this->assertSame(0, $counters[2]['wash']['difference']);

        $this->actingAs($user)
            ->get(route('admin.z-readings.create', ['business_date' => today()->toDateString()]))
            ->assertOk()
            ->assertViewHas('machineCounters', fn ($counters) => $counters[1]['wash']['ending'] === 104
                && $counters[1]['wash']['difference'] === 1);
    }

    public function test_ending_lower_than_beginning_is_rejected_unless_it_is_a_real_rollover(): void
    {
        $user = $this->userFor($this->productionBranch);
        $idle = ['wash' => ['beginning' => 0, 'ending' => 0], 'dry' => ['beginning' => 0, 'ending' => 0]];

        // Typo: 95 entered for a counter that started at 100 would mean 9,995 cycles.
        $this->actingAs($user)
            ->from(route('admin.z-readings.create'))
            ->post(route('admin.z-readings.store'), [
                'business_date' => today()->toDateString(),
                'machine_counters' => [
                    1 => ['wash' => ['beginning' => 100, 'ending' => 95], 'dry' => ['beginning' => 0, 'ending' => 0]],
                    2 => $idle,
                ],
            ])
            ->assertSessionHasErrors('machine_counters.1.wash.ending');
        $this->assertDatabaseCount('z_readings', 0);

        $this->followingRedirects()
            ->actingAs($user)
            ->from(route('admin.z-readings.create'))
            ->post(route('admin.z-readings.store'), [
                'business_date' => today()->toDateString(),
                'machine_counters' => [
                    1 => ['wash' => ['beginning' => 100, 'ending' => 95], 'dry' => ['beginning' => 0, 'ending' => 0]],
                    2 => $idle,
                ],
            ])
            ->assertSee('The Z Reading was not saved')
            ->assertSee('Wash 1 ending (0095) is lower than its beginning (0100)');

        // Genuine rollover: 9998 -> 0003 is 5 cycles.
        $this->actingAs($user)->post(route('admin.z-readings.store'), [
            'business_date' => today()->toDateString(),
            'machine_counters' => [
                1 => ['wash' => ['beginning' => 9998, 'ending' => 3], 'dry' => ['beginning' => 0, 'ending' => 0]],
                2 => $idle,
            ],
        ])->assertSessionHasNoErrors();

        $counters = ZReading::query()->firstOrFail()->machine_counters;
        $this->assertSame(5, $counters[1]['wash']['total']);
    }

    public function test_corrected_beginning_fixes_the_previous_z_reading_ending(): void
    {
        $user = $this->userFor($this->productionBranch);
        $idle = ['wash' => ['beginning' => 0, 'ending' => 0], 'dry' => ['beginning' => 0, 'ending' => 0]];

        $yesterday = ZReading::query()->create($this->readingAttributes($this->productionBranch, $user, today()->subDay()->toDateString(), [
            1 => ['wash' => ['beginning' => 90, 'ending' => 100, 'total' => 10], 'dry' => ['beginning' => 50, 'ending' => 60, 'total' => 10]],
            2 => $idle,
        ]));

        // The form shows yesterday's ending as the beginning, and knows yesterday's ending.
        $this->actingAs($user)->get(route('admin.z-readings.create', ['business_date' => today()->toDateString()]))
            ->assertOk()
            ->assertViewHas('machineCounters', fn ($c) => $c[1]['wash']['beginning'] === 100)
            ->assertViewHas('previousEndings', fn ($p) => $p[1]['wash'] === 100);

        // The actual Wash 1 counter this morning reads 0102 (yesterday really closed at 102).
        $this->actingAs($user)->post(route('admin.z-readings.store'), [
            'business_date' => today()->toDateString(),
            'machine_counters' => [
                1 => ['wash' => ['beginning' => 102, 'ending' => 110], 'dry' => ['beginning' => 60, 'ending' => 65]],
                2 => $idle,
            ],
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Wash 1 ending 0100 → 0102'));

        $previous = $yesterday->fresh()->machine_counters;
        $this->assertSame(102, $previous[1]['wash']['ending']);
        $this->assertSame(12, $previous[1]['wash']['total']);
        $this->assertSame(60, $previous[1]['dry']['ending']); // unchanged

        $today = ZReading::query()->whereDate('business_date', today())->firstOrFail()->machine_counters;
        $this->assertSame(102, $today[1]['wash']['beginning']);
        $this->assertSame(8, $today[1]['wash']['total']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'z_reading_machine_counters_synced']);
    }

    public function test_editing_a_past_ending_updates_the_next_day_beginning(): void
    {
        $user = $this->userFor($this->productionBranch);
        $idle = ['wash' => ['beginning' => 0, 'ending' => 0], 'dry' => ['beginning' => 0, 'ending' => 0]];

        ZReading::query()->create($this->readingAttributes($this->productionBranch, $user, today()->subDays(2)->toDateString(), [
            1 => ['wash' => ['beginning' => 80, 'ending' => 90], 'dry' => ['beginning' => 0, 'ending' => 0]],
            2 => $idle,
        ]));
        $yesterday = ZReading::query()->create($this->readingAttributes($this->productionBranch, $user, today()->subDay()->toDateString(), [
            1 => ['wash' => ['beginning' => 90, 'ending' => 100], 'dry' => ['beginning' => 0, 'ending' => 0]],
            2 => $idle,
        ]));
        $todayReading = ZReading::query()->create(array_merge($this->readingAttributes($this->productionBranch, $user, today()->toDateString(), [
            1 => ['wash' => ['beginning' => 100, 'ending' => 120], 'dry' => ['beginning' => 0, 'ending' => 0]],
            2 => $idle,
        ]), ['reading_number' => 'ZR-TODAY']));

        // Re-open yesterday and set the ending to the real counter, 0105.
        $this->actingAs($user)->post(route('admin.z-readings.store'), [
            'business_date' => today()->subDay()->toDateString(),
            'machine_counters' => [
                1 => ['wash' => ['beginning' => 90, 'ending' => 105], 'dry' => ['beginning' => 0, 'ending' => 0]],
                2 => $idle,
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(105, $yesterday->fresh()->machine_counters[1]['wash']['ending']);
        $next = $todayReading->fresh()->machine_counters;
        $this->assertSame(105, $next[1]['wash']['beginning']);
        $this->assertSame(120, $next[1]['wash']['ending']);
        $this->assertSame(15, $next[1]['wash']['total']);

        // And today's form opens with the matching beginning.
        $this->actingAs($user)->get(route('admin.z-readings.create', ['business_date' => today()->toDateString()]))
            ->assertViewHas('machineCounters', fn ($c) => $c[1]['wash']['beginning'] === 105 && $c[1]['wash']['total'] === 15);
    }

    public function test_no_machine_branch_has_no_machine_counters_and_sees_cycles_run_for_its_laundry(): void
    {
        $user = $this->userFor($this->dropoffBranch);
        $this->createOrderWithCycles($this->dropoffBranch, $this->productionBranch, 'JO-DROP', ['wash' => 2, 'dry' => 1]);

        $response = $this->actingAs($user)->get(route('admin.z-readings.create', ['business_date' => today()->toDateString()]));

        $response->assertOk()
            ->assertSee('Machine Cycles at Production Branches')
            ->assertDontSee('Machine Counter Readings')
            ->assertViewHas('machineCount', 0);
        $outsourced = $response->viewData('summary')['outsourced_cycles'];
        $this->assertSame('Branch 1', $outsourced[0]['branch_name']);
        $this->assertSame(2, $outsourced[0]['wash']);
        $this->assertSame(1, $outsourced[0]['dry']);

        $this->actingAs($user)->post(route('admin.z-readings.store'), [
            'business_date' => today()->toDateString(),
        ])->assertRedirect();

        $reading = ZReading::query()->where('branch_id', $this->dropoffBranch->id)->firstOrFail();
        $this->assertSame([], $reading->machine_counters ?? []);

        $this->actingAs($user)->get(route('admin.z-readings.pdf', $reading))->assertOk();
    }

    private function userFor(Branch $branch): User
    {
        return User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
            'access' => ['z_readings'],
        ]);
    }

    private function createOrderWithCycles(Branch $origin, Branch $production, string $number, array $cycles): JobOrder
    {
        $customer = Customer::query()->create([
            'branch_id' => $origin->id,
            'name' => 'Customer '.$number,
            'phone' => '09170000000',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);

        $order = JobOrder::query()->create([
            'branch_id' => $origin->id,
            'processing_branch_id' => $production->id,
            'current_branch_id' => $production->id,
            'release_branch_id' => $origin->id,
            'customer_id' => $customer->id,
            'job_order_number' => $number,
            'status' => 'washing',
            'production_accepted_at' => now(),
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'balance' => 0,
        ]);

        foreach ($cycles as $type => $count) {
            for ($i = 1; $i <= $count; $i++) {
                CycleRecord::query()->create([
                    'job_order_id' => $order->id,
                    'cycle_type' => $type,
                    'machine_number' => 1,
                    'cycle_number' => $i,
                    'started_at' => now(),
                ]);
            }
        }

        return $order;
    }

    private function readingAttributes(Branch $branch, User $user, string $date, array $counters): array
    {
        return [
            'branch_id' => $branch->id,
            'business_date' => $date,
            'reading_number' => 'ZR-'.$branch->code.'-'.$date,
            'prepared_by' => $user->id,
            'machine_counters' => $counters,
            'expected_cash_amount' => 0,
            'cash_expense_amount' => 0,
            'expected_cash_drawer_amount' => 0,
            'actual_cash_amount' => 0,
            'expected_gcash_amount' => 0,
            'actual_gcash_amount' => 0,
            'expected_bank_amount' => 0,
            'actual_bank_amount' => 0,
            'expected_total_amount' => 0,
            'actual_total_amount' => 0,
            'over_short_amount' => 0,
            'transaction_count' => 0,
            'signature_name' => $user->name,
            'closed_at' => now()->subDay(),
        ];
    }
}
