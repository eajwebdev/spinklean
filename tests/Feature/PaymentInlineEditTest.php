<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentInlineEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_type_and_reference_can_be_updated_inline(): void
    {
        $branch = Branch::query()->create(['name' => 'Osmena', 'code' => 'OSM', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'super_admin', 'branch_id' => $branch->id]);
        $payment = $this->payment($branch, $admin, 'cash');

        $this->actingAs($admin)
            ->patchJson(route('admin.payments.update', $payment), [
                'payment_type' => 'gcash',
                'reference_no' => '  GC-12345  ',
            ])
            ->assertOk()
            ->assertJsonPath('payment_type', 'gcash')
            ->assertJsonPath('reference_no', 'GC-12345');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_type' => 'gcash',
            'reference_no' => 'GC-12345',
        ]);
    }

    public function test_switching_a_payment_to_cash_clears_its_reference(): void
    {
        $branch = Branch::query()->create(['name' => 'Osmena', 'code' => 'OSM', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'super_admin', 'branch_id' => $branch->id]);
        $payment = $this->payment($branch, $admin, 'gcash', 'OLD-REFERENCE');

        $this->actingAs($admin)
            ->patchJson(route('admin.payments.update', $payment), [
                'payment_type' => 'cash',
                'reference_no' => 'SHOULD-BE-CLEARED',
            ])
            ->assertOk()
            ->assertJsonPath('reference_no', null);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_type' => 'cash',
            'reference_no' => null,
        ]);
    }

    public function test_branch_user_cannot_edit_another_branches_payment(): void
    {
        $ownBranch = Branch::query()->create(['name' => 'Osmena', 'code' => 'OSM', 'is_active' => true]);
        $otherBranch = Branch::query()->create(['name' => 'Pacana', 'code' => 'PAC', 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $ownBranch->id,
            'access' => ['payments'],
        ]);
        $receiver = User::factory()->create(['branch_id' => $otherBranch->id]);
        $payment = $this->payment($otherBranch, $receiver, 'cash');

        $this->withoutMiddleware()
            ->actingAs($staff)
            ->patchJson(route('admin.payments.update', $payment), [
                'payment_type' => 'gcash',
                'reference_no' => 'GC-999',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'payment_type' => 'cash']);
    }

    private function payment(Branch $branch, User $receiver, string $type, ?string $reference = null): Payment
    {
        return Payment::query()->create([
            'branch_id' => $branch->id,
            'collected_branch_id' => $branch->id,
            'received_by' => $receiver->id,
            'payment_number' => 'PAY-'.uniqid(),
            'payment_type' => $type,
            'reference_no' => $reference,
            'amount' => 195,
            'paid_at' => now(),
        ]);
    }
}
