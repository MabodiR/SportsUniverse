<?php

namespace Database\Seeders;

use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssignExistingUsersToSubscriptionPlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = SubscriptionPlan::query()->whereIn('slug', ['free', 'pro', 'elite'])->get()->keyBy('slug');
        if ($plans->count() !== 3) {
            $this->command?->error('Free, Pro, and Elite plans must exist before assigning memberships.');
            return;
        }

        $assigned = ['free' => 0, 'pro' => 0, 'elite' => 0, 'preserved' => 0];

        User::query()->orderBy('id')->chunkById(250, function ($users) use ($plans, &$assigned) {
            DB::transaction(function () use ($users, $plans, &$assigned) {
                foreach ($users as $user) {
                    if ($user->subscriptions()->whereIn('status', ['active', 'scheduled'])->exists()) {
                        $assigned['preserved']++;
                        continue;
                    }

                    $bucket = $user->id % 10;
                    $slug = $bucket === 0 ? 'elite' : ($bucket <= 3 ? 'pro' : 'free');
                    Subscription::create([
                        'user_id' => $user->id,
                        'subscription_plan_id' => $plans[$slug]->id,
                        'status' => 'active',
                        'billing_interval' => $slug === 'free' ? 'monthly' : 'annual',
                        'provider' => 'platform_assignment',
                        'provider_reference' => 'INITIAL-'.$user->id,
                        'starts_at' => now(),
                        'ends_at' => $slug === 'free' ? null : now()->addYear(),
                    ]);
                    $assigned[$slug]++;
                }
            });
        });

        $this->command?->info(sprintf(
            'Memberships assigned — Free: %d, Pro: %d, Elite: %d, existing preserved: %d.',
            $assigned['free'], $assigned['pro'], $assigned['elite'], $assigned['preserved'],
        ));
    }
}
