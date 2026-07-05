<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEmployee;
use App\Models\Branch;
use App\Models\DailyTask;
use App\Models\DailyTaskCompletion;
use App\Models\EmployeeAttendanceRecord;
use App\Models\JobOrder;
use App\Support\PublicUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function kiosk()
    {
        $employee = AttendanceEmployee::query()
            ->with('branch')
            ->whereKey(session('attendance_employee_id'))
            ->where('status', 'active')
            ->first();

        if (! $employee) {
            return redirect()->route('attendance.login');
        }

        $workDate = today()->toDateString();
        $workBranch = $this->workBranchForToday($employee);
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'address']);
        $dailyTasks = DailyTask::query()
            ->with(['completions' => fn ($query) => $query
                ->with(['completer', 'employeeCompleter'])
                ->where('branch_id', $workBranch->id)
                ->whereDate('work_date', $workDate)])
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $workBranch->id))
            ->orderBy('name')
            ->get();

        $employee->setRelation('branch', $workBranch);

        return view('attendance.kiosk', compact('employee', 'dailyTasks', 'workDate', 'branches', 'workBranch'));
    }

    public function connectivity()
    {
        return response()
            ->json(['online' => true, 'checked_at' => now()->toIso8601String()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function challenge()
    {
        $sequence = collect(['blink', 'left', 'right'])
            ->shuffle()
            ->values()
            ->all();
        $nonce = (string) Str::uuid();

        Cache::put($this->challengeCacheKey($nonce), $sequence, now()->addMinutes(2));

        return response()->json([
            'nonce' => $nonce,
            'sequence' => $sequence,
            'expires_in' => 120,
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $canChooseBranch = $user->canManageAllBranches();
        $selectedBranchId = $canChooseBranch
            ? ($request->integer('branch_id') ?: null)
            : $user->branch_id;
        $selectedEmployeeId = $request->integer('employee_id') ?: null;
        [$dateFrom, $dateTo] = $this->dateRange($request);
        $workDate = $dateFrom === $dateTo ? $dateFrom : $dateFrom.' to '.$dateTo;
        $matchingAttendanceRecords = fn ($query) => $query
            ->when($selectedBranchId, fn ($query) => $query->where('branch_id', $selectedBranchId))
            ->whereDate('work_date', '>=', $dateFrom)
            ->whereDate('work_date', '<=', $dateTo);

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $canChooseBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();

        $employeesQuery = AttendanceEmployee::query()
            ->withTrashed()
            ->with('branch')
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('status', 'active'))
                ->orWhereHas('attendanceRecords', $matchingAttendanceRecords))
            ->when($selectedBranchId, fn ($query) => $query->where(fn ($query) => $query
                ->where('branch_id', $selectedBranchId)
                ->orWhereHas('attendanceRecords', $matchingAttendanceRecords)))
            ->when($selectedEmployeeId, fn ($query) => $query->whereKey($selectedEmployeeId))
            ->orderBy('first_name')
            ->orderBy('last_name');

        $employees = (clone $employeesQuery)
            ->get();

        $records = $employeesQuery
            ->paginate(20)
            ->withQueryString();

        $attendanceByEmployee = EmployeeAttendanceRecord::query()
            ->with(['employee', 'branch'])
            ->whereIn('attendance_employee_id', $records->getCollection()->pluck('id'))
            ->when($selectedBranchId, fn ($query) => $query->where('branch_id', $selectedBranchId))
            ->whereDate('work_date', '>=', $dateFrom)
            ->whereDate('work_date', '<=', $dateTo)
            ->latest('work_date')
            ->latest()
            ->get()
            ->groupBy('attendance_employee_id');

        $records->through(fn (AttendanceEmployee $employee) => $this->attendanceRowForEmployee(
            $employee,
            $attendanceByEmployee->get($employee->id)?->first(),
            $dateFrom
        ));

        return view('admin.attendance.index', compact('branches', 'employees', 'records', 'selectedBranchId', 'selectedEmployeeId', 'workDate', 'dateFrom', 'dateTo', 'canChooseBranch'));
    }

    public function proof(Request $request, EmployeeAttendanceRecord $record, string $type, int $index)
    {
        if (! $request->user()->canManageAllBranches()) {
            abort_unless((int) $request->user()->branch_id === (int) $record->branch_id, 403);
        }

        $photos = match ($type) {
            'clock-in' => $record->clock_in_photos ?? [],
            'clock-out' => $record->clock_out_photos ?? [],
            default => abort(404),
        };
        $path = $photos[$index] ?? null;

        abort_unless(
            is_string($path)
                && str_starts_with($path, 'attendance-proofs/')
                && PublicUpload::exists($path),
            404
        );

        return PublicUpload::response($path, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function timeIn(Request $request)
    {
        $validated = $this->validateAttendanceRequest($request);
        $employee = $this->attendanceEmployeeForAdmin($request, (int) $validated['employee_id']);
        $branch = $this->branchForAttendanceEmployee($employee);
        $record = $this->attendanceRecordForToday($employee, $branch);

        $record->update([
            'clock_in' => [...($record->clock_in ?? []), now()->format('H:i:s')],
            'clock_in_photos' => [...($record->clock_in_photos ?? []), $this->storeAttendanceImage($validated['face_image'], 'attendance-proofs')],
        ]);

        return back()->with('success', "{$employee->name} timed in successfully.");
    }

    public function timeOut(Request $request)
    {
        $validated = $this->validateAttendanceRequest($request);
        $employee = $this->attendanceEmployeeForAdmin($request, (int) $validated['employee_id']);
        $branch = $this->branchForAttendanceEmployee($employee);
        $record = $this->attendanceRecordForToday($employee, $branch);

        $record->update([
            'clock_out' => [...($record->clock_out ?? []), now()->format('H:i:s')],
            'clock_out_photos' => [...($record->clock_out_photos ?? []), $this->storeAttendanceImage($validated['face_image'], 'attendance-proofs')],
        ]);

        return back()->with('success', "{$employee->name} timed out successfully.");
    }

    public function publicTimeIn(Request $request)
    {
        $validated = $this->validatePublicAttendanceRequest($request);
        $employee = $this->employeeFromAttendanceSession();
        $branch = $this->branchForPublicSelection((int) $validated['branch_id']);
        $record = $this->attendanceRecordForToday($employee, $branch);

        $record->update([
            'clock_in' => [...($record->clock_in ?? []), now()->format('H:i:s')],
            'clock_in_photos' => [...($record->clock_in_photos ?? []), $this->storeAttendanceImage($validated['face_image'], 'attendance-proofs')],
        ]);

        return response()->json([
            'message' => "{$employee->name} timed in successfully.",
            'employee' => $employee->name,
            'branch' => $branch->name,
            'time' => now()->format('h:i A'),
        ]);
    }

    public function publicTimeOut(Request $request)
    {
        $validated = $this->validatePublicAttendanceRequest($request);
        $employee = $this->employeeFromAttendanceSession();
        $branch = $this->branchForPublicSelection((int) $validated['branch_id']);
        $record = $this->attendanceRecordForToday($employee, $branch);

        $record->update([
            'clock_out' => [...($record->clock_out ?? []), now()->format('H:i:s')],
            'clock_out_photos' => [...($record->clock_out_photos ?? []), $this->storeAttendanceImage($validated['face_image'], 'attendance-proofs')],
        ]);

        return response()->json([
            'message' => "{$employee->name} timed out successfully.",
            'employee' => $employee->name,
            'branch' => $branch->name,
            'time' => now()->format('h:i A'),
        ]);
    }

    public function preparePublicAttendance(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);
        $employee = $this->employeeFromAttendanceSession();
        $branch = $this->branchForPublicSelection((int) $validated['branch_id']);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
            ],
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'address' => $branch->address,
            ],
            'captured_at' => now()->format('M d, Y h:i A'),
        ]);
    }

    public function publicCompleteDailyTask(Request $request, DailyTask $task)
    {
        $employee = $this->employeeFromAttendanceSession();
        $workBranch = $this->workBranchForToday($employee);

        abort_if($task->branch_id !== null && (int) $task->branch_id !== (int) $workBranch->id, 403);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $workDate = today()->toDateString();
        $path = PublicUpload::store($request->file('photo'), 'daily-tasks');
        $existing = DailyTaskCompletion::query()
            ->where('daily_task_id', $task->id)
            ->where('branch_id', $workBranch->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if ($existing?->photo_path) {
            PublicUpload::delete($existing->photo_path);
        }

        DailyTaskCompletion::updateOrCreate(
            ['daily_task_id' => $task->id, 'branch_id' => $workBranch->id, 'work_date' => $workDate],
            [
                'completed_by' => null,
                'completed_by_employee_id' => $employee->id,
                'photo_path' => $path,
                'remarks' => $validated['remarks'] ?? null,
                'completed_at' => now(),
            ]
        );

        return back()->with('success', 'End-of-day proof uploaded successfully.');
    }

    public function publicScanJobOrder(Request $request)
    {
        $employee = $this->employeeFromAttendanceSession();
        $workBranch = $this->workBranchForToday($employee);
        $validated = $request->validate([
            'qr_text' => ['required', 'string', 'max:1000'],
        ]);

        $jobOrder = $this->jobOrderFromQrText($validated['qr_text']);
        $productionBranch = app(JobOrderController::class)
            ->acceptProductionByBranch($request, $jobOrder, (int) $workBranch->id);

        $jobOrder->refresh();

        return response()->json([
            'message' => "Accepted {$jobOrder->job_order_number} for production at {$productionBranch->name}.",
            'job_order_number' => $jobOrder->job_order_number,
            'dropoff_branch' => $jobOrder->branch?->name,
            'processing_branch' => $productionBranch->name,
            'status' => $jobOrder->status,
        ]);
    }

    private function validateAttendanceRequest(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:attendance_employees,id'],
            'face_image' => ['required', 'string'],
        ]);
    }

    private function validatePublicAttendanceRequest(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'face_image' => ['required', 'string'],
        ]);
    }

    private function verifyChallenge(array $validated): void
    {
        $expected = Cache::pull($this->challengeCacheKey($validated['challenge_nonce']));

        if (! $expected || array_values($validated['challenge_result']) !== array_values($expected)) {
            throw ValidationException::withMessages([
                'face' => 'Live face challenge expired or was not completed correctly. Please try again.',
            ]);
        }
    }

    private function branchForAttendanceEmployee(AttendanceEmployee $employee): Branch
    {
        $branch = Branch::query()
            ->whereKey($employee->branch_id)
            ->where('is_active', true)
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'branch' => 'The assigned attendance branch is inactive or missing.',
            ]);
        }

        return $branch;
    }

    private function branchForPublicSelection(int $branchId): Branch
    {
        $branch = Branch::query()
            ->whereKey($branchId)
            ->where('is_active', true)
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'branch_id' => 'Please choose an active branch.',
            ]);
        }

        return $branch;
    }

    private function employeeFromAttendanceSession(): AttendanceEmployee
    {
        $employee = AttendanceEmployee::query()
            ->with('branch')
            ->whereKey(session('attendance_employee_id'))
            ->where('status', 'active')
            ->first();

        if (! $employee) {
            throw ValidationException::withMessages(['employee' => 'Please login as an employee first.']);
        }

        return $employee;
    }

    private function attendanceEmployeeForAdmin(Request $request, int $employeeId): AttendanceEmployee
    {
        $employee = AttendanceEmployee::query()
            ->whereKey($employeeId)
            ->where('status', 'active')
            ->with('branch')
            ->firstOrFail();

        if (! $request->user()->isAdmin()) {
            abort_unless((int) $request->user()->branch_id === (int) $employee->branch_id, 403);
        }

        return $employee;
    }

    private function attendanceRecordForToday(AttendanceEmployee $employee, Branch $branch): EmployeeAttendanceRecord
    {
        $record = EmployeeAttendanceRecord::query()
            ->where('attendance_employee_id', $employee->id)
            ->where('branch_id', $branch->id)
            ->whereDate('work_date', today())
            ->first();

        if ($record) {
            return $record;
        }

        return EmployeeAttendanceRecord::create([
            'branch_id' => $branch->id,
            'attendance_employee_id' => $employee->id,
            'work_date' => today()->toDateString(),
            'clock_in' => [],
            'clock_out' => [],
            'clock_in_photos' => [],
            'clock_out_photos' => [],
            'clock_in_locations' => [],
            'clock_out_locations' => [],
        ]);
    }

    private function workBranchForToday(AttendanceEmployee $employee): Branch
    {
        $branchId = EmployeeAttendanceRecord::query()
            ->where('attendance_employee_id', $employee->id)
            ->whereDate('work_date', today())
            ->latest('updated_at')
            ->value('branch_id');

        return Branch::query()->findOrFail($branchId ?: $employee->branch_id);
    }

    private function attendanceRowForEmployee(AttendanceEmployee $employee, ?EmployeeAttendanceRecord $record, string $dateFrom): EmployeeAttendanceRecord
    {
        if ($record) {
            $record->setRelation('employee', $employee);

            return $record;
        }

        $emptyRecord = new EmployeeAttendanceRecord([
            'attendance_employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'work_date' => $dateFrom,
            'clock_in' => [],
            'clock_out' => [],
            'clock_in_photos' => [],
            'clock_out_photos' => [],
            'clock_in_locations' => [],
            'clock_out_locations' => [],
        ]);

        $emptyRecord->exists = false;
        $emptyRecord->setRelation('employee', $employee);
        $emptyRecord->setRelation('branch', $employee->branch);

        return $emptyRecord;
    }

    private function jobOrderFromQrText(string $qrText): JobOrder
    {
        $qrText = trim($qrText);
        $path = parse_url($qrText, PHP_URL_PATH) ?: $qrText;

        if (preg_match('#/job-orders/(\d+)/(?:scan|receipt)#', $path, $matches)) {
            return JobOrder::query()->findOrFail((int) $matches[1]);
        }

        if (preg_match('/\bJO[-A-Z0-9]+\b/i', $qrText, $matches)) {
            return JobOrder::query()
                ->where('job_order_number', strtoupper($matches[0]))
                ->firstOrFail();
        }

        return JobOrder::query()
            ->where('job_order_number', $qrText)
            ->firstOrFail();
    }

    private function storeAttendanceImage(string $image, string $directory): string
    {
        abort_unless(str_starts_with($image, 'data:image/'), 422, 'Invalid attendance image.');
        abort_unless(str_contains($image, ','), 422, 'Invalid attendance image.');

        [$meta, $contents] = explode(',', $image, 2);
        $extension = str_contains($meta, 'image/png') ? 'png' : 'jpg';
        $decoded = base64_decode($contents, true);
        abort_if($decoded === false, 422, 'Invalid attendance image.');
        $path = $directory.'/'.uniqid('attendance_', true).'.'.$extension;

        PublicUpload::put($path, $decoded);

        return $path;
    }

    private function dateRange(Request $request): array
    {
        if ($request->filled('date_range')) {
            $parts = preg_split('/\s+to\s+/', $request->date_range);

            return [
                $this->parseDate($parts[0] ?? null, today()->toDateString()),
                $this->parseDate($parts[1] ?? $parts[0] ?? null, today()->toDateString()),
            ];
        }

        $date = $this->parseDate($request->date, today()->toDateString());

        return [$date, $date];
    }

    private function parseDate(?string $date, string $fallback): string
    {
        if (! $date) {
            return $fallback;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function challengeCacheKey(string $nonce): string
    {
        return 'attendance_challenge:'.$nonce;
    }
}
