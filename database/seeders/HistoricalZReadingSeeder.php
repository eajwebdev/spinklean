<?php

namespace Database\Seeders;

use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use SplFileObject;

class HistoricalZReadingSeeder extends Seeder
{
    private const SOURCE_FILE = 'u669236935_spinklean.sql';

    private const COUNTER_MODULUS = 10000;

    /**
     * Physical machine readings supplied on September 23, 2026.
     * Values with leading zeroes are stored as integers and rendered as
     * four-digit counters by the Z Reading views.
     */
    private const FINAL_COUNTERS = [
        'B0002' => [ // Osmeña
            1 => ['wash' => 6394, 'dry' => 8503],
            2 => ['wash' => 6388, 'dry' => 8093],
            3 => ['wash' => 6997, 'dry' => 310],
            4 => ['wash' => 6337, 'dry' => 9799],
            5 => ['wash' => 4101, 'dry' => 6931],
        ],
        'B0001' => [ // Pacana
            1 => ['wash' => 9878, 'dry' => 5688],
            2 => ['wash' => 381, 'dry' => 6198],
            3 => ['wash' => 137, 'dry' => 6812],
            4 => ['wash' => 6017, 'dry' => 470],
        ],
    ];

    private const TABLES = [
        'branches',
        'job_orders',
        'payments',
        'cycle_records',
        'branch_expenses',
        'money_movements',
        'accounts_payables',
        'accounts_payable_payments',
    ];

    public function run(): void
    {
        if (! Schema::hasTable('z_readings')) {
            $this->command?->warn('The z_readings table does not exist. Run migrations first.');

            return;
        }

        $sourcePath = base_path(self::SOURCE_FILE);
        $history = $this->buildFromDump($sourcePath);
        $written = 0;

        DB::transaction(function () use ($history, &$written): void {
            foreach ($history['branches'] as $branchHistory) {
                $sourceBranch = $branchHistory['branch'];
                $branch = Branch::query()->where('code', $sourceBranch['code'])->first();

                if (! $branch) {
                    throw new RuntimeException("Branch {$sourceBranch['code']} ({$sourceBranch['name']}) is missing from the migrated database.");
                }

                foreach ($branchHistory['readings'] as $reading) {
                    $now = now();

                    DB::table('z_readings')->updateOrInsert(
                        [
                            'branch_id' => $branch->id,
                            'business_date' => $reading['business_date'],
                        ],
                        [
                            'prepared_by' => null,
                            'reading_number' => 'ZR-'.$branch->code.'-'.str_replace('-', '', $reading['business_date']).'-HIST',
                            'cash_count' => json_encode($reading['cash_count'], JSON_THROW_ON_ERROR),
                            'payment_breakdown' => json_encode($reading['payment_breakdown'], JSON_THROW_ON_ERROR),
                            'expense_breakdown' => json_encode($reading['expense_breakdown'], JSON_THROW_ON_ERROR),
                            'machine_counters' => json_encode($reading['machine_counters'], JSON_THROW_ON_ERROR),
                            'expected_cash_amount' => $reading['expected_cash_amount'],
                            'cash_expense_amount' => $reading['cash_expense_amount'],
                            'expected_cash_drawer_amount' => $reading['expected_cash_drawer_amount'],
                            'actual_cash_amount' => $reading['expected_cash_drawer_amount'],
                            'expected_gcash_amount' => $reading['expected_gcash_amount'],
                            'actual_gcash_amount' => $reading['expected_gcash_amount'],
                            'expected_bank_amount' => $reading['expected_bank_amount'],
                            'actual_bank_amount' => $reading['expected_bank_amount'],
                            'expected_total_amount' => $reading['expected_total_amount'],
                            'actual_total_amount' => $reading['expected_total_amount'],
                            'over_short_amount' => 0,
                            'transaction_count' => $reading['transaction_count'],
                            'first_job_order_number' => $reading['first_job_order_number'],
                            'last_job_order_number' => $reading['last_job_order_number'],
                            'signature_name' => 'Historical SQL Import',
                            'remarks' => 'Generated from '.self::SOURCE_FILE.'. Canceled and deleted job orders are excluded. Machine counters are anchored to the supplied physical readings.',
                            'closed_at' => $reading['business_date'].' 23:59:59',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );

                    $written++;
                }
            }
        });

        $this->command?->info("Seeded {$written} day-by-day Z readings from ".self::SOURCE_FILE.'.');

        foreach ($history['branches'] as $branchHistory) {
            $this->command?->line(sprintf(
                '%s: %s to %s (%d readings)',
                $branchHistory['branch']['name'],
                $branchHistory['start_date'],
                $branchHistory['end_date'],
                count($branchHistory['readings'])
            ));
        }
    }

    /**
     * Parse the SQL dump and calculate the complete historical Z-reading data.
     * This method intentionally does not read operational data from the database.
     */
    public function buildFromDump(string $sourcePath): array
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException("SQL source file is missing or unreadable: {$sourcePath}");
        }

