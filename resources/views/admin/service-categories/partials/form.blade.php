<form method="POST" action="{{ $action }}" x-data="{ visibility: '{{ old('visibility', $category->visibility ?? 'all') }}' }" class="space-y-4">
    @csrf
    @if($method !== 'POST') @method($method) @endif

    <div>
        <label class="mb-1.5 block text-sm font-medium">Category Name</label>
        <input name="name" value="{{ old('name', $category->name) }}" required placeholder="e.g. Small Machine" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium">Visibility</label>
        <select name="visibility" x-model="visibility" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
            <option value="all">Both Branches</option>
            <option value="branch">Specific Branch Only</option>
        </select>
    </div>

    <div x-show="visibility === 'branch'" x-cloak>
        <label class="mb-1.5 block text-sm font-medium">Branch</label>
        <select name="branch_id" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
            <option value="">— Select Branch —</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id', $category->branch_id) == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium">Sort Order</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        <p class="mt-1 text-xs text-muted">Lower number = appears first in the POS tabs.</p>
    </div>

    <div>
        <label class="inline-flex h-9 items-center gap-2 text-sm text-muted">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) class="rounded border-border text-primary">
            Active (visible in POS)
        </label>
    </div>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="flex justify-end">
        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">Save Category</button>
    </div>
</form>
