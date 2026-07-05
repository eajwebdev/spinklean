<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\AccountsPayablePayment;
use App\Models\Branch;
use App\Models\MoneyMovement;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountsPayableController extends Controller
{
    private const METHODS = ['cash', 'bank', 'gcash', 'cheque'];
    private const UI_METHODS = ['cash', 'gcash', 'cheque'];

    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->canManageAllBranches();
        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $branchId = $canChooseBranch ? ($request->integer('branch_id') ?: null) : $user->branch_id;
        $baseQuery = AccountsPayable::query()
            ->with(['branch', 'creator', 'payments.recorder', 'expense'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(in_array($request->status, ['unpaid', 'partial', 'paid'], true), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(fn ($query) => $query
                    ->where('payable_number', 'like', "%{$search}%")
                    ->orWhere('creditor_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%"));
            });

        $summary = (clone $baseQuery)
            ->selectRaw("COALESCE(SUM(original_amount), 0) as original_total, COALESCE(SUM(paid_amount), 0) as paid_total, COALESCE(SUM(balance), 0) as balance_total, SUM(CASE WHEN status != 'paid' THEN 1 ELSE 0 END) as open_count")
            ->first();

        $payables = $baseQuery
            ->latest('funded_at')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.accounts-payable.index', [
            'branches' => $branches,
            'canChooseBranch' => $canChooseBranch,
            'methods' => self::UI_METHODS,
            'payables' => $payables,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'branch_id' => [$user->canManageAllBranches() ? 'required' : 'nullable', 'exists:branches,id'],
            'creditor_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'funding_method' => ['required', Rule::in(self::METHODS)],
            'funded_at' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:funded_at'],
            'reference_no' => ['nullable', 'string', 'max:255'],
        ]);

        $branchId = $user->canManageAllBranches() ? (int) $validated['branch_id'] : (int) $user->branch_id;
        abort_unless($branchId, 403);

        DB::transaction(function () use ($request, $validated, $branchId, $user): void {
            $amount = round((float) $validated['amount'], 2);
            $payable = AccountsPayable::query()->create([
                'branch_id' => $branchId,
                'created_by' => $user->id,
                'payable_number' => AccountsPayable::nextNumber(),
                'creditor_name' => $validated['creditor_name'],
                'source_type' => 'owner_funding',
                'funding_method' => $validated['funding_method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'description' => $validated['description'],
                'original_amount' => $amount,
                'paid_amount' => 0,
                'balance' => $amount,
                'status' => 'unpaid',
                'funded_at' => Carbon::parse($validated['funded_at'])->toDateString(),
                'due_date' => filled($validated['due_date'] ?? null) ? Carbon::parse($validated['due_date'])->toDateString() : null,
            ]);

            if ($validated['funding_method'] === 'cash') {
                MoneyMovement::query()->create([
                    'branch_id' => $branchId,
                    'recorded_by' => $user->id,
                    'movement_date' => $payable->funded_at,
                    'type' => 'deposit',
                    'direction' => 'in',
                    'amount' => $amount,
                    'reference_no' => $payable->payable_number,
                    'description' => "Owner funding received: {$payable->description}",
                ]);
            }

            Activity::log($request, 'accounts_payable_created', $payable, [
                'payable_number' => $payable->payable_number,
                'amount' => $amount,
                'funding_method' => $payable->funding_method,
            ], $branchId);
        });

        return back()->with('success', 'Owner funding recorded as an accounts payable.');
    }

    public function storePayment(Request $request, AccountsPayable $accountsPayable)
    {
        $this->authorizePayable($request, $accountsPayable);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(self::METHODS)],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $validated, $accountsPayable): void {
            $payable = AccountsPayable::query()->lockForUpdate()->findOrFail($accountsPayable->id);
            $amount = round((float) $validated['amount'], 2);

            if ($amount > (float) $payable->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Repayment cannot exceed the remaining payable balance.',
                ]);
            }

            $movement = null;
            if ($validated['payment_method'] === 'cash') {
                $movement = MoneyMovement::query()->create([
                    'branch_id' => $payable->branch_id,
                    'recorded_by' => $request->user()->id,
                    'movement_date' => Carbon::parse($validated['payment_date'])->toDateString(),
                    'type' => 'withdraw',
                    'direction' => 'out',
                    'amount' => $amount,
                    'reference_no' => $payable->payable_number,
                    'description' => "Accounts payable repayment to {$payable->creditor_name}",
                ]);
            }

            AccountsPayablePayment::query()->create([
                'accounts_payable_id' => $payable->id,
                'branch_id' => $payable->branch_id,
                'recorded_by' => $request->user()->id,
                'money_movement_id' => $movement?->id,
                'payment_number' => $this->nextPaymentNumber(),
                'payment_date' => Carbon::parse($validated['payment_date'])->toDateString(),
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $paid = round((float) $payable->paid_amount + $amount, 2);
            $balance = max(round((float) $payable->original_amount - $paid, 2), 0);
            $payable->update([
                'paid_amount' => $paid,
                'balance' => $balance,
                'status' => $balance <= 0 ? 'paid' : 'partial',
            ]);

            Activity::log($request, 'accounts_payable_payment_recorded', $payable, [
                'payable_number' => $payable->payable_number,
                'amount' => $amount,
                'remaining_balance' => $balance,
            ], $payable->branch_id);
        });

        return back()->with('success', 'Payable repayment recorded successfully.');
    }

    public function destroy(Request $request, AccountsPayable $accountsPayable)
    {
        $this->authorizePayable($request, $accountsPayable);

        if ($accountsPayable->payments()->exists() || (float) $accountsPayable->paid_amount > 0) {
            return back()->with('error', 'This payable already has repayments and cannot be deleted.');
        }

        if ($accountsPayable->expense()->exists()) {
            return back()->with('error', 'This payable is linked to an expense. Delete the expense record instead.');
        }

        DB::transaction(function () use ($request, $accountsPayable): void {
            if ($accountsPayable->source_type === 'owner_funding' && $accountsPayable->funding_method === 'cash') {
                MoneyMovement::query()
                    ->where('branch_id', $accountsPayable->branch_id)
                    ->where('type', 'deposit')
                    ->where('reference_no', $accountsPayable->payable_number)
                    ->delete();
            }

            Activity::log($request, 'accounts_payable_deleted', $accountsPayable, [
                'payable_number' => $accountsPayable->payable_number,
                'amount' => (float) $accountsPayable->original_amount,
            ], $accountsPayable->branch_id);

            $accountsPayable->delete();
        });

        return back()->with('success', 'Payable deleted successfully.');
    }

    private function authorizePayable(Request $request, AccountsPayable $payable): void
    {
        if ($request->user()->canManageAllBranches()) {
            return;
        }

        abort_unless((int) $request->user()->branch_id === (int) $payable->branch_id, 403);
    }

    private function nextPaymentNumber(): string
    {
        return 'APP-'.now()->format('Ymd').'-'.str_pad((string) (AccountsPayablePayment::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
