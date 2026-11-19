<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BaggageDeposit;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\JobOrder;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Activity;
use App\Support\BaggageCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Baggage Counter. The board shows the branch's numbered cubicles. A customer (name and
 * mobile number) can take one or more cubicles under one claim tag: the number of the
 * first cubicle. Each cubicle has its own size and payment; together they are one paid
 * sale (one Job Order, one payment) under that customer. Cubicles are claimed one at a
 * time or all at once, and a claimed cubicle is free again. Small/medium/large are due
 * back in 2 hours (overnight: 24 hours).
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

        // Every cubicle (claimed ones too) of the tickets still at the counter.
        $tickets = $active->isEmpty()
            ? collect()
            : BaggageDeposit::query()
                ->whereIn('ticket_code', $active->pluck('ticket_code')->filter()->unique()->values())
                ->whereNull('voided_at')
                ->with(['releaser:id,name', 'jobOrder:id,job_order_number'])
                ->orderBy('tag_number')
                ->get()
                ->groupBy('ticket_code')
                ->map(fn (Collection $deposits) => $this->ticketPayload($deposits));

        $tagCount = (int) $branch?->baggage_tag_count;

        $tags = collect($tagCount > 0 ? range(1, $tagCount) : [])
            ->map(function (int $number) use ($active) {
                $deposit = $active->get($number);

                return [
                    'number' => $number,
                    'label' => BaggageDeposit::pad($number),
                    'deposit' => $deposit ? $this->depositPayload($deposit) : null,
                ];
            })
            ->values();

        // Bags left in cubicles above the current count (count was lowered) stay visible.
        $outOfRange = $active
            ->filter(fn (BaggageDeposit $deposit) => $deposit->tag_number > $tagCount)
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
                ->orderBy('tag_number')
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
            'tickets' => $tickets,
            'heldClaimTags' => $this->heldClaimTags($active->values()),
            'today' => $today,
            'todayTotal' => (float) $today->whereNull('voided_at')->sum('amount'),
            'todayCash' => (float) $today->whereNull('voided_at')->where('payment_type', 'cash')->sum('amount'),
            'todayGcash' => (float) $today->whereNull('voided_at')->where('payment_type', 'gcash')->sum('amount'),
        ]);
    }

    public function store(Request $request)
    {
        $branch = $this->branchFor($request);
        abort_unless($branch, 404);

        $rates = BaggageCounter::rates($branch);
        $tagCount = (int) $branch->baggage_tag_count;

        if ($tagCount < 1) {
            throw ValidationException::withMessages([
                'cubicles' => 'No cubicles are set up for this branch yet.',
            ]);
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'cubicles' => ['required', 'array', 'min:1', 'max:'.$tagCount],
            'cubicles.*.number' => ['required', 'integer', 'min:1', 'max:'.$tagCount, 'distinct'],
            'cubicles.*.size' => ['required', Rule::in($rates->keys()->all())],
            'cubicles.*.amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'payment_type' => ['required', Rule::in(['cash', 'gcash'])],
            'payment_reference_no' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'cubicles.required' => 'Add at least one cubicle.',
            'cubicles.*.number.distinct' => 'The same cubicle was added twice.',
            'cubicles.*.number.max' => 'Cubicle #:input does not exist.',
            'cubicles.*.size.required' => 'Pick a size for every cubicle.',
            'cubicles.*.size.in' => 'Pick a size for every cubicle.',
            'cubicles.*.amount.required' => 'Enter the payment for every cubicle.',
            'cubicles.*.amount.min' => 'A cubicle payment cannot be negative.',
            'customer_phone.regex' => 'Enter a valid mobile number.',
        ], [
            'customer_name' => 'customer name',
            'customer_phone' => 'mobile number',
        ]);

        $cubicles = collect($validated['cubicles'])
            ->map(fn (array $cubicle) => [
                'number' => (int) $cubicle['number'],
                'size' => $cubicle['size'],
                'amount' => round((float) $cubicle['amount'], 2),
            ])
            ->values();

        $customerName = trim($validated['customer_name']);
        $customerPhone = filled($validated['customer_phone'] ?? null) ? trim($validated['customer_phone']) : null;

        $deposits = DB::transaction(function () use ($request, $branch, $rates, $validated, $cubicles, $customerName, $customerPhone) {
            // Serialize per branch so two cashiers cannot hand out the same cubicle.
            Branch::query()->whereKey($branch->id)->lockForUpdate()->first();

            $active = BaggageDeposit::query()->active()->where('branch_id', $branch->id)->get();

            $taken = $cubicles->pluck('number')->intersect($active->pluck('tag_number'))->values();
            if ($taken->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'cubicles' => 'Cubicle '.$taken->map(fn ($n) => '#'.BaggageDeposit::pad($n))->join(', ', ' and ').' is already in use. Pick a free cubicle.',
                ]);
            }

            // The claim tag is the first cubicle whose tag card is not still out with another customer.
            $held = $this->heldClaimTags($active);
            $claimTag = $cubicles->pluck('number')->first(fn (int $number) => ! in_array($number, $held, true));
            if (! $claimTag) {
                throw ValidationException::withMessages([
                    'cubicles' => 'The claim tag of '.($cubicles->count() === 1 ? 'this cubicle is' : 'these cubicles are').' still with another customer. Add a different cubicle.',
                ]);
            }

            $user = $request->user();
            $claimLabel = BaggageDeposit::pad($claimTag);
            $jobOrders = app(JobOrderController::class);
            $customer = $this->customerFor($branch, $customerName, $customerPhone);
            $settings = SystemSetting::current();
            $vatRate = $settings->vat_enabled ? (float) $settings->vat_rate / 100 : 0;
            $now = now();

            $lines = $cubicles->map(fn (array $cubicle) => $cubicle + [
                'tax' => round($cubicle['amount'] * $vatRate, 2),
                'service' => $rates->get($cubicle['size']),
            ]);

            $subtotal = round((float) $lines->sum('amount'), 2);
            $tax = round((float) $lines->sum('tax'), 2);
            $total = $subtotal + $tax;
            $cubicleList = $cubicles->map(fn ($c) => BaggageDeposit::pad($c['number']))->join(', ');

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
                'notes' => "Baggage tag #{$claimLabel} (cubicle {$cubicleList})"
                    .(filled($validated['notes'] ?? null) ? ' - '.$validated['notes'] : ''),
                'completed_at' => $now,
                'released_at' => $now,
                'inventory_deducted_at' => $now,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'laundry_service_id' => $line['service']->id,
                    'description' => $line['service']->name.' (Cubicle #'.BaggageDeposit::pad($line['number']).')',
                    'service_category' => $line['service']->report_category,
                    'quantity' => 1,
                    'unit_price' => $line['amount'],
                    'total' => $line['amount'],
                ]);
            }

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

            $ticketCode = (string) Str::uuid();

            $deposits = $lines->map(fn (array $line) => BaggageDeposit::create([
                'branch_id' => $branch->id,
                'job_order_id' => $order->id,
                'ticket_code' => $ticketCode,
                'tag_number' => $line['number'],
                'claim_tag' => $claimTag,
                'customer_id' => $customer->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'size' => $line['size'],
                'amount' => $line['amount'] + $line['tax'],
                'payment_type' => $validated['payment_type'],
                'deposited_at' => $now,
                'due_at' => $now->copy()->addHours(BaggageCounter::hours($line['size'])),
                'deposited_by' => $user->id,
                'notes' => $validated['notes'] ?? null,
            ]));

            Activity::log($request, 'baggage_deposited', $deposits->first(), [
                'claim_tag' => $claimLabel,
                'cubicles' => $cubicleList,
                'customer' => $customerName,
                'amount' => $total,
                'job_order_number' => $order->job_order_number,
            ], $branch->id);

            return $deposits;
        });

        $first = $deposits->first();
        $count = $deposits->count();

        return $this->backToCounter($branch)->with('success', sprintf(
            '%s stored in %s %s. Give the customer claim tag #%s.',
            $first->customer_name,
            $count === 1 ? 'cubicle' : $count.' cubicles',
            $deposits->map(fn (BaggageDeposit $d) => '#'.$d->tagLabel())->join(', '),
            $first->claimLabel(),
        ));
    }

    /** Claim one cubicle. The rest of the ticket stays at the counter. */
    public function release(Request $request, BaggageDeposit $deposit)
    {
        $this->authorizeDeposit($request, $deposit);
        abort_unless($deposit->isActive(), 422, 'This cubicle was already claimed.');

        $this->releaseDeposits($request, collect([$deposit]));

        $left = $this->ticketOf($deposit)->filter->isActive()->count();

        return $this->backToCounter($deposit->branch)->with('success', "Cubicle #{$deposit->tagLabel()} claimed and free again."
            .($left > 0
                ? " {$left} more ".Str::plural('cubicle', $left)." still under tag #{$deposit->claimLabel()}."
                : " Take back claim tag #{$deposit->claimLabel()}."));
    }

    /** Claim every cubicle still at the counter under this ticket and free them all. */
    public function releaseAll(Request $request, BaggageDeposit $deposit)
    {
        $this->authorizeDeposit($request, $deposit);

        $active = $this->ticketOf($deposit)->filter->isActive()->values();
        abort_if($active->isEmpty(), 422, 'Every cubicle of this ticket was already claimed.');

        $this->releaseDeposits($request, $active);

        return $this->backToCounter($deposit->branch)->with('success', sprintf(
            'All %s under tag #%s claimed (%s) and free again. Take back the claim tag.',
            $active->count() === 1 ? 'cubicle' : $active->count().' cubicles',
            $deposit->claimLabel(),
            $active->map(fn (BaggageDeposit $d) => '#'.$d->tagLabel())->join(', '),
        ));
    }

    /** Void the whole ticket (entered by mistake): the sale is cancelled and every cubicle freed. */
    public function void(Request $request, BaggageDeposit $deposit)
    {
        $this->authorizeDeposit($request, $deposit);
        abort_unless($deposit->isActive(), 422, 'Only bags still at the counter can be voided.');

        $ticket = $this->ticketOf($deposit);
        abort_if(
            $ticket->contains(fn (BaggageDeposit $d) => $d->released_at),
            422,
            'Some cubicles of this ticket were already claimed, so it can no longer be voided.'
        );

        DB::transaction(function () use ($request, $deposit, $ticket) {
            $order = $deposit->jobOrder;

            if ($order && $order->status !== 'cancelled') {
                // Same as cancelling the sale from Job Orders: the payment drops out of the totals.
                app(JobOrderController::class)->cancel($request, $order);
            }

            BaggageDeposit::query()->whereKey($ticket->modelKeys())->active()->update(['voided_at' => now()]);

            Activity::log($request, 'baggage_voided', $deposit, [
                'claim_tag' => $deposit->claimLabel(),
                'cubicles' => $ticket->map(fn (BaggageDeposit $d) => $d->tagLabel())->join(', '),
                'job_order_number' => $order?->job_order_number,
            ], $deposit->branch_id);
        });

        return $this->backToCounter($deposit->branch)
            ->with('success', "Tag #{$deposit->claimLabel()} voided and the sale cancelled. Its cubicles are free again.");
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

        return $this->backToCounter($branch)->with('success', "Baggage cubicles set to {$validated['baggage_tag_count']}.");
    }

    private function releaseDeposits(Request $request, Collection $deposits): void
    {
        $now = now();

        foreach ($deposits as $deposit) {
            $wasOverdue = $deposit->isOverdue();

            $deposit->update([
                'released_at' => $now,
                'released_by' => $request->user()->id,
            ]);

            Activity::log($request, 'baggage_released', $deposit, [
                'cubicle' => $deposit->tagLabel(),
                'claim_tag' => $deposit->claimLabel(),
                'overdue' => $wasOverdue,
            ], $deposit->branch_id);
        }
    }

    /** All non-voided cubicles stored under the same ticket as $deposit. */
    private function ticketOf(BaggageDeposit $deposit): Collection
    {
        if (! $deposit->ticket_code) {
            return collect([$deposit->fresh()]);
        }

        return BaggageDeposit::query()
            ->where('ticket_code', $deposit->ticket_code)
            ->whereNull('voided_at')
            ->orderBy('tag_number')
            ->get();
    }

    /**
     * Claim tag numbers still out with a customer (their ticket has a cubicle at the counter).
     *
     * @return array<int, int>
     */
    private function heldClaimTags(Collection $activeDeposits): array
    {
        return $activeDeposits
            ->map(fn (BaggageDeposit $d) => (int) ($d->claim_tag ?: $d->tag_number))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** The customer for this name / number (matched by number first); created when new. */
    private function customerFor(Branch $branch, string $name, ?string $phone): Customer
    {
        $customer = $phone
            ? Customer::query()->where('branch_id', $branch->id)->where('phone', $phone)->first()
            : Customer::query()->where('branch_id', $branch->id)->whereNull('phone')->where('name', $name)->first();

        return $customer ?? Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => $name,
            'phone' => $phone,
            'billing_type' => 'regular',
            'unpaid_limit' => 0,
            'is_active' => true,
        ]);
    }

    private function depositPayload(BaggageDeposit $deposit): array
    {
        return [
            'id' => $deposit->id,
            'ticket_code' => $deposit->ticket_code,
            'claim_tag' => (int) ($deposit->claim_tag ?: $deposit->tag_number),
            'claim_label' => $deposit->claimLabel(),
            'customer_name' => $deposit->customer_name,
            'customer_phone' => $deposit->customer_phone,
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
            'release_all_url' => route('admin.baggage.release-all', $deposit),
            'void_url' => route('admin.baggage.void', $deposit),
        ];
    }

    /** One customer's ticket: its cubicles (claimed ones too) and the total paid. */
    private function ticketPayload(Collection $deposits): array
    {
        $first = $deposits->first();

        return [
            'code' => $first->ticket_code,
            'claim_label' => $first->claimLabel(),
            'customer_name' => $first->customer_name,
            'customer_phone' => $first->customer_phone,
            'payment_type' => $first->payment_type,
            'total' => round((float) $deposits->sum('amount'), 2),
            'job_order_number' => $first->jobOrder?->job_order_number,
            'job_order_url' => $first->job_order_id ? route('admin.job-orders.show', $first->job_order_id) : null,
            'can_void' => ! $deposits->contains(fn (BaggageDeposit $d) => $d->released_at),
            'cubicles' => $deposits->sortBy('tag_number')->map(fn (BaggageDeposit $d) => [
                'number' => $d->tag_number,
                'label' => $d->tagLabel(),
                'size_label' => BaggageCounter::label($d->size),
                'amount' => (float) $d->amount,
                'active' => $d->isActive(),
                'released_at_label' => $d->released_at?->format('M d, h:i A'),
                'released_by' => $d->releaser?->name,
            ])->values(),
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
