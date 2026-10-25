<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ReportController;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReportSectionPdfTest extends TestCase
{
    use RefreshDatabase;

    private const SECTION_HEADINGS = [
        'sales' => ['Sales by Date', 'Sales by Branch', 'Physical Collections by Branch', 'Cross-Branch Collections for Remittance'],
        'z_reading' => ['Consolidated Z Reading', 'Daily Operations by Date'],
        'operations' => ['Operational Summary'],
        'receivables' => ['Receivables'],
        'inventory' => ['Inventory Usage'],
        'payments' => ['Sales Payment Type', 'GCash Reference Breakdown'],
        'expenses' => ['Expenses'],
        'payables' => ['Accounts Payable', 'Accounts Payable Repayments'],
        'cash' => ['Cash Drawer Movements'],
        'ledger' => ['Customer Ledger'],
        'activity' => ['Activity Logs'],
    ];

    private User $admin;

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
            'is_completed' => true,
        ]);

        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    public function test_every_report_tab_has_a_pdf_section(): void
    {
        $this->assertSame(array_keys(self::SECTION_HEADINGS), array_keys(ReportController::SECTIONS));
    }

    public function test_section_pdf_contains_only_that_tabs_sections(): void
    {
        $allHeadings = collect(self::SECTION_HEADINGS)->flatten()->push('Financial Reconciliation');

        foreach (self::SECTION_HEADINGS as $section => $headings) {
            $html = $this->renderReportPdf(['section' => $section]);

            $this->assertStringContainsString(ReportController::SECTIONS[$section].' Report', $html, $section);

            foreach ($allHeadings as $heading) {
                in_array($heading, $headings, true)
                    ? $this->assertStringContainsString("<h2>{$heading}</h2>", $html, "{$section} should include {$heading}")
                    : $this->assertStringNotContainsString("<h2>{$heading}</h2>", $html, "{$section} should not include {$heading}");
            }
        }
    }

    public function test_full_pdf_and_unknown_section_include_every_section(): void
    {
        foreach ([[], ['section' => 'not-a-tab']] as $query) {
            $html = $this->renderReportPdf($query);

            foreach (collect(self::SECTION_HEADINGS)->flatten()->push('Financial Reconciliation') as $heading) {
                $this->assertStringContainsString("<h2>{$heading}</h2>", $html);
            }
        }
    }

    public function test_section_pdf_is_a_real_pdf_named_after_the_tab(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.pdf', ['date_range' => '2026-05-01 to 2026-05-14', 'section' => 'z_reading']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=report-z-reading-2026-05-01-to-2026-05-14.pdf');
    }

    public function test_reports_page_keeps_the_selected_tab(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['tab' => 'payments']))
            ->assertOk()
            ->assertSee("tab: 'payments'", false)
            ->assertSee('Only the tab you are viewing');

        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['tab' => 'bogus']))
            ->assertOk()
            ->assertSee("tab: 'sales'", false);
    }

    private function renderReportPdf(array $query): string
    {
        $html = '';

        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function (string $view, array $data) use (&$html) {
            $html = view($view, $data)->render();

            $pdf = Mockery::mock(DomPdf::class);
            $pdf->shouldReceive('setPaper')->andReturnSelf();
            $pdf->shouldReceive('stream')->andReturn(response('pdf'));

            return $pdf;
        });

        $this->actingAs($this->admin)
            ->get(route('admin.reports.pdf', ['date_range' => '2026-05-01 to 2026-05-14', ...$query]))
            ->assertOk();

        return $html;
    }
}
