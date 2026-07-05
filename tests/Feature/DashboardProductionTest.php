<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_for_core_roles_and_defaults_to_today_data(): void
    {
        $this->settings();

        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Dashboard Customer',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);

        $todayOrder = $this->order($branch, $customer, 'JO-DASH-TODAY', 250, today()->setTime(9, 0));
        $this->payment($branch, $customer, $todayOrder, 'PAY-DASH-TODAY', 250, today()->setTime(9, 10));

        $oldOrder = $this->order($branch, $customer, 'JO-DASH-OLD', 999, today()->subDay()->setTime(9, 0));
        $this->payment($branch, $customer, $oldOrder, 'PAY-DASH-OLD', 999, today()->subDay()->setTime(9, 10));

        foreach (['super_admin', 'admin', 'branch_manager', 'cashier', 'staff'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'branch_id' => $role === 'super_admin' || $role === 'admin' ? null : $branch->id,
                'access' => ['dashboard'],
            ]);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Dashboard');

            $payload = $this->actingAs($user)
                ->getJson(route('dashboard.data'))
                ->assertOk()
                ->json();

            $this->assertSame('PHP 250.00', $payload['stats']['sales']);
            $this->assertSame('1', $payload['stats']['orders']);
        }
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

        SystemTrialSetting::query()->create([
            'trial_enabled' => true,
            'trial_start_date' => today()->subDay(),
            'trial_end_date' => today()->addDay(),
            'trial_status' => 'active',
            'grace_period_days' => 0,
        ]);
    }

    private function order(Branch $branch, Customer $customer, string $number, float $amount, $createdAt): JobOrder
    {
        $order = JobOrder::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'job_order_number' => $number,
            'status' => 'completed',
            'subtotal' => $amount,
            'total' => $amount,
            'paid_amount' => $amount,
            'balance' => 0,
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order;
    }

    private function payment(Branch $branch, Customer $customer, JobOrder $order, string $number, float $amount, $paidAt): Payment
    {
        return Payment::query()->create([
            'branch_id' => $branch->id,
            'collected_branch_id' => $branch->id,
            'job_order_id' => $order->id,
            'customer_id' => $customer->id,
            'payment_number' => $number,
            'payment_type' => 'cash',
            'amount' => $amount,
            'settlement_status' => 'local',
            'paid_at' => $paidAt,
        ]);
    }
}
