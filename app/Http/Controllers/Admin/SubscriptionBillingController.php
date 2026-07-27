<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchBillingRecord;
use App\Services\PayMongoService;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionBillingController extends Controller
{
    public function __construct(
        private SubscriptionBillingService $billing,
        private PayMongoService $paymongo,
    ) {}

    /**
     * Start a PayMongo checkout for the given record (or the branch's next due
     * record) and redirect the user to the hosted payment page.
     */
    public function pay(Request $request, ?BranchBillingRecord $record = null)
    {
        $user = $request->user();

        if (! $record) {
            if (! $user->branch_id) {
                return back()->with('error', 'No branch is linked to your account.');
            }

            $branch = Branch::find($user->branch_id);
            $record = $branch ? $this->billing->payableRecord($branch) : null;
        }

        if (! $record) {
            return back()->with('error', 'There is no outstanding subscription to pay right now.');
        }

        if (! $user->isSuperAdmin() && (int) $record->branch_id !== (int) $user->branch_id) {
            abort(403);
        }

        if ($record->status === 'paid') {
            return redirect()->route('dashboard')->with('success', 'This subscription period is already paid.');
        }

        try {
            $session = $this->paymongo->createCheckoutSession($record);
        } catch (\Throwable $e) {
            Log::error('PayMongo checkout failed', ['record' => $record->id, 'message' => $e->getMessage()]);

            return back()->with('error', 'Unable to start the payment: '.$e->getMessage());
        }

        $record->update([
            'paymongo_checkout_id' => $session['id'],
            'paymongo_checkout_url' => $session['url'],
        ]);

        return redirect()->away($session['url']);
    }

    /**
     * Show the in-system QR Ph code for a record so the branch can pay by
     * scanning it with a phone. The page polls for real-time confirmation.
     */
    public function payQr(Request $request, ?BranchBillingRecord $record = null)
    {
        $user = $request->user();

        if (! $record) {
            if (! $user->branch_id) {
                return back()->with('error', 'No branch is linked to your account.');
            }

            $branch = Branch::find($user->branch_id);
            $record = $branch ? $this->billing->payableRecord($branch) : null;
        }

        if (! $record) {
            return back()->with('error', 'There is no outstanding subscription to pay right now.');
        }

        if (! $user->isSuperAdmin() && (int) $record->branch_id !== (int) $user->branch_id) {
            abort(403);
        }

        if ($record->status === 'paid') {
            return redirect()->route('dashboard')->with('success', 'This subscription period is already paid.');
        }

        try {
            $qr = $this->paymongo->createQrPhPayment($record);
        } catch (\Throwable $e) {
            Log::error('PayMongo QR failed', ['record' => $record->id, 'message' => $e->getMessage()]);

            return back()->with('error', 'Unable to generate the QR code: '.$e->getMessage());
        }

        $record->update([
            'paymongo_checkout_id' => $qr['intent_id'],
        ]);

        $record->loadMissing('branch');
        $settings = \App\Models\SystemSetting::current();

        return view('billing.qr', [
            'record' => $record,
            'branch' => $record->branch,
            'qrImage' => $qr['qr_image'],
            'currency' => $settings->currency ?? 'PHP',
        ]);
    }

    /**
     * JSON status endpoint polled by the QR page for real-time confirmation.
     */
    public function payStatus(Request $request, BranchBillingRecord $record)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && (int) $record->branch_id !== (int) $user->branch_id) {
            abort(403);
        }

        // Already confirmed (e.g. by webhook).
        if ($record->status === 'paid') {
            return response()->json(['paid' => true, 'redirect' => route('dashboard')]);
        }

        try {
            if ($record->paymongo_checkout_id) {
                $status = $this->paymongo->paymentIntentStatus($record->paymongo_checkout_id);

                if ($status['paid']) {
                    $this->billing->markPaidAndRenew($record, [
                        'payment_method' => 'PayMongo (QR Ph)',
                        'reference_no' => $status['payment_id'],
                        'paymongo_payment_id' => $status['payment_id'],
                        'payment_date' => now()->toDateString(),
                        'paid_by' => $user->id,
                    ]);

                    return response()->json(['paid' => true, 'redirect' => route('dashboard')]);
                }

                return response()->json(['paid' => false, 'status' => $status['status']]);
            }
        } catch (\Throwable $e) {
            Log::error('PayMongo QR status check failed', ['record' => $record->id, 'message' => $e->getMessage()]);
        }

        return response()->json(['paid' => false, 'status' => 'pending']);
    }

    /**
     * Success return URL from PayMongo. Confirms the payment then renews.
     */
    public function returnFromGateway(Request $request, BranchBillingRecord $record)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && (int) $record->branch_id !== (int) $user->branch_id) {
            abort(403);
        }

        if ($record->status === 'paid') {
            return redirect()->route('dashboard')->with('success', 'Subscription payment confirmed. Thank you!');
        }

        try {
            if ($record->paymongo_checkout_id) {
                $status = $this->paymongo->checkoutPaymentStatus($record->paymongo_checkout_id);

                if ($status['paid']) {
                    $this->billing->markPaidAndRenew($record, [
                        'payment_method' => 'PayMongo ('.$status['method'].')',
                        'reference_no' => $status['payment_id'],
                        'paymongo_payment_id' => $status['payment_id'],
                        'payment_date' => now()->toDateString(),
                        'paid_by' => $user->id,
                    ]);

                    return redirect()->route('dashboard')->with('success', 'Subscription payment received. Thank you!');
                }
            }
        } catch (\Throwable $e) {
            Log::error('PayMongo return confirmation failed', ['record' => $record->id, 'message' => $e->getMessage()]);
        }

        return redirect()->route('dashboard')
            ->with('info', 'We have not confirmed your payment yet. If you completed it, it will reflect shortly.');
    }

    public function cancel(Request $request, BranchBillingRecord $record)
    {
        return redirect()->route('dashboard')->with('info', 'Payment was canceled. Your subscription is still due.');
    }

    /**
     * PayMongo webhook — the authoritative confirmation of payment. Public,
     * CSRF-exempt, and idempotent.
     */
    public function webhook(Request $request)
    {
        if (! $this->paymongo->verifyWebhook($request)) {
            return response()->json(['ok' => false], 400);
        }

        $data = $request->json('data');
        $type = data_get($data, 'attributes.type');

        if (in_array($type, ['checkout_session.payment.paid', 'payment.paid', 'qr.paid'], true)) {
            $record = $this->paymongo->resolveRecordFromEvent(is_array($data) ? $data : []);

            if ($record) {
                $this->billing->markPaidAndRenew($record, [
                    'payment_method' => 'PayMongo (webhook)',
                    'reference_no' => data_get($data, 'attributes.data.id'),
                    'paymongo_payment_id' => data_get($data, 'attributes.data.id'),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
