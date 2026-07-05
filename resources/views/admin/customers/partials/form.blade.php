<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    @if(! empty($redirectTo))
        <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
    @endif
    @php($canChooseBranch = auth()->user()->isSuperAdmin() || auth()->user()->role === 'admin')

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-sm font-medium">Name</label>
            <input name="name" value="{{ old('name', $customer->name) }}" required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Branch</label>
            @if(! $canChooseBranch)
                <input type="hidden" name="branch_id" @if(! empty($branchModel)) :value="{{ $branchModel }}" @else value="{{ auth()->user()->branch_id }}" @endif>
                <input value="{{ auth()->user()->branch?->name }}" disabled class="h-9 w-full rounded-md border border-border bg-smoke px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
            @else
                <select name="branch_id" @if(! empty($branchModel)) x-model="{{ $branchModel }}" @endif required class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', $customer->branch_id) == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Phone</label>
            <input name="phone" value="{{ old('phone', $customer->phone) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Email</label>
            <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Billing Type</label>
            <select name="billing_type" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
                <option value="regular" @selected(old('billing_type', $customer->billing_type) === 'regular')>Regular</option>
                <option value="po" @selected(old('billing_type', $customer->billing_type) === 'po')>PO</option>
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Unpaid Limit</label>
            <input type="number" step="0.01" min="0" name="unpaid_limit" value="{{ old('unpaid_limit', $customer->unpaid_limit ?? 0) }}" class="h-9 w-full rounded-md border border-border bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-950">
        </div>

        <div class="md:col-span-2">
            <label class="mb-1.5 block text-sm font-medium">Address</label>
            <textarea name="address" rows="3" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">{{ old('address', $customer->address) }}</textarea>
        </div>

        <div class="md:col-span-2">
            <label class="inline-flex h-9 items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active)) class="rounded border-border text-primary">
                Active customer
            </label>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="flex justify-end">
        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:opacity-90">
            Save Customer
        </button>
    </div>
</form>
