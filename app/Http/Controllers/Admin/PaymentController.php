<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CustomerLedger;
use App\Models\Payment;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    private const PAYMENT_TYPES = ['cash', 'gcash', 'bank', 'unpaid', 'po', 'monthly_billing'];

    private const UI_PAYMENT_TYPES = ['cash', 'gcash', 'unpaid', 'po'];

    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $this->canChooseBranch($user);
        [$dateFrom, $dateTo] = $this->dateRange($request);

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();
        $selectedBranchId = $canChooseBranch ? ($request->integer('branch_id') ?: null) : $user->branch_id;

        $baseQuery = Payment::query()
            ->with(['branch', 'collectedBranch', 'customer', 'jobOrder', 'receiver'])
            ->whereIn('payment_type', self::UI_PAYMENT_TYPES)
            ->when(! $canChooseBranch, fn ($query) => $query->where(fn ($query) => $query
                ->where('branch_id', $user->branch_id)
                ->orWhere('collected_branch_id', $user->branch_id)))
            ->when($request->filled('branch_id') && $canChooseBranch, fn ($query) => $query->where(fn ($query) => $query
                ->where('branch_id', $request->branch_id)
                ->orWhere('collected_branch_id', $request->branch_id)))
            ->when(in_array($request->payment_type, self::PAYMENT_TYPES, true), fn ($query) => $query->where('payment_type', $request->payment_type))
            ->when($dateFrom, fn ($query) => $query->whereDate('paid_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('paid_at', '<=', $dateTo))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($query) use ($search) {
                    $query->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhereHas('jobOrder', fn ($query) => $query->where('job_order_number', 'like', "%{$search}%"))
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('receiver', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            });

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as payments_count, COALESCE(SUM(amount), 0) as total_amount')
            ->first();

        $todayTotal = (clone $baseQuery)
            ->whereDate('paid_at', today())
            ->sum('amount');

        $salesOwnerTotal = $this->filteredPaymentQuery($request, $selectedBranchId, 'branch_id')->sum('amount');
        $physicalCollectionTotal = $this->filteredPaymentQuery($request, $selectedBranchId, 'collected_branch_id')
            ->whereIn('payment_type', ['cash', 'gcash', 'bank'])
            ->sum('amount');
        $todayCollectionTotal = $this->filteredPaymentQuery($request, $selectedBranchId, 'collected_branch_id')
            ->whereIn('payment_type', ['cash', 'gcash', 'bank'])
            ->whereDate('paid_at', today())
            ->sum('amount');

        $paymentsByType = (clone $baseQuery)
            ->selectRaw('payment_type, COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as payments_count')
            ->groupBy('payment_type')
            ->orderByDesc('total_amount')
            ->get();

        $crossBranchTotal = (clone $baseQuery)
            ->whereColumn('collected_branch_id', '!=', 'branch_id')
            ->whereIn('payment_type', ['cash', 'gcash', 'bank'])
            ->sum('amount');

        $payments = $baseQuery
            ->latest('paid_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.payments.index', compact(
            'branches',
            'canChooseBranch',
            'payments',
            'paymentsByType',
            'crossBranchTotal',
            'salesOwnerTotal',
            'physicalCollectionTotal',
            'todayCollectionTotal',
            'summary',
            'todayTotal',
            'dateFrom',
            'dateTo'
        ) + ['paymentTypes' => self::UI_PAYMENT_TYPES]);
    }

    public function update(Request $request, Payment $payment)
    {
        $user = $request->user();

        abort_unless(
            $this->canChooseBranch($user)
                || (int) $payment->branch_id === (int) $user->branch_id
                || (int) ($payment->collected_branch_id ?: $payment->branch_id) === (int) $user->branch_id,
            403
        );

        $validated = $request->validate([
            'payment_type' => ['required', Rule::in(['cash', 'gcash'])],
            'reference_no' => ['nullable', 'string', 'max:255'],
        ]);

        $reference = trim((string) ($validated['reference_no'] ?? ''));

        $payment->update([
            'payment_type' => $validated['payment_type'],
            'reference_no' => $validated['payment_type'] === 'cash' || $reference === '' ? null : $reference,
        ]);

        return response()->json([
            'message' => 'Payment updated.',
            'payment_type' => $payment->payment_type,
            'reference_no' => $payment->reference_no,
        ]);
    }

    public function destroy(Request $request, Payment $payment)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        DB::transaction(function () use ($request, $payment): void {
            $payment = Payment::withoutGlobalScope('financially_active')
                ->with('jobOrder')
                ->lockForUpdate()
                ->findOrFail($payment->id);
            $jobOrder = $payment->jobOrder;
            $snapshot = [
                'payment_number' => $payment->payment_number,
                'job_order_id' => $payment->job_order_id,
                'job_order_number' => $jobOrder?->job_order_number,
                'customer_id' => $payment->customer_id,
                'payment_type' => $payment->payment_type,
                'amount' => (float) $payment->amount,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
            ];

            CustomerLedger::query()->where('payment_id', $payment->id)->delete();
            $payment->delete();

            if ($jobOrder) {
                $paidAmount = (float) Payment::withoutGlobalScope('financially_active')
                    ->where('job_order_id', $jobOrder->id)
                    ->sum('amount');

                $jobOrder->update([
                    'paid_amount' => $paidAmount,
                    'balance' => max((float) $jobOrder->total - $paidAmount, 0),
                ]);
            }

            if ($payment->customer_id) {
                $this->recalculateCustomerLedger((int) $payment->customer_id);
            }

            Activity::log($request, 'payment_deleted', $payment, $snapshot, $payment->branch_id);
        });

        return back()->with('success', 'Payment deleted successfully. The job order balance and customer ledger were recalculated.');
    }

    private function canChooseBranch($user): bool
    {
        return $user->isSuperAdmin() || $user->role === 'admin';
    }

    private function recalculateCustomerLedger(int $customerId): void
    {
        $runningBalance = 0.0;

        CustomerLedger::query()
            ->where('customer_id', $customerId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function (CustomerLedger $ledger) use (&$runningBalance): void {
                $amount = (float) $ledger->amount;
                $runningBalance += $ledger->entry_type === 'credit' ? -$amount : $amount;
                $runningBalance = max($runningBalance, 0);

                $ledger->update(['running_balance' => $runningBalance]);
            });
    }

    private function filteredPaymentQuery(Request $request, ?int $branchId, string $branchColumn)
    {
        return Payment::query()
            ->when($branchId, fn ($query) => $query->where($branchColumn, $branchId))
            ->when(in_array($request->payment_type, self::PAYMENT_TYPES, true), fn ($query) => $query->where('payment_type', $request->payment_type))
            ->when($request->filled('date_range'), function ($query) use ($request) {
                [$dateFrom, $dateTo] = $this->dateRange($request);
                $query
                    ->when($dateFrom, fn ($query) => $query->whereDate('paid_at', '>=', $dateFrom))
                    ->when($dateTo, fn ($query) => $query->whereDate('paid_at', '<=', $dateTo));
            })
            ->when(! $request->filled('date_range'), function ($query) use ($request) {
                [$dateFrom, $dateTo] = $this->dateRange($request);
                $query
                    ->when($dateFrom, fn ($query) => $query->whereDate('paid_at', '>=', $dateFrom))
                    ->when($dateTo, fn ($query) => $query->whereDate('paid_at', '<=', $dateTo));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($query) use ($search) {
                    $query->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhereHas('jobOrder', fn ($query) => $query->where('job_order_number', 'like', "%{$search}%"))
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('receiver', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            });
    }

    private function dateRange(Request $request): array
    {
        if ($request->filled('date_range')) {
            $parts = preg_split('/\s+to\s+/', $request->date_range);

            return [
                $this->parseDate($parts[0] ?? null),
                $this->parseDate($parts[1] ?? $parts[0] ?? null),
            ];
        }

        $from = $this->parseDate($request->date_from);
        $to = $this->parseDate($request->date_to);

        if ($from || $to) {
            return [$from, $to];
        }

        return [today()->toDateString(), today()->toDateString()];
    }

    private function parseDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
