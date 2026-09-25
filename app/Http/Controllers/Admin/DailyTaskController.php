<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DailyTask;
use App\Models\DailyTaskCompletion;
use App\Support\PublicUpload;
use Illuminate\Http\Request;

class DailyTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->isAdmin();
        $branchId = $canChooseBranch ? ($request->integer('branch_id') ?: Branch::where('is_active', true)->value('id')) : $user->branch_id;
        $workDate = $request->date ?: today()->toDateString();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $tasks = DailyTask::query()
            ->with(['completions' => fn ($query) => $query
                ->with(['completer', 'employeeCompleter'])
                ->where('branch_id', $branchId)
                ->whereDate('work_date', $workDate)])
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        $branch = $branches->firstWhere('id', $branchId) ?: Branch::query()->find($branchId);
        $machineCount = max(1, (int) $branch?->machine_count);

        return view('admin.daily-tasks.index', compact('branches', 'canChooseBranch', 'branch', 'branchId', 'machineCount', 'workDate', 'tasks'));
    }

    public function complete(Request $request, DailyTask $task)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'work_date' => ['required', 'date'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'machines' => ['nullable', 'array'],
            'machines.wash' => ['nullable', 'array'],
            'machines.wash.*' => ['integer', 'min:1', 'max:100'],
            'machines.dry' => ['nullable', 'array'],
            'machines.dry.*' => ['integer', 'min:1', 'max:100'],
        ]);

        if (! $request->user()->isAdmin()) {
            abort_unless((int) $request->user()->branch_id === (int) $validated['branch_id'], 403);
        }

        abort_if($task->branch_id !== null && (int) $task->branch_id !== (int) $validated['branch_id'], 403);

        $cleanedMachines = null;
        if ($task->affectsMachineCounter()) {
            $branch = Branch::query()->findOrFail($validated['branch_id']);
            $maxMachines = max(1, (int) $branch->machine_count);

            $washSelected = $task->affectsWash() ? array_values(array_unique(array_filter(
                array_map('intval', (array) $request->input('machines.wash', [])),
                fn ($m) => $m >= 1 && $m <= $maxMachines
            ))) : [];

            $drySelected = $task->affectsDry() ? array_values(array_unique(array_filter(
                array_map('intval', (array) $request->input('machines.dry', [])),
                fn ($m) => $m >= 1 && $m <= $maxMachines
            ))) : [];

            if (empty($washSelected) && empty($drySelected)) {
                return back()->withErrors(['machines' => 'Please select at least one machine that was cleaned for this task.'])->withInput();
            }

            $cleanedMachines = [
                'wash' => $washSelected,
                'dry' => $drySelected,
            ];
        }

        $path = $request->file('photo')->store('daily-tasks', PublicUpload::DISK);
        $existing = DailyTaskCompletion::query()
            ->where('daily_task_id', $task->id)
            ->where('branch_id', $validated['branch_id'])
            ->whereDate('work_date', $validated['work_date'])
            ->first();

        if ($existing?->photo_path) {
            PublicUpload::delete($existing->photo_path);
        }

        DailyTaskCompletion::updateOrCreate(
            ['daily_task_id' => $task->id, 'branch_id' => $validated['branch_id'], 'work_date' => $validated['work_date']],
            [
                'completed_by' => $request->user()->id,
                'photo_path' => $path,
                'remarks' => $validated['remarks'] ?? null,
                'cleaned_machines' => $cleanedMachines,
                'completed_at' => now(),
            ]
        );

        return back()->with('success', 'Daily task completed with photo proof' . ($cleanedMachines ? ' and recorded for machine Z Reading counter.' : '.'));
    }

}
