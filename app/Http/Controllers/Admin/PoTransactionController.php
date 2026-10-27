<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Customer;
use App\Models\PoDeliveryItem;
use App\Models\PoTransaction;
use App\Models\PoTransactionPayment;
use App\Models\SystemSetting;
use App\Support\Activity;
use App\Support\PoDeliveries;
use App\Support\StatementOfAccountRows;
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
            ->with(['branch', 'customer', 'jobOrder.items', 'deliveryItems', 'payments.receiver'])
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

        // The statement is issued by the branch the customer belongs to.
        $data['customer']->loadMissing('branch.setting');
        $issuingBranch = $data['customer']->branch;

        return Pdf::loadView('admin.po-transactions.statement-of-account-pdf', [
            ...$data,
            'issuingBranch' => $issuingBranch,
            'statementDetails' => BranchSetting::statementDetailsFor($issuingBranch?->setting),
            'soa' => StatementOfAccountRows::build($data['lines'], $data['dateFrom'], $data['dateTo']),
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

            // The status can't claim money that was never recorded, or reports and balances disagree.
            if ($validated['status'] === 'paid' && round($remainingBalance - $paymentAmount, 2) > 0) {
                throw ValidationException::withMessages([
                    'status' => 'Record the payment to mark this PO paid. ₱'.number_format($remainingBalance - $paymentAmount, 2).' is still unpaid.',
                ]);
            }

            if ($validated['status'] === 'partially_paid' && round((float) $po->paid_amount + $paymentAmount, 2) <= 0) {
                throw ValidationException::withMessages([
                    'status' => 'Record the payment received to mark this PO partially paid.',
                ]);
            }

            $this->applyPayment($request, $po, $paymentAmount, $validated['status'], $validated['payment_method'] ?? null, $validated['reference_no'] ?? null, $validated['remarks'] ?? null);
        });

        return back()->with('success', 'PO transaction updated successfully.');
    }

    /**
     * One payment for a whole statement (e.g. the customer's monthly cheque). It is applied to the
     * delivered, unpaid POs oldest delivery first, and cannot pay for pieces not yet delivered.
     */
    public function payStatement(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['cash', 'gcash', 'bank', 'cheque'])],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'paid_on' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $customer = Customer::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->findOrFail($validated['customer_id']);
        $dateTo = Carbon::parse($validated['date_to'])->toDateString();
        $statement = [
            'statement_from' => filled($validated['date_from'] ?? null) ? Carbon::parse($validated['date_from'])->toDateString() : null,
            'statement_to' => $dateTo,
        ];
        $paidAt = filled($validated['paid_on'] ?? null) ? Carbon::parse($validated['paid_on'])->setTimeFrom(now()) : now();
        $amount = round((float) $validated['amount'], 2);

        $applied = DB::transaction(function () use ($request, $user, $customer, $dateTo, $paidAt, $amount, $validated, $statement) {
            $purchaseOrders = PoTransaction::query()
                ->with('deliveryItems.delivery')
                ->where('customer_id', $customer->id)
                ->when(! $user->isAdmin(), fn ($query) => $query->where('branch_id', $user->branch_id))
                ->whereHas('deliveryItems')
                ->lockForUpdate()
                ->get()
                ->filter(fn (PoTransaction $po) => PoDeliveries::dueAmount($po, $dateTo) > 0)
                ->sortBy(fn (PoTransaction $po) => $po->deliveryItems->min(fn ($item) => $item->delivery?->delivered_on?->toDateString()).'-'.str_pad((string) $po->id, 10, '0', STR_PAD_LEFT));

            $totalDue = round((float) $purchaseOrders->sum(fn (PoTransaction $po) => PoDeliveries::dueAmount($po, $dateTo)), 2);

            if ($amount > $totalDue) {
                throw ValidationException::withMessages([
                    'amount' => 'Only ₱'.number_format($totalDue, 2).' is due for delivered laundry up to '.Carbon::parse($dateTo)->format('M d, Y').'.',
                ]);
            }

            $left = $amount;
            $applied = [];

            foreach ($purchaseOrders as $po) {
                if ($left <= 0) {
                    break;
                }

                $portion = min($left, PoDeliveries::dueAmount($po, $dateTo));
                $this->applyPayment($request, $po, $portion, 'billed', $validated['payment_method'], $validated['reference_no'] ?? null, $validated['remarks'] ?? 'Statement payment', $paidAt, $statement);
                $left = round($left - $portion, 2);
                $applied[] = $po->po_number;
            }

            return $applied;
        });

        return back()->with('success', 'Payment of ₱'.number_format($amount, 2).' applied to '.count($applied).' PO(s): '.implode(', ', $applied).'.');
    }

    private function applyPayment(Request $request, PoTransaction $po, float $paymentAmount, string $status, ?string $method, ?string $referenceNo, ?string $remarks, ?Carbon $paidAt = null, array $statement = []): void
    {
        $paidAmount = round((float) $po->paid_amount + $paymentAmount, 2);
        $balance = max(round((float) $po->amount - $paidAmount, 2), 0);

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
                'payment_method' => $method,
                'reference_no' => $referenceNo,
                'amount' => $paymentAmount,
                'remarks' => $remarks,
                'statement_from' => $statement['statement_from'] ?? null,
                'statement_to' => $statement['statement_to'] ?? null,
                'paid_at' => $paidAt ?? now(),
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
    }

    /**
     * Edit only the delivery details; amounts, payments and status are left untouched.
     */
    public function updateDelivery(Request $request, PoTransaction $poTransaction)
    {
        $this->authorizePoTransaction($request, $poTransaction);

        $validated = $request->validate([
            'date_delivered' => ['nullable', 'date'],
            'dr_number' => ['nullable', 'string', 'max:100'],
        ]);

        $before = [
            'date_delivered' => $poTransaction->date_delivered?->toDateString(),
            'dr_number' => $poTransaction->dr_number,
        ];

        $poTransaction->update([
            'date_delivered' => $validated['date_delivered'] ?? null,
            'dr_number' => filled($validated['dr_number'] ?? null) ? trim($validated['dr_number']) : null,
        ]);

        Activity::log($request, 'po_transaction_delivery_updated', $poTransaction, [
            'po_number' => $poTransaction->po_number,
            'before' => $before,
            'date_delivered' => $poTransaction->date_delivered?->toDateString(),
            'dr_number' => $poTransaction->dr_number,
        ], $poTransaction->branch_id);

        return back()->with('success', 'Delivery details updated for PO '.$poTransaction->po_number.'.');
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
        $selectedStatus = in_array($request->status, PoTransaction::STATUSES, true) ? $request->status : null;
        $poFilter = fn ($query) => $query
            ->where('customer_id', $customer?->id)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($selectedStatus, fn ($query) => $query->where('status', $selectedStatus));
        $lines = collect();
        $accounts = collect();
        $notYetDelivered = 0.0;

        if ($customer) {
            // Laundry is billed on the day it was delivered (the DR date), not the day it was dropped off.
            $lines = PoDeliveryItem::query()
                ->with(['delivery', 'poTransaction.jobOrder:id,job_order_number,status'])
                ->whereHas('poTransaction', $poFilter)
                ->whereHas('delivery', fn ($query) => $query->whereDate('delivered_on', '>=', $dateFrom)->whereDate('delivered_on', '<=', $dateTo))
                ->get()
                ->sortBy(fn (PoDeliveryItem $line) => $line->delivery->delivered_on->toDateString().'-'.str_pad((string) $line->po_delivery_id, 10, '0', STR_PAD_LEFT))
                ->values();

            $purchaseOrders = PoTransaction::query()
                ->with([
                    'branch:id,name,code',
                    'jobOrder:id,job_order_number,status',
                    'jobOrder.items',
                    'deliveryItems.delivery',
                    'payments' => fn ($query) => $query->with('receiver:id,name')->orderBy('paid_at')->orderBy('id'),
                ])
                ->where($poFilter)
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            // Each PO billed in this period, plus older deliveries that are still unpaid.
            $accounts = $purchaseOrders->map(fn (PoTransaction $po) => [
                'po' => $po,
                'in_period' => round((float) $lines->where('po_transaction_id', $po->id)->sum('amount'), 2),
                'delivered' => PoDeliveries::deliveredAmount($po, $dateTo),
                'due' => PoDeliveries::dueAmount($po, $dateTo),
            ])->filter(fn (array $account) => $account['in_period'] > 0 || $account['due'] > 0)->values();

            $notYetDelivered = round((float) $purchaseOrders->sum(fn (PoTransaction $po) => max((float) $po->amount - PoDeliveries::deliveredAmount($po), 0)), 2);
        }

        $deliveries = $lines->groupBy('po_delivery_id')->map(fn ($group) => [
            'delivery' => $group->first()->delivery,
            'job_orders' => $group->map(fn ($line) => $line->poTransaction?->jobOrder?->job_order_number)->filter()->unique()->values(),
            'po_numbers' => $group->map(fn ($line) => $line->poTransaction?->po_number)->filter()->unique()->values(),
            'pieces' => (float) $group->reject(fn ($line) => StatementOfAccountRows::isDeliveryCharge($line))->sum('quantity'),
            'amount' => round((float) $group->sum('amount'), 2),
        ])->values();

        return [
            'settings' => SystemSetting::current(),
            'branches' => $branches,
            'customers' => $customers,
            'customer' => $customer,
            'lines' => $lines,
            'deliveries' => $deliveries,
            'accounts' => $accounts,
            'payments' => $accounts->flatMap(fn (array $account) => $account['po']->payments)->sortBy('paid_at')->values(),
            'summary' => [
                'deliveries' => $deliveries->count(),
                'amount' => round((float) $lines->sum('amount'), 2),
                'paid' => round((float) $accounts->sum(fn (array $account) => (float) $account['po']->paid_amount), 2),
                'balance' => round((float) $accounts->sum('due'), 2),
                'not_yet_delivered' => $notYetDelivered,
            ],
            'statuses' => PoTransaction::STATUSES,
            'selectedBranchId' => $branchId,
            'selectedStatus' => $selectedStatus,
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
