<?php

namespace Tests\Feature;

use App\Models\AttendanceEmployee;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DailyTask;
use App\Models\DailyTaskCompletion;
use App\Models\JobOrder;
use App\Models\LaundryService;
use App\Models\LaundryServiceCategory;
use App\Models\SmsLog;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_see_super_admins_in_user_list(): void
    {
        $this->completeSystemSettings();

        $admin = User::factory()->create([
            'name' => 'Regular Admin',
            'role' => 'admin',
            'access' => ['users'],
        ]);

        User::factory()->create([
            'name' => 'Hidden Super Admin',
            'username' => 'hidden-super-admin',
            'email' => 'hidden-super-admin@example.com',
            'role' => 'super_admin',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.users.index'));

        $response
            ->assertOk()
            ->assertSee('Regular Admin')
            ->assertDontSee('Hidden Super Admin')
            ->assertDontSee('super_admin');
    }

    public function test_admin_cannot_create_super_admin_user(): void
    {
        $this->completeSystemSettings();

        $admin = User::factory()->create([
            'role' => 'admin',
            'access' => ['users'],
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Super Admin',
                'username' => 'new-super-admin',
                'email' => 'new-super-admin@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => 'super_admin',
                'branch_id' => null,
                'status' => 'active',
                'access' => ['users'],
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', [
            'username' => 'new-super-admin',
        ]);
    }

    public function test_admin_cannot_see_or_assign_billing_access(): void
    {
        $this->completeSystemSettings();

        $admin = User::factory()->create([
            'role' => 'admin',
            'access' => ['users'],
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Billing')
            ->assertDontSee('admin.billing.index');

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Billing Admin',
                'username' => 'billing-admin',
                'email' => 'billing-admin@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => 'admin',
                'branch_id' => null,
                'status' => 'active',
                'access' => ['dashboard', 'users', 'billing'],
            ]);

        $response->assertRedirect(route('admin.users.index'));

        $created = User::where('username', 'billing-admin')->firstOrFail();

        $this->assertContains('users', $created->access);
        $this->assertNotContains('billing', $created->access);

        $this
            ->actingAs($created)
            ->get(route('admin.billing.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_see_billing_access_checkbox_and_sidebar_link(): void
    {
        $this->completeSystemSettings();

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this
            ->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Billing')
            ->assertSee('Superadmin only');

        $this
            ->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Billing')
            ->assertSee(route('admin.billing.index'), false);
    }

    public function test_branch_manager_cannot_create_global_admin_user(): void
    {
        $this->completeSystemSettings();

        $branch = Branch::query()->create(['name' => 'Branch A', 'code' => 'A', 'is_active' => true]);
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
            'access' => ['users'],
        ]);

        $response = $this
            ->actingAs($manager)
            ->post(route('admin.users.store'), [
                'name' => 'Global Admin',
                'username' => 'global-admin',
                'email' => 'global-admin@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => 'admin',
                'branch_id' => $branch->id,
                'status' => 'active',
                'access' => ['dashboard'],
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'global-admin']);
    }

    public function test_admin_can_create_branch_manager_with_default_access_preset(): void
    {
        $this->completeSystemSettings();

        $admin = User::factory()->create([
            'role' => 'admin',
            'access' => ['users'],
        ]);
        $branch = Branch::query()->create([
            'name' => 'Branch 3',
            'code' => 'B0003',
            'branch_type' => 'pickup_dropoff',
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Branch 3 Manager',
                'username' => 'branch3-manager',
                'email' => 'branch3-manager@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => 'branch_manager',
                'branch_id' => $branch->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::where('username', 'branch3-manager')->firstOrFail();

        $this->assertSame('branch_manager', $created->role);
        $this->assertSame($branch->id, $created->branch_id);
        $this->assertContains('job_orders', $created->access);
        $this->assertContains('cycles', $created->access);
        $this->assertContains('service_categories', $created->access);
        $this->assertContains('users', $created->access);
        $this->assertNotContains('billing', $created->access);
    }

    public function test_service_categories_uses_its_own_menu_access_key(): void
    {
        $this->completeSystemSettings();

        $branch = Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        LaundryServiceCategory::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Wash',
            'visibility' => 'all',
            'is_active' => true,
        ]);

        $categoryUser = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $branch->id,
            'access' => ['service_categories'],
        ]);

        $serviceUser = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $branch->id,
            'access' => ['services'],
        ]);

        $this
            ->actingAs($categoryUser)
            ->get(route('admin.service-categories.index'))
            ->assertOk()
            ->assertSee('Service Categories');

        $this
            ->actingAs($serviceUser)
            ->get(route('admin.service-categories.index'))
            ->assertForbidden();
    }

    public function test_non_admin_user_creation_allows_no_email_without_creating_employee(): void
    {
        $this->completeSystemSettings();

        $admin = User::factory()->create([
            'role' => 'admin',
            'access' => ['users'],
        ]);
        $branch = Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Juan Dela Cruz',
                'username' => 'juan.cruz',
                'email' => '',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'role' => 'cashier',
                'branch_id' => $branch->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $user = User::where('username', 'juan.cruz')->firstOrFail();

        $this->assertNull($user->email);
        $this->assertSame($branch->id, $user->branch_id);
        $this->assertDatabaseCount('attendance_employees', 0);
    }

    public function test_updating_user_does_not_modify_separately_managed_employee(): void
    {
        $this->completeSystemSettings();

        $admin = User::factory()->create([
            'role' => 'admin',
            'access' => ['users'],
        ]);
        $branch = Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);
        $employee = AttendanceEmployee::query()->create([
            'branch_id' => $branch->id,
            'first_name' => 'Existing',
            'last_name' => 'Employee',
            'username' => 'same.employee',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Separate User',
                'username' => 'different-system-login',
                'email' => '',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'role' => 'staff',
                'branch_id' => $branch->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $user = User::where('username', 'different-system-login')->firstOrFail();

        $this
            ->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Updated Separate User',
                'username' => 'updated-system-login',
                'email' => '',
                'role' => 'staff',
                'branch_id' => $branch->id,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.users.index'));

        $employee->refresh();

        $this->assertSame('Existing', $employee->first_name);
        $this->assertSame('same.employee', $employee->username);
        $this->assertSame('active', $employee->status);
        $this->assertNull($employee->user_id);
    }

    public function test_branch_level_roles_require_branch_assignment(): void
    {
        $this->completeSystemSettings();

        $admin = User::factory()->create([
            'role' => 'admin',
            'access' => ['users'],
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'No Branch Cashier',
                'username' => 'no-branch-cashier',
                'email' => 'no-branch-cashier@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => 'cashier',
                'branch_id' => null,
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('users', ['username' => 'no-branch-cashier']);
    }

    public function test_branch_manager_cannot_edit_other_branch_job_order(): void
    {
        $this->completeSystemSettings();

        [$branchA, $branchB] = $this->twoBranches();
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branchA->id,
            'access' => ['job_orders'],
        ]);
        $customer = Customer::query()->create([
            'branch_id' => $branchB->id,
            'name' => 'Other Customer',
            'billing_type' => 'regular',
            'unpaid_limit' => 0,
            'is_active' => true,
        ]);
        $service = LaundryService::query()->create([
            'branch_id' => $branchB->id,
            'name' => 'Wash',
            'pricing_type' => 'kilo',
            'price' => 100,
            'is_active' => true,
        ]);
        $order = JobOrder::query()->create([
            'branch_id' => $branchB->id,
            'customer_id' => $customer->id,
            'created_by' => $manager->id,
            'job_order_number' => 'JO-B-0001',
            'status' => 'completed',
            'transaction_type' => 'walk_in',
            'subtotal' => 100,
            'discount' => 0,
            'tax' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'balance' => 100,
        ]);
        $order->items()->create([
            'laundry_service_id' => $service->id,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        $this
            ->actingAs($manager)
            ->put(route('admin.job-orders.update', $order), [
                'customer_id' => $customer->id,
                'status' => 'pending',
                'transaction_type' => 'walk_in',
                'discount' => 0,
                'items' => [[
                    'laundry_service_id' => $service->id,
                    'description' => $service->name,
                    'quantity' => 1,
                    'unit_price' => 100,
                ]],
            ])
            ->assertForbidden();
    }

    public function test_branch_manager_expense_is_forced_to_assigned_branch(): void
    {
        $this->completeSystemSettings();

        [$branchA, $branchB] = $this->twoBranches();
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branchA->id,
            'access' => ['expenses'],
        ]);

        $this
            ->actingAs($manager)
            ->post(route('admin.expenses.store'), [
                'branch_id' => $branchB->id,
                'category' => 'Supplies',
                'title' => 'Detergent',
                'amount' => '250',
                'expense_date' => today()->toDateString(),
                'payment_method' => 'Cash',
                'paid_from' => 'store_cash',
                'expense_type' => 'regular',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branch_expenses', [
            'branch_id' => $branchA->id,
            'title' => 'Detergent',
        ]);
        $this->assertDatabaseMissing('branch_expenses', [
            'branch_id' => $branchB->id,
            'title' => 'Detergent',
        ]);
    }

    public function test_branch_manager_cannot_complete_other_branch_daily_task(): void
    {
        $this->completeSystemSettings();
        Storage::fake('uploads');

        [$branchA, $branchB] = $this->twoBranches();
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branchA->id,
            'access' => ['branches', 'daily_tasks'],
        ]);
        $task = DailyTask::query()->create([
            'branch_id' => $branchB->id,
            'name' => 'Other branch cleaning',
            'requires_photo' => true,
            'is_active' => true,
        ]);

        $this
            ->actingAs($manager)
            ->post(route('admin.daily-tasks.complete', $task), [
                'branch_id' => $branchA->id,
                'work_date' => today()->toDateString(),
                'photo' => UploadedFile::fake()->createWithContent('proof.jpg', base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ASP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/ASP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Al//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EFBQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EFBQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EFBABAQAAAAAAAAAAAAAAAAAAARD/2gAIAQEAAT8QH//Z')),
            ])
            ->assertForbidden();

        $this->assertSame(0, DailyTaskCompletion::query()->count());
    }

    public function test_branch_manager_can_add_task_only_to_assigned_branch(): void
    {
        $this->completeSystemSettings();

        [$branchA, $branchB] = $this->twoBranches();
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branchA->id,
            'access' => ['branches', 'daily_tasks'],
        ]);

        $this
            ->actingAs($manager)
            ->post(route('admin.branches.daily-tasks.store', $branchB), [
                'name' => 'Clean delivery shelf',
                'requires_photo' => '1',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('daily_tasks', [
            'branch_id' => $branchB->id,
            'name' => 'Clean delivery shelf',
        ]);

        $this
            ->actingAs($manager)
            ->post(route('admin.branches.daily-tasks.store', $branchA), [
                'name' => 'Clean delivery shelf',
                'requires_photo' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_tasks', [
            'branch_id' => $branchA->id,
            'name' => 'Clean delivery shelf',
        ]);
    }

    public function test_non_admin_user_with_branch_access_only_sees_own_branch(): void
    {
        $this->completeSystemSettings();

        [$branchA, $branchB] = $this->twoBranches();
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'branch_id' => $branchA->id,
            'access' => ['branches'],
        ]);

        $this
            ->actingAs($cashier)
            ->get(route('admin.branches.index'))
            ->assertOk()
            ->assertSee('Branch A')
            ->assertDontSee('Branch B');
    }

    public function test_sms_log_search_does_not_leak_other_branch_messages(): void
    {
        $this->completeSystemSettings();

        [$branchA, $branchB] = $this->twoBranches();
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branchA->id,
            'access' => ['sms_logs'],
        ]);

        SmsLog::query()->create([
            'branch_id' => $branchA->id,
            'recipient' => '09170000001',
            'message' => 'Ready for pickup',
            'status' => 'sent',
        ]);
        SmsLog::query()->create([
            'branch_id' => $branchB->id,
            'recipient' => '09170000002',
            'message' => 'Secret customer ready for pickup',
            'status' => 'sent',
        ]);

        $this
            ->actingAs($manager)
            ->get(route('admin.sms-logs.index', ['search' => 'ready']))
            ->assertOk()
            ->assertSee('09170000001')
            ->assertDontSee('09170000002')
            ->assertDontSee('Secret customer');
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

        SystemTrialSetting::query()->create([
            'trial_enabled' => true,
            'trial_start_date' => now()->subDay()->toDateString(),
            'trial_end_date' => now()->addDay()->toDateString(),
            'trial_status' => 'active',
            'grace_period_days' => 0,
        ]);
    }

    private function twoBranches(): array
    {
        return [
            Branch::query()->create(['name' => 'Branch A', 'code' => 'A', 'is_active' => true]),
            Branch::query()->create(['name' => 'Branch B', 'code' => 'B', 'is_active' => true]),
        ];
    }
}
