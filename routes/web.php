<?php

use App\Http\Controllers\Admin\AccountsPayableController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CycleController;
use App\Http\Controllers\Admin\DailyTaskController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\JobOrderController;
use App\Http\Controllers\Admin\LaundryServiceCategoryController;
use App\Http\Controllers\Admin\LaundryServiceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PettyCashController;
use App\Http\Controllers\Admin\PoTransactionController;
use App\Http\Controllers\Admin\ReceivableController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SmsLogController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ZReadingController;
use App\Http\Controllers\PublicUploadController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/pay', function () {
    return view('pay');
});

Route::get('/uploads/{path}', [PublicUploadController::class, 'show'])
    ->where('path', '.*')
    ->name('uploads.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
});

Route::get('/attendance-login', [LoginController::class, 'showAttendanceLogin'])->name('attendance.login');
Route::post('/attendance-login', [LoginController::class, 'attendanceLogin'])->name('attendance.login.submit');

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::post('/attendance-logout', [LoginController::class, 'attendanceLogout'])->name('attendance.logout');

Route::middleware('attendance.employee')->group(function () {
    Route::get('/time-clock', [AttendanceController::class, 'kiosk'])->name('attendance.kiosk');
    Route::get('/time-clock/connectivity', [AttendanceController::class, 'connectivity'])->name('attendance.connectivity');
    Route::get('/time-clock/challenge', [AttendanceController::class, 'challenge'])->name('attendance.challenge');
    Route::post('/time-clock/prepare', [AttendanceController::class, 'preparePublicAttendance'])->name('attendance.prepare');
    Route::post('/time-clock/time-in', [AttendanceController::class, 'publicTimeIn'])->name('attendance.public-time-in');
    Route::post('/time-clock/time-out', [AttendanceController::class, 'publicTimeOut'])->name('attendance.public-time-out');
    Route::post('/time-clock/job-orders/scan', [AttendanceController::class, 'publicScanJobOrder'])->name('attendance.job-orders.scan');
    Route::post('/time-clock/daily-tasks/{task}/complete', [AttendanceController::class, 'publicCompleteDailyTask'])->name('attendance.daily-tasks.complete');
});

