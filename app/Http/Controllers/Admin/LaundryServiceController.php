<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\LaundryService;
use App\Models\LaundryServiceCategory;
use App\Models\ServicePreset;
use App\Support\Activity;
use App\Support\ServiceCategories;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class LaundryServiceController extends Controller
{
    private const PRICING_TYPES = ['kilo', 'load', 'piece', 'custom'];
    private const STATUS_FILTERS = ['active', 'inactive'];

    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $this->canChooseBranch($user);

        $branches = Branch::where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $selectedBranchId = $canChooseBranch
            ? ($request->integer('branch_id') ?: $branches->first()?->id)
            : $user->branch_id;

        $services = LaundryService::with(['branch', 'inventoryUsages'])
            ->where('branch_id', $selectedBranchId)
            ->when(in_array($request->pricing_type, self::PRICING_TYPES, true), fn ($query) => $query->where('pricing_type', $request->pricing_type))
            ->when(in_array($request->status, self::STATUS_FILTERS, true), fn ($query) => $query->where('is_active', $request->status === 'active'))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();
        $inventoryItems = Inventory::query()
            ->where('branch_id', $selectedBranchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'quantity']);
        $presetServices = LaundryService::query()
            ->where('branch_id', $selectedBranchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'pricing_type']);

        $serviceCategories = LaundryServiceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'visibility', 'branch_id']);
        $servicePresets = ServicePreset::with(['items.service', 'serviceCategory'])
            ->where('branch_id', $selectedBranchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.services.index', compact('services', 'branches', 'selectedBranchId', 'canChooseBranch', 'inventoryItems', 'presetServices', 'serviceCategories', 'servicePresets'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated = $this->normalizeBranch($validated);
        $validated['is_active'] = $request->boolean('is_active', true);

        $service = DB::transaction(function () use ($validated) {
            $service = LaundryService::create(collect($validated)->except('inventory_usages')->all());
            $this->syncInventoryUsages($service, $validated['inventory_usages'] ?? []);

            return $service;
        });

        Activity::log($request, 'service_created', $service, [
            'name' => $service->name,
            'price' => $service->price,
        ], $service->branch_id);

        return redirect()->route('admin.services.index', ['branch_id' => $service->branch_id])->with('success', 'Service created successfully.');
    }

    public function update(Request $request, LaundryService $service)
    {
        $this->authorizeService($service);

        $validated = $request->validate($this->rules());
        $validated = $this->normalizeBranch($validated);
        $validated['is_active'] = $request->boolean('is_active');

        DB::transaction(function () use ($service, $validated) {
            $service->update(collect($validated)->except('inventory_usages')->all());
            $this->syncInventoryUsages($service, $validated['inventory_usages'] ?? []);
        });

        Activity::log($request, 'service_updated', $service, [
            'name' => $service->name,
            'price' => $service->price,
        ], $service->branch_id);

        return redirect()->route('admin.services.index', ['branch_id' => $service->branch_id])->with('success', 'Service updated successfully.');
    }

    public function destroy(LaundryService $service)
    {
        $this->authorizeService($service);
        $service->delete();

        Activity::log(request(), 'service_deleted', $service, [
            'name' => $service->name,
        ], $service->branch_id);

        return redirect()->route('admin.services.index')->with('success', 'Service deleted successfully.');
    }

    public function storePreset(Request $request)
    {
        $validated = $request->validate($this->presetRules());
        $validated = $this->normalizeBranch($validated);

        $preset = DB::transaction(function () use ($request, $validated) {
            $preset = ServicePreset::create([
                'branch_id' => $validated['branch_id'],
                'service_category_id' => $validated['service_category_id'] ?? null,
                'name' => $validated['name'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->syncPresetItems($preset, $validated['items'] ?? []);

            return $preset;
        });

        return redirect()->route('admin.services.index', ['branch_id' => $preset->branch_id])->with('success', 'Preset created successfully.');
    }

    public function showPreset(ServicePreset $preset)
    {
        $this->authorizePreset($preset);

        return redirect()->route('admin.services.index', [
            'branch_id' => $preset->branch_id,
            'edit_preset' => $preset->id,
        ]);
    }

    public function updatePreset(Request $request, ServicePreset $preset)
    {
        $this->authorizePreset($preset);

        $validated = $request->validate($this->presetRules());
        $validated = $this->normalizeBranch($validated);

        DB::transaction(function () use ($request, $preset, $validated) {
            $preset->update([
                'branch_id' => $validated['branch_id'],
                'service_category_id' => $validated['service_category_id'] ?? null,
                'name' => $validated['name'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->syncPresetItems($preset, $validated['items'] ?? []);
        });

        return redirect()->route('admin.services.index', ['branch_id' => $preset->branch_id])->with('success', 'Preset updated successfully.');
    }

    public function destroyPreset(ServicePreset $preset)
    {
        $this->authorizePreset($preset);
        $branchId = $preset->branch_id;
        $preset->delete();

        return redirect()->route('admin.services.index', ['branch_id' => $branchId])->with('success', 'Preset deleted successfully.');
    }

    private function rules(): array
    {
        return [
            'branch_id'           => ['required', 'exists:branches,id'],
            'name'                => ['required', 'string', 'max:255'],
            'report_category'     => ['nullable', 'string', Rule::in(ServiceCategories::keys())],
            'service_category_id' => ['nullable', 'exists:laundry_service_categories,id'],
            'pricing_type'        => ['required', Rule::in(['kilo', 'load', 'piece', 'custom'])],
            'price'               => ['required', 'numeric', 'min:0'],
            'is_active'           => ['nullable', 'boolean'],
            'inventory_usages'    => ['nullable', 'array'],
            'inventory_usages.*'  => ['nullable', 'numeric', 'min:0', 'max:999999.9999'],
        ];
    }

    private function presetRules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'service_category_id' => ['nullable', 'exists:laundry_service_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    private function syncInventoryUsages(LaundryService $service, array $usages): void
    {
        $quantities = collect($usages)
            ->filter(fn ($quantity) => is_numeric($quantity) && (float) $quantity > 0)
            ->mapWithKeys(fn ($quantity, $inventoryId) => [(int) $inventoryId => (float) $quantity]);

        $validInventoryIds = Inventory::query()
            ->where('branch_id', $service->branch_id)
            ->whereIn('id', $quantities->keys())
            ->pluck('id');

        if ($validInventoryIds->count() !== $quantities->count()) {
            throw ValidationException::withMessages([
                'inventory_usages' => 'Every inventory usage must belong to the selected service branch.',
            ]);
        }

        $service->inventoryUsages()->whereNotIn('inventory_id', $validInventoryIds)->delete();

        foreach ($validInventoryIds as $inventoryId) {
            $service->inventoryUsages()->updateOrCreate(
                ['inventory_id' => $inventoryId],
                ['quantity' => $quantities->get($inventoryId)]
            );
        }
    }

    private function syncPresetItems(ServicePreset $preset, array $items): void
    {
        $quantities = collect($items)
            ->filter(fn ($quantity) => is_numeric($quantity) && (float) $quantity > 0)
            ->mapWithKeys(fn ($quantity, $serviceId) => [(int) $serviceId => (float) $quantity]);

        if ($quantities->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Choose at least one service for this preset.',
            ]);
        }

        $validServiceIds = LaundryService::query()
            ->where('branch_id', $preset->branch_id)
            ->whereIn('id', $quantities->keys())
            ->pluck('id');

        if ($validServiceIds->count() !== $quantities->count()) {
            throw ValidationException::withMessages([
                'items' => 'Every preset service must belong to the selected branch.',
            ]);
        }

        $preset->items()->whereNotIn('laundry_service_id', $validServiceIds)->delete();

        foreach ($validServiceIds as $serviceId) {
            $preset->items()->updateOrCreate(
                ['laundry_service_id' => $serviceId],
                ['quantity' => $quantities->get($serviceId)]
            );
        }
    }

    private function normalizeBranch(array $validated): array
    {
        $user = auth()->user();

        if (! $this->canChooseBranch($user)) {
            if (! $user->branch_id) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Your account is not assigned to a branch yet.',
                ]);
            }

            $validated['branch_id'] = $user->branch_id;
        }

        return $validated;
    }

    private function authorizeService(LaundryService $service): void
    {
        $user = auth()->user();

        if ($this->canChooseBranch($user)) {
            return;
        }

        abort_unless((int) $service->branch_id === (int) $user->branch_id, 403);
    }

    private function authorizePreset(ServicePreset $preset): void
    {
        $user = auth()->user();

        if ($this->canChooseBranch($user)) {
            return;
        }

        abort_unless((int) $preset->branch_id === (int) $user->branch_id, 403);
    }

    private function canChooseBranch($user): bool
    {
        return $user->isSuperAdmin() || $user->role === 'admin';
    }
}
