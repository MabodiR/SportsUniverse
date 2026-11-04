<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MobileVerifyEmail;
use App\Notifications\VerifyPendingRegistration;
use App\Notifications\VoucherMilestoneReached;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Markdown;
use Tests\TestCase;

class BrandedEmailTest extends TestCase
{
    public function test_password_reset_preserves_token_email_expiry_and_plain_text_fallback(): void
    {
        config(['app.url' => 'https://sportsuniverse.co.za', 'auth.passwords.users.expire' => 45]);
        $user = new User(['name' => '<script>Example</script>', 'email' => 'member+test@example.com']);
        $message = (new ResetPassword('example-token'))->toMail($user);
        $html = (string) $message->render();
        $text = (string) app(Markdown::class)->renderText($message->markdown, $message->data());

        $this->assertStringContainsString('example-token', $message->actionUrl);
        $this->assertStringContainsString('member%2Btest%40example.com', $message->actionUrl);
        $this->assertStringContainsString('45 minutes', $html);
        $this->assertStringNotContainsString('<script>Example</script>', $html);
        $this->assertStringContainsString('sportuniverse-logo-horizontal-transparent-black.png', $html);
        $this->assertStringContainsString('support@sportsuniverse.co.za', $html);
        $this->assertStringContainsString('Choose a new password', $text);
        $this->assertStringContainsString('example-token', $text);
    }

    public function test_mobile_verification_keeps_its_signed_api_link(): void
    {
        $user = new User(['name' => 'Alex', 'email' => 'alex@example.com']);
        $user->id = 123;
        $message = (new MobileVerifyEmail)->toMail($user);

        $this->assertStringContainsString('signature=', $message->actionUrl);
        $this->assertStringContainsString('/123/', $message->actionUrl);
        $this->assertStringContainsString(sha1('alex@example.com'), $message->actionUrl);
        $this->assertStringContainsString('Verify my email', (string) $message->render());
    }

    public function test_registration_and_operational_notifications_share_the_brand(): void
    {
        $user = new User(['name' => 'Alex', 'email' => 'alex@example.com']);
        $url = 'https://sportsuniverse.co.za/verify?signature=example';
        $registration = (new VerifyPendingRegistration('Alex', $url))->toMail($user);
        $milestone = (new VoucherMilestoneReached('Community launch', 25, 100))->toMail($user);

        $this->assertSame($url, $registration->actionUrl);
        foreach ([$registration, $milestone] as $message) {
            $html = (string) $message->render();
            $this->assertStringContainsString('TALENT. OPPORTUNITY. COMMUNITY.', $html);
            $this->assertStringContainsString('Get in touch', $html);
        }
    }
}
