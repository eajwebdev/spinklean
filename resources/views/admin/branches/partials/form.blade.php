<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-sm font-medium">Branch Name</label>
            <input name="name" value="{{ old('name', $branch->name) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Branch Code</label>
            <input name="code" value="{{ old('code', $branch->code) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Contact Number</label>
            <input name="contact_number" value="{{ old('contact_number', $branch->contact_number) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div class="flex items-end">
            <label class="inline-flex h-9 items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active)) class="rounded border-border text-primary">
                Active branch
            </label>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1.5 block text-sm font-medium">Branch Type</label>
            <select name="branch_type" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950" required>
                <option value="full_service" @selected(old('branch_type', $branch->branch_type ?? 'full_service') === 'full_service')>Full Service - with production/machines</option>
                <option value="pickup_dropoff" @selected(old('branch_type', $branch->branch_type ?? 'full_service') === 'pickup_dropoff')>Pickup & Drop-off Only - no machines</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1.5 block text-sm font-medium">Address</label>
            <textarea name="address" rows="3" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">{{ old('address', $branch->address) }}</textarea>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1.5 block text-sm font-medium">Washing Machines</label>
            <input type="number" min="0" max="100" name="machine_count" value="{{ old('machine_count', $branch->machine_count ?? 0) }}" placeholder="5" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
            <p class="mt-1 text-xs text-muted">This controls the machine choices shown in Cycle Monitoring.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="flex justify-end">
        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">
            Save Branch
        </button>
    </div>
</form>
