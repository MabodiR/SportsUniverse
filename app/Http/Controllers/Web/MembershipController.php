<?php

namespace App\Http\Controllers\Web;

use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Domain\Subscriptions\Services\SubscriptionEntitlements;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function __invoke(Request $request, SubscriptionEntitlements $entitlements): Response
    {
        $current = $entitlements->plan($request->user());
        $subscription = $request->user()->subscriptions()->current()->latest('starts_at')->first();
        $scheduled = $request->user()->subscriptions()->where('status', 'scheduled')->with('plan')->latest('starts_at')->first();

        return Inertia::render('Membership/Index', [
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'currentPlan' => $current->slug,
            'membership' => $subscription ? ['status' => $subscription->status, 'billing_interval' => $subscription->billing_interval, 'renews_or_ends_at' => $subscription->ends_at?->toIso8601String()] : null,
            'scheduledPlan' => $scheduled ? ['name' => $scheduled->plan->name, 'starts_at' => $scheduled->starts_at?->toIso8601String()] : null,
        ]);
    }
}
