<?php

namespace Tests\Feature;

use App\Domain\Feed\Models\Video;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['athlete', 'fan'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_guest_can_render_login_and_registration_interfaces(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    public function test_guest_can_open_about_and_privacy_policy_pages(): void
    {
        $this->get('/about')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/About'));
        $this->get('/privacy-policy')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/PrivacyPolicy'));
    }

    public function test_guest_can_render_a_limited_public_feed(): void
    {
        $this->get('/feed')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Feed/Index')
            ->where('auth.user', null)
            ->has('videos')
            ->has('suggestions'));
    }

    public function test_guest_can_open_the_mobile_app_download_page(): void
    {
        $this->get('/mobile-app')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('MobileApp/Download')
            ->has('downloads')
            ->where('downloads.ios', null)
            ->where('downloads.android', null)
            ->where('downloads.direct', config('services.mobile_app.direct_url')));
    }

    public function test_member_can_register_and_enter_feed(): void
    {
        $response = $this->post('/register', [
            'name' => 'Lerato Athlete',
            'email' => 'lerato@example.com',
            'password' => 'Password9',
            'password_confirmation' => 'Password9',
            'role' => 'athlete',
        ]);
        $response->assertRedirect('/feed');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('user_profiles', ['slug' => 'lerato-athlete', 'completeness' => 35]);
        $this->get('/feed')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Feed/Index')->has('videos')->has('suggestions'));
    }

    public function test_member_can_login_and_logout_through_web_session(): void
    {
        $user = User::factory()->create(['email' => 'fan@example.com', 'password' => 'Password9']);
        $this->post('/login', ['login' => 'fan@example.com', 'password' => 'Password9'])->assertRedirect('/feed');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_web_session_authenticates_same_origin_api_requests(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['slug' => 'session-member', 'completeness' => 20]);

        $this->actingAs($user)
            ->withHeader('referer', config('app.url').'/profile')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.name', $user->name);
    }

    public function test_authenticated_member_can_open_primary_navigation_pages(): void
    {
        $user = User::factory()->create();

        foreach (['/explore', '/women-in-sports', '/following', '/videos/watch', '/upload', '/uploads/status', '/messages', '/profile', '/profile/gallery', '/opportunities', '/applications', '/sponsorship', '/saved', '/settings/devices', '/notifications'] as $uri) {
            $response = $this->actingAs($user)->get($uri);
            $this->assertSame(200, $response->status(), "{$uri} should be available to authenticated members.");
        }
    }

    public function test_authenticated_member_can_view_their_saved_videos_on_the_saved_page(): void
    {
        $viewer = User::factory()->create();
        $video = Video::factory()->create(['caption' => 'Saved highlight']);
        $video->savers()->attach($viewer->id);

        $this->actingAs($viewer)
            ->get('/saved')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Saved/Index')->has('videos', 1));
    }

    public function test_visiting_an_athlete_profile_increments_profile_views(): void
    {
        $viewer = User::factory()->create();
        $athlete = User::factory()->create();
        $athlete->profile()->create(['slug' => 'profile-view-athlete', 'is_public' => true]);

        $this->actingAs($viewer)
            ->get('/@profile-view-athlete')
            ->assertOk();

        $this->assertSame(1, $athlete->profile->fresh()->views_count);
    }

    public function test_authenticated_web_member_can_follow_an_athlete_without_sanctum_token(): void
    {
        $viewer = User::factory()->create();
        $athlete = User::factory()->create();

        $this->actingAs($viewer)->postJson('/athletes/'.$athlete->id.'/follow')
            ->assertOk()
            ->assertJsonPath('data.followers_count', 1)
            ->assertJsonPath('data.viewer_following_count', 1);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $viewer->id,
            'followed_id' => $athlete->id,
        ]);
    }
}
