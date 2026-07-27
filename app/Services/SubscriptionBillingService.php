<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchBillingRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionBillingService
{
    public function cycleMonths(): int
    {
        return max(1, (int) config('billing.cycle_months', 1));
    }

    public function notifyDaysBefore(): int
    {
        return max(0, (int) config('billing.notify_days_before', 3));
    }

    public function lockGraceDays(): int
    {
        return max(0, (int) config('billing.lock_grace_days', 2));
    }

    /**
     * End date (inclusive) of a cycle that starts on the given date.
     */
    public function periodEnd(Carbon $start): Carbon
    {
        return $start->copy()->startOfDay()->addMonthsNoOverflow($this->cycleMonths())->subDay();
    }

    /**
     * Resolve the subscription price for a branch: the fixed per-branch price,
     * falling back to the most recent record amount when not yet configured.
     */
    public function priceFor(Branch $branch): ?float
    {
        if ($branch->subscription_price !== null && (float) $branch->subscription_price > 0) {
            return (float) $branch->subscription_price;
        }

        $last = $branch->billingRecords()
            ->orderByDesc('subscription_end_date')
            ->orderByDesc('id')
            ->first();

        return $last ? (float) $last->amount : null;
    }

    /**
     * Ensure the branch has at least one billing record. Creates an initial
     * cycle starting today when a price is known and none exist yet.
     */
    public function ensureActiveRecord(Branch $branch): ?BranchBillingRecord
    {
        if ($branch->billingRecords()->exists()) {
            return null;
        }

        $price = $this->priceFor($branch);

        if ($price === null) {
            return null;
        }

        return $this->createRecord($branch, now()->startOfDay(), $price);
    }

    /**
     * Create a billing record for a cycle beginning on $start. The due date is
     * derived automatically as the first day of the cycle.
     */
    public function createRecord(Branch $branch, Carbon $start, float $amount, ?int $generatedBy = null): BranchBillingRecord
    {
        $start = $start->copy()->startOfDay();
        $end = $this->periodEnd($start);

        return BranchBillingRecord::create([
            'branch_id' => $branch->id,
            'billing_month' => $start->month,
            'billing_year' => $start->year,
            'subscription_start_date' => $start->toDateString(),
            'subscription_end_date' => $end->toDateString(),
            'amount' => $amount,
            'due_date' => $start->toDateString(),
            'status' => 'unpaid',
            'generated_by' => $generatedBy,
        ]);
    }

    /**
     * The record a branch should pay next: the oldest open record, or a freshly
     * generated upcoming cycle when everything is paid (advance payment).
     */
    public function payableRecord(Branch $branch): ?BranchBillingRecord
    {
        $this->ensureActiveRecord($branch);

        $open = $branch->billingRecords()
            ->whereIn('status', ['unpaid', 'overdue', 'suspended'])
            ->orderBy('subscription_start_date')
            ->orderBy('id')
            ->first();

        if ($open) {
            return $open;
        }

        return $this->createNextAfterLatest($branch);
    }

    /**
     * The paid record whose subscription window covers the given date.
     */
    public function activePaidRecord(Branch $branch, ?Carbon $date = null): ?BranchBillingRecord
    {
        $date ??= now();

        return $branch->billingRecords()
            ->where('status', 'paid')
            ->whereDate('subscription_start_date', '<=', $date->toDateString())
            ->whereDate('subscription_end_date', '>=', $date->toDateString())
            ->orderByDesc('subscription_end_date')
            ->first();
    }

    /**
     * Create the cycle that follows the branch's latest coverage (for advance pay).
     */
    public function createNextAfterLatest(Branch $branch): ?BranchBillingRecord
    {
        $price = $this->priceFor($branch);

        if ($price === null) {
            return null;
        }

        $latest = $branch->billingRecords()
            ->orderByDesc('subscription_end_date')
            ->orderByDesc('id')
            ->first();

        $start = $latest && $latest->subscription_end_date
            ? $latest->subscription_end_date->copy()->addDay()
            : now()->startOfDay();

        $existing = $branch->billingRecords()
            ->whereDate('subscription_start_date', $start->toDateString())
            ->first();

        return $existing ?: $this->createRecord($branch, $start, $price);
    }

    /**
     * Mark a record as paid and auto-generate the following cycle. Idempotent:
     * a record already paid is returned untouched and never double-renewed.
     */
    public function markPaidAndRenew(BranchBillingRecord $record, array $data = []): BranchBillingRecord
    {
        return DB::transaction(function () use ($record, $data) {
            $record->refresh();

            if ($record->status === 'paid') {
                return $record;
            }

            $record->update([
                'status' => 'paid',
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'] ?? 'PayMongo',
                'reference_no' => $data['reference_no'] ?? $record->reference_no,
                'paymongo_payment_id' => $data['paymongo_payment_id'] ?? $record->paymongo_payment_id,
                'remarks' => $data['remarks'] ?? $record->remarks,
                'paid_by' => $data['paid_by'] ?? null,
            ]);

            $branch = $record->branch()->first();

            if ($branch && $record->subscription_end_date) {
                $price = $this->priceFor($branch);

                if ($price !== null) {
                    $nextStart = $record->subscription_end_date->copy()->addDay();

                    $exists = $branch->billingRecords()
                        ->whereDate('subscription_start_date', $nextStart->toDateString())
                        ->exists();

                    if (! $exists) {
                        $this->createRecord($branch, $nextStart, $price, $data['paid_by'] ?? null);
                    }
                }
            }

            return $record;
        });
    }
}