        $tables = $this->parseDump($sourcePath);
        $branches = collect($tables['branches'] ?? [])->keyBy(fn (array $row) => (int) $row['id']);
        $orders = collect($tables['job_orders'] ?? [])->keyBy(fn (array $row) => (int) $row['id']);
        $activeOrders = $orders->filter(fn (array $order) => $order['deleted_at'] === null && $order['status'] !== 'cancelled');
        $activeOrderIds = $activeOrders->keys()->flip();

        $payments = collect($tables['payments'] ?? [])->filter(fn (array $payment) => $payment['job_order_id'] !== null
            && $payment['paid_at'] !== null
            && $activeOrderIds->has((int) $payment['job_order_id'])
        );
        $cycles = collect($tables['cycle_records'] ?? [])->filter(fn (array $cycle) => in_array($cycle['cycle_type'], ['wash', 'dry'], true)
            && $cycle['machine_number'] !== null
            && $cycle['started_at'] !== null
            && $activeOrderIds->has((int) $cycle['job_order_id'])
        );

        $dates = $activeOrders->pluck('created_at')
            ->merge($payments->pluck('paid_at'))
            ->merge($cycles->pluck('started_at'))
            ->filter()
            ->map(fn ($value) => substr((string) $value, 0, 10));

        if ($dates->isEmpty()) {
            throw new RuntimeException('The SQL dump has no valid job-order, payment, or cycle dates.');
        }

        $startDate = (string) $dates->min();
        $endDate = (string) $dates->max();
        $expenses = collect($tables['branch_expenses'] ?? []);
        $movements = collect($tables['money_movements'] ?? []);
        $payables = collect($tables['accounts_payables'] ?? []);
        $payablePayments = collect($tables['accounts_payable_payments'] ?? []);
        $result = [];

