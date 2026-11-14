<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchExpense;
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

    public function test_dashboard_tabs_expose_sales_deductions_and_balances_for_today(): void
    {
        $this->settings();

        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Tab Customer',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);

        $todayOrder = $this->order($branch, $customer, 'JO-TAB-TODAY', 400, today()->setTime(10, 0));
        $todayOrder->forceFill(['subtotal' => 450, 'discount' => 50])->save();
        $this->payment($branch, $customer, $todayOrder, 'PAY-TAB-TODAY', 400, today()->setTime(10, 15));

        $yesterdayOrder = $this->order($branch, $customer, 'JO-TAB-YDAY', 200, today()->subDay()->setTime(10, 0));
        $this->payment($branch, $customer, $yesterdayOrder, 'PAY-TAB-YDAY', 200, today()->subDay()->setTime(10, 15));

        BranchExpense::query()->create([
            'branch_id' => $branch->id,
            'category' => 'Utilities',
            'title' => 'Water bill',
            'amount' => 150,
            'expense_date' => today(),
            'payment_method' => 'cash',
            'paid_from' => 'store_cash',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null, 'access' => ['dashboard']]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('Sales')
            ->assertSee('Deductions')
            ->assertSee('Cash &amp; Balances', false)
            ->assertSee('Operations');

        $payload = $this->actingAs($admin)->getJson(route('dashboard.data'))->assertOk()->json();

        $this->assertSame('Today', $payload['period']['label']);
        $this->assertTrue($payload['period']['is_today']);
        $this->assertCount(24, $payload['charts']['sales']['labels']);
        $this->assertEquals(400, $payload['charts']['sales']['values'][10]);

        $this->assertSame('PHP 400.00', $payload['sales']['sales_owned']);
        $this->assertSame('PHP 200.00', $payload['sales']['previous_sales']);
        $this->assertEquals(100.0, $payload['sales']['change_percent']);
        $this->assertSame('PHP 450.00', $payload['sales']['gross_sales']);
        $this->assertSame('PHP 50.00', $payload['sales']['discounts']);
        $this->assertSame('PHP 250.00', $payload['sales']['net_after_expenses']);

        $this->assertSame('PHP 150.00', $payload['deductions']['expenses']);
        $this->assertSame('PHP 150.00', $payload['deductions']['total']);
        $this->assertSame('Utilities', $payload['deductions']['categories'][0]['label']);
        $this->assertCount(1, $payload['recent_expenses']);
        $this->assertCount(1, $payload['recent_payments']);

        $this->assertSame('PHP 250.00', $payload['balances']['expected_cash_drawer']);

        $ranged = $this->actingAs($admin)
            ->getJson(route('dashboard.data', ['date_range' => today()->subDay()->toDateString().' to '.today()->toDateString()]))
            ->assertOk()
            ->json();

        $this->assertCount(2, $ranged['charts']['sales']['labels']);
        $this->assertSame('PHP 600.00', $ranged['sales']['sales_owned']);
    }

    public function test_dashboard_analytics_cover_ratios_aging_staff_and_audit(): void
    {
        $this->settings();

        $branch = Branch::query()->create(['name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true]);
        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Analytics Customer',
            'billing_type' => 'regular',
            'is_active' => true,
        ]);
        $cashier = User::factory()->create(['role' => 'cashier', 'branch_id' => $branch->id, 'access' => ['dashboard'], 'name' => 'Cara Cashier']);
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null, 'access' => ['dashboard']]);

        // Returning customer: an older order already exists.
        $this->order($branch, $customer, 'JO-AN-OLD', 100, today()->subDays(40)->setTime(9, 0));

        // Today: 300 billed, 200 paid (partial), created by the cashier.
        $today = $this->order($branch, $customer, 'JO-AN-TODAY', 300, today()->setTime(9, 0));
        $today->forceFill(['created_by' => $cashier->id, 'paid_amount' => 200, 'balance' => 100])->save();
        $payment = $this->payment($branch, $customer, $today, 'PAY-AN-TODAY', 200, today()->setTime(14, 30));
        $payment->forceFill(['received_by' => $cashier->id])->save();

        // Older unpaid order sitting in the 31-60 day bucket.
        $old = JobOrder::query()->where('job_order_number', 'JO-AN-OLD')->first();
        $old->forceFill(['paid_amount' => 0, 'balance' => 100])->save();

        ActivityLog::query()->create([
            'user_id' => $admin->id,
            'branch_id' => $branch->id,
            'action' => 'payment_deleted',
            'properties' => ['payment_number' => 'PAY-X', 'amount' => 75],
        ]);

        $payload = $this->actingAs($admin)->getJson(route('dashboard.data'))->assertOk()->json();
        $analytics = $payload['analytics'];

        $this->assertEquals(66.7, $analytics['ratios']['collection_rate']);
        $this->assertSame('1', $analytics['orders_breakdown']['partial']);
        $this->assertSame('1', $analytics['customers']['served']);
        $this->assertSame('1', $analytics['customers']['returning']);
        $this->assertSame('2 PM – 3 PM', $analytics['peak']['hour']);
        $this->assertCount(7, $analytics['trend_7d']['labels']);
        $this->assertEquals(200, $analytics['trend_7d']['sales'][6]);

        $aging = collect($analytics['receivables_aging']['rows'])->keyBy('label');
        $this->assertSame('PHP 100.00', $aging['0–7 days']['amount']);
        $this->assertSame('PHP 100.00', $aging['31–60 days']['amount']);
        $this->assertSame('PHP 200.00', $analytics['top_debtors'][0]['balance']);

        $staff = collect($analytics['audit']['staff'])->firstWhere('name', 'Cara Cashier');
        $this->assertSame(1, $staff['orders']);
        $this->assertSame(1, $staff['payments']);
        $this->assertSame('PHP 200.00', $staff['collected']);

        $deleted = collect($analytics['audit']['flags'])->firstWhere('label', 'Deleted payments');
        $this->assertSame('1 · PHP 75.00', $deleted['value']);
        $this->assertSame('bad', $deleted['tone']);
        $this->assertSame('Payment deleted', $analytics['audit']['activity'][0]['action']);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Audit &amp; Staff', false);

        $cashierPayload = $this->actingAs($cashier)->getJson(route('dashboard.data'))->assertOk()->json();
        $this->assertNull($cashierPayload['analytics']['audit']);
        $this->actingAs($cashier)->get(route('dashboard'))->assertOk()->assertDontSee('Audit &amp; Staff', false);
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
