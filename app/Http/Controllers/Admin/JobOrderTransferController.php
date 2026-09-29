<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\JobOrder;
use App\Models\JobOrderTransfer;
use App\Support\Activity;
use App\Support\TagValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobOrderTransferController extends Controller
{
    /**
     * Get pending incoming transfers for machine-equipped branch.
     */
    public function pendingIncoming(Request $request)
    {
        $user = $request->user();
        $branchId = $this->resolveBranchId($request);
        $search = trim((string) $request->input('search'));

        $query = JobOrderTransfer::query()
            ->with([
                'jobOrder.customer:id,name,phone,billing_type',
                'jobOrder.items:id,job_order_id,description,quantity,unit_price,total',
                'jobOrder.payments:id,job_order_id,amount,payment_type',
                'originBranch:id,name,code',
                'destinationBranch:id,name,code',
                'transferredBy:id,name',
            ])
            ->where('destination_branch_id', $branchId)
            ->where('transfer_status', 'pending')
            ->where('transfer_type', 'outbound')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('tag_number', 'like', "%{$search}%")
                        ->orWhere('job_order_number', 'like', "%{$search}%")
                        ->orWhereHas('originBranch', fn ($b) => $b->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('jobOrder.customer', function ($c) use ($search) {
                            $c->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('transferred_at');

        if ($request->wantsJson() || $request->ajax()) {
            $transfers = $query->get()->map(function (JobOrderTransfer $t) {
                $order = $t->jobOrder;
                $paymentStatus = 'Unpaid';
                if ($order) {
                    if ((float) $order->balance <= 0) {
                        $paymentStatus = 'Paid';
                    } elseif ((float) $order->paid_amount > 0) {
                        $paymentStatus = 'Partial (₱'.number_format($order->paid_amount, 2).')';
                    }
                }

                $servicesList = $order?->items?->map(fn ($i) => $i->description)->filter()->implode(', ') ?: 'Standard Laundry';

                return [
                    'id' => $t->id,
                    'job_order_id' => $t->job_order_id,
                    'tag_number' => $t->tag_number ?: 'N/A',
                    'job_order_number' => $t->job_order_number,
                    'customer_name' => $order?->customer?->name ?? 'Walk-in Customer',
                    'customer_phone' => $order?->customer?->phone ?? 'N/A',
                    'origin_branch_name' => $t->originBranch?->name ?? 'Branch',
                    'services' => $servicesList,
                    'total' => (float) ($order?->total ?? 0),
                    'balance' => (float) ($order?->balance ?? 0),
                    'payment_status' => $paymentStatus,
                    'transferred_at' => $t->transferred_at?->format('M d, Y h:i A') ?? $t->created_at?->format('M d, Y h:i A'),
                    'order_date' => $order?->created_at?->format('M d, Y') ?? 'N/A',
                ];
            });

            return response()->json([
                'success' => true,
                'count' => $transfers->count(),
                'transfers' => $transfers,
            ]);
        }

        $transfers = $query->paginate(20)->withQueryString();

        return view('admin.transfers.incoming', compact('transfers'));
    }

    /**
     * Get count of pending incoming transfers for TAG badge.
     */
    public function countPending(Request $request)
    {
        $branchId = $this->resolveBranchId($request);

        $count = JobOrderTransfer::query()
            ->where('destination_branch_id', $branchId)
            ->where('transfer_status', 'pending')
            ->where('transfer_type', 'outbound')
            ->count();

        return response()->json([
            'count' => $count,
            'pending_count' => $count,
        ]);
    }

    /**
     * Receive one or multiple incoming transfers and add to Cycle Monitoring.
     */
    public function receive(Request $request)
    {
        $user = $request->user();
        $branchId = $this->resolveBranchId($request);
        $branch = Branch::query()->findOrFail($branchId);

        abort_unless($branch->isMachineEquipped(), 403, 'Only machine-equipped branches can receive laundry for cycle monitoring.');

        $validated = $request->validate([
            'transfer_ids' => ['required', 'array', 'min:1'],
            'transfer_ids.*' => ['required', 'integer'],
        ]);

        $receivedCount = 0;

        DB::transaction(function () use ($validated, $branchId, $user, $request, &$receivedCount) {
            $transfers = JobOrderTransfer::query()
                ->whereIn('id', $validated['transfer_ids'])
                ->lockForUpdate()
                ->get();

            foreach ($transfers as $transfer) {
                // Must be destination branch
                if ((int) $transfer->destination_branch_id !== (int) $branchId) {
                    throw ValidationException::withMessages([
                        'transfer_ids' => "Transfer #{$transfer->id} is not assigned to this branch.",
                    ]);
                }

                // Check if already received
                if ($transfer->transfer_status === 'received') {
                    $tagLabel = $transfer->tag_number ? "Tag #{$transfer->tag_number}" : "Order #{$transfer->job_order_number}";
                    throw ValidationException::withMessages([
                        'transfer_ids' => "{$tagLabel} has already been received by this branch.",
                    ]);
                }

                $jobOrder = JobOrder::query()
                    ->lockForUpdate()
                    ->find($transfer->job_order_id);

                if (! $jobOrder) {
                    continue;
                }

                // Mark transfer received
                $transfer->update([
                    'transfer_status' => 'received',
                    'received_at' => now(),
                    'received_by' => $user->id,
                ]);

                // Update job order
                $jobOrder->update([
                    'current_branch_id' => $branchId,
                    'release_branch_id' => $branchId,
                    'production_accepted_at' => $jobOrder->production_accepted_at ?: now(),
                    'status' => in_array($jobOrder->status, ['pending', 'received'], true) ? 'received' : $jobOrder->status,
                ]);

                // Deduct inventory at processing branch if not already deducted
                if (! $jobOrder->inventory_deducted_at) {
                    $jobOrder->loadMissing('items');
                    app(JobOrderController::class)->deductInventoryForOrder(
                        $jobOrder,
                        $jobOrder->items->map(fn ($i) => ['laundry_service_id' => $i->laundry_service_id, 'quantity' => (float) $i->quantity])->all(),
                        $user->id
                    );
                    $jobOrder->update(['inventory_deducted_at' => now()]);
                }

                Activity::log($request, 'transfer_received_and_added_to_cycles', $jobOrder, [
                    'job_order_number' => $jobOrder->job_order_number,
                    'tag_number' => $jobOrder->tag_number,
                    'origin_branch_id' => $transfer->origin_branch_id,
                    'receiving_branch_id' => $branchId,
                    'transfer_id' => $transfer->id,
                ], $branchId);

                $receivedCount++;
            }
        });

        $message = "{$receivedCount} laundry ".\Illuminate\Support\Str::plural('order', $receivedCount).' received and added to Cycle Monitoring.';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'received_count' => $receivedCount,
            ]);
        }

        return redirect()->route('admin.cycles.index')->with('success', $message);
    }

    /**
     * Receive returned laundry at drop-off / pickup branch.
     */
    public function receiveReturn(Request $request, JobOrderTransfer $transfer)
    {
        $user = $request->user();
        $branchId = (int) ($user->branch_id ?: $transfer->destination_branch_id);

        abort_unless($user->canManageAllBranches() || (int) $transfer->destination_branch_id === $branchId, 403);
        abort_unless($transfer->transfer_type === 'return', 422, 'Invalid transfer type.');

        if ($transfer->transfer_status === 'received') {
            return back()->with('info', "Tag #{$transfer->tag_number} was already marked as received.");
        }

        DB::transaction(function () use ($transfer, $user, $request) {
            $transfer->update([
                'transfer_status' => 'received',
                'received_at' => now(),
                'received_by' => $user->id,
            ]);

            $jobOrder = $transfer->jobOrder;
            if ($jobOrder) {
                $jobOrder->update([
                    'current_branch_id' => $transfer->destination_branch_id,
                    'release_branch_id' => $transfer->destination_branch_id,
                    'returned_received_at' => now(),
                    'status' => $jobOrder->transaction_type === 'delivery' ? 'ready_for_delivery' : 'ready_for_pickup',
                ]);

                Activity::log($request, 'returned_laundry_received_at_branch', $jobOrder, [
                    'job_order_number' => $jobOrder->job_order_number,
                    'tag_number' => $jobOrder->tag_number,
                    'origin_branch_id' => $transfer->origin_branch_id,
                    'branch_id' => $transfer->destination_branch_id,
                ], $transfer->destination_branch_id);
            }
        });

        return back()->with('success', "Laundry (Tag #{$transfer->tag_number}) received back and ready for customer pickup.");
    }

    /**
     * Real-time Tag availability checker for frontend.
     */
    public function checkTag(Request $request)
    {
        $tag = trim((string) ($request->input('tag') ?? $request->input('tag_number') ?? ''));

        if ($tag === '') {
            return response()->json([
                'available' => false,
                'message' => 'Tag number cannot be empty.',
            ]);
        }

        $ignoreId = $request->integer('ignore_id') ?: null;
        $error = TagValidator::checkAvailability($tag, $ignoreId);

        return response()->json([
            'available' => is_null($error),
            'message' => $error ?? 'Tag number is available.',
        ]);
    }

    private function resolveBranchId(Request $request): int
    {
        $user = $request->user();

        if ($user->canManageAllBranches() && $request->filled('branch_id')) {
            return (int) $request->integer('branch_id');
        }

        return (int) ($user->branch_id ?: Branch::where('is_active', true)->where('branch_type', 'full_service')->value('id'));
    }
}
