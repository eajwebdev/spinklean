<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\BranchBillingRecord;
use App\Models\SystemSetting;
use App\Models\SystemTrialSetting;
use App\Services\SubscriptionBillingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchBillingAccess
{
    public function __construct(private SubscriptionBillingService $billing)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        View::share('billingBanner', null);
        View::share('billingNotifications', []);

        if (! $user) {
            return $next($request);
        }

        // Always let the user log out and reach the payment gateway routes, even
        // when the branch is locked.
        if ($request->routeIs('logout')
            || $request->routeIs('admin.billing.pay')
            || $request->routeIs('admin.billing.pay.*')) {
            return $next($request);
        }

        if (! Schema::hasTable('system_trial_settings') || ! Schema::hasTable('branch_billing_records')) {
            return $next($request);
        }

        $today = now();
        View::share('billingNotifications', $this->billingNotifications($user, $today));

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $trial = SystemTrialSetting::current();

        if ($trial->isActive()) {
            View::share('billingBanner', [
                'type' => 'trial',
                'message' => 'System Free Trial Active Until '.$trial->trial_end_date->format('M d, Y'),
            ]);

            return $next($request);
        }

        if (! $trial->shouldEnforceBilling()) {
            return $next($request);
        }

        if (! $user->branch_id) {
            if ($user->canManageAllBranches()) {
                return $next($request);
            }

            View::share('billingBanner', [
                'type' => 'danger',
                'dismissible' => false,
                'key' => 'billing-missing-branch-'.$user->id,
                'message' => 'Branch subscription has expired. Please contact your administrator to continue using the system.',
            ]);

            return $next($request);
        }

        $branch = Branch::find($user->branch_id);

        if (! $branch) {
            return $next($request);
        }

        // Make sure a first billing record exists once a price is configured.
        $this->billing->ensureActiveRecord($branch);

        $notifyDays = $this->billing->notifyDaysBefore();
        $lockGraceDays = $this->billing->lockGraceDays();

        // 1) Currently within a paid coverage window.
        $paidRecord = $this->billing->activePaidRecord($branch, $today);

        if ($paidRecord) {
            $daysUntilEnd = $paidRecord->subscription_end_date
                ? (int) $today->copy()->startOfDay()->diffInDays($paidRecord->subscription_end_date->copy()->startOfDay(), false)
                : null;

            // Prompt to renew (advance pay) as the coverage window nears its end.
            if ($daysUntilEnd !== null && $daysUntilEnd >= 0 && $daysUntilEnd <= $notifyDays) {
                $nextRecord = $this->billing->payableRecord($branch);

                View::share('billingBanner', [
                    'type' => 'billing',
                    'dismissible' => true,
                    'autoOpen' => true,
                    'key' => 'billing-renew-'.($nextRecord?->id ?? $paidRecord->id).'-'.$paidRecord->subscription_end_date->toDateString(),
                    'message' => 'Your subscription ends in '.$daysUntilEnd.' day'.($daysUntilEnd === 1 ? '' : 's').' ('.$paidRecord->subscription_end_date->format('M d, Y').'). Pay now to keep the system active.',
                    'payRecordId' => $nextRecord?->isPayable() ? $nextRecord->id : null,
                ]);
            }

            return $next($request);
        }

        // 2) No active paid coverage — resolve the outstanding record.
        $record = $branch->billingRecords()
            ->whereIn('status', ['unpaid', 'overdue', 'suspended'])
            ->orderBy('subscription_start_date')
            ->orderBy('id')
            ->first();

        if (! $record) {
            View::share('billingBanner', [
                'type' => 'danger',
                'dismissible' => false,
                'key' => 'billing-missing-'.$branch->id.'-'.$today->year.'-'.$today->month,
                'message' => 'No active subscription was found for '.$today->format('F Y').'. Please contact your administrator to continue using the system.',
            ]);

            return $next($request);
        }

        // Flip unpaid -> overdue once the due date has passed.
        if ($record->status === 'unpaid' && $record->due_date && $record->due_date->toDateString() < $today->toDateString()) {
            $record->update(['status' => 'overdue']);
            $record->status = 'overdue';
        }

        // 3) Hard lock: overdue by at least the grace days, or suspended.
        if ($record->isLocked($lockGraceDays, $today)) {
            $settings = SystemSetting::current();

            return response(view('billing.locked', [
                'record' => $record,
                'branch' => $branch,
                'daysPastDue' => $record->daysPastDue($today),
                'currency' => $settings->currency ?? 'PHP',
                'message' => $record->status === 'suspended'
                    ? 'Your branch subscription has been suspended. Settle the outstanding balance to continue.'
                    : 'Your subscription is overdue. Please pay to continue using the system.',
            ]));
        }

        // 4) Due / within grace — allow access but push the payment prompt.
        $daysPastDue = $record->daysPastDue($today);
        $daysUntilDue = $record->due_date
            ? (int) $today->copy()->startOfDay()->diffInDays($record->due_date->copy()->startOfDay(), false)
            : null;
        $isUpcoming = $daysUntilDue !== null && $daysUntilDue >= 0;

        View::share('billingBanner', [
            'type' => $isUpcoming ? 'billing' : 'danger',
            'dismissible' => $isUpcoming,
            'autoOpen' => true,
            'key' => 'billing-due-'.$record->id.'-'.$today->toDateString(),
            'message' => $isUpcoming
                ? 'Your subscription for '.$record->periodLabel().' is due on '.$record->due_date->format('M d, Y').'. Pay now to avoid interruption.'
                : 'Your subscription for '.$record->periodLabel().' is overdue by '.$daysPastDue.' day'.($daysPastDue === 1 ? '' : 's').'. Pay within '.max(0, $lockGraceDays - $daysPastDue).' day'.((max(0, $lockGraceDays - $daysPastDue)) === 1 ? '' : 's').' to avoid a lockout.',
            'payRecordId' => $record->id,
        ]);

        return $next($request);
    }

    private function billingNotifications($user, $today): array
    {
        if (! $user->branch_id && ! $user->canManageAllBranches()) {
            return [];
        }

        $notifyDays = $this->billing->notifyDaysBefore();

        $query = BranchBillingRecord::query()
            ->with('branch:id,name')
            ->when(! $user->canManageAllBranches(), fn ($query) => $query->where('branch_id', $user->branch_id));

        $upcoming = (clone $query)
            ->whereIn('status', ['unpaid', 'overdue', 'suspended'])
            ->whereDate('due_date', '<=', $today->copy()->addDays($notifyDays)->toDateString())
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn (BranchBillingRecord $record) => [
                'type' => $record->due_date?->isPast() && ! $record->due_date?->isToday() ? 'danger' : 'billing',
                'title' => ($record->branch?->name ? $record->branch->name.' - ' : '').'Billing due',
                'message' => $record->periodLabel().' is '.str_replace('_', ' ', $record->status).' and due on '.$record->due_date?->format('M d, Y').'.',
                'key' => 'billing-due-'.$record->id.'-'.$record->status,
            ]);

        $paid = (clone $query)
            ->where('status', 'paid')
            ->whereDate('subscription_start_date', '<=', $today->toDateString())
            ->whereDate('subscription_end_date', '>=', $today->toDateString())
            ->latest('subscription_end_date')
            ->limit(5)
            ->get()
            ->map(function (BranchBillingRecord $record) use ($today, $notifyDays) {
                $daysUntilEnd = $record->subscription_end_date
                    ? (int) $today->copy()->startOfDay()->diffInDays($record->subscription_end_date->copy()->startOfDay(), false)
                    : null;

                if ($daysUntilEnd !== null && $daysUntilEnd >= 0 && $daysUntilEnd <= $notifyDays) {
                    return [
                        'type' => 'billing',
                        'title' => ($record->branch?->name ? $record->branch->name.' - ' : '').'Renewal coming up',
                        'message' => 'Paid through '.$record->subscription_end_date->format('M d, Y').'. Renews in '.$daysUntilEnd.' day'.($daysUntilEnd === 1 ? '' : 's').'.',
                        'key' => 'billing-paid-'.$record->id,
                    ];
                }

                return null;
            })
            ->filter();

        return collect($upcoming->all())
            ->merge($paid->all())
            ->unique('key')
            ->take(8)
            ->values()
            ->all();
    }
}
