<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PoTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\StatementOfAccountRows;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StatementOfAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::query()->create([
            'business_name' => 'SPIN KLEAN LAUNDRY',
            'contact_number' => '09171234567',
            'business_address' => 'Main Office',
            'currency' => 'PHP',
            'job_order_prefix' => 'JO',
            'invoice_prefix' => 'INV',
            'is_completed' => true,
        ]);

        $this->admin = User::factory()->create(['role' => 'super_admin']);
        $this->branch = Branch::query()->create([
            'name' => 'Branch 2-OSMENA',
            'code' => 'B0002',
            'address' => 'Osmena St. Cagayan de Oro City',
            'is_active' => true,
        ]);
    }

    public function test_daily_rows_cover_every_day_and_split_loads_dry_extension_and_delivery(): void
    {
        $customer = $this->customer('LAYBARE', 'SM DOWNTOWN');
        // Package item: 2 loads @165 + dry extension + delivery = 400
        $this->poOrder($customer, '2026-02-02', [
            ['Laybare', 'other', 2, 165],
            ['Dry Extension', 'other', 1, 20],
            ['Delivery-50', 'delivery', 1, 50],
        ]);
        $this->poOrder($customer, '2026-02-02', [['Laybare', 'other', 1, 165]]);
        $this->poOrder($customer, '2026-02-05', [['Laybare', 'other', 3, 165]]);

        $soa = $this->rows($customer, '2026-02-01', '2026-02-28');

        $this->assertCount(28, $soa['days']);
        $this->assertSame(165.0, $soa['usual_price']);
        $this->assertTrue($soa['has_delivery']);
        $this->assertSame(1060.0, $soa['total']);

        $feb2 = $soa['days'][1];
        $this->assertSame('2026-02-02', $feb2['date']->toDateString());
        $this->assertSame(3.0, $feb2['loads']);
        $this->assertSame([165.0], $feb2['prices']);
        $this->assertSame(20.0, $feb2['dry_extension']);
        $this->assertSame(50.0, $feb2['delivery']);
        $this->assertSame(565.0, $feb2['amount']);

        $feb1 = $soa['days'][0];
        $this->assertSame(0.0, $feb1['loads']);
        $this->assertSame(0.0, $feb1['amount']);
    }

    public function test_machine_items_count_one_load_per_wash_with_full_service_price(): void
    {
        $customer = $this->customer('UNIQA LAUNDRY', 'LOOP LIMKETKAI');
        // 3 small loads (195 each) + 1 big load (295)
        $this->poOrder($customer, '2026-09-01', [
            ['Wash 7kg', 'small', 3, 60], ['Dry 7kg', 'small', 3, 80], ['Fold 7kg', 'small', 3, 25],
            ['Detergent 80ml', 'small', 3, 15], ['Fabcon 70ml', 'small', 3, 15],
            ['Wash 10kg', 'big', 1, 100], ['Dry 10kg', 'big', 1, 120], ['Fold 10kg', 'big', 1, 35],
            ['Detergent 100ml', 'big', 1, 20], ['Fabcon 100ml', 'big', 1, 20],
        ]);

        $day = $this->rows($customer, '2026-09-01', '2026-09-01')['days'][0];

        $this->assertSame(4.0, $day['loads']);
        $this->assertEqualsCanonicalizing([195.0, 295.0], $day['prices']);
        $this->assertSame(880.0, $day['amount']);
    }

    public function test_pdf_uses_customers_branch_address_and_branch_statement_details(): void
    {
        $customer = $this->customer('LAYBARE', 'SM DOWNTOWN');
        $this->poOrder($customer, '2026-02-02', [['Laybare', 'other', 3, 165]]);
        BranchSetting::query()->create([
            'branch_id' => $this->branch->id,
            'soa_tin' => '111-222-333-0000',
            'soa_account_number' => '9999-0000',
        ]);

        $html = $this->renderPdf($customer, '2026-02-01', '2026-02-28');

        $this->assertStringContainsString('SPIN KLEAN', $html);
        $this->assertStringContainsString('LAUNDRY EXPRESS', $html);
        $this->assertStringContainsString('Osmena St. Cagayan de Oro City', $html);
        $this->assertStringNotContainsString('Main Office', $html);
        $this->assertStringContainsString('Non VAT Reg TIN: 111-222-333-0000', $html);
        $this->assertStringContainsString('9999-0000', $html);
        // Blank branch details fall back to the business defaults.
        $this->assertStringContainsString(BranchSetting::SOA_DEFAULTS['soa_bank_name'], $html);
        $this->assertStringContainsString('STATEMENT OF ACCOUNT', $html);
        $this->assertStringContainsString('SM DOWNTOWN', $html);
        $this->assertStringContainsString('FEB. 2026', $html);
        $this->assertStringContainsString('February 28', $html);
        $this->assertStringContainsString('₱495.00', $html);
        $this->assertStringContainsString('Thank you for your business!', $html);
        // No delivery charges in the period: the column matches the original template.
        $this->assertStringNotContainsString('<th>Delivery</th>', $html);
    }

    public function test_statement_pdf_downloads_as_a_real_pdf(): void
    {
        $customer = $this->customer('LAYBARE', 'SM DOWNTOWN');
        $this->poOrder($customer, '2026-02-02', [['Laybare', 'other', 3, 165]]);

        $response = $this->actingAs($this->admin)->get(route('admin.po-transactions.statement-of-account.pdf', [
            'customer_id' => $customer->id,
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
        ]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $response->getContent()));
    }

    public function test_branch_form_saves_statement_details_and_blank_clears_them(): void
    {
        $payload = [
            'name' => $this->branch->name,
            'code' => $this->branch->code,
            'address' => 'Julio Pacana St. Barangay 25 Cagayan de Oro City',
            'branch_type' => 'full_service',
            'machine_count' => 4,
            'is_active' => 1,
            'soa_tin' => ' 619-588-144-0002 ',
            'soa_viber' => '0917-000-0000',
        ];

        $this->actingAs($this->admin)->put(route('admin.branches.update', $this->branch), $payload)->assertRedirect();

        $setting = $this->branch->fresh()->setting;
        $this->assertSame('619-588-144-0002', $setting->soa_tin);
        $this->assertSame('0917-000-0000', $setting->soa_viber);
        $this->assertNull($setting->soa_bank_name);
        $this->assertSame('Julio Pacana St. Barangay 25 Cagayan de Oro City', $this->branch->fresh()->address);

        $this->actingAs($this->admin)->put(route('admin.branches.update', $this->branch), [...$payload, 'soa_tin' => ''])->assertRedirect();
        $this->assertNull($this->branch->fresh()->setting->soa_tin);
    }

    private function customer(string $name, string $address): Customer
    {
        return Customer::query()->create([
            'branch_id' => $this->branch->id,
            'name' => $name,
            'address' => $address,
            'billing_type' => 'po',
            'is_active' => true,
        ]);
    }

    private function poOrder(Customer $customer, string $date, array $items): PoTransaction
    {
        $total = collect($items)->sum(fn (array $item) => $item[2] * $item[3]);
        $order = JobOrder::query()->create([
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'job_order_number' => 'JO-'.uniqid(),
            'status' => 'completed',
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => 0,
            'balance' => $total,
            'created_at' => $date,
        ]);

        foreach ($items as [$description, $category, $quantity, $price]) {
            $order->items()->create([
                'description' => $description,
                'service_category' => $category,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total' => $quantity * $price,
            ]);
        }

        return PoTransaction::query()->create([
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'job_order_id' => $order->id,
            'company_name' => $customer->name,
            'po_number' => 'PO-'.$order->job_order_number,
            'transaction_date' => $date,
            'amount' => $total,
            'balance' => $total,
            'status' => 'pending',
        ]);
    }

    private function rows(Customer $customer, string $from, string $to): array
    {
        $transactions = PoTransaction::query()
            ->with('jobOrder.items')
            ->where('customer_id', $customer->id)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->get();

        return StatementOfAccountRows::build($transactions, $from, $to);
    }

    private function renderPdf(Customer $customer, string $from, string $to): string
    {
        $html = '';

        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function (string $view, array $data) use (&$html) {
            $html = view($view, $data)->render();
            $pdf = Mockery::mock(DomPdf::class);
            $pdf->shouldReceive('setPaper')->andReturnSelf();
            $pdf->shouldReceive('stream')->andReturn(response('pdf'));

            return $pdf;
        });

        $this->actingAs($this->admin)->get(route('admin.po-transactions.statement-of-account.pdf', [
            'customer_id' => $customer->id,
            'date_from' => $from,
            'date_to' => $to,
        ]))->assertOk();

        return $html;
    }
}
