<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if($method !== 'POST') @method($method) @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-sm font-medium">Name</label>
            <input name="name" value="{{ old('name', $service->name) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Branch</label>
            @if(! (auth()->user()->isSuperAdmin() || auth()->user()->role === 'admin'))
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
                <input value="{{ auth()->user()->branch?->name }}" disabled class="h-9 w-full rounded-md border border-border bg-smoke px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
            @else
                <select name="branch_id" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', $service->branch_id ?: $selectedBranchId) == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Pricing Type</label>
            <select name="pricing_type" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                @foreach(['kilo', 'load', 'piece', 'custom'] as $type)
                    <option value="{{ $type }}" @selected(old('pricing_type', $service->pricing_type) === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Category</label>
            <select name="service_category_id" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                <option value="">— No Category —</option>
                @foreach($serviceCategories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('service_category_id', $service->service_category_id) == $cat->id)>
                        {{ $cat->name }}{{ $cat->visibility === 'branch' ? ' (Branch only)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Price</label>
            <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $service->price ?? 0) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Z Reading Column</label>
            <select name="report_category" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                @foreach(\App\Support\ServiceCategories::LABELS as $key => $label)
                    <option value="{{ $key }}" @selected(old('report_category', $service->report_category ?: \App\Support\ServiceCategories::infer($service->name)) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="inline-flex h-9 items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active)) class="rounded border-border text-primary">
                Active service
            </label>
        </div>
    </div>

    <div>
        <div class="mb-2">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold">Inventory Consumption</h3>
                <a href="{{ route('admin.inventory.index', ['branch_id' => $service->branch_id ?: $selectedBranchId]) }}" target="_blank" class="text-xs font-medium text-primary hover:underline">View Inventory</a>
            </div>
            <p class="text-xs text-muted">Quantity deducted from stock for every 1 service quantity sold. Leave zero when no stock is consumed.</p>
        </div>
        <div class="grid max-h-56 gap-2 overflow-y-auto rounded-md border border-border p-2 dark:border-gray-800 sm:grid-cols-2">
            @forelse($inventoryItems as $inventory)
                @php
                    $savedQuantity = $service->inventoryUsages?->firstWhere('inventory_id', $inventory->id)?->quantity ?? 0;
                @endphp
                <label class="grid grid-cols-[minmax(0,1fr)_7rem] items-center gap-2 rounded-md bg-smoke p-2 dark:bg-gray-950">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium">{{ $inventory->name }}</span>
                        <span class="text-xs text-muted">{{ number_format((float) $inventory->quantity, 2) }} {{ $inventory->unit }} in stock</span>
                    </span>
                    <input
                        name="inventory_usages[{{ $inventory->id }}]"
                        value="{{ old('inventory_usages.'.$inventory->id, $savedQuantity) }}"
                        type="number"
                        min="0"
                        step="0.0001"
                        class="h-9 w-full rounded-md border border-border bg-white px-2 text-right text-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                </label>
            @empty
                <p class="col-span-full p-3 text-center text-sm text-muted">No active inventory items are configured for this branch.</p>
            @endforelse
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="flex justify-end">
        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Save Service</button>
    </div>
</form>
