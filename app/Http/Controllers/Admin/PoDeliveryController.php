<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchSetting;
use App\Models\Customer;
use App\Models\PoDelivery;
use App\Models\PoTransaction;
use App\Models\SystemSetting;
use App\Support\Activity;
use App\Support\PoDeliveries;
use App\Support\StatementOfAccountRows;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PoDeliveryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $customers = $this->poCustomers($request);
        [$dateFrom, $dateTo] = $this->dateRange($request);

        $deliveries = PoDelivery::query()
            ->with(['customer:id,name', 'branch:id,name', 'creator:id,name', 'items.jobOrder:id,job_order_number'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->when($request->integer('customer_id'), fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($dateFrom, fn ($query) => $query->whereDate('delivered_on', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('delivered_on', '<=', $dateTo))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $query->where('dr_number', 'like', '%'.$request->search.'%')
                    ->orWhereHas('items.jobOrder', fn ($query) => $query->where('job_order_number', 'like', '%'.$request->search.'%'));
            }))
            ->latest('delivered_on')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.po-deliveries.index', [
            'deliveries' => $deliveries,
            'customers' => $customers,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'settings' => SystemSetting::current(),
        ]);
    }

    public function create(Request $request)
    {
        $customers = $this->poCustomers($request);
        $customer = $request->integer('customer_id') ? $customers->firstWhere('id', $request->integer('customer_id')) : null;
        $purchaseOrders = $customer ? PoDeliveries::openPurchaseOrders($customer->id, $customer->branch_id) : collect();

        return view('admin.po-deliveries.create', [
            'customers' => $customers,
            'customer' => $customer,
            'purchaseOrders' => $purchaseOrders->map(fn (PoTransaction $po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'job_order_number' => $po->jobOrder?->job_order_number,
                'tag_number' => $po->jobOrder?->tag_number,
                'status' => $po->jobOrder?->status,
                'order_date' => $po->transaction_date?->format('M d, Y'),
                'focus' => (int) $request->integer('po_transaction_id') === (int) $po->id,
                'lines' => PoDeliveries::remainingLines($po)->values(),
            ])->values(),
            'nextDrNumber' => $customer ? PoDeliveries::nextDrNumber((int) $customer->branch_id) : null,
            'settings' => SystemSetting::current(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer'],
            'delivered_on' => ['required', 'date'],
            'dr_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['array'],
            'quantities.*.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customer = $this->poCustomers($request)->firstWhere('id', (int) $validated['customer_id']);
        abort_unless($customer, 403);

        $delivery = PoDeliveries::record(
            (int) $customer->branch_id,
            (int) $customer->id,
            $validated['quantities'],
            Carbon::parse($validated['delivered_on'])->toDateString(),
            $validated['dr_number'] ?? null,
            $validated['notes'] ?? null,
            $request->user()->id,
        );

        Activity::log($request, 'po_delivery_recorded', $delivery, [
            'dr_number' => $delivery->dr_number,
            'customer' => $customer->name,
            'delivered_on' => $delivery->delivered_on->toDateString(),
            'pieces' => (float) $delivery->items()->sum('quantity'),
            'amount' => (float) $delivery->amount,
        ], $delivery->branch_id);

        return redirect()
            ->route('admin.po-deliveries.index', ['customer_id' => $customer->id])
            ->with('success', "DR #{$delivery->dr_number} saved for {$customer->name}.")
            ->with('printDeliveryId', $delivery->id);
    }

    public function print(Request $request, PoDelivery $poDelivery)
    {
        $this->authorizeDelivery($request, $poDelivery);

        $poDelivery->load(['customer', 'branch.setting', 'creator', 'items.jobOrder', 'items.poTransaction.jobOrder.items', 'items.poTransaction.deliveryItems']);

        // Laundry pieces still left to deliver right after this DR, per job order (delivery fees not counted).
        $remainingAfter = $poDelivery->items->pluck('poTransaction')->filter()->unique('id')->map(function (PoTransaction $po) use ($poDelivery) {
            $ordered = (float) ($po->jobOrder?->items->reject(fn ($item) => StatementOfAccountRows::isDeliveryCharge($item))->sum('quantity') ?? 0);
            $delivered = (float) $po->deliveryItems
                ->where('po_delivery_id', '<=', $poDelivery->id)
                ->reject(fn ($item) => StatementOfAccountRows::isDeliveryCharge($item))
                ->sum('quantity');

            return [
                'job_order_number' => $po->jobOrder?->job_order_number,
                'po_number' => $po->po_number,
                'remaining' => max(round($ordered - $delivered, 2), 0),
            ];
        })->values();

        $filename = 'DR-'.$poDelivery->dr_number.'-'.str($poDelivery->customer?->name)->slug().'.pdf';

        return Pdf::loadView('admin.po-deliveries.pdf', [
            'delivery' => $poDelivery,
            'remainingAfter' => $remainingAfter,
            'statementDetails' => BranchSetting::statementDetailsFor($poDelivery->branch?->setting),
        ])->setPaper('a4', 'landscape')->stream($filename);
    }

    public function destroy(Request $request, PoDelivery $poDelivery)
    {
        $this->authorizeDelivery($request, $poDelivery);

        $snapshot = [
            'dr_number' => $poDelivery->dr_number,
            'customer_id' => $poDelivery->customer_id,
            'delivered_on' => $poDelivery->delivered_on->toDateString(),
            'amount' => (float) $poDelivery->amount,
            'lines' => $poDelivery->items()->get(['po_transaction_id', 'description', 'quantity', 'amount'])->toArray(),
        ];

        PoDeliveries::void($poDelivery);

        Activity::log($request, 'po_delivery_voided', $poDelivery, $snapshot, $poDelivery->branch_id);

        return back()->with('success', "DR #{$snapshot['dr_number']} voided. Its pieces are back to not yet delivered.");
    }

    private function authorizeDelivery(Request $request, PoDelivery $delivery): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless((int) $request->user()->branch_id === (int) $delivery->branch_id, 403);
    }

    private function poCustomers(Request $request)
    {
        $user = $request->user();

        return Customer::query()
            ->where(fn ($query) => $query->where('billing_type', 'po')->orWhereHas('poTransactions'))
            ->when(! $user->isAdmin(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'phone', 'address']);
    }

    private function dateRange(Request $request): array
    {
        if (! $request->filled('date_range')) {
            return [null, null];
        }

        $parts = preg_split('/\s+to\s+/', $request->date_range);

        try {
            return [
                Carbon::parse($parts[0])->toDateString(),
                Carbon::parse($parts[1] ?? $parts[0])->toDateString(),
            ];
        } catch (\Throwable) {
            return [null, null];
        }
    }
}
