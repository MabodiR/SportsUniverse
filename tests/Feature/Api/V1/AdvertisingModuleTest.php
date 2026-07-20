<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Advertising\Models\AdCampaign;
use App\Domain\Advertising\Models\CampaignPayment;
use App\Domain\Advertising\Services\PayFastGateway;
use App\Domain\Feed\Models\Video;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdvertisingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['athlete', 'sponsor', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_user_can_create_promotion_and_sponsorship_campaigns(): void
    {
        $user = $this->member('athlete');
        $video = Video::factory()->for($user)->create(['status' => 'published']);
        $base = ['title' => 'Winter Campaign', 'goal' => 'views', 'audience' => ['gender' => 'all', 'min_age' => 18, 'max_age' => 35], 'daily_budget_cents' => 10000, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDays(2)->toDateString()];

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/campaigns', [...$base, 'campaign_type' => 'post_promotion', 'video_id' => $video->public_id, 'submit' => true])
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.total_budget_cents', 30000)
            ->assertJsonPath('data.checkout.provider', 'payfast')
            ->assertJsonPath('data.checkout.fields.amount', '300.00');
        $this->postJson('/api/v1/campaigns', [...$base, 'campaign_type' => 'sponsorship', 'goal' => 'applications', 'video_id' => null, 'submit' => false])
            ->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->getJson('/api/v1/campaigns')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_default_sandbox_credentials_never_sign_with_a_passphrase(): void
    {
        config(['payfast.sandbox' => true, 'payfast.merchant_id' => '10000100', 'payfast.merchant_key' => '46f0cd694581a', 'payfast.passphrase' => 'incorrect-passphrase']);
        $fields = ['merchant_id' => '10000100', 'merchant_key' => '46f0cd694581a', 'item_name' => ' Test campaign '];
        $signatureWithConfiguredPassphrase = app(PayFastGateway::class)->signature($fields);

        config(['payfast.passphrase' => null]);

        $this->assertSame($signatureWithConfiguredPassphrase, app(PayFastGateway::class)->signature($fields));
        $this->assertSame(app(PayFastGateway::class)->signature($fields), app(PayFastGateway::class)->signature([...$fields, 'item_name' => 'Test campaign']));
    }

    public function test_admin_can_activate_campaign_and_active_events_are_counted(): void
    {
        $owner = $this->member('sponsor');
        $admin = $this->member('admin');
        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/campaigns', ['campaign_type' => 'sponsorship', 'title' => 'Talent Partner', 'goal' => 'awareness', 'daily_budget_cents' => 5000, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addWeek()->toDateString(), 'submit' => true])->assertCreated();
        $id = $created->json('data.id');
        $this->completePayment($id);

        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/campaigns/'.$id.'/review', ['status' => 'active'])->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/campaigns/'.$id.'/events', ['event' => 'impression'])->assertOk();
        $this->postJson('/api/v1/campaigns/'.$id.'/events', ['event' => 'click'])->assertOk();
        $this->assertDatabaseHas('ad_campaigns', ['public_id' => $id, 'impressions_count' => 1, 'clicks_count' => 1]);
    }

    public function test_campaign_cannot_be_activated_before_payfast_confirms_payment(): void
    {
        $owner = $this->member('sponsor');
        $admin = $this->member('admin');
        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/campaigns', [
            'campaign_type' => 'sponsorship', 'title' => 'Unpaid campaign', 'goal' => 'awareness',
            'daily_budget_cents' => 5000, 'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDay()->toDateString(), 'submit' => true,
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/campaigns/'.$created->json('data.id').'/review', ['status' => 'active'])
            ->assertStatus(409);
    }

    public function test_user_cannot_promote_another_users_post(): void
    {
        $owner = $this->member('athlete');
        $other = $this->member('athlete');
        $video = Video::factory()->for($other)->create(['status' => 'published']);

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/campaigns', ['campaign_type' => 'post_promotion', 'video_id' => $video->public_id, 'title' => 'Invalid', 'goal' => 'views', 'daily_budget_cents' => 5000, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDay()->toDateString()])->assertUnprocessable();
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->profile()->create(['is_public' => true]);

        return $user;
    }

    private function completePayment(string $campaignId): void
    {
        config(['payfast.validate_ip' => false, 'payfast.validate_server' => false]);
        $campaign = AdCampaign::where('public_id', $campaignId)->firstOrFail();
        $payment = CampaignPayment::where('campaign_id', $campaign->id)->firstOrFail();
        $payload = [
            'merchant_id' => (string) config('payfast.merchant_id'),
            'm_payment_id' => $payment->merchant_reference,
            'pf_payment_id' => 'sandbox-'.strtolower($payment->public_id),
            'payment_status' => 'COMPLETE',
            'amount_gross' => number_format($payment->amount_cents / 100, 2, '.', ''),
        ];
        $payload['signature'] = app(PayFastGateway::class)->signature($payload);

        $this->post('/api/v1/payments/payfast/notify', $payload)->assertOk();
        $this->assertDatabaseHas('campaign_payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('ad_campaigns', ['id' => $campaign->id, 'status' => 'pending_review']);
    }
}
