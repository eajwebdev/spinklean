<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    private const BILLING_TYPES = ['regular', 'po', 'monthly_billing'];
    private const UI_BILLING_TYPES = ['regular', 'po'];
    private const STATUS_FILTERS = ['active', 'inactive'];

    public function index(Request $request)
    {
        $user = $request->user();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $this->canChooseBranch($user), fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $customers = Customer::query()
            ->with('branch')
            ->withCount('jobOrders')
            ->when(! $this->canChooseBranch($user), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->when($request->filled('branch_id') && $this->canChooseBranch($user), fn ($query) => $query->where('branch_id', $request->branch_id))
            ->when(in_array($request->billing_type, self::UI_BILLING_TYPES, true), fn ($query) => $query->where('billing_type', $request->billing_type))
            ->when(in_array($request->status, self::STATUS_FILTERS, true), fn ($query) => $query->where('is_active', $request->status === 'active'))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'branches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated = $this->normalizeBranch($validated);
        $validated['is_active'] = $request->boolean('is_active', true);
        unset($validated['redirect_to']);

        $customer = Customer::create($validated);

        if ($request->input('redirect_to') === 'pos') {
            return redirect()
                ->route('admin.job-orders.create', [
                    'branch_id' => $customer->branch_id,
                    'customer_id' => $customer->id,
                ])
                ->with('success', 'Customer created successfully.');
        }

        return redirect()
            ->route('admin.customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($customer);

        $validated = $request->validate($this->rules($customer));
        $validated = $this->normalizeBranch($validated);
        $validated['is_active'] = $request->boolean('is_active');

        $customer->update($validated);

        return redirect()
            ->route('admin.customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorizeCustomer($customer);

        if ($customer->jobOrders()->exists()) {
            return back()->with('error', 'Customer has job orders and cannot be deleted.');
        }

        $customer->delete();

        return redirect()
            ->route('admin.customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    private function rules(?Customer $customer = null): array
    {
        return [
            'branch_id' => ['nullable', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer?->id),
            ],
            'address' => ['nullable', 'string'],
            'billing_type' => ['required', Rule::in(['regular', 'po', 'monthly_billing'])],
            'unpaid_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'redirect_to' => ['nullable', Rule::in(['pos'])],
        ];
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
            return $validated;
        }

        if (empty($validated['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => 'Please choose a branch.',
            ]);
        }

        return $validated;
    }

    private function authorizeCustomer(Customer $customer): void
    {
        $user = auth()->user();

        if ($user->isSuperAdmin() || $user->role === 'admin') {
            return;
        }

        abort_unless((int) $user->branch_id === (int) $customer->branch_id, 403);
    }

    private function canChooseBranch($user): bool
    {
        return $user->isSuperAdmin() || $user->role === 'admin';
    }
}
