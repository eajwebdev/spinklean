<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per delivery trip (Delivery Receipt) to a PO customer.
        Schema::create('po_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('dr_number', 100);
            $table->date('delivered_on');
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'dr_number']);
            $table->index(['customer_id', 'delivered_on']);
        });

        // The pieces delivered on a DR. A DR can carry part of several PO job orders.
        Schema::create('po_delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_delivery_id')->constrained('po_deliveries')->cascadeOnDelete();
            $table->foreignId('po_transaction_id')->constrained('po_transactions')->cascadeOnDelete();
            $table->foreignId('job_order_id')->nullable()->constrained('job_orders')->nullOnDelete();
            $table->string('description');
            $table->string('service_category')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            // What the customer is billed for this line, after the order's discount and tax.
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index('po_transaction_id');
        });

        $this->backfillDeliveredPurchaseOrders();
    }

    public function down(): void
    {
        Schema::dropIfExists('po_delivery_items');
        Schema::dropIfExists('po_deliveries');
    }

    /**
     * Before DRs, statements billed a PO on its order date, and finished PO orders were usually
     * left at Ready for Pickup / Ready for Delivery rather than released. So every finished PO
     * gets one DR for everything, dated on its recorded delivery date or else its order date,
     * and past statements come out exactly as before. Orders still being processed get none:
     * they are billed when they go out on a DR.
     */
    private function backfillDeliveredPurchaseOrders(): void
    {
        $purchaseOrders = DB::table('po_transactions')
            ->join('job_orders', 'job_orders.id', '=', 'po_transactions.job_order_id')
            ->whereNull('job_orders.deleted_at')
            ->where('job_orders.status', '!=', 'cancelled')
            ->where(fn ($query) => $query
                ->whereIn('job_orders.status', ['ready_for_pickup', 'ready_for_delivery', 'completed'])
                ->orWhereNotNull('po_transactions.date_delivered'))
            ->orderBy('po_transactions.id')
            ->get([
                'po_transactions.id', 'po_transactions.branch_id', 'po_transactions.customer_id', 'po_transactions.job_order_id',
                'po_transactions.amount', 'po_transactions.dr_number', 'po_transactions.date_delivered', 'po_transactions.transaction_date',
            ]);

        $deliveryIds = [];

        foreach ($purchaseOrders as $po) {
            $deliveredOn = Carbon::parse($po->date_delivered ?: $po->transaction_date)->toDateString();
            $drNumber = filled($po->dr_number) ? trim($po->dr_number) : 'OLD-'.$po->id;
            $key = $po->branch_id.'|'.$drNumber;

            // POs that already shared one DR number stay on one DR.
            if (! isset($deliveryIds[$key])) {
                $existing = DB::table('po_deliveries')->where('branch_id', $po->branch_id)->where('dr_number', $drNumber)->value('id');
                $deliveryIds[$key] = $existing ?: DB::table('po_deliveries')->insertGetId([
                    'branch_id' => $po->branch_id,
                    'customer_id' => $po->customer_id,
                    'dr_number' => $drNumber,
                    'delivered_on' => $deliveredOn,
                    'amount' => 0,
                    'notes' => 'Recorded automatically for an order finished before Delivery Receipts were added.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $items = DB::table('job_order_items')->where('job_order_id', $po->job_order_id)->orderBy('id')->get();
            $itemsTotal = (float) $items->sum('total');
            $billed = 0.0;

            foreach ($items->values() as $index => $item) {
                $amount = $index === $items->count() - 1
                    ? round((float) $po->amount - $billed, 2)
                    : round($itemsTotal > 0 ? (float) $item->total * (float) $po->amount / $itemsTotal : 0, 2);
                $billed += $amount;

                DB::table('po_delivery_items')->insert([
                    'po_delivery_id' => $deliveryIds[$key],
                    'po_transaction_id' => $po->id,
                    'job_order_id' => $po->job_order_id,
                    'description' => $item->description,
                    'service_category' => $item->service_category,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'total' => $item->total,
                    'amount' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

        }

        foreach (array_unique($deliveryIds) as $deliveryId) {
            DB::table('po_deliveries')->where('id', $deliveryId)->update([
                'amount' => DB::table('po_delivery_items')->where('po_delivery_id', $deliveryId)->sum('amount'),
            ]);
        }
    }
};
