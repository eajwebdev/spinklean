<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    private function canChooseBranch($user): bool
    {
        return $user->isSuperAdmin() || $user->role === 'admin';
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
