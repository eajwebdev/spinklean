<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

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

        $this->branch = Branch::query()->create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
    }

    private function cashier(string $password = 'oldpass1'): User
    {
        return User::factory()->create([
            'username' => 'cashier1',
            'role' => 'cashier',
            'branch_id' => $this->branch->id,
            'status' => 'active',
            'access' => ['job_orders'],
            'password' => $password,
        ]);
    }

    private function editPayload(User $user, array $overrides = []): array
    {
        return array_merge([
            '_form' => 'user-edit-'.$user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'branch_id' => $user->branch_id,
            'status' => $user->status,
            'access' => $user->access,
        ], $overrides);
    }

    public function test_admin_changes_a_users_password_and_the_user_can_log_in_with_it(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $cashier = $this->cashier();

        $this->actingAs($admin)->put(route('admin.users.update', $cashier), $this->editPayload($cashier, [
            'password' => 'newpass99',
            'password_confirmation' => 'newpass99',
        ]))->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newpass99', $cashier->fresh()->password));
        $this->assertFalse(Hash::check('oldpass1', $cashier->fresh()->password));

        auth()->logout();
        $this->post(route('login'), ['login' => 'cashier1', 'password' => 'newpass99'])->assertRedirect();
        $this->assertAuthenticatedAs($cashier->fresh());
    }

    public function test_blank_password_keeps_the_current_one(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $cashier = $this->cashier();

        $this->actingAs($admin)->put(route('admin.users.update', $cashier), $this->editPayload($cashier, [
            'name' => 'Renamed',
            'password' => '',
            'password_confirmation' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $cashier->fresh()->name);
        $this->assertTrue(Hash::check('oldpass1', $cashier->fresh()->password));
    }

    public function test_failed_edit_reopens_only_that_users_popup_with_the_error(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'name' => 'Admin Person']);
        $cashier = $this->cashier();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.update', $cashier), $this->editPayload($cashier, [
                'name' => 'Typed Name',
                'password' => 'newpass99',
                'password_confirmation' => 'different',
            ]))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('oldpass1', $cashier->fresh()->password));

        $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();
        // The popup of this user reopens and shows the error...
        $this->assertStringContainsString('editOpen: '.$cashier->id, preg_replace('/\s+/', ' ', $html));
        $this->assertStringContainsString('Not saved. Please fix:', $html);
        // ...and the typed name is refilled only once (in this user's form, not in every form).
        $this->assertSame(1, substr_count($html, 'value="Typed Name"'));
    }

    public function test_user_changes_own_password_from_the_account_menu(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get(route('dashboard'))->assertOk()->assertSee('Change password');

        $this->actingAs($cashier)->from(route('dashboard'))->put(route('account.password.update'), [
            'current_password' => 'oldpass1',
            'password' => 'mynewpass',
            'password_confirmation' => 'mynewpass',
        ])->assertRedirect(route('dashboard'))->assertSessionHas('success', 'Your password has been changed.');

        $this->assertTrue(Hash::check('mynewpass', $cashier->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'account_password_changed']);
    }

    public function test_own_password_change_requires_the_correct_current_password(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->from(route('dashboard'))->put(route('account.password.update'), [
            'current_password' => 'wrong',
            'password' => 'mynewpass',
            'password_confirmation' => 'mynewpass',
        ])->assertSessionHasErrorsIn('accountPassword', 'current_password');

        $this->actingAs($cashier)->from(route('dashboard'))->put(route('account.password.update'), [
            'current_password' => 'oldpass1',
            'password' => 'mynewpass',
            'password_confirmation' => 'typo',
        ])->assertSessionHasErrorsIn('accountPassword', 'password');

        $this->assertTrue(Hash::check('oldpass1', $cashier->fresh()->password));
    }
}
