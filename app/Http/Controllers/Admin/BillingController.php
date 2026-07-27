<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchBillingRecord;
use App\Models\BranchExpense;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(private SubscriptionBillingService $billing)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'billing_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'billing_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'subscription_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['unpaid', 'paid', 'overdue', 'suspended'])],
        ]);

        $trial = SystemTrialSetting::current();
        $branches = Branch::orderBy('name')->get();
        $records = BranchBillingRecord::with(['branch', 'paidBy', 'expense'])
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['billing_year'] ?? null, fn ($query, $year) => $query->where('billing_year', $year))
            ->when($filters['billing_month'] ?? null, fn ($query, $month) => $query->where('billing_month', $month))
            ->when($filters['subscription_date'] ?? null, fn ($query, $date) => $query
                ->whereDate('subscription_start_date', '<=', $date)
                ->whereDate('subscription_end_date', '>=', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('subscription_end_date')
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $activePaidSubscriptions = BranchBillingRecord::query()
            ->where('status', 'paid')
            ->where(function ($query) {
                $query
                    ->where(function ($query) {
                        $query
                            ->whereDate('subscription_start_date', '<=', now()->toDateString())
                            ->whereDate('subscription_end_date', '>=', now()->toDateString());
                    })
                    ->orWhere(function ($query) {
                        $query
                            ->whereNull('subscription_start_date')
                            ->whereNull('subscription_end_date')
                            ->where('billing_month', (int) now()->month)
                            ->where('billing_year', (int) now()->year);
                    });
            })
            ->count();
        $trialStatus = $trial->computedStatus();

        $summary = [
            'paid' => BranchBillingRecord::where('status', 'paid')->sum('amount'),
            'unpaid_count' => BranchBillingRecord::whereIn('status', ['unpaid', 'overdue', 'suspended'])->count(),
            'branches' => $branches->count(),
            'trial_status' => $trialStatus,
            'system_status' => $activePaidSubscriptions > 0 ? 'subscribed' : $trialStatus,
            'system_status_label' => $activePaidSubscriptions > 0 ? 'System: Subscribed' : 'Trial: '.ucfirst($trialStatus),
            'active_paid_subscriptions' => $activePaidSubscriptions,
        ];
        $settings = SystemSetting::current();

        return view('admin.billing.index', compact('trial', 'branches', 'records', 'summary', 'settings', 'filters'));
    }

    public function updateTrial(Request $request)
    {
        $validated = $request->validate([
            'trial_enabled' => ['nullable', 'boolean'],
            'trial_start_date' => ['nullable', 'date'],
            'trial_end_date' => ['nullable', 'date', 'after_or_equal:trial_start_date'],
            'trial_remarks' => ['nullable', 'string'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $trial = SystemTrialSetting::current();
        $trial->fill([
            'trial_enabled' => (bool) ($validated['trial_enabled'] ?? false),
            'trial_start_date' => $validated['trial_start_date'] ?? null,
            'trial_end_date' => $validated['trial_end_date'] ?? null,
            'trial_remarks' => $validated['trial_remarks'] ?? null,
            'grace_period_days' => $validated['grace_period_days'],
            'updated_by' => $request->user()->id,
        ]);
        $trial->trial_status = $trial->computedStatus();
        $trial->save();

        return back()->with('success', 'Trial settings updated successfully.');
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'branches' => ['required', 'array', 'min:1'],
            'branches.*' => ['integer', 'exists:branches,id'],
            'subscription_start_date' => ['required', 'date'],
            'subscription_end_date' => ['nullable', 'date', 'after_or_equal:subscription_start_date'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'update_unpaid' => ['nullable', 'boolean'],
        ]);

        $branchModels = Branch::whereIn('id', $validated['branches'])->get()->keyBy('id');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($validated, $branchModels, $request, &$created, &$updated, &$skipped): void {
            $start = Carbon::parse($validated['subscription_start_date'])->startOfDay();
            $startDate = $start->toDateString();
            // Due date and period end are auto-derived unless explicitly overridden.
            $endDate = ! empty($validated['subscription_end_date'])
                ? Carbon::parse($validated['subscription_end_date'])->toDateString()
                : $this->billing->periodEnd($start)->toDateString();
            $dueDate = ! empty($validated['due_date'])
                ? Carbon::parse($validated['due_date'])->toDateString()
                : $startDate;
            $billingMonth = $start->month;
            $billingYear = $start->year;

            foreach ($validated['branches'] as $branchId) {
                $branch = $branchModels->get($branchId);
                $amount = (float) ($validated['prices'][$branchId]
                    ?? ($branch?->subscription_price ?? 0));

                $record = BranchBillingRecord::where('branch_id', $branchId)
                    ->whereDate('subscription_start_date', $startDate)
                    ->whereDate('subscription_end_date', $endDate)
                    ->first();

                if (! $record) {
                    BranchBillingRecord::create([
                        'branch_id' => $branchId,
                        'billing_year' => $billingYear,
                        'billing_month' => $billingMonth,
                        'subscription_start_date' => $startDate,
                        'subscription_end_date' => $endDate,
                        'amount' => $amount,
                        'due_date' => $dueDate,
                        'status' => 'unpaid',
                        'generated_by' => $request->user()->id,
                    ]);
                    $created++;
                    continue;
                }

                if (($validated['update_unpaid'] ?? false) && $record->status !== 'paid') {
                    $record->update([
                        'billing_year' => $billingYear,
                        'billing_month' => $billingMonth,
                        'amount' => $amount,
                        'due_date' => $dueDate,
                        'generated_by' => $request->user()->id,
                    ]);
                    $updated++;
                    continue;
                }

                $skipped++;
            }
        });

        return back()->with('success', "Billing generated. Created: {$created}. Updated unpaid: {$updated}. Skipped: {$skipped}.");
    }

    public function updateMaintenance(Request $request)
    {
        $validated = $request->validate([
            'maintenance_mode' => ['nullable', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = SystemSetting::current();
        $enabled = (bool) ($validated['maintenance_mode'] ?? false);
        $wasEnabled = $settings->isUnderMaintenance();

        $settings->maintenance_mode = $enabled;
        $settings->maintenance_message = $validated['maintenance_message'] ?? null;

        if ($enabled && ! $wasEnabled) {
            $settings->maintenance_started_at = now();
        } elseif (! $enabled) {
            $settings->maintenance_started_at = null;
        }

        $settings->save();

        return back()->with('success', $enabled
            ? 'Maintenance mode is ON. Only super admins can access the system now.'
            : 'Maintenance mode is OFF. The system is open again.');
    }

    public function updatePrices(Request $request)
    {
        $validated = $request->validate([
            'subscription_prices' => ['required', 'array'],
            'subscription_prices.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $updated = 0;

        foreach ($validated['subscription_prices'] as $branchId => $price) {
            $branch = Branch::find($branchId);

            if (! $branch) {
                continue;
            }

            $branch->subscription_price = ($price === null || $price === '') ? null : (float) $price;
            $branch->save();
            $updated++;
        }

        return back()->with('success', "Subscription prices saved for {$updated} branch".($updated === 1 ? '' : 'es').". Future billing cycles will use these amounts automatically.");
    }

    public function updateStatus(Request $request, BranchBillingRecord $billingRecord)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['unpaid', 'overdue', 'suspended'])],
        ]);

        if ($billingRecord->status === 'paid') {
            return back()->with('error', 'Paid billing records cannot be changed with status controls.');
        }

        $billingRecord->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Billing status updated successfully.');
    }

    public function markPaid(Request $request, BranchBillingRecord $billingRecord)
    {
        $request->merge(['paid_from' => 'store_cash']);

        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:100'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'add_to_expenses' => ['nullable', 'boolean'],
            'paid_from' => ['nullable', Rule::in(['store_cash'])],
        ]);

        DB::transaction(function () use ($billingRecord, $validated, $request): void {
            $billingRecord->loadMissing('branch');

            $expense = $billingRecord->expense;
            $shouldAddExpense = (bool) ($validated['add_to_expenses'] ?? false);

            if ($shouldAddExpense) {
                $expense = BranchExpense::updateOrCreate(
                    [
                        'source' => 'branch_billing',
                        'source_id' => $billingRecord->id,
                    ],
                    [
                        'branch_id' => $billingRecord->branch_id,
                        'category' => 'software_subscription',
                        'expense_type' => 'software_subscription',
                        'title' => 'System Billing - '.$billingRecord->periodLabel(),
                        'amount' => $billingRecord->amount,
                        'expense_date' => $validated['payment_date'],
                        'payment_method' => $validated['payment_method'],
                        'paid_from' => 'store_cash',
                        'reference_no' => $validated['reference_no'] ?? null,
                        'remarks' => $validated['remarks'] ?? null,
                        'created_by' => $request->user()->id,
                    ]
                );

                if ($expense->accounts_payable_id) {
                    $expense->loadMissing('accountsPayable.payments');
                    abort_if($expense->accountsPayable?->payments->isNotEmpty(), 422, 'This payable already has repayments and cannot be changed to store-funded.');
                    $expense->accountsPayable?->delete();
                    $expense->update(['accounts_payable_id' => null]);
                }
            } elseif ($expense && $expense->source === 'branch_billing') {
                $expense->loadMissing('accountsPayable.payments');
                abort_if($expense->accountsPayable?->payments->isNotEmpty(), 422, 'This billing expense has payable repayments and cannot be removed.');
                $expense->accountsPayable?->delete();
                $expense->delete();
                $expense = null;
            }

            $billingRecord->update([
                'status' => 'paid',
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'paid_by' => $request->user()->id,
                'expense_id' => $expense?->id,
            ]);
        });

        return back()->with('success', 'Billing record marked as paid.');
    }

    private function dueDate(int $year, int $month, int $dueDay): Carbon
    {
        $date = Carbon::create($year, $month, 1);

        return $date->day(min($dueDay, $date->daysInMonth));
    }
}
