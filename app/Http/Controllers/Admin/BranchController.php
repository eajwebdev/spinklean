<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\DailyTask;
use App\Support\DefaultInventoryItems;
use App\Support\DefaultLaundryServices;
use App\Support\DefaultServiceInventoryUsages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $branches = Branch::query()
            ->when(! $user->canManageAllBranches(), fn ($query) => $query->whereKey($user->branch_id))
            ->when(in_array($request->status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $request->status === 'active'))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->with(['dailyTasks' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->withCount('users')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.branches.index', [
            'branches' => $branches,
            'canCreateBranch' => $user->isSuperAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $validated = $request->validate($this->rules());
        $validated['branch_type'] = $validated['branch_type'] ?? 'full_service';

        DB::transaction(function () use ($request, $validated) {
            $branch = Branch::create($validated + ['is_active' => $request->boolean('is_active')]);

            DefaultLaundryServices::seedForBranch($branch);
            DefaultInventoryItems::seedForBranch($branch);
            DefaultServiceInventoryUsages::seedForBranch($branch);
            BranchSetting::firstOrCreate(
                ['branch_id' => $branch->id],
                [
                    'job_order_prefix' => $branch->code,
                    'invoice_prefix' => 'INV-'.$branch->code,
                ]
            );
        });

        return redirect()
            ->route('admin.branches.index')
            ->with('success', 'Branch created successfully.');
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorizeBranch($branch);

        $validated = $request->validate($this->rules($branch));
        $validated['branch_type'] = $validated['branch_type'] ?? 'full_service';
        $validated['is_active'] = $request->boolean('is_active');
        $validated['latitude'] = null;
        $validated['longitude'] = null;
        $validated['attendance_radius_meters'] = null;

        $branch->update($validated);

        return redirect()
            ->route('admin.branches.index')
            ->with('success', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        if ($branch->users()->exists()) {
            return back()->with('error', 'Branch has users and cannot be deleted.');
        }

        $branch->delete();

        return redirect()
            ->route('admin.branches.index')
            ->with('success', 'Branch deleted successfully.');
    }

    public function storeTask(Request $request, Branch $branch)
    {
        $this->authorizeBranch($branch);

        $validated = $request->validate($this->taskRules($branch));
        $validated['branch_id'] = $branch->id;
        $validated['requires_photo'] = $request->boolean('requires_photo', true);
        $validated['is_active'] = $request->boolean('is_active', true);

        DailyTask::create($validated);

        return back()->with('success', 'Branch end-of-day task added successfully.');
    }

    public function updateTask(Request $request, Branch $branch, DailyTask $task)
    {
        $this->authorizeBranchTask($branch, $task);

        $validated = $request->validate($this->taskRules($branch, $task));
        $validated['requires_photo'] = $request->boolean('requires_photo');
        $validated['is_active'] = $request->boolean('is_active');

        $task->update($validated);

        return back()->with('success', 'Branch end-of-day task updated successfully.');
    }

    public function destroyTask(Branch $branch, DailyTask $task)
    {
        $this->authorizeBranchTask($branch, $task);
        $task->update(['is_active' => false]);

        return back()->with('success', 'Branch end-of-day task removed.');
    }

    private function rules(?Branch $branch = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('branches', 'code')->ignore($branch?->id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'branch_type' => ['nullable', Rule::in(['full_service', 'pickup_dropoff'])],
            'machine_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function authorizeBranch(Branch $branch): void
    {
        $user = auth()->user();

        if ($user->canManageAllBranches()) {
            return;
        }

        abort_unless((int) $user->branch_id === (int) $branch->id, 403);
    }

    private function authorizeBranchTask(Branch $branch, DailyTask $task): void
    {
        $this->authorizeBranch($branch);
        abort_unless((int) $task->branch_id === (int) $branch->id, 403);
    }

    private function taskRules(Branch $branch, ?DailyTask $task = null): array
    {
        $nameRule = Rule::unique('daily_tasks', 'name')
            ->where(fn ($query) => $query
                ->where('branch_id', $branch->id)
                ->where('is_active', true));

        if ($task) {
            $nameRule->ignore($task->id);
        }

        return [
            'name' => ['required', 'string', 'max:255', $nameRule],
            'requires_photo' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
