<?php

namespace App\Http\Controllers\Web;

use App\Domain\Advertising\Services\PayFastGateway;
use App\Domain\Subscriptions\Models\SubscriptionPayment;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Domain\Subscriptions\Services\SubscriptionManager;
use App\Domain\Subscriptions\Services\SubscriptionPayFastGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MembershipPaymentController extends Controller
{
    public function checkout(Request $request, SubscriptionPlan $plan, SubscriptionPayFastGateway $gateway, SubscriptionManager $manager): JsonResponse
    {
        $data = $request->validate(['billing_interval' => ['required', 'in:monthly,annual']]);
        abort_unless($plan->is_active, 404);
        if ($plan->monthly_price_cents === 0) {
            $subscription = $manager->downgradeToFree($request->user(), $plan);
            return response()->json(['data' => ['scheduled' => $subscription->status === 'scheduled', 'starts_at' => $subscription->starts_at?->toIso8601String()]]);
        }
        return response()->json(['data' => ['checkout' => $gateway->checkout($plan, $request->user(), $data['billing_interval'])]]);
    }

    public function notify(Request $request, PayFastGateway $security, SubscriptionManager $manager): JsonResponse
    {
        $payload = $request->post();
        abort_unless($security->validSource($request), 403, 'Invalid PayFast source.');
        abort_unless($security->validSignature($payload), 400, 'Invalid PayFast signature.');
        abort_unless(hash_equals((string) config('payfast.merchant_id'), (string) ($payload['merchant_id'] ?? '')), 400, 'Invalid PayFast merchant.');
        $payment = SubscriptionPayment::where('merchant_reference', $payload['m_payment_id'] ?? '')->firstOrFail();
        abort_if(abs(((int) round(((float) ($payload['amount_gross'] ?? 0)) * 100)) - $payment->amount_cents) > 1, 400, 'Invalid PayFast amount.');
        abort_unless($security->validServerConfirmation($payload), 400, 'PayFast could not confirm this payment.');

        DB::transaction(function () use ($payment, $payload, $manager) {
            $payment = SubscriptionPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'paid') return;
            $complete = strtoupper((string) ($payload['payment_status'] ?? '')) === 'COMPLETE';
            $payment->update(['provider_payment_id' => $payload['pf_payment_id'] ?? null, 'status' => $complete ? 'paid' : 'failed', 'provider_payload' => $payload, 'paid_at' => $complete ? now() : null]);
            if ($complete) $manager->activate($payment);
        });
        return response()->json(['message' => 'PayFast membership notification accepted.']);
    }

    public function returned(SubscriptionPayment $payment): RedirectResponse { return redirect('/membership?payment=processing'); }
    public function cancelled(SubscriptionPayment $payment): RedirectResponse
    {
        if ($payment->status === 'pending') $payment->update(['status' => 'cancelled']);
        return redirect('/membership?payment=cancelled');
    }
}
