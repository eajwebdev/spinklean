<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\Branch;
use App\Models\BranchExpense;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    private const CATEGORIES = [
        'supplies',
        'inventory_purchase',
        'utilities',
        'rent',
        'payroll',
        'repairs_maintenance',
        'transport_delivery',
        'marketing',
        'government_fees',
        'professional_fees',
        'software_subscription',
        'other',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->canManageAllBranches();
        [$dateFrom, $dateTo] = $this->dateRange($request);

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $baseQuery = BranchExpense::query()
            ->with(['branch', 'creator', 'accountsPayable'])
            ->when(! $canChooseBranch, fn ($query) => $query->where('branch_id', $user->branch_id))
            ->when($canChooseBranch && $request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->branch_id))
            ->when($dateFrom, fn ($query) => $query->whereDate('expense_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('expense_date', '<=', $dateTo))
            ->when($request->filled('paid_from'), fn ($query) => $query->where('paid_from', $request->paid_from))
            ->when(in_array($request->expense_type, self::CATEGORIES, true), fn ($query) => $query->where('expense_type', $request->expense_type))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(fn ($query) => $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%"));
            });

        $summary = (clone $baseQuery)
            ->selectRaw("COALESCE(SUM(amount), 0) as total_expenses, COALESCE(SUM(CASE WHEN paid_from = 'store_cash' THEN amount ELSE 0 END), 0) as store_cash_expenses, COALESCE(SUM(CASE WHEN paid_from = 'owner' THEN amount ELSE 0 END), 0) as owner_expenses")
            ->first();

        $expenses = $baseQuery
            ->latest('expense_date')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.expenses.index', [
            'branches' => $branches,
            'canChooseBranch' => $canChooseBranch,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'expenses' => $expenses,
            'summary' => $summary,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $normalizedCategory = str((string) $request->input('category'))->snake()->toString();
        $normalizedCategory = match ($normalizedCategory) {
            'stocks', 'stock', 'inventory' => 'inventory_purchase',
            'repairs', 'maintenance' => 'repairs_maintenance',
            'transport', 'delivery' => 'transport_delivery',
            'fees' => 'government_fees',
            default => $normalizedCategory,
        };
        $request->merge([
            'category' => $normalizedCategory,
            'expense_type' => $normalizedCategory,
        ]);

        $validated = $request->validate([
            'branch_id' => [$user->canManageAllBranches() ? 'required' : 'nullable', 'exists:branches,id'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'paid_from' => ['nullable', Rule::in(['store_cash', 'owner'])],
            'expense_type' => ['nullable', Rule::in(self::CATEGORIES)],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (! $user->canManageAllBranches()) {
            $validated['branch_id'] = $user->branch_id;
        }

        $validated['expense_type'] = $validated['category'];
        $validated['paid_from'] = $validated['paid_from'] ?? 'store_cash';

        DB::transaction(function () use ($request, $validated, $user): void {
            $expense = BranchExpense::create($validated + ['created_by' => $user->id]);

            if ($expense->paid_from === 'owner') {
                $payable = AccountsPayable::query()->create([
                    'branch_id' => $expense->branch_id,
                    'created_by' => $user->id,
                    'payable_number' => AccountsPayable::nextNumber(),
                    'creditor_name' => 'Owner',
                    'source_type' => 'owner_paid_expense',
                    'source_id' => $expense->id,
                    'funding_method' => $this->payableFundingMethod($expense->payment_method),
                    'reference_no' => $expense->reference_no,
                    'description' => 'Reimbursement for '.$expense->title,
                    'original_amount' => $expense->amount,
                    'paid_amount' => 0,
                    'balance' => $expense->amount,
                    'status' => 'unpaid',
                    'funded_at' => $expense->expense_date->toDateString(),
                ]);
                $expense->update(['accounts_payable_id' => $payable->id]);
            }

            Activity::log($request, 'expense_recorded', $expense, [
                'title' => $expense->title,
                'amount' => $expense->amount,
                'funding_source' => $expense->paid_from,
            ], $expense->branch_id);
        });

        return back()->with('success', 'Expense recorded successfully.');
    }

    private function payableFundingMethod(?string $paymentMethod): string
    {
        $method = str((string) $paymentMethod)->lower()->trim()->toString();

        return in_array($method, ['cash', 'gcash', 'bank', 'cheque'], true) ? $method : 'cash';
    }

    public function destroy(Request $request, BranchExpense $expense)
    {
        if (! $request->user()->canManageAllBranches()) {
            abort_unless((int) $request->user()->branch_id === (int) $expense->branch_id, 403);
        }

        $expense->loadMissing('accountsPayable.payments');

        if ($expense->accountsPayable?->payments->isNotEmpty()) {
            return back()->with('error', 'This expense already has payable repayments and cannot be deleted.');
        }

        DB::transaction(function () use ($expense): void {
            $payable = $expense->accountsPayable;
            $expense->delete();
            $payable?->delete();
        });

        return back()->with('success', 'Expense removed successfully.');
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
            return \Illuminate\Support\Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
