<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PoTransaction;
use App\Models\PoTransactionPayment;
use App\Models\SystemSetting;
use App\Support\Activity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PoTransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->isAdmin();
        [$dateFrom, $dateTo] = $this->dateRange($request);
        $branchId = $canChooseBranch ? ($request->integer('branch_id') ?: null) : $user->branch_id;

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $customers = $this->poCustomers($branchId, $canChooseBranch ? null : (int) $user->branch_id);

        $baseQuery = PoTransaction::query()
            ->with(['branch', 'customer', 'jobOrder', 'payments.receiver'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($dateFrom, fn ($query) => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('transaction_date', '<=', $dateTo))
            ->when($request->integer('customer_id'), fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when(in_array($request->status, PoTransaction::STATUSES, true), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($query) use ($search) {
                    $query->where('po_number', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('jobOrder', fn ($query) => $query->where('job_order_number', 'like', "%{$search}%"));
                });
            });

        $summary = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount, COALESCE(SUM(CASE WHEN status = "pending" THEN balance ELSE 0 END), 0) as pending_amount, COALESCE(SUM(paid_amount), 0) as paid_amount, COALESCE(SUM(balance), 0) as outstanding_balance')
            ->first();

        $transactions = $baseQuery
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'billed' THEN 1 WHEN 'partially_paid' THEN 2 WHEN 'paid' THEN 3 ELSE 4 END")
            ->latest('transaction_date')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.po-transactions.index', [
            'branches' => $branches,
            'canChooseBranch' => $canChooseBranch,
            'customers' => $customers,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'statuses' => PoTransaction::STATUSES,
            'summary' => $summary,
            'transactions' => $transactions,
        ]);
    }

    public function statementOfAccount(Request $request)
    {
        return view('admin.po-transactions.statement-of-account', $this->statementOfAccountData($request));
    }

    public function statementOfAccountPdf(Request $request)
    {
        $data = $this->statementOfAccountData($request);

        abort_unless($data['customer'], 404, 'Select a PO customer first.');

        $filename = 'po-statement-of-account-'.str($data['customer']->name)->slug().'-'.$data['dateFrom'].'-to-'.$data['dateTo'].'.pdf';

        return Pdf::loadView('admin.po-transactions.statement-of-account-pdf', [
            ...$data,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait')->stream($filename);
    }

    public function update(Request $request, PoTransaction $poTransaction)
    {
        $this->authorizePoTransaction($request, $poTransaction);

        $validated = $request->validate([
            'status' => ['required', Rule::in(PoTransaction::STATUSES)],
            'payment_method' => ['nullable', Rule::in(['cash', 'gcash', 'bank', 'cheque'])],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:'.$poTransaction->balance],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $validated, $poTransaction) {
            $po = PoTransaction::query()
                ->whereKey($poTransaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $paymentAmount = round((float) ($validated['paid_amount'] ?? 0), 2);
            $remainingBalance = round((float) $po->balance, 2);

            if ($paymentAmount > $remainingBalance) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'PO payment cannot exceed the remaining balance.',
                ]);
            }

            if ($paymentAmount > 0 && blank($validated['payment_method'] ?? null)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Please choose a payment method for this PO payment.',
                ]);
            }

            $paidAmount = round((float) $po->paid_amount + $paymentAmount, 2);
            $balance = max(round((float) $po->amount - $paidAmount, 2), 0);
            $status = $validated['status'];

            if ($balance <= 0) {
                $status = 'paid';
            } elseif ($paidAmount > 0) {
                $status = 'partially_paid';
            }

            $po->update([
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'status' => $status,
                'billed_at' => in_array($status, ['billed', 'partially_paid', 'paid'], true) ? ($po->billed_at ?: now()) : null,
                'paid_at' => $status === 'paid' ? ($po->paid_at ?: now()) : null,
            ]);

            if ($paymentAmount > 0) {
                $payment = PoTransactionPayment::query()->create([
                    'po_transaction_id' => $po->id,
                    'branch_id' => $po->branch_id,
                    'customer_id' => $po->customer_id,
                    'job_order_id' => $po->job_order_id,
                    'received_by' => $request->user()?->id,
                    'payment_number' => $this->nextPaymentNumber(),
                    'payment_method' => $validated['payment_method'],
                    'reference_no' => $validated['reference_no'] ?? null,
                    'amount' => $paymentAmount,
                    'remarks' => $validated['remarks'] ?? null,
                    'paid_at' => now(),
                ]);

                Activity::log($request, 'po_transaction_payment_recorded', $payment, [
                    'payment_number' => $payment->payment_number,
                    'po_number' => $po->po_number,
                    'amount' => $payment->amount,
                    'remaining_balance' => $po->balance,
                ], $po->branch_id);
            }

            Activity::log($request, 'po_transaction_updated', $po, [
                'po_number' => $po->po_number,
                'status' => $po->status,
                'paid_amount' => $po->paid_amount,
                'balance' => $po->balance,
            ], $po->branch_id);
        });

        return back()->with('success', 'PO transaction updated successfully.');
    }

    private function authorizePoTransaction(Request $request, PoTransaction $poTransaction): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless((int) $request->user()->branch_id === (int) $poTransaction->branch_id, 403);
    }

    private function statementOfAccountData(Request $request): array
    {
        $user = $request->user();
        $canChooseBranch = $user->isAdmin();
        [$dateFrom, $dateTo] = $this->dateRange(
            $request,
            today()->startOfMonth()->toDateString(),
            today()->toDateString()
        );
        $branchId = $canChooseBranch ? ($request->integer('branch_id') ?: null) : (int) $user->branch_id;

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();
        $customers = $this->poCustomers($branchId, $canChooseBranch ? null : (int) $user->branch_id);
        $customerId = $request->integer('customer_id') ?: null;
        $customer = $customerId
            ? Customer::query()
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->when(! $canChooseBranch, fn ($query) => $query->where('branch_id', $user->branch_id))
                ->find($customerId)
            : null;
        $transactions = collect();

        if ($customer) {
            $transactions = PoTransaction::query()
                ->with([
                    'branch:id,name,code',
                    'jobOrder:id,job_order_number,status',
                    'payments' => fn ($query) => $query->with('receiver:id,name')->orderBy('paid_at')->orderBy('id'),
                ])
                ->where('customer_id', $customer->id)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereDate('transaction_date', '>=', $dateFrom)
                ->whereDate('transaction_date', '<=', $dateTo)
                ->when(in_array($request->status, PoTransaction::STATUSES, true), fn ($query) => $query->where('status', $request->status))
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();
        }

        return [
            'settings' => SystemSetting::current(),
            'branches' => $branches,
            'customers' => $customers,
            'customer' => $customer,
            'transactions' => $transactions,
            'payments' => $transactions->flatMap->payments->sortBy('paid_at')->values(),
            'summary' => [
                'transactions' => $transactions->count(),
                'amount' => round((float) $transactions->sum('amount'), 2),
                'paid' => round((float) $transactions->sum('paid_amount'), 2),
                'balance' => round((float) $transactions->sum('balance'), 2),
            ],
            'statuses' => PoTransaction::STATUSES,
            'selectedBranchId' => $branchId,
            'selectedStatus' => in_array($request->status, PoTransaction::STATUSES, true) ? $request->status : null,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'canChooseBranch' => $canChooseBranch,
        ];
    }

    private function poCustomers(?int $branchId, ?int $restrictedBranchId)
    {
        return Customer::query()
            ->whereHas('poTransactions')
            ->when($restrictedBranchId, fn ($query) => $query->where('branch_id', $restrictedBranchId))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'phone', 'email', 'address']);
    }

    private function dateRange(Request $request, ?string $defaultFrom = null, ?string $defaultTo = null): array
    {
        if ($request->filled('date_range')) {
            $parts = preg_split('/\s+to\s+/', $request->date_range);

            return [
                $this->parseDate($parts[0] ?? null),
                $this->parseDate($parts[1] ?? ($parts[0] ?? null)),
            ];
        }

        $from = $this->parseDate($request->date_from);
        $to = $this->parseDate($request->date_to);

        if ($from || $to) {
            return [$from, $to];
        }

        return [
            $defaultFrom ?? today()->toDateString(),
            $defaultTo ?? today()->toDateString(),
        ];
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

    private function nextPaymentNumber(): string
    {
        return 'POPAY-'.now()->format('Ymd').'-'.str_pad((string) (PoTransactionPayment::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
