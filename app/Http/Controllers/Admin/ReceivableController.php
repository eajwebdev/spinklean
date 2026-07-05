<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CustomerLedger;
use App\Models\JobOrder;
use App\Models\Payment;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReceivableController extends Controller
{
    private const BILLING_TYPES = ['regular', 'monthly_billing'];
    private const UI_BILLING_TYPES = ['regular'];
    private const STATUSES = ['pending', 'washing', 'drying', 'folding', 'ready_for_pickup', 'ready_for_delivery', 'completed'];

    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $this->canChooseBranch($user);

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $baseQuery = JobOrder::query()
            ->with(['branch', 'currentBranch', 'releaseBranch', 'customer'])
            ->where('balance', '>', 0)
            ->regularReceivable()
            ->when(! $canChooseBranch, fn ($query) => $query->where(fn ($query) => $query
                ->where('branch_id', $user->branch_id)
                ->orWhere('current_branch_id', $user->branch_id)
                ->orWhere('release_branch_id', $user->branch_id)))
            ->when($request->filled('branch_id') && $canChooseBranch, fn ($query) => $query->where(fn ($query) => $query
                ->where('branch_id', $request->branch_id)
                ->orWhere('current_branch_id', $request->branch_id)
                ->orWhere('release_branch_id', $request->branch_id)))
            ->when(in_array($request->billing_type, self::UI_BILLING_TYPES, true), fn ($query) => $query->whereHas('customer', fn ($query) => $query->where('billing_type', $request->billing_type)))
            ->when(in_array($request->status, self::STATUSES, true), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($query) use ($search) {
                    $query->where('job_order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            });

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(balance), 0) as total_balance')
            ->first();

        $receivables = $baseQuery
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.receivables.index', [
            'branches' => $branches,
            'receivables' => $receivables,
            'summary' => $summary,
            'canChooseBranch' => $canChooseBranch,
            'billingTypes' => self::UI_BILLING_TYPES,
            'statuses' => self::STATUSES,
        ]);
    }

    public function storePayment(Request $request, JobOrder $jobOrder)
    {
        $this->authorizeJobOrder($request, $jobOrder);

        if ((float) $jobOrder->balance <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'This job order has no remaining balance.',
            ]);
        }

        $jobOrder->loadMissing(['customer', 'poTransaction']);
        if ($jobOrder->poTransaction || $jobOrder->customer?->billing_type === 'po') {
            throw ValidationException::withMessages([
                'amount' => 'PO transactions are handled in the PO Transactions module.',
            ]);
        }

        $validated = $request->validate([
            'payment_type' => ['required', Rule::in(['cash', 'gcash', 'bank', 'po', 'monthly_billing'])],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$jobOrder->balance],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated, $jobOrder, $request) {
            $amount = (float) $validated['amount'];
            $collectedBranchId = $this->collectedBranchId($request, $jobOrder);

            $payment = Payment::create([
                'branch_id' => $jobOrder->branch_id,
                'collected_branch_id' => $collectedBranchId,
                'job_order_id' => $jobOrder->id,
                'customer_id' => $jobOrder->customer_id,
                'received_by' => $request->user()->id,
                'payment_number' => $this->nextPaymentNumber(),
                'payment_type' => $validated['payment_type'],
                'reference_no' => $validated['reference_no'] ?? null,
                'amount' => $amount,
                'remarks' => $validated['remarks'] ?? null,
                'settlement_status' => $collectedBranchId === (int) $jobOrder->branch_id ? 'local' : 'pending',
                'paid_at' => now(),
            ]);

            $jobOrder->update([
                'paid_amount' => (float) $jobOrder->paid_amount + $amount,
                'balance' => max((float) $jobOrder->balance - $amount, 0),
            ]);

            $running = (float) CustomerLedger::where('customer_id', $jobOrder->customer_id)
                ->latest()
                ->value('running_balance');

            CustomerLedger::create([
                'branch_id' => $jobOrder->branch_id,
                'customer_id' => $jobOrder->customer_id,
                'job_order_id' => $jobOrder->id,
                'payment_id' => $payment->id,
                'entry_type' => 'credit',
                'amount' => $amount,
                'running_balance' => max($running - $amount, 0),
                'description' => "Receivable payment {$payment->payment_number}",
            ]);

            Activity::log($request, 'receivable_payment_recorded', $payment, [
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'job_order_number' => $jobOrder->job_order_number,
            ], $payment->branch_id);
        });

        return back()->with('success', 'Payment recorded successfully.');
    }

    private function authorizeJobOrder(Request $request, JobOrder $jobOrder): void
    {
        $user = $request->user();

        if ($this->canChooseBranch($user)) {
            return;
        }

        abort_unless(in_array((int) $user->branch_id, [
            (int) $jobOrder->branch_id,
            (int) ($jobOrder->current_branch_id ?: $jobOrder->branch_id),
            (int) ($jobOrder->release_branch_id ?: $jobOrder->branch_id),
        ], true), 403);
    }

    private function collectedBranchId(Request $request, JobOrder $jobOrder): int
    {
        return (int) ($request->user()->branch_id ?: ($jobOrder->release_branch_id ?: $jobOrder->branch_id));
    }

    private function canChooseBranch($user): bool
    {
        return $user->isSuperAdmin() || $user->role === 'admin';
    }

    private function nextPaymentNumber(): string
    {
        return 'PAY-'.now()->format('Ymd').'-'.str_pad((string) (Payment::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
