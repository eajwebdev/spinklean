<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if($method !== 'POST') @method($method) @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-sm font-medium">Item Name</label>
            <input name="name" value="{{ old('name', $item->name) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Branch</label>
            @if($canChooseBranch)
                <select name="branch_id" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', $item->branch_id ?: $selectedBranchId) == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
                <input value="{{ auth()->user()->branch?->name }}" disabled class="h-9 w-full rounded-md border border-border bg-smoke px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
            @endif
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Supplier</label>
            <select name="supplier_id" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                <option value="">No supplier</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id', $item->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">SKU</label>
            <input name="sku" value="{{ old('sku', $item->sku) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Unit</label>
            <input name="unit" value="{{ old('unit', $item->unit ?: 'pcs') }}" required placeholder="pcs, kg, pack, bottle" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Current Stock</label>
            <input type="number" step="0.01" min="0" name="quantity" value="{{ old('quantity', $item->quantity ?? 0) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Reorder Level</label>
            <input type="number" step="0.01" min="0" name="reorder_level" value="{{ old('reorder_level', $item->reorder_level ?? 0) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Unit Cost</label>
            <input type="number" step="0.01" min="0" name="unit_cost" value="{{ old('unit_cost', $item->unit_cost ?? 0) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div class="md:col-span-2">
            <label class="inline-flex h-9 items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active)) class="rounded border-border text-primary">
                Active item
            </label>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="flex justify-end">
        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Save Item</button>
    </div>
</form>
