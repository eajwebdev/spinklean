<?php

namespace Tests\Unit;

use Database\Seeders\HistoricalZReadingSeeder;
use Tests\TestCase;

class HistoricalZReadingSeederTest extends TestCase
{
    public function test_workspace_dump_builds_continuous_readings_with_supplied_final_counters(): void
    {
        $source = base_path('u669236935_spinklean.sql');
        if (! is_file($source)) {
            $this->markTestSkipped('Historical SQL dump is not present.');
        }

        $history = (new HistoricalZReadingSeeder)->buildFromDump($source);

        $this->assertSame('2026-07-24', $history['start_date']);
        $this->assertSame('2026-09-23', $history['end_date']);
        $this->assertCount(62, $history['branches']['B0002']['readings']);
        $this->assertCount(62, $history['branches']['B0001']['readings']);

        $osmena = collect($history['branches']['B0002']['readings'])->last()['machine_counters'];
        $pacana = collect($history['branches']['B0001']['readings'])->last()['machine_counters'];

        $this->assertSame(6394, $osmena[1]['wash']['ending']);
        $this->assertSame(8503, $osmena[1]['dry']['ending']);
        $this->assertSame(310, $osmena[3]['dry']['ending']);
        $this->assertSame(9878, $pacana[1]['wash']['ending']);
        $this->assertSame(381, $pacana[2]['wash']['ending']);
        $this->assertSame(470, $pacana[4]['dry']['ending']);

        foreach ($history['branches'] as $branch) {
            $previous = null;
            foreach ($branch['readings'] as $reading) {
                if ($previous !== null) {
                    foreach ($reading['machine_counters'] as $machine => $types) {
                        foreach (['wash', 'dry'] as $type) {
                            $this->assertSame(
                                $previous[$machine][$type]['ending'],
                                $types[$type]['beginning'],
                                "Machine {$machine} {$type} must continue from the prior day."
                            );
                        }
                    }
                }
                $previous = $reading['machine_counters'];
            }
        }
    }

    public function test_cancelled_job_orders_do_not_affect_payments_or_machine_counters(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'z-reading-fixture-');
        file_put_contents($source, <<<'SQL'
INSERT INTO `branches` (`id`, `name`, `code`) VALUES
(1, 'Pacana', 'B0001'),
(2, 'Osmena', 'B0002');
INSERT INTO `job_orders` (`id`, `branch_id`, `processing_branch_id`, `job_order_number`, `status`, `created_at`, `deleted_at`) VALUES
(1, 2, 2, 'JO-ACTIVE', 'completed', '2026-09-23 08:00:00', NULL),
(2, 2, 2, 'JO-CANCELLED', 'cancelled', '2026-09-23 09:00:00', NULL);
INSERT INTO `payments` (`id`, `branch_id`, `collected_branch_id`, `job_order_id`, `payment_type`, `amount`, `paid_at`) VALUES
(1, 2, 2, 1, 'cash', 100.00, '2026-09-23 08:00:00'),
(2, 2, 2, 2, 'cash', 999.00, '2026-09-23 09:00:00');
INSERT INTO `cycle_records` (`id`, `job_order_id`, `cycle_type`, `machine_number`, `started_at`) VALUES
(1, 1, 'wash', 1, '2026-09-23 08:00:00'),
(2, 2, 'wash', 1, '2026-09-23 09:00:00');
SQL);

        try {
            $history = (new HistoricalZReadingSeeder)->buildFromDump($source);
        } finally {
            @unlink($source);
        }

        $reading = $history['branches']['B0002']['readings'][0];
        $this->assertSame(1, $reading['transaction_count']);
        $this->assertSame(100.0, $reading['expected_cash_amount']);
        $this->assertSame(1, $reading['machine_counters'][1]['wash']['total']);
        $this->assertSame(6394, $reading['machine_counters'][1]['wash']['ending']);
    }
}
