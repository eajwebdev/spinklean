<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if($method !== 'POST') @method($method) @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-sm font-medium">Preset Name</label>
            <input name="name" value="{{ old('name', $preset->name) }}" required placeholder="e.g. Full Wash Package" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Branch</label>
            @if(! (auth()->user()->isSuperAdmin() || auth()->user()->role === 'admin'))
                <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}">
                <input value="{{ auth()->user()->branch?->name }}" disabled class="h-9 w-full rounded-md border border-border bg-smoke px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
            @else
                <select name="branch_id" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', $preset->branch_id ?: $selectedBranchId) == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Catalog Category</label>
            <select name="service_category_id" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                <option value="">No category</option>
                @foreach($serviceCategories as $cat)
                    @continue($cat->visibility === 'branch' && (string) $cat->branch_id !== (string) ($preset->branch_id ?: $selectedBranchId))
                    <option value="{{ $cat->id }}" @selected(old('service_category_id', $preset->service_category_id) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Sort Order</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $preset->sort_order ?? 0) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>
    </div>

    <div>
        <div class="mb-2 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold">Included Services</h3>
            <label class="inline-flex h-8 items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $preset->is_active ?? true)) class="rounded border-border text-primary">
                Active
            </label>
        </div>
        <div class="grid max-h-64 gap-2 overflow-y-auto rounded-md border border-border p-2 dark:border-gray-800 sm:grid-cols-2">
            @forelse($presetServices as $serviceOption)
                @php($savedQuantity = $preset->items?->firstWhere('laundry_service_id', $serviceOption->id)?->quantity ?? 0)
                <label class="grid grid-cols-[minmax(0,1fr)_6rem] items-center gap-2 rounded-md bg-smoke p-2 dark:bg-gray-950">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium">{{ $serviceOption->name }}</span>
                        <span class="text-xs text-muted">{{ ucfirst($serviceOption->pricing_type) }} · {{ $appSettings?->currency ?? 'PHP' }} {{ number_format((float) $serviceOption->price, 2) }}</span>
                    </span>
                    <input
                        name="items[{{ $serviceOption->id }}]"
                        value="{{ old('items.'.$serviceOption->id, $savedQuantity) }}"
                        type="number"
                        min="0"
                        step="0.01"
                        class="h-9 w-full rounded-md border border-border bg-white px-2 text-right text-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                </label>
            @empty
                <p class="col-span-full p-3 text-center text-sm text-muted">Add services for this branch before creating a preset.</p>
            @endforelse
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="flex justify-end">
        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Save Preset</button>
    </div>
</form>
