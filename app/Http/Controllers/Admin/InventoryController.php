<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Supplier;
use App\Support\Activity;
use App\Support\InventoryAddOns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    private const STOCK_FILTERS = ['low', 'ok'];
    private const STATUS_FILTERS = ['active', 'inactive'];

    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->isAdmin();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $selectedBranchId = $canChooseBranch
            ? ($request->integer('branch_id') ?: $branches->first()?->id)
            : $user->branch_id;

        $baseQuery = Inventory::query()
            ->with(['branch', 'supplier'])
            ->where('branch_id', $selectedBranchId)
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->supplier_id))
            ->when(in_array($request->stock_status, self::STOCK_FILTERS, true), function ($query) use ($request) {
                if ($request->stock_status === 'low') {
                    $query->whereColumn('quantity', '<=', 'reorder_level');
                }

                if ($request->stock_status === 'ok') {
                    $query->whereColumn('quantity', '>', 'reorder_level');
                }
            })
            ->when(in_array($request->status, self::STATUS_FILTERS, true), fn ($query) => $query->where('is_active', $request->status === 'active'))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            });

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as items_count, COALESCE(SUM(quantity * unit_cost), 0) as inventory_value')
            ->first();

        $lowStockCount = (clone $baseQuery)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->count();

        $items = $baseQuery
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $recentMovements = InventoryMovement::query()
            ->with(['inventory', 'user'])
            ->whereHas('inventory', fn ($query) => $query->where('branch_id', $selectedBranchId))
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.inventory.index', compact(
            'branches',
            'canChooseBranch',
            'items',
            'lowStockCount',
            'recentMovements',
            'selectedBranchId',
            'summary',
            'suppliers'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->inventoryRules());
        $validated = $this->normalizeBranch($request, $validated);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated = $this->normalizeAddOn($request, $validated);

        DB::transaction(function () use ($request, $validated) {
            $item = Inventory::create($validated);
            InventoryAddOns::sync($item);

            if ((float) $item->quantity > 0) {
                $item->movements()->create([
                    'user_id' => $request->user()->id,
                    'movement_type' => 'adjustment',
                    'quantity' => $item->quantity,
                    'remarks' => 'Opening stock',
                ]);
            }

            Activity::log($request, 'inventory_created', $item, [
                'name' => $item->name,
                'quantity' => $item->quantity,
            ], $item->branch_id);
        });

        return back()->with('success', 'Inventory item created successfully.');
    }

    public function update(Request $request, Inventory $inventory)
    {
        $this->authorizeInventory($request, $inventory);

        $validated = $request->validate($this->inventoryRules());
        $validated = $this->normalizeBranch($request, $validated);
        $validated['is_active'] = $request->boolean('is_active');
        $validated = $this->normalizeAddOn($request, $validated);

        DB::transaction(function () use ($inventory, $validated) {
            $inventory->update($validated);
            InventoryAddOns::sync($inventory);
        });

        Activity::log($request, 'inventory_updated', $inventory, [
            'name' => $inventory->name,
            'quantity' => $inventory->quantity,
        ], $inventory->branch_id);

        return back()->with('success', 'Inventory item updated successfully.');
    }

    public function destroy(Request $request, Inventory $inventory)
    {
        $this->authorizeInventory($request, $inventory);
        DB::transaction(function () use ($inventory) {
            $inventory->delete();
            InventoryAddOns::sync($inventory); // stop selling it as an add-on
        });

        Activity::log($request, 'inventory_deleted', $inventory, [
            'name' => $inventory->name,
        ], $inventory->branch_id);

        return back()->with('success', 'Inventory item deleted successfully.');
    }

    public function storeMovement(Request $request, Inventory $inventory)
    {
        $this->authorizeInventory($request, $inventory);

        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(['in', 'out', 'adjustment'])],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['movement_type'] === 'out' && (float) $validated['quantity'] > (float) $inventory->quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock out quantity cannot exceed current stock.',
            ]);
        }

        DB::transaction(function () use ($request, $inventory, $validated) {
            $newQuantity = match ($validated['movement_type']) {
                'in' => (float) $inventory->quantity + (float) $validated['quantity'],
                'out' => (float) $inventory->quantity - (float) $validated['quantity'],
                'adjustment' => (float) $validated['quantity'],
            };

            $inventory->movements()->create([
                'user_id' => $request->user()->id,
                'movement_type' => $validated['movement_type'],
                'quantity' => $validated['quantity'],
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $inventory->update(['quantity' => max($newQuantity, 0)]);

            Activity::log($request, 'inventory_movement_recorded', $inventory, [
                'name' => $inventory->name,
                'movement_type' => $validated['movement_type'],
                'quantity' => $validated['quantity'],
            ], $inventory->branch_id);
        });

        return back()->with('success', 'Stock movement recorded successfully.');
    }

    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $supplier = Supplier::create($validated);

        Activity::log($request, 'supplier_created', $supplier, [
            'name' => $supplier->name,
        ]);

        return back()->with('success', 'Supplier created successfully.');
    }

    private function inventoryRules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_add_on' => ['nullable', 'boolean'],
            'add_on_type' => ['nullable', 'required_if_accepted:is_add_on', Rule::in(array_keys(InventoryAddOns::TYPES))],
            'selling_price' => ['nullable', 'required_if_accepted:is_add_on', 'numeric', 'min:0'],
        ];
    }

    private function normalizeAddOn(Request $request, array $validated): array
    {
        $validated['is_add_on'] = $request->boolean('is_add_on');

        if (! $validated['is_add_on']) {
            // Keep the last type / price so re-enabling the add-on restores them.
            unset($validated['add_on_type'], $validated['selling_price']);
        }

        return $validated;
    }

    private function normalizeBranch(Request $request, array $validated): array
    {
        if (! $request->user()->isAdmin()) {
            if (! $request->user()->branch_id) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Your account is not assigned to a branch yet.',
                ]);
            }

            $validated['branch_id'] = $request->user()->branch_id;
        }

        return $validated;
    }

    private function authorizeInventory(Request $request, Inventory $inventory): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless((int) $request->user()->branch_id === (int) $inventory->branch_id, 403);
    }
}
