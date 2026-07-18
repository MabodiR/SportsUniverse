<?php

namespace App\Http\Controllers\Api\V1\Advertising;

use App\Domain\Advertising\Models\AdCampaign;
use App\Domain\Advertising\Models\CampaignPayment;
use App\Domain\Advertising\Services\PayFastGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayFastController extends Controller
{
    public function checkout(Request $request, AdCampaign $campaign, PayFastGateway $gateway): JsonResponse
    {
        abort_unless($campaign->user_id === $request->user()->id, 403);
        abort_unless(in_array($campaign->status, ['awaiting_payment', 'payment_failed', 'payment_cancelled'], true), 409, 'This campaign is not awaiting payment.');
        $campaign->update(['status' => 'awaiting_payment']);
        return response()->json(['data' => $gateway->checkout($campaign, $request->user())]);
    }

    public function notify(Request $request, PayFastGateway $gateway): JsonResponse
    {
        $payload = $request->post();
        abort_unless($gateway->validSource($request), 403, 'Invalid PayFast source.');
        abort_unless($gateway->validSignature($payload), 400, 'Invalid PayFast signature.');
        abort_unless(hash_equals((string) config('payfast.merchant_id'), (string) ($payload['merchant_id'] ?? '')), 400, 'Invalid PayFast merchant.');
        $payment = CampaignPayment::where('merchant_reference', $payload['m_payment_id'] ?? '')->firstOrFail();
        abort_if(abs(((int) round(((float) ($payload['amount_gross'] ?? 0)) * 100)) - $payment->amount_cents) > 1, 400, 'Invalid PayFast amount.');
        abort_unless($gateway->validServerConfirmation($payload), 400, 'PayFast could not confirm this payment.');

        DB::transaction(function () use ($payment, $payload) {
            $payment = CampaignPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') return;
            $complete = strtoupper((string) ($payload['payment_status'] ?? '')) === 'COMPLETE';
            $payment->update([
                'provider_payment_id' => $payload['pf_payment_id'] ?? $payment->provider_payment_id,
                'status' => $complete ? 'paid' : 'failed', 'provider_payload' => $payload,
                'paid_at' => $complete ? now() : null,
            ]);
            $payment->campaign()->update($complete
                ? ['status' => 'pending_review', 'submitted_at' => now()]
                : ['status' => 'payment_failed']);
        });
        return response()->json(['message' => 'PayFast notification accepted.']);
    }

    public function returned(CampaignPayment $payment): RedirectResponse
    {
        return redirect('/sponsorship?payment=processing&campaign='.$payment->campaign->public_id);
    }

    public function cancelled(CampaignPayment $payment): RedirectResponse
    {
        if ($payment->status === 'pending') {
            $payment->update(['status' => 'cancelled']);
            $payment->campaign()->update(['status' => 'payment_cancelled']);
        }
        return redirect('/sponsorship?payment=cancelled&campaign='.$payment->campaign->public_id);
    }
}
