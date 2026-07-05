<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchBillingRecord;
use App\Models\BranchExpense;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_allows_branch_user_and_shows_banner_but_not_super_admin(): void
    {
        $this->completeSystemSettings();
        $this->activeTrial('2026-05-01', '2026-05-31');

        $branch = $this->createBranch('Trial Branch', 'TRIAL');
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['dashboard'],
        ]);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->travelTo(Carbon::parse('2026-05-15'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('System Free Trial Active Until May 31, 2026');

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('System Free Trial Active Until');
    }

    public function test_trial_date_range_skips_billing_even_when_toggle_is_off(): void
    {
        $this->completeSystemSettings();
        SystemTrialSetting::query()->create([
            'trial_enabled' => false,
            'trial_start_date' => '2026-05-01',
            'trial_end_date' => '2026-05-31',
            'trial_status' => 'inactive',
            'grace_period_days' => 0,
        ]);

        $branch = $this->createBranch('Date Trial Branch', 'DTRIAL');
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['dashboard'],
        ]);

        $this->travelTo(Carbon::parse('2026-05-15'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('System Free Trial Active Until May 31, 2026');
    }

    public function test_blank_trial_settings_do_not_lock_branch_users_before_trial_is_configured(): void
    {
        $this->completeSystemSettings();

        $branch = $this->createBranch('Setup Branch', 'SETUP');
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['dashboard'],
        ]);

        SystemTrialSetting::query()->create([
            'trial_enabled' => false,
            'trial_status' => 'inactive',
            'grace_period_days' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Subscription Expired');
    }

    public function test_expired_trial_allows_unpaid_and_missing_subscription_with_warnings(): void
    {
        $this->completeSystemSettings();
        $this->expiredTrial(graceDays: 2);
        $this->travelTo(Carbon::parse('2026-05-10'));

        $paidBranch = $this->createBranch('Paid Branch', 'PAID');
        $unpaidBranch = $this->createBranch('Unpaid Branch', 'UNPAID');
        $missingBranch = $this->createBranch('Missing Branch', 'MISS');
        $paidUser = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $paidBranch->id,
            'access' => ['dashboard'],
        ]);
        $unpaidUser = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $unpaidBranch->id,
            'access' => ['dashboard'],
        ]);
        $missingUser = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $missingBranch->id,
            'access' => ['dashboard'],
        ]);

        BranchBillingRecord::create([
            'branch_id' => $paidBranch->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'amount' => 1000,
            'due_date' => '2026-05-05',
            'status' => 'paid',
        ]);

        BranchBillingRecord::create([
            'branch_id' => $unpaidBranch->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'amount' => 1000,
            'due_date' => '2026-05-05',
            'status' => 'unpaid',
        ]);

        $this->actingAs($paidUser)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($unpaidUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your branch subscription for May 2026 is overdue');

        $this->actingAs($missingUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No active branch subscription was found for May 2026')
            ->assertSee('Subscription Warning');
    }

    public function test_unpaid_branch_can_access_with_dismissible_warning(): void
    {
        $this->completeSystemSettings();
        $this->expiredTrial(graceDays: 5);
        $this->travelTo(Carbon::parse('2026-05-07'));

        $branch = $this->createBranch('Grace Branch', 'GRACE');
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['dashboard'],
        ]);

        BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'amount' => 1000,
            'due_date' => '2026-05-05',
            'status' => 'unpaid',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your branch subscription for May 2026 is overdue')
            ->assertSee('Dismiss billing notice');
    }

    public function test_paid_current_subscription_suppresses_warning_even_with_other_open_record(): void
    {
        $this->completeSystemSettings();
        $this->expiredTrial(graceDays: 0);
        $this->travelTo(Carbon::parse('2026-06-15'));

        $branch = $this->createBranch('Paid Current Branch', 'PCUR');
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['dashboard'],
        ]);

        BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'subscription_start_date' => '2026-06-01',
            'subscription_end_date' => '2026-06-30',
            'amount' => 1000,
            'due_date' => '2026-06-05',
            'status' => 'paid',
        ]);

        BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'subscription_start_date' => '2026-06-01',
            'subscription_end_date' => '2026-06-30',
            'amount' => 500,
            'due_date' => '2026-06-05',
            'status' => 'unpaid',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Subscription Warning')
            ->assertDontSee('Branch subscription has expired')
            ->assertDontSee('Your branch subscription');
    }

    public function test_paid_current_subscription_does_not_show_mid_cycle_paid_notice(): void
    {
        $this->completeSystemSettings();
        $this->expiredTrial(graceDays: 0);
        $this->travelTo(Carbon::parse('2026-06-15'));

        $branch = $this->createBranch('Paid Notice Branch', 'PNOT');
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['dashboard'],
        ]);

        BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'subscription_start_date' => '2026-06-01',
            'subscription_end_date' => '2026-06-30',
            'amount' => 1000,
            'due_date' => '2026-06-05',
            'status' => 'paid',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Billing Paid')
            ->assertDontSee('System billing for Jun 01, 2026 - Jun 30, 2026 is already paid.')
            ->assertSee('Subscription Notifications')
            ->assertDontSee('Billing paid');
    }

    public function test_paid_subscription_auto_opens_upcoming_billing_notice_five_days_before_end(): void
    {
        $this->completeSystemSettings();
        $this->expiredTrial(graceDays: 0);
        $this->travelTo(Carbon::parse('2026-06-25'));

        $branch = $this->createBranch('Upcoming Billing Branch', 'UPB');
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['dashboard'],
        ]);

        BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'subscription_start_date' => '2026-06-01',
            'subscription_end_date' => '2026-06-30',
            'amount' => 1000,
            'due_date' => '2026-06-05',
            'status' => 'paid',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Upcoming Billing')
            ->assertSee('Next billing is coming up in 5 days.')
            ->assertSee('Subscription Notifications')
            ->assertSee('autoOpen: true', false);
    }

    public function test_unpaid_billing_due_within_five_days_appears_in_notification_bell(): void
    {
        $this->completeSystemSettings();
        $this->expiredTrial(graceDays: 0);
        $this->travelTo(Carbon::parse('2026-06-10'));

        $branch = $this->createBranch('Due Soon Branch', 'DUE');
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['dashboard'],
        ]);

        BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'subscription_start_date' => '2026-06-01',
            'subscription_end_date' => '2026-06-30',
            'amount' => 1000,
            'due_date' => '2026-06-15',
            'status' => 'unpaid',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Subscription Notifications')
            ->assertSee('Due Soon Branch - Billing due')
            ->assertSee('Jun 01, 2026 - Jun 30, 2026 is unpaid and due on Jun 15, 2026.')
            ->assertSee('Your branch subscription for Jun 01, 2026 - Jun 30, 2026 is due in 5 days.')
            ->assertSee('autoOpen: true', false);
    }

    public function test_global_admin_without_branch_does_not_show_branch_expired_warning(): void
    {
        $this->completeSystemSettings();
        $this->expiredTrial(graceDays: 0);

        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => null,
            'access' => ['dashboard'],
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Branch subscription has expired');
    }

    public function test_suspended_current_subscription_warns_branch_users_without_locking(): void
    {
        $this->completeSystemSettings();
        $this->expiredTrial(graceDays: 0);
        $this->travelTo(Carbon::parse('2026-05-07'));

        $branch = $this->createBranch('Suspended Branch', 'SUSP');
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'access' => ['dashboard'],
        ]);

        BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'amount' => 1000,
            'due_date' => '2026-05-05',
            'status' => 'suspended',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Branch subscription has been suspended')
            ->assertSee('Subscription Warning');
    }

    public function test_super_admin_generates_billing_and_updates_only_unpaid_records(): void
    {
        $this->completeSystemSettings();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $branch = $this->createBranch('Generated Branch', 'GEN');

        BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'amount' => 900,
            'due_date' => '2026-05-05',
            'status' => 'paid',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.billing.generate'), [
                'branches' => [$branch->id],
                'subscription_start_date' => '2026-05-01',
                'subscription_end_date' => '2026-06-30',
                'prices' => [$branch->id => 1500],
                'due_date' => '2026-05-10',
                'update_unpaid' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branch_billing_records', [
            'branch_id' => $branch->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'amount' => 900,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('branch_billing_records', [
            'branch_id' => $branch->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'subscription_start_date' => '2026-05-01 00:00:00',
            'subscription_end_date' => '2026-06-30 00:00:00',
            'amount' => 1500,
            'status' => 'unpaid',
        ]);
    }

    public function test_super_admin_can_generate_multiple_exact_date_subscriptions_in_same_month(): void
    {
        $this->completeSystemSettings();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $branch = $this->createBranch('Date Range Branch', 'DATE');

        foreach ([
            ['start' => '2026-06-01', 'end' => '2026-06-15', 'amount' => 700],
            ['start' => '2026-06-16', 'end' => '2026-06-30', 'amount' => 800],
        ] as $range) {
            $this->actingAs($superAdmin)
                ->post(route('admin.billing.generate'), [
                    'branches' => [$branch->id],
                    'subscription_start_date' => $range['start'],
                    'subscription_end_date' => $range['end'],
                    'prices' => [$branch->id => $range['amount']],
                    'due_date' => $range['start'],
                ])
                ->assertRedirect();
        }

        $this->assertSame(2, BranchBillingRecord::where('branch_id', $branch->id)->count());
        $this->assertDatabaseHas('branch_billing_records', [
            'branch_id' => $branch->id,
            'subscription_start_date' => '2026-06-01 00:00:00',
            'subscription_end_date' => '2026-06-15 00:00:00',
            'amount' => 700,
        ]);
        $this->assertDatabaseHas('branch_billing_records', [
            'branch_id' => $branch->id,
            'subscription_start_date' => '2026-06-16 00:00:00',
            'subscription_end_date' => '2026-06-30 00:00:00',
            'amount' => 800,
        ]);
    }

    public function test_billing_dashboard_shows_subscribed_instead_of_expired_trial_when_active_paid_subscription_exists(): void
    {
        $this->completeSystemSettings();
        $this->expiredTrial(graceDays: 0);
        $this->travelTo(Carbon::parse('2026-06-18'));

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $branch = $this->createBranch('Subscribed Branch', 'SUB');

        BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'subscription_start_date' => '2026-06-01',
            'subscription_end_date' => '2026-06-30',
            'amount' => 1000,
            'due_date' => '2026-06-05',
            'status' => 'paid',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.billing.index'))
            ->assertOk()
            ->assertSee('System: Subscribed')
            ->assertDontSee('Trial: Expired');
    }

    public function test_marking_paid_creates_one_linked_branch_expense(): void
    {
        $this->completeSystemSettings();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $branch = $this->createBranch('Expense Branch', 'EXP');
        $record = BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'amount' => 1200,
            'due_date' => '2026-05-05',
            'status' => 'unpaid',
        ]);

        $payload = [
            'payment_date' => '2026-05-12',
            'payment_method' => 'Bank Transfer',
            'reference_no' => 'REF-001',
            'remarks' => 'Paid in full',
            'add_to_expenses' => '1',
            'paid_from' => 'store_cash',
        ];

        $this->actingAs($superAdmin)
            ->patch(route('admin.billing.records.mark-paid', $record), $payload)
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->patch(route('admin.billing.records.mark-paid', $record), $payload)
            ->assertRedirect();

        $record->refresh();

        $this->assertSame('paid', $record->status);
        $this->assertNotNull($record->expense_id);
        $this->assertSame(1, BranchExpense::where('source', 'branch_billing')->where('source_id', $record->id)->count());
        $this->assertDatabaseHas('branch_expenses', [
            'branch_id' => $branch->id,
            'category' => 'software_subscription',
            'title' => 'System Billing - May 2026',
            'amount' => 1200,
            'source' => 'branch_billing',
            'source_id' => $record->id,
            'paid_from' => 'store_cash',
            'created_by' => $superAdmin->id,
        ]);
    }

    public function test_marking_billing_paid_can_skip_branch_expense(): void
    {
        $this->completeSystemSettings();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $branch = $this->createBranch('No Expense Branch', 'NOEXP');
        $record = BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'amount' => 1200,
            'due_date' => '2026-05-05',
            'status' => 'unpaid',
        ]);

        $this->actingAs($superAdmin)
            ->patch(route('admin.billing.records.mark-paid', $record), [
                'payment_date' => '2026-05-12',
                'payment_method' => 'GCash',
                'reference_no' => 'GCASH-001',
            ])
            ->assertRedirect();

        $record->refresh();

        $this->assertSame('paid', $record->status);
        $this->assertNull($record->expense_id);
        $this->assertSame(0, BranchExpense::where('source', 'branch_billing')->where('source_id', $record->id)->count());
    }

    private function completeSystemSettings(): void
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
    }

    private function createBranch(string $name, string $code): Branch
    {
        return Branch::query()->create([
            'name' => $name,
            'code' => $code,
            'address' => 'Manila',
            'contact_number' => '09171234567',
            'is_active' => true,
        ]);
    }

    private function activeTrial(string $start, string $end): void
    {
        SystemTrialSetting::query()->create([
            'trial_enabled' => true,
            'trial_start_date' => $start,
            'trial_end_date' => $end,
            'trial_status' => 'active',
            'grace_period_days' => 0,
        ]);
    }

    private function expiredTrial(int $graceDays): void
    {
        SystemTrialSetting::query()->create([
            'trial_enabled' => false,
            'trial_start_date' => '2026-04-01',
            'trial_end_date' => '2026-04-30',
            'trial_status' => 'expired',
            'grace_period_days' => $graceDays,
        ]);
    }
}
