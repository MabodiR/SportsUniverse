<?php

namespace Tests\Feature;

use App\Domain\Auth\Services\RegistrationFlow;
use App\Models\PendingRegistration;
use App\Models\RegistrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function details(array $extra = []): array
    {
        return [...['name' => 'New Member', 'email' => 'new@example.com', 'password' => 'Password9!', 'password_confirmation' => 'Password9!', 'role' => 'fan'], ...$extra];
    }

    public function test_filament_registration_page_renders_and_lists_submissions(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        app(RegistrationFlow::class)->store($this->details());
        $this->actingAs($admin)->get('/admin/registrations')->assertOk()->assertSee('New Member')->assertSee('Require email or WhatsApp verification');
    }

    public function test_phone_only_api_registration_still_works_without_verification(): void
    {
        $data = $this->details(['phone' => '+27820000000']);
        unset($data['email']);
        $this->postJson('/api/v1/auth/register', $data)->assertCreated()->assertJsonStructure(['token']);
    }

    public function test_unprivileged_member_cannot_approve_registration(): void
    {
        $pending = app(RegistrationFlow::class)->store($this->details());
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/admin/registrations/'.$pending->id.'/approve')->assertForbidden();
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_admin_list_has_pagination_and_does_not_expose_credentials(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
        for ($i = 0; $i < 26; $i++) {
            app(RegistrationFlow::class)->store($this->details(['email' => 'member'.$i.'@example.com']));
        }
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/system-settings?pending_page=2');
        $response->assertOk()->assertJsonPath('data.registration_pagination.total', 26)->assertJsonCount(1, 'data.pending_registrations')->assertJsonMissingPath('data.pending_registrations.0.payload')->assertJsonMissingPath('data.pending_registrations.0.otp_hash');
    }

    public function test_verification_defaults_off_for_web_and_mobile(): void
    {
        Notification::fake();
        $this->post('/register', $this->details())->assertRedirect('/feed');
        $this->assertAuthenticated();
        $this->assertNull(User::where('email', 'new@example.com')->firstOrFail()->email_verified_at);
        $this->postJson('/api/v1/auth/register', $this->details(['email' => 'mobile@example.com']))->assertCreated()->assertJsonStructure(['token']);
        Notification::assertNothingSent();
    }

    public function test_pending_web_registration_survives_cache_loss_and_admin_can_approve(): void
    {
        Notification::fake();
        RegistrationSetting::current()->update(['verification_required' => true]);
        $this->post('/register', $this->details())->assertRedirect('/register/verification-sent');
        $pending = PendingRegistration::firstOrFail();
        Cache::flush();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertStringNotContainsString('Password9!', DB::table('pending_registrations')->value('payload'));
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/registrations/'.$pending->id.'/approve')->assertOk();
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Password9!', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertSame($admin->id, $pending->fresh()->approved_by);
        $this->assertSame([], $pending->fresh()->payload);
        $this->postJson('/api/v1/admin/registrations/'.$pending->id.'/approve')->assertStatus(422);
    }

    public function test_members_cannot_approve_or_change_policy_and_admin_cannot_change_policy(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/admin/system-settings/registration', ['verification_required' => true, 'whatsapp_enabled' => false])->assertForbidden();
        Role::findOrCreate('admin', 'web');
        $user->assignRole('admin');
        $this->patchJson('/api/v1/admin/system-settings/registration', ['verification_required' => true, 'whatsapp_enabled' => false])->assertForbidden();
        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');
        $this->patchJson('/api/v1/admin/system-settings/registration', ['verification_required' => true, 'whatsapp_enabled' => false])->assertOk();
    }

    public function test_failed_delivery_remains_pending_and_resend_works(): void
    {
        RegistrationSetting::current()->update(['verification_required' => true]);
        config(['mail.default' => 'unavailable-test-mailer']);
        $response = $this->postJson('/api/v1/auth/register', $this->details())->assertStatus(202)->assertJsonMissingPath('token');
        $pending = PendingRegistration::findOrFail($response->json('pending_registration.id'));
        $this->assertSame('failed', $pending->delivery_status);
        Notification::fake();
        $this->postJson('/api/v1/auth/register/resend', ['id' => $pending->id])->assertOk();
        $this->assertSame('sent', $pending->fresh()->delivery_status);
    }

    public function test_live_legacy_cached_link_is_imported_without_rehashing_password(): void
    {
        $id = (string) Str::uuid();
        $data = $this->details();
        $data['password'] = Hash::make($data['password']);
        Cache::put('pending-registration:'.$id, $data, now()->addMinutes(60));
        $url = URL::temporarySignedRoute('register.verify', now()->addMinutes(60), ['token' => $id]);
        $this->get($url)->assertRedirect('/feed');
        $this->assertTrue(Hash::check('Password9!', User::where('email', 'new@example.com')->firstOrFail()->password));
        $this->assertDatabaseHas('pending_registrations', ['id' => $id, 'status' => 'completed']);
    }

    public function test_email_link_completes_registration_once(): void
    {
        Notification::fake();
        RegistrationSetting::current()->update(['verification_required' => true]);
        $this->post('/register', $this->details());
        $pending = PendingRegistration::firstOrFail();
        $url = URL::temporarySignedRoute('register.verify', now()->addMinutes(60), ['token' => $pending->id]);
        $this->get($url)->assertRedirect('/feed');
        $this->assertNotNull(User::where('email', 'new@example.com')->firstOrFail()->email_verified_at);
    }

    public function test_whatsapp_code_is_single_use_and_does_not_verify_email(): void
    {
        config(['services.registration_whatsapp.token' => 'test', 'services.registration_whatsapp.phone_id' => '123', 'services.registration_whatsapp.template' => 'auth']);
        RegistrationSetting::current()->update(['verification_required' => true, 'whatsapp_enabled' => true]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'message']]])]);
        $response = $this->postJson('/api/v1/auth/register', $this->details(['phone' => '+27768120088', 'verification_channel' => 'whatsapp']))->assertStatus(202);
        $id = $response->json('pending_registration.id');
        $otp = Http::recorded()[0][0]['template']['components'][0]['parameters'][0]['text'];
        $this->postJson('/api/v1/auth/register/verify', ['id' => $id, 'otp' => $otp])->assertOk();
        $this->assertNull(User::where('email', 'new@example.com')->firstOrFail()->email_verified_at);
        $this->postJson('/api/v1/auth/register/verify', ['id' => $id, 'otp' => $otp])->assertUnprocessable();
    }

    public function test_otp_attempts_are_persisted_and_expired_codes_are_rejected(): void
    {
        $flow = app(RegistrationFlow::class);
        $pending = $flow->store($this->details());
        $pending->update(['channel' => 'whatsapp', 'otp_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10)]);
        for ($i = 0; $i < 5; $i++) {
            try {
                $flow->verifyOtp($pending->id, '000000');
                $this->fail('Incorrect OTP accepted');
            } catch (ValidationException $e) {
            }
        }
        $this->assertSame(5, $pending->fresh()->attempts);
        try {
            $flow->verifyOtp($pending->id, '123456');
            $this->fail('Locked OTP accepted');
        } catch (ValidationException $e) {
        }
        $pending->update(['attempts' => 0, 'expires_at' => now()->subMinute()]);
        $this->expectException(ValidationException::class);
        $flow->verifyOtp($pending->id, '123456');
    }
}
