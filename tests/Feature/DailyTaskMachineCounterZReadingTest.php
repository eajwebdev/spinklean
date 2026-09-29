<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CycleRecord;
use App\Models\DailyTask;
use App\Models\DailyTaskCompletion;
use App\Models\JobOrder;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use App\Models\ZReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DailyTaskMachineCounterZReadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

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
    }

    public function test_branch_daily_tasks_can_be_configured_with_machine_counter_impact(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Branch 1-JULIO PACANA',
            'code' => 'B0001',
            'machine_count' => 4,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['branches'],
        ]);

        // 1. Add Wash task: Machine tub cleaning
        $this->actingAs($admin)
            ->post(route('admin.branches.daily-tasks.store', $branch), [
                'name' => 'Machine tub cleaning',
                'requires_photo' => 1,
                'affects_machine_counter' => 'wash',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('daily_tasks', [
            'branch_id' => $branch->id,
            'name' => 'Machine tub cleaning',
            'affects_machine_counter' => 'wash',
        ]);

        // 2. Add Dry task: Lint filter cleaning
        $this->actingAs($admin)
            ->post(route('admin.branches.daily-tasks.store', $branch), [
                'name' => 'Lint filter cleaning',
                'requires_photo' => 1,
                'affects_machine_counter' => 'dry',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('daily_tasks', [
            'branch_id' => $branch->id,
            'name' => 'Lint filter cleaning',
            'affects_machine_counter' => 'dry',
        ]);

        // 3. Update task to both
        $task = DailyTask::where('name', 'Lint filter cleaning')->first();
        $this->actingAs($admin)
            ->put(route('admin.branches.daily-tasks.update', [$branch, $task]), [
                'name' => 'Lint filter cleaning',
                'requires_photo' => 1,
                'affects_machine_counter' => 'both',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('both', $task->fresh()->affects_machine_counter);
        $this->assertTrue($task->fresh()->affectsWash());
        $this->assertTrue($task->fresh()->affectsDry());
    }

    public function test_daily_task_completion_records_multiple_selected_machines(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Branch 1-JULIO PACANA',
            'code' => 'B0001',
            'machine_count' => 4,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['daily_tasks'],
        ]);

        $task = DailyTask::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Machine tub cleaning',
            'requires_photo' => true,
            'affects_machine_counter' => 'wash',
            'is_active' => true,
        ]);

        $workDate = today()->toDateString();
        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($user)
            ->post(route('admin.daily-tasks.complete', $task), [
                'branch_id' => $branch->id,
                'work_date' => $workDate,
                'photo' => $file,
                'remarks' => 'Cleaned tub on Wash 1 and Wash 2',
                'machines' => [
                    'wash' => [1, 2],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $completion = DailyTaskCompletion::query()
            ->where('daily_task_id', $task->id)
            ->where('branch_id', $branch->id)
            ->whereDate('work_date', $workDate)
            ->first();

        $this->assertNotNull($completion);
        $this->assertSame([1, 2], $completion->cleanedWashMachines());
        $this->assertEmpty($completion->cleanedDryMachines());
        $this->assertSame('Wash: #1, #2', $completion->cleanedMachinesSummary());
    }

    public function test_z_reading_auto_computes_machine_counters_with_cleaning_cycles(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Branch 1-JULIO PACANA',
            'code' => 'B0001',
            'machine_count' => 4,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['z_readings', 'daily_tasks'],
        ]);

        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Test Customer',
            'phone' => '09170000000',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);

        $today = today()->toDateString();

        // Previous Z Reading ending counters
        ZReading::query()->create([
            'branch_id' => $branch->id,
            'business_date' => today()->subDay()->toDateString(),
            'reading_number' => 'ZR-B0001-YESTERDAY-0001',
            'prepared_by' => $user->id,
            'machine_counters' => [
                1 => [
                    'wash' => ['beginning' => 1000, 'ending' => 1005, 'total' => 5],
                    'dry' => ['beginning' => 2000, 'ending' => 2004, 'total' => 4],
                ],
                2 => [
                    'wash' => ['beginning' => 3000, 'ending' => 3002, 'total' => 2],
                    'dry' => ['beginning' => 4000, 'ending' => 4003, 'total' => 3],
                ],
                3 => [
                    'wash' => ['beginning' => 5000, 'ending' => 5001, 'total' => 1],
                    'dry' => ['beginning' => 6000, 'ending' => 6002, 'total' => 2],
                ],
                4 => [
                    'wash' => ['beginning' => 7000, 'ending' => 7003, 'total' => 3],
                    'dry' => ['beginning' => 8000, 'ending' => 8004, 'total' => 4],
                ],
            ],
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
            'signature_name' => 'Admin User',
            'closed_at' => now()->subDay(),
        ]);

        // Customer job order with cycles today:
        // Wash 1: 3 customer cycles
        // Dry 1: 2 customer cycles
        $order = JobOrder::query()->create([
            'branch_id' => $branch->id,
            'processing_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'release_branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_number' => 'JO-TODAY-001',
            'status' => 'washing',
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'balance' => 0,
            'created_at' => today(),
        ]);

        for ($i = 0; $i < 3; $i++) {
            CycleRecord::query()->create([
                'job_order_id' => $order->id,
                'user_id' => $user->id,
                'cycle_type' => 'wash',
                'machine_number' => 1,
                'cycle_number' => $i + 1,
                'started_at' => today(),
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            CycleRecord::query()->create([
                'job_order_id' => $order->id,
                'user_id' => $user->id,
                'cycle_type' => 'dry',
                'machine_number' => 1,
                'cycle_number' => $i + 1,
                'started_at' => today(),
            ]);
        }

        // Daily Task 1: Tub cleaning (wash) cleaned Wash 1 and Wash 2
        $tubTask = DailyTask::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Machine tub cleaning',
            'requires_photo' => true,
            'affects_machine_counter' => 'wash',
            'is_active' => true,
        ]);
        DailyTaskCompletion::query()->create([
            'daily_task_id' => $tubTask->id,
            'branch_id' => $branch->id,
            'completed_by' => $user->id,
            'work_date' => $today,
            'photo_path' => 'proofs/tub.jpg',
            'cleaned_machines' => ['wash' => [1, 2], 'dry' => []],
            'completed_at' => now(),
        ]);

        // Daily Task 2: Filter cleaning (dry) cleaned Dry 1 and Dry 3
        $filterTask = DailyTask::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Lint filter cleaning',
            'requires_photo' => true,
            'affects_machine_counter' => 'dry',
            'is_active' => true,
        ]);
        DailyTaskCompletion::query()->create([
            'daily_task_id' => $filterTask->id,
            'branch_id' => $branch->id,
            'completed_by' => $user->id,
            'work_date' => $today,
            'photo_path' => 'proofs/filter.jpg',
            'cleaned_machines' => ['wash' => [], 'dry' => [1, 3]],
            'completed_at' => now(),
        ]);

        // Load Create Z Reading page
        $response = $this->actingAs($user)->get(route('admin.z-readings.create', [
            'branch_id' => $branch->id,
            'business_date' => $today,
        ]));

        $response->assertOk();
        $response->assertSee('Machine tub cleaning');
        $response->assertSee('Lint filter cleaning');
        $response->assertSee('End-of-Day Cleaning Cycles Accounted For Today');

        $machineCounters = $response->viewData('machineCounters');

        // Wash 1:
        // Beginning: 1005 (from previous ending)
        // Customer cycles: 3
        // Cleaning cycles: 1
        // Total wash cycle: 4
        // Ending: 1005 + 4 = 1009
        $this->assertSame(1005, $machineCounters[1]['wash']['beginning']);
        $this->assertSame(1009, $machineCounters[1]['wash']['ending']);
        $this->assertSame(4, $machineCounters[1]['wash']['total']);
        $this->assertSame(3, $machineCounters[1]['wash']['customer_cycles']);
        $this->assertSame(1, $machineCounters[1]['wash']['cleaning_cycles']);

        // Wash 2:
        // Beginning: 3002
        // Customer cycles: 0
        // Cleaning cycles: 1
        // Total wash cycle: 1
        // Ending: 3002 + 1 = 3003
        $this->assertSame(3002, $machineCounters[2]['wash']['beginning']);
        $this->assertSame(3003, $machineCounters[2]['wash']['ending']);
        $this->assertSame(1, $machineCounters[2]['wash']['total']);
        $this->assertSame(0, $machineCounters[2]['wash']['customer_cycles']);
        $this->assertSame(1, $machineCounters[2]['wash']['cleaning_cycles']);

        // Dry 1:
        // Beginning: 2004
        // Customer cycles: 2
        // Cleaning cycles: 1
        // Total dry cycle: 3
        // Ending: 2004 + 3 = 2007
        $this->assertSame(2004, $machineCounters[1]['dry']['beginning']);
        $this->assertSame(2007, $machineCounters[1]['dry']['ending']);
        $this->assertSame(3, $machineCounters[1]['dry']['total']);
        $this->assertSame(2, $machineCounters[1]['dry']['customer_cycles']);
        $this->assertSame(1, $machineCounters[1]['dry']['cleaning_cycles']);

        // Dry 3:
        // Beginning: 6002
        // Customer cycles: 0
        // Cleaning cycles: 1
        // Total dry cycle: 1
        // Ending: 6002 + 1 = 6003
        $this->assertSame(6002, $machineCounters[3]['dry']['beginning']);
        $this->assertSame(6003, $machineCounters[3]['dry']['ending']);
        $this->assertSame(1, $machineCounters[3]['dry']['total']);

        // Store Z-Reading
        $storeResponse = $this->actingAs($user)->post(route('admin.z-readings.store'), [
            'branch_id' => $branch->id,
            'business_date' => $today,
            'cash_count' => ['1000' => 0],
            'actual_gcash_amount' => 0,
            'actual_bank_amount' => 0,
            'machine_counters' => $machineCounters,
            'remarks' => 'Tested with tub and filter cleaning',
        ]);

        $storeResponse->assertRedirect();

        $savedReading = ZReading::query()
            ->where('branch_id', $branch->id)
            ->whereDate('business_date', $today)
            ->first();

        $this->assertNotNull($savedReading);
        $this->assertSame(1009, data_get($savedReading->machine_counters, '1.wash.ending'));
        $this->assertSame(4, data_get($savedReading->machine_counters, '1.wash.total'));
        $this->assertSame(3003, data_get($savedReading->machine_counters, '2.wash.ending'));
        $this->assertSame(1, data_get($savedReading->machine_counters, '2.wash.total'));

        // Test PDF generation
        $pdfResponse = $this->actingAs($user)->get(route('admin.z-readings.pdf', $savedReading));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
    }
}