Route::middleware(['auth', 'settings.completed', 'billing.access'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/dashboard/assistant/options', [DashboardController::class, 'assistantOptions'])->name('dashboard.assistant.options');
    Route::post('/dashboard/assistant', [DashboardController::class, 'assistant'])->name('dashboard.assistant');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('super.admin')->group(function () {
            Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
            Route::put('/billing/trial', [BillingController::class, 'updateTrial'])->name('billing.trial.update');
            Route::post('/billing/generate', [BillingController::class, 'generate'])->name('billing.generate');
            Route::patch('/billing/records/{billingRecord}/status', [BillingController::class, 'updateStatus'])->name('billing.records.status');
            Route::patch('/billing/records/{billingRecord}/paid', [BillingController::class, 'markPaid'])->name('billing.records.mark-paid');
        });

        Route::middleware('menu.access:branches')->group(function () {
            Route::resource('branches', BranchController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('/branches/{branch}/daily-tasks', [BranchController::class, 'storeTask'])->name('branches.daily-tasks.store');
            Route::put('/branches/{branch}/daily-tasks/{task}', [BranchController::class, 'updateTask'])->name('branches.daily-tasks.update');
            Route::delete('/branches/{branch}/daily-tasks/{task}', [BranchController::class, 'destroyTask'])->name('branches.daily-tasks.destroy');
        });
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('menu.access:users');
        Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('menu.access:customers');
        Route::middleware('menu.access:services')->group(function () {
            Route::resource('services', LaundryServiceController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('/services/presets', [LaundryServiceController::class, 'storePreset'])->name('services.presets.store');
            Route::get('/services/presets/{preset}', [LaundryServiceController::class, 'showPreset'])->name('services.presets.show');
            Route::put('/services/presets/{preset}', [LaundryServiceController::class, 'updatePreset'])->name('services.presets.update');
            Route::post('/services/presets/{preset}', [LaundryServiceController::class, 'updatePreset'])->name('services.presets.update.post');
            Route::delete('/services/presets/{preset}', [LaundryServiceController::class, 'destroyPreset'])->name('services.presets.destroy');
        });
        Route::resource('service-categories', LaundryServiceCategoryController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('menu.access:service_categories');
        Route::middleware('menu.access:job_orders')->group(function () {
            Route::get('/job-orders', [JobOrderController::class, 'index'])->name('job-orders.index');
            Route::get('/job-orders/create', [JobOrderController::class, 'create'])->name('job-orders.create');
            Route::post('/job-orders', [JobOrderController::class, 'store'])->name('job-orders.store');
            Route::post('/job-orders/customers', [CustomerController::class, 'store'])->name('job-orders.customers.store');
            Route::get('/job-orders/{jobOrder}/edit', [JobOrderController::class, 'edit'])->name('job-orders.edit');
            Route::put('/job-orders/{jobOrder}', [JobOrderController::class, 'update'])->name('job-orders.update');
            Route::get('/job-orders/{jobOrder}', [JobOrderController::class, 'show'])->name('job-orders.show');
            Route::get('/job-orders/{jobOrder}/receipt', [JobOrderController::class, 'receipt'])->name('job-orders.receipt');
            Route::patch('/job-orders/{jobOrder}/status', [JobOrderController::class, 'updateStatus'])->name('job-orders.status');
            Route::patch('/job-orders/{jobOrder}/release', [JobOrderController::class, 'release'])->name('job-orders.release');
            Route::post('/job-orders/{jobOrder}/payments', [ReceivableController::class, 'storePayment'])->name('job-orders.payments.store');
            Route::patch('/job-orders/{jobOrder}/cancel', [JobOrderController::class, 'cancel'])->name('job-orders.cancel');
            Route::delete('/job-orders/{jobOrder}', [JobOrderController::class, 'destroy'])->name('job-orders.destroy');
        });
        Route::get('/payments', [PaymentController::class, 'index'])->middleware('menu.access:payments')->name('payments.index');
        Route::middleware('menu.access:inventory')->group(function () {
            Route::resource('inventory', InventoryController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('/inventory/{inventory}/movements', [InventoryController::class, 'storeMovement'])->name('inventory.movements.store');
            Route::post('/inventory/suppliers', [InventoryController::class, 'storeSupplier'])->name('inventory.suppliers.store');
        });
        Route::middleware('menu.access:receivables')->group(function () {
            Route::get('/receivables', [ReceivableController::class, 'index'])->name('receivables.index');
            Route::post('/receivables/job-orders/{jobOrder}/payments', [ReceivableController::class, 'storePayment'])->name('receivables.payments.store');
        });
        Route::middleware('menu.access:po_transactions')->group(function () {
            Route::get('/po-transactions', [PoTransactionController::class, 'index'])->name('po-transactions.index');
            Route::patch('/po-transactions/{poTransaction}', [PoTransactionController::class, 'update'])->name('po-transactions.update');
        });
        Route::middleware('menu.access:cycles')->group(function () {
            Route::get('/cycles', [CycleController::class, 'index'])->name('cycles.index');
            Route::get('/cycles/job-orders/{jobOrder}/scan', [JobOrderController::class, 'acceptProductionScan'])->name('cycles.scan');
            Route::get('/cycles/job-orders/{jobOrder}/receipt', [JobOrderController::class, 'receipt'])->name('cycles.receipt');
            Route::patch('/cycles/job-orders/{jobOrder}/status', [CycleController::class, 'updateStatus'])->name('cycles.status');
            Route::patch('/cycles/job-orders/{jobOrder}/release', [CycleController::class, 'releaseAction'])->name('cycles.release');
            Route::post('/cycles/job-orders/{jobOrder}', [CycleController::class, 'storeCycle'])->name('cycles.store');
            Route::patch('/cycles/{cycle}/end', [CycleController::class, 'endCycle'])->name('cycles.end');
            Route::delete('/cycles/{cycle}', [CycleController::class, 'destroyCycle'])->name('cycles.destroy');
        });
        Route::middleware('menu.access:employees')->group(function () {
            Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
            Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
        });
        Route::middleware('menu.access:attendance')->group(function () {
            Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::get('/attendance/{record}/proof/{type}/{index}', [AttendanceController::class, 'proof'])
                ->whereIn('type', ['clock-in', 'clock-out'])
                ->whereNumber('index')
                ->name('attendance.proof');
            Route::post('/attendance/time-in', [AttendanceController::class, 'timeIn'])->name('attendance.time-in');
            Route::post('/attendance/time-out', [AttendanceController::class, 'timeOut'])->name('attendance.time-out');
        });

        Route::middleware('menu.access:daily_tasks')->group(function () {
            Route::get('/daily-tasks', [DailyTaskController::class, 'index'])->name('daily-tasks.index');
            Route::post('/daily-tasks/{task}/complete', [DailyTaskController::class, 'complete'])->name('daily-tasks.complete');
        });

        Route::get('/reports', [ReportController::class, 'index'])
            ->middleware('menu.access:reports')
            ->name('reports.index');
        Route::get('/reports/pdf', [ReportController::class, 'pdf'])
            ->middleware('menu.access:reports')
            ->name('reports.pdf');
        Route::get('/reports/z-reading/pdf', [ReportController::class, 'zReadingPdf'])
            ->middleware('menu.access:reports')
            ->name('reports.z-reading.pdf');

        Route::middleware('menu.access:expenses')->group(function () {
            Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        });

        Route::middleware('menu.access:accounts_payable')->group(function () {
            Route::get('/accounts-payable', [AccountsPayableController::class, 'index'])->name('accounts-payable.index');
            Route::post('/accounts-payable', [AccountsPayableController::class, 'store'])->name('accounts-payable.store');
            Route::post('/accounts-payable/{accountsPayable}/payments', [AccountsPayableController::class, 'storePayment'])->name('accounts-payable.payments.store');
            Route::delete('/accounts-payable/{accountsPayable}', [AccountsPayableController::class, 'destroy'])->name('accounts-payable.destroy');
        });

        Route::middleware('menu.access:z_readings')->group(function () {
            Route::get('/z-readings', [ZReadingController::class, 'index'])->name('z-readings.index');
            Route::get('/z-readings/create', [ZReadingController::class, 'create'])->name('z-readings.create');
            Route::post('/z-readings', [ZReadingController::class, 'store'])->name('z-readings.store');
            Route::get('/z-readings/{zReading}/pdf', [ZReadingController::class, 'pdf'])->name('z-readings.pdf');
        });

        Route::middleware('menu.access:petty_cash')->group(function () {
            Route::get('/petty-cash', [PettyCashController::class, 'index'])->name('petty-cash.index');
            Route::post('/petty-cash', [PettyCashController::class, 'store'])->name('petty-cash.store');
            Route::delete('/petty-cash/{moneyMovement}', [PettyCashController::class, 'destroy'])->name('petty-cash.destroy');
        });

        Route::get('/sms-logs', [SmsLogController::class, 'index'])
            ->middleware('menu.access:sms_logs')
            ->name('sms-logs.index');

        Route::get('/settings', [SystemSettingController::class, 'edit'])
            ->middleware('menu.access:settings')
            ->name('settings.edit');

        Route::put('/settings', [SystemSettingController::class, 'update'])
            ->middleware('menu.access:settings')
            ->name('settings.update');

        Route::post('/settings/sms-test', [SystemSettingController::class, 'sendTestSms'])
            ->middleware('menu.access:settings')
            ->name('settings.sms-test');
    });
});
