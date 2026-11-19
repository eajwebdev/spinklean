<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\JobOrder;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobOrderBranchFilterTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    private JobOrder $orderA;

    private JobOrder $orderB;

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

        $this->branchA = $this->makeBranch('Alpha Branch', 'ALPHA');
        $this->branchB = $this->makeBranch('Bravo Branch', 'BRAVO');

        // Real orders, created the way cashiers do (a baggage deposit is a completed job order).
        $this->orderA = $this->depositAt($this->branchA);
        $this->orderB = $this->depositAt($this->branchB);
    }

    private function makeBranch(string $name, string $code): Branch
    {
        return Branch::query()->create([
            'name' => $name,
            'code' => $code,
            'address' => 'Quezon City',
            'contact_number' => '09171234568',
            'branch_type' => 'pickup_dropoff',
            'machine_count' => 0,
            'baggage_tag_count' => 6,
            'is_active' => true,
        ]);
    }

    private function depositAt(Branch $branch): JobOrder
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'branch_id' => $branch->id, 'access' => ['job_orders']]);

        $this->actingAs($cashier)->post(route('admin.baggage.store'), [
            'customer_name' => 'Juan Dela Cruz',
            'cubicles' => [['number' => 1, 'size' => 'small', 'amount' => 20]],
            'payment_type' => 'cash',
        ])->assertSessionHasNoErrors();

        return JobOrder::query()->where('branch_id', $branch->id)->latest('id')->firstOrFail();
    }

    public function test_admin_sees_all_branches_and_can_filter_by_one(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'branch_id' => $this->branchA->id]);

        $this->actingAs($admin)->get(route('admin.job-orders.index'))
            ->assertOk()
            ->assertSee('All branches')
            ->assertSee($this->orderA->job_order_number)
            ->assertSee($this->orderB->job_order_number);

        $this->actingAs($admin)->get(route('admin.job-orders.index', ['branch_id' => $this->branchB->id]))
            ->assertOk()
            ->assertSee($this->orderB->job_order_number)
            ->assertDontSee($this->orderA->job_order_number)
            ->assertViewHas('statusCounts', fn ($counts) => $counts['total'] === 1);
    }

    public function test_branch_staff_cannot_use_the_filter_to_see_another_branch(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'branch_id' => $this->branchA->id, 'access' => ['job_orders']]);

        $this->actingAs($cashier)->get(route('admin.job-orders.index', ['branch_id' => $this->branchB->id]))
            ->assertOk()
            ->assertDontSee('All branches')
            ->assertSee($this->orderA->job_order_number)
            ->assertDontSee($this->orderB->job_order_number);
    }
}