        foreach (self::FINAL_COUNTERS as $branchCode => $finalCounters) {
            $branch = $branches->first(fn (array $row) => $row['code'] === $branchCode);

            if (! $branch) {
                throw new RuntimeException("Branch {$branchCode} is not present in ".self::SOURCE_FILE.'.');
            }

            $sourceBranchId = (int) $branch['id'];
            $cycleCounts = $this->cycleCounts($cycles, $activeOrders, $sourceBranchId);
            $runningCounters = $this->initialCounters($finalCounters, $cycleCounts);
            $readings = [];

            for ($date = Carbon::parse($startDate); $date->lte(Carbon::parse($endDate)); $date->addDay()) {
                $businessDate = $date->toDateString();
                $dailyOrders = $activeOrders
                    ->filter(fn (array $order) => (int) $order['branch_id'] === $sourceBranchId && substr((string) $order['created_at'], 0, 10) === $businessDate)
                    ->sortBy(fn (array $order) => $order['created_at'].'-'.str_pad((string) $order['id'], 12, '0', STR_PAD_LEFT))
                    ->values();
                $dailyPayments = $payments->filter(fn (array $payment) => (int) ($payment['collected_branch_id'] ?? $payment['branch_id']) === $sourceBranchId
                    && substr((string) $payment['paid_at'], 0, 10) === $businessDate
                );
                $dailyCycleCounts = $cycleCounts[$businessDate] ?? [];
                $machineCounters = [];

                foreach ($finalCounters as $machine => $types) {
                    foreach (['wash', 'dry'] as $type) {
                        $beginning = $runningCounters[$machine][$type];
                        $total = (int) ($dailyCycleCounts[$machine][$type] ?? 0);
                        $ending = ($beginning + $total) % self::COUNTER_MODULUS;
                        $machineCounters[$machine][$type] = compact('beginning', 'ending', 'total');
                        $runningCounters[$machine][$type] = $ending;
                    }
                }

                $finance = $this->dailyFinance(
                    $businessDate,
                    $sourceBranchId,
                    $dailyPayments,
                    $orders,
                    $expenses,
                    $movements,
                    $payables,
                    $payablePayments
                );

                $readings[] = [
                    'business_date' => $businessDate,
                    'cash_count' => $this->cashCountForAmount($finance['expected_cash_drawer'], $branchCode, $businessDate),
                    'payment_breakdown' => $finance['payment_breakdown'],
                    'expense_breakdown' => $finance['expense_breakdown'],
                    'machine_counters' => $machineCounters,
                    'expected_cash_amount' => $finance['cash_collections'],
                    'cash_expense_amount' => $finance['store_cash_expenses'],
                    'expected_cash_drawer_amount' => $finance['expected_cash_drawer'],
                    'expected_gcash_amount' => $finance['expected_gcash'],
                    'expected_bank_amount' => $finance['expected_bank'],
                    'expected_total_amount' => $finance['expected_total'],
                    'transaction_count' => $dailyOrders->count(),
                    'first_job_order_number' => $dailyOrders->first()['job_order_number'] ?? null,
                    'last_job_order_number' => $dailyOrders->last()['job_order_number'] ?? null,
                ];
            }

            $lastCounters = collect($readings)->last()['machine_counters'];
            foreach ($finalCounters as $machine => $types) {
                foreach ($types as $type => $expected) {
                    $actual = (int) $lastCounters[$machine][$type]['ending'];
                    if ($actual !== $expected) {
                        throw new RuntimeException("{$branchCode} machine {$machine} {$type} ends at {$actual}; expected {$expected}.");
                    }
                }
            }

            $result[$branchCode] = [
                'branch' => $branch,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'initial_counters' => $this->initialCounters($finalCounters, $cycleCounts),
                'final_counters' => $finalCounters,
                'readings' => $readings,
            ];
        }

