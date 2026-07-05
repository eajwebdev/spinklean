<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_can_update_branch_settings_without_global_fields(): void
    {
        $this->completeSystemSettings();
        $this->activeTrial();

        $branch = $this->createBranch();
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
            'access' => ['settings'],
        ]);

        $this->actingAs($manager)
            ->put(route('admin.settings.update'), [
                'branch_name' => 'Fresh Wash Branch',
                'branch_code' => 'FWB',
                'branch_address' => '123 Clean Water Avenue',
                'branch_contact' => '09170000000',
                'machine_count' => 6,
                'receipt_header' => 'Fresh Wash',
                'receipt_footer' => 'Thank you',
                'default_price_per_kilo' => 75,
                'default_price_per_load' => 180,
                'default_price_per_piece' => 25,
                'job_order_prefix' => 'FWB',
                'invoice_prefix' => 'INV-FWB',
                'operating_hours' => [
                    'monday' => ['open' => '08:00', 'close' => '18:00'],
                ],
            ])
            ->assertRedirect(route('admin.settings.edit', ['branch_id' => $branch->id]));

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Fresh Wash Branch',
            'code' => 'FWB',
            'address' => '123 Clean Water Avenue',
            'machine_count' => 6,
        ]);

        $this->assertDatabaseHas('branch_settings', [
            'branch_id' => $branch->id,
            'receipt_header' => 'Fresh Wash',
            'job_order_prefix' => 'FWB',
        ]);
    }

    public function test_business_logo_upload_uses_public_uploads_and_reflects_on_shared_pages(): void
    {
        Storage::fake('uploads');
        $this->completeSystemSettings();
        $this->activeTrial();

        $branch = $this->createBranch();
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'branch_id' => $branch->id,
            'access' => ['settings'],
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(asset('logo.png'), false);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_code' => $branch->code,
                'branch_address' => $branch->address,
                'branch_contact' => $branch->contact_number,
                'branch_type' => 'full_service',
                'machine_count' => 5,
                'business_name' => 'Logo Laundry',
                'business_logo' => $this->tinyPngUpload('brand.png'),
                'contact_number' => '09171234567',
                'business_address' => 'Manila',
                'currency' => 'PHP',
                'primary_color' => '#0EA5E9',
            ])
            ->assertRedirect(route('admin.settings.edit', ['branch_id' => $branch->id]));

        $logoPath = SystemSetting::current()->business_logo;

        $this->assertNotNull($logoPath);
        Storage::disk('uploads')->assertExists($logoPath);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('uploads.show', ['path' => $logoPath]), false)
            ->assertDontSee(asset('logo.png'), false);

        $this->get(route('uploads.show', ['path' => $logoPath]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_branch_settings_no_longer_show_attendance_geofence_fields(): void
    {
        $this->completeSystemSettings();
        $this->activeTrial();

        $branch = $this->createBranch();
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
            'access' => ['settings', 'branches'],
        ]);

        $this->actingAs($manager)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertDontSee('Attendance Latitude')
            ->assertDontSee('Attendance Longitude')
            ->assertDontSee('Allowed Attendance Radius');

        $this->actingAs($manager)
            ->get(route('admin.branches.index'))
            ->assertOk()
            ->assertDontSee('Geofence')
            ->assertDontSee('name="latitude"', false)
            ->assertDontSee('name="longitude"', false)
            ->assertDontSee('attendance_radius_meters', false);
    }

    public function test_super_admin_can_update_sms_templates(): void
    {
        $this->completeSystemSettings();
        $this->activeTrial();

        $branch = $this->createBranch();
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'branch_id' => $branch->id,
            'access' => ['settings'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.edit', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Order Received Template')
            ->assertSee('{customer_name}', false);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_code' => $branch->code,
                'branch_address' => $branch->address,
                'branch_contact' => $branch->contact_number,
                'branch_type' => 'full_service',
                'machine_count' => 5,
                'business_name' => 'Template Laundry',
                'contact_number' => '09171234567',
                'business_address' => 'Manila',
                'currency' => 'PHP',
                'primary_color' => '#0EA5E9',
                'sms_enabled' => '1',
                'sms_provider' => 'unisms',
                'sms_api_key' => 'secret',
                'unisms_sender_id' => 'SPINKLEAN',
                'sms_template_order_received' => 'Hello {customer_name}, order {job_order_number} is received.',
                'sms_template_delivery_received' => 'Pickup received for {job_order_number}.',
                'sms_template_ready_for_pickup' => 'Claim {job_order_number} at {branch_name}.',
                'sms_template_ready_for_delivery' => 'Delivery ready for {job_order_number}.',
                'sms_template_completed' => 'Completed {job_order_number}. Thank you.',
            ])
            ->assertRedirect(route('admin.settings.edit', ['branch_id' => $branch->id]));

        $this->assertDatabaseHas('system_settings', [
            'id' => 1,
            'sms_enabled' => true,
            'sms_template_order_received' => 'Hello {customer_name}, order {job_order_number} is received.',
            'sms_template_ready_for_pickup' => 'Claim {job_order_number} at {branch_name}.',
        ]);
    }

    public function test_settings_rejects_too_long_branch_address_cleanly(): void
    {
        $this->completeSystemSettings();
        $this->activeTrial();

        $branch = $this->createBranch();
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
            'access' => ['settings'],
        ]);

        $this->actingAs($manager)
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                'branch_name' => $branch->name,
                'branch_code' => $branch->code,
                'branch_address' => str_repeat('A', 256),
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors('branch_address');
    }

    public function test_login_page_uses_laundry_presentation(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Laundry operations login')
            ->assertSee('laundry-bubble')
            ->assertSee('--bubble-x')
            ->assertSee('window.appPrimaryColor')
            ->assertDontSee('particles-js');
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
            'primary_color' => '#0EA5E9',
            'is_completed' => true,
        ]);
    }

    private function activeTrial(): void
    {
        SystemTrialSetting::query()->create([
            'trial_enabled' => true,
            'trial_start_date' => now()->subDay()->toDateString(),
            'trial_end_date' => now()->addDay()->toDateString(),
            'trial_status' => 'active',
            'grace_period_days' => 0,
        ]);
    }

    private function createBranch(): Branch
    {
        return Branch::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => 'Old Address',
            'contact_number' => '09171234567',
            'is_active' => true,
        ]);
    }

    private function tinyPngUpload(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'logo_');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADElEQVR42mP8z8AARQAHAQH9pWfNAAAAAElFTkSuQmCC'
        ));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
