<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BaggageDeposit;
use App\Models\Branch;
use App\Models\CustomerLedger;
use App\Models\JobOrder;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Activity;
use App\Support\BaggageCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Baggage Counter: a bag is left under a numbered claim tag. The cashier picks the size
 * (sets the rate), the customer pays, and gets the matching tag copy. Small/medium/large
 * bags are due back in 2 hours (overnight: 24 hours). Returning the tag releases the bag
 * and frees the tag. No customer details are kept: the sale goes to the Walk-in Customer.
 */
class BaggageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branch = $this->branchFor($request);
        $branches = $user->canManageAllBranches()
            ? Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $rates = $branch ? BaggageCounter::rates($branch) : collect();

        $active = $branch
            ? BaggageDeposit::query()->active()->where('branch_id', $branch->id)->with('depositor:id,name')->get()->keyBy('tag_number')
            : collect();

        $tagCount = (int) $branch?->baggage_tag_count;

        $tags = collect($tagCount > 0 ? range(1, $tagCount) : [])
            ->map(function (int $number) use ($active) {
                $deposit = $active->get($number);

                return [
                    'number' => $number,
                    'label' => str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'deposit' => $deposit ? $this->depositPayload($deposit) : null,
                ];
            })
            ->values();

        // Bags left on tag numbers above the current tag count (count was lowered) stay visible.
        $outOfRange = $active
            ->filter(fn (BaggageDeposit $deposit) => $deposit->tag_number > (int) $branch?->baggage_tag_count)
            ->map(fn (BaggageDeposit $deposit) => [
                'number' => $deposit->tag_number,
                'label' => $deposit->tagLabel(),
                'deposit' => $this->depositPayload($deposit),
            ])
            ->values();

        $today = $branch
            ? BaggageDeposit::query()
                ->where('branch_id', $branch->id)
                ->whereDate('deposited_at', today())
                ->with(['depositor:id,name', 'releaser:id,name', 'jobOrder:id,job_order_number'])
                ->latest('deposited_at')
                ->get()
            : collect();

        return view('admin.baggage.index', [
            'branch' => $branch,
            'branches' => $branches,
            'canConfigure' => $this->canConfigure($user),
            'rates' => $rates->map(fn ($service, $size) => [
                'size' => $size,
                'label' => BaggageCounter::label($size),
                'price' => (float) $service->price,
                'hours' => BaggageCounter::hours($size),
            ])->values(),
            'tags' => $tags->concat($outOfRange)->values(),
            'today' => $today,
            'todayTotal' => (float) $today->whereNull('voided_at')->sum('amount'),
        ]);
    }

    public function store(Request $request)
    {
        $branch = $this->branchFor($request);
        abort_unless($branch, 404);

        $rates = BaggageCounter::rates($branch);

        if ((int) $branch->baggage_tag_count < 1) {
            throw ValidationException::withMessages([
                'tag_number' => 'No claim tags are set up for this branch yet.',
            ]);
        }

        $validated = $request->validate([
            'tag_number' => ['required', 'integer', 'min:1', 'max:'.(int) $branch->baggage_tag_count],
            'size' => ['required', Rule::in($rates->keys()->all())],
            'payment_type' => ['required', Rule::in(['cash', 'gcash'])],
            'payment_reference_no' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $deposit = DB::transaction(function () use ($request, $branch, $rates, $validated) {
            // Serialize per branch so two cashiers cannot hand out the same tag.
            Branch::query()->whereKey($branch->id)->lockForUpdate()->first();

            $occupied = BaggageDeposit::query()
                ->active()
                ->where('branch_id', $branch->id)
                ->where('tag_number', $validated['tag_number'])
                ->exists();

            if ($occupied) {
                throw ValidationException::withMessages([
                    'tag_number' => 'Tag #'.str_pad((string) $validated['tag_number'], 2, '0', STR_PAD_LEFT).' is already in use. Pick a free tag.',
                ]);
            }

            $user = $request->user();
            $service = $rates->get($validated['size']);
            $tagLabel = str_pad((string) $validated['tag_number'], 2, '0', STR_PAD_LEFT);
            $jobOrders = app(JobOrderController::class);
            $customer = $jobOrders->walkInCustomer($branch);
            $settings = SystemSetting::current();

            $subtotal = (float) $service->price;
            $tax = $settings->vat_enabled ? $subtotal * ((float) $settings->vat_rate / 100) : 0;
            $total = $subtotal + $tax;
            $now = now();

            $order = JobOrder::create([
                'branch_id' => $branch->id,
                'processing_branch_id' => $branch->id,
                'current_branch_id' => $branch->id,
                'release_branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'created_by' => $user->id,
                'job_order_number' => $jobOrders->nextJobOrderNumber($branch->id),
                'status' => 'completed',
                'transaction_type' => 'walk_in',
                'is_product_sale' => true,
                'is_rush' => false,
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax' => $tax,
                'total' => $total,
                'paid_amount' => $total,
                'balance' => 0,
                'notes' => "Baggage tag #{$tagLabel}".(filled($validated['notes'] ?? null) ? ' - '.$validated['notes'] : ''),
                'completed_at' => $now,
                'released_at' => $now,
                'inventory_deducted_at' => $now,
            ]);

            $order->items()->create([
                'laundry_service_id' => $service->id,
                'description' => "{$service->name} (Tag #{$tagLabel})",
                'service_category' => $service->report_category,
                'quantity' => 1,
                'unit_price' => $subtotal,
                'total' => $subtotal,
            ]);

            $running = (float) CustomerLedger::where('customer_id', $customer->id)->latest()->value('running_balance');

            CustomerLedger::create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'job_order_id' => $order->id,
                'entry_type' => 'debit',
                'amount' => $total,
                'running_balance' => $running + $total,
                'description' => "Job order {$order->job_order_number}",
            ]);

            if ($total > 0) {
                $payment = Payment::create([
                    'branch_id' => $branch->id,
                    'collected_branch_id' => $branch->id,
                    'job_order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'received_by' => $user->id,
                    'payment_number' => $jobOrders->nextPaymentNumber(),
                    'payment_type' => $validated['payment_type'],
                    'reference_no' => $validated['payment_reference_no'] ?? null,
                    'amount' => $total,
                    'settlement_status' => 'local',
                    'paid_at' => $now,
                ]);

                CustomerLedger::create([
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'job_order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'entry_type' => 'credit',
                    'amount' => $total,
                    'running_balance' => $running,
                    'description' => "Payment {$payment->payment_number}",
                ]);
            }

            $deposit = BaggageDeposit::create([
                'branch_id' => $branch->id,
                'job_order_id' => $order->id,
                'tag_number' => $validated['tag_number'],
                'size' => $validated['size'],
                'amount' => $total,
                'payment_type' => $validated['payment_type'],
                'deposited_at' => $now,
                'due_at' => $now->copy()->addHours(BaggageCounter::hours($validated['size'])),
                'deposited_by' => $user->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            Activity::log($request, 'baggage_deposited', $deposit, [
                'tag_number' => $tagLabel,
                'size' => $validated['size'],
                'amount' => $total,
                'job_order_number' => $order->job_order_number,
            ], $branch->id);

            return $deposit;
        });

        return $this->backToCounter($branch)
            ->with('success', "Bag stored under Tag #{$deposit->tagLabel()} (".BaggageCounter::label($deposit->size).'). Give the customer their tag copy.');
    }

    public function release(Request $request, BaggageDeposit $deposit)
    {
        $this->authorizeDeposit($request, $deposit);
        abort_unless($deposit->isActive(), 422, 'This bag was already released.');

        $wasOverdue = $deposit->isOverdue();

        $deposit->update([
            'released_at' => now(),
            'released_by' => $request->user()->id,
        ]);

        Activity::log($request, 'baggage_released', $deposit, [
            'tag_number' => $deposit->tagLabel(),
            'overdue' => $wasOverdue,
        ], $deposit->branch_id);

        return $this->backToCounter($deposit->branch)
            ->with('success', "Tag #{$deposit->tagLabel()} released. The tag is free again.");
    }

    public function void(Request $request, BaggageDeposit $deposit)
    {
        $this->authorizeDeposit($request, $deposit);
        abort_unless($deposit->isActive(), 422, 'Only bags still at the counter can be voided.');

        DB::transaction(function () use ($request, $deposit) {
            $order = $deposit->jobOrder;

            if ($order && $order->status !== 'cancelled') {
                // Same as cancelling the sale from Job Orders: the payment drops out of the totals.
                app(JobOrderController::class)->cancel($request, $order);
            }

            $deposit->refresh();
            if ($deposit->isActive()) {
                $deposit->update(['voided_at' => now()]);
            }

            Activity::log($request, 'baggage_voided', $deposit, [
                'tag_number' => $deposit->tagLabel(),
                'job_order_number' => $order?->job_order_number,
            ], $deposit->branch_id);
        });

        return $this->backToCounter($deposit->branch)
            ->with('success', "Tag #{$deposit->tagLabel()} voided and the sale cancelled. The tag is free again.");
    }

    public function updateSettings(Request $request)
    {
        abort_unless($this->canConfigure($request->user()), 403);

        $branch = $this->branchFor($request);
        abort_unless($branch, 404);

        $validated = $request->validate([
            'baggage_tag_count' => ['required', 'integer', 'min:0', 'max:'.BaggageCounter::MAX_TAGS],
        ]);

        $branch->update(['baggage_tag_count' => $validated['baggage_tag_count']]);

        Activity::log($request, 'baggage_tags_updated', $branch, [
            'baggage_tag_count' => $validated['baggage_tag_count'],
        ], $branch->id);

        return $this->backToCounter($branch)->with('success', "Baggage tags set to {$validated['baggage_tag_count']}.");
    }

    private function depositPayload(BaggageDeposit $deposit): array
    {
        return [
            'id' => $deposit->id,
            'size' => $deposit->size,
            'size_label' => BaggageCounter::label($deposit->size),
            'amount' => (float) $deposit->amount,
            'payment_type' => $deposit->payment_type,
            'deposited_at' => $deposit->deposited_at?->toIso8601String(),
            'deposited_at_label' => $deposit->deposited_at?->format('M d, h:i A'),
            'due_at' => $deposit->due_at?->toIso8601String(),
            'due_at_label' => $deposit->due_at?->format('M d, h:i A'),
            'deposited_by' => $deposit->depositor?->name,
            'notes' => $deposit->notes,
            'release_url' => route('admin.baggage.release', $deposit),
            'void_url' => route('admin.baggage.void', $deposit),
        ];
    }

    private function branchFor(Request $request): ?Branch
    {
        $user = $request->user();

        if (! $user->canManageAllBranches()) {
            return $user->branch;
        }

        $branchId = $request->integer('branch_id') ?: (int) $user->branch_id;

        return Branch::query()->where('is_active', true)
            ->when($branchId, fn ($query) => $query->whereKey($branchId))
            ->orderBy('name')
            ->first();
    }

    private function authorizeDeposit(Request $request, BaggageDeposit $deposit): void
    {
        $user = $request->user();
        abort_unless($user->canManageAllBranches() || (int) $user->branch_id === (int) $deposit->branch_id, 403);
    }

    private function canConfigure(User $user): bool
    {
        return $user->canManageAllBranches() || $user->role === 'branch_manager';
    }

    private function backToCounter(?Branch $branch)
    {
        return redirect()->route('admin.baggage.index', $branch ? ['branch_id' => $branch->id] : []);
    }
}