        return [
            'source_file' => $sourcePath,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'branches' => $result,
        ];
    }

    private function cycleCounts($cycles, $orders, int $branchId): array
    {
        $counts = [];

        foreach ($cycles as $cycle) {
            $order = $orders->get((int) $cycle['job_order_id']);
            $processingBranchId = (int) ($order['processing_branch_id'] ?? $order['branch_id']);
            $machine = (int) $cycle['machine_number'];

            if ($processingBranchId !== $branchId || $machine < 1) {
                continue;
            }

            $date = substr((string) $cycle['started_at'], 0, 10);
            $type = $cycle['cycle_type'];
            $counts[$date][$machine][$type] = ($counts[$date][$machine][$type] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    private function initialCounters(array $finalCounters, array $cycleCounts): array
    {
        $totals = [];
        foreach ($cycleCounts as $dailyCounts) {
            foreach ($dailyCounts as $machine => $types) {
                foreach ($types as $type => $count) {
                    $totals[$machine][$type] = ($totals[$machine][$type] ?? 0) + $count;
                }
            }
        }

        $initial = [];
        foreach ($finalCounters as $machine => $types) {
            foreach ($types as $type => $ending) {
                $total = (int) ($totals[$machine][$type] ?? 0);
                $initial[$machine][$type] = (($ending - $total) % self::COUNTER_MODULUS + self::COUNTER_MODULUS) % self::COUNTER_MODULUS;
            }
        }

        return $initial;
    }

    private function dailyFinance(string $date, int $branchId, $payments, $orders, $expenses, $movements, $payables, $payablePayments): array
    {
        $amounts = $payments->groupBy('payment_type')->map(fn ($rows) => round((float) $rows->sum('amount'), 2))->all();
        $counts = $payments->groupBy('payment_type')->map(fn ($rows) => $rows->count())->all();
        $current = [];
        $previous = [];

        foreach ($payments as $payment) {
            $order = $orders->get((int) $payment['job_order_id']);
            $bucket = substr((string) ($order['created_at'] ?? ''), 0, 10) === $date ? 'current' : 'previous';
            $type = $payment['payment_type'];
            ${$bucket}[$type] = round((${$bucket}[$type] ?? 0) + (float) $payment['amount'], 2);
        }

        $dailyExpenses = $expenses->filter(fn (array $expense) => (int) $expense['branch_id'] === $branchId && substr((string) $expense['expense_date'], 0, 10) === $date
        );
        $expenseFor = fn (string $method, ?string $paidFrom = 'store_cash') => round((float) $dailyExpenses
            ->filter(fn (array $expense) => ($paidFrom === null || $expense['paid_from'] === $paidFrom)
                && strtolower((string) ($expense['payment_method'] ?: 'cash')) === $method
            )->sum('amount'), 2);
        $storeCash = $expenseFor('cash');
        $storeGcash = $expenseFor('gcash');
        $storeBank = $expenseFor('bank');
        $ownerExpenses = round((float) $dailyExpenses->where('paid_from', 'owner')->sum('amount'), 2);
        $dailyMovements = $movements->filter(fn (array $movement) => (int) $movement['branch_id'] === $branchId && substr((string) $movement['movement_date'], 0, 10) === $date
        );
        $cashIn = round((float) $dailyMovements->where('direction', 'in')->sum('amount'), 2);
        $cashOut = round((float) $dailyMovements->where('direction', 'out')->sum('amount'), 2);
        $funding = ['gcash' => 0.0, 'bank' => 0.0];
        foreach ($payables as $payable) {
            $method = strtolower((string) $payable['funding_method']);
            if ((int) $payable['branch_id'] === $branchId
                && $payable['source_type'] === 'owner_funding'
                && isset($funding[$method])
                && substr((string) $payable['funded_at'], 0, 10) === $date) {
                $funding[$method] += (float) $payable['original_amount'];
            }
        }
        $repayments = ['gcash' => 0.0, 'bank' => 0.0];
        foreach ($payablePayments as $payment) {
            $method = strtolower((string) $payment['payment_method']);
            if ((int) $payment['branch_id'] === $branchId
                && isset($repayments[$method])
                && substr((string) $payment['payment_date'], 0, 10) === $date) {
                $repayments[$method] += (float) $payment['amount'];
            }
        }

        $cash = round((float) ($amounts['cash'] ?? 0), 2);
        $gcash = round((float) ($amounts['gcash'] ?? 0), 2);
        $bank = round((float) ($amounts['bank'] ?? 0), 2);
        $expectedCashDrawer = round($cash + $cashIn - $storeCash - $cashOut, 2);
        $expectedGcash = round($gcash + $funding['gcash'] - $repayments['gcash'] - $storeGcash, 2);
        $expectedBank = round($bank + $funding['bank'] - $repayments['bank'] - $storeBank, 2);

        return [
            'cash_collections' => $cash,
            'store_cash_expenses' => $storeCash,
            'expected_cash_drawer' => $expectedCashDrawer,
            'expected_gcash' => $expectedGcash,
            'expected_bank' => $expectedBank,
            'expected_total' => round($expectedCashDrawer + $expectedGcash + $expectedBank, 2),
            'payment_breakdown' => [
                'amounts' => $amounts,
                'counts' => $counts,
                'current_sales' => $current,
                'previous_payments' => $previous,
                'previous_payment_items' => [],
                'unpaid_amount' => 0,
                'po_amount' => round((float) ($amounts['po'] ?? 0), 2),
                'monthly_billing_amount' => round((float) ($amounts['monthly_billing'] ?? 0), 2),
            ],
            'expense_breakdown' => [
                'store_cash' => $storeCash,
                'store_gcash' => $storeGcash,
                'store_bank' => $storeBank,
                'owner' => $ownerExpenses,
                'items' => [],
                'accounts_payable' => [
                    'gcash_funding' => round($funding['gcash'], 2),
                    'gcash_repayments' => round($repayments['gcash'], 2),
                    'bank_funding' => round($funding['bank'], 2),
                    'bank_repayments' => round($repayments['bank'], 2),
                ],
                'money_movements' => [
                    'cash_in' => $cashIn,
                    'cash_out' => $cashOut,
                    'items' => [],
                ],
            ],
        ];
    }

    private function parseDump(string $sourcePath): array
    {
        $tables = array_fill_keys(self::TABLES, []);
        $file = new SplFileObject($sourcePath, 'r');
        $activeTable = null;
        $columns = [];

        while (! $file->eof()) {
            $line = trim((string) $file->fgets());

            if (preg_match('/^INSERT INTO `([^`]+)` \((.+)\) VALUES$/', $line, $matches)) {
                $activeTable = in_array($matches[1], self::TABLES, true) ? $matches[1] : null;
                preg_match_all('/`([^`]+)`/', $matches[2], $columnMatches);
                $columns = $columnMatches[1];

                continue;
            }

            if ($activeTable === null || ! str_starts_with($line, '(')) {
                continue;
            }

            $values = $this->parseSqlTuple($line);
            if (count($values) !== count($columns)) {
                throw new RuntimeException("Could not parse {$activeTable} row near SQL line ".($file->key() + 1).'.');
            }

            $tables[$activeTable][] = array_combine($columns, $values);

            if (str_ends_with($line, ';')) {
                $activeTable = null;
                $columns = [];
            }
        }

        return $tables;
    }

    private function parseSqlTuple(string $line): array
    {
        $line = rtrim($line, ",;\r\n");
        if (! str_starts_with($line, '(') || ! str_ends_with($line, ')')) {
            throw new RuntimeException('Invalid SQL tuple encountered.');
        }

        $content = substr($line, 1, -1);
        $length = strlen($content);
        $values = [];
        $index = 0;

        while ($index < $length) {
            while ($index < $length && ctype_space($content[$index])) {
                $index++;
            }

            if ($index < $length && $content[$index] === "'") {
                $index++;
                $value = '';

                while ($index < $length) {
                    $character = $content[$index++];
                    if ($character === '\\' && $index < $length) {
                        $escaped = $content[$index++];
                        $value .= match ($escaped) {
                            '0' => "\0",
                            'n' => "\n",
                            'r' => "\r",
                            't' => "\t",
                            'Z' => chr(26),
                            default => $escaped,
                        };

                        continue;
                    }

                    if ($character === "'") {
                        if ($index < $length && $content[$index] === "'") {
                            $value .= "'";
                            $index++;

                            continue;
                        }

                        break;
                    }

                    $value .= $character;
                }

                $values[] = $value;
            } else {
                $start = $index;
                while ($index < $length && $content[$index] !== ',') {
                    $index++;
                }
                $raw = trim(substr($content, $start, $index - $start));
                $values[] = match (true) {
                    strcasecmp($raw, 'NULL') === 0 => null,
                    is_numeric($raw) => str_contains($raw, '.') ? (float) $raw : (int) $raw,
                    default => $raw,
                };
            }

            while ($index < $length && $content[$index] !== ',') {
                $index++;
            }
            if ($index < $length && $content[$index] === ',') {
                $index++;
            }
        }

        return $values;
    }

    private function cashCountForAmount(float $amount, string $branchCode, string $businessDate): array
    {
        $denominations = [
            '1000' => 100000,
            '500' => 50000,
            '200' => 20000,
            '100' => 10000,
            '50' => 5000,
            '20' => 2000,
            '10' => 1000,
            '5' => 500,
            '1' => 100,
            '0.25' => 25,
        ];

        $remainingCents = (int) round($amount * 100);
        if ($remainingCents < 0 || $remainingCents % 25 !== 0) {
            throw new RuntimeException(sprintf(
                '%s on %s has an expected cash drawer of %.2f, which cannot be represented by the available cash denominations.',
                $branchCode,
                $businessDate,
                $amount
            ));
        }

        $cashCount = [];
        foreach ($denominations as $label => $denominationCents) {
            $cashCount[$label] = intdiv($remainingCents, $denominationCents);
            $remainingCents %= $denominationCents;
        }

        return $cashCount;
    }
}
