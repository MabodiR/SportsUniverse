<?php

namespace App\Domain\Auth\Services;

use App\Domain\Profiles\Actions\EnsureProfileSlug;
use App\Domain\Promotions\Services\VoucherService;
use App\Models\PendingRegistration;
use App\Models\RegistrationSetting;
use App\Models\User;
use App\Notifications\VerifyPendingRegistration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationFlow
{
    public function findPending(string $id): PendingRegistration
    {
        if ($pending = PendingRegistration::find($id)) {
            return $pending;
        }
        // Preserve still-live signed links from the former cache-only implementation.
        $legacy = Cache::get('pending-registration:'.$id);
        if (is_array($legacy) && isset($legacy['name'], $legacy['email'], $legacy['password'], $legacy['role'])) {
            $pending = PendingRegistration::firstOrCreate(['id' => $id], ['name' => $legacy['name'], 'email' => $legacy['email'], 'phone' => $legacy['phone'] ?? null, 'payload' => $legacy, 'channel' => 'email', 'status' => 'pending', 'delivery_status' => 'sent']);
            Cache::forget('pending-registration:'.$id);

            return $pending;
        }

        return PendingRegistration::findOrFail($id);
    }

    public function store(array $data): PendingRegistration
    {
        $channel = $data['verification_channel'] ?? 'email';
        if (RegistrationSetting::current()->verification_required) {
            if ($channel === 'whatsapp' && (! RegistrationSetting::current()->whatsapp_enabled || ! config('services.registration_whatsapp.token') || empty($data['phone']) || ! preg_match('/^\+[1-9][0-9]{7,14}$/', $data['phone']))) {
                throw ValidationException::withMessages(['phone' => 'WhatsApp verification requires an enabled provider and a phone number in international format (+27…).']);
            }
            if ($channel === 'email' && empty($data['email'])) {
                throw ValidationException::withMessages(['email' => 'An email address is required for email verification.']);
            }
        }
        $data['password'] = Hash::make($data['password']);
        unset($data['password_confirmation'], $data['device_name']);
        $data['role'] = $data['role'] ?? 'fan';

        return PendingRegistration::create(['id' => (string) Str::uuid(), 'name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null, 'payload' => $data, 'channel' => $channel, 'status' => 'pending', 'delivery_status' => 'pending']);
    }

    public function send(PendingRegistration $pending): bool
    {
        if ($pending->status !== 'pending') {
            return false;
        }
        $otp = (string) random_int(100000, 999999);
        $pending->update(['otp_hash' => Hash::make($otp), 'expires_at' => now()->addMinutes(10), 'attempts' => 0]);
        try {
            if ($pending->channel === 'whatsapp') {
                if (! RegistrationSetting::current()->whatsapp_enabled) {
                    throw new \RuntimeException('WhatsApp disabled');
                }
                Http::withToken(config('services.registration_whatsapp.token'))->timeout(15)
                    ->post('https://graph.facebook.com/'.config('services.registration_whatsapp.version').'/'.config('services.registration_whatsapp.phone_id').'/messages', [
                        'messaging_product' => 'whatsapp', 'to' => ltrim($pending->phone, '+'), 'type' => 'template',
                        'template' => ['name' => config('services.registration_whatsapp.template'), 'language' => ['code' => config('services.registration_whatsapp.language')], 'components' => [
                            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $otp]]],
                            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $otp]]],
                        ]],
                    ])->throw();
            } else {
                $url = URL::temporarySignedRoute('register.verify', now()->addMinutes(60), ['token' => $pending->id]);
                Notification::route('mail', $pending->email)->notify(new VerifyPendingRegistration($pending->name, $url));
            }
            $pending->update(['delivery_status' => 'sent']);

            return true;
        } catch (\Throwable $exception) {
            // Do not log provider requests, OTPs or registration payloads.
            $pending->update(['delivery_status' => 'failed']);

            return false;
        }
    }

    public function verifyOtp(string $id, string $otp): User
    {
        $result = DB::transaction(function () use ($id, $otp) {
            $pending = PendingRegistration::lockForUpdate()->findOrFail($id);
            if ($pending->status !== 'pending' || $pending->channel !== 'whatsapp' || ! $pending->expires_at || $pending->expires_at->isPast() || $pending->attempts >= 5 || ! $pending->otp_hash) {
                throw ValidationException::withMessages(['otp' => 'This code has expired. Request a new code.']);
            }
            $pending->increment('attempts');
            if (! Hash::check($otp, $pending->otp_hash)) {
                return null;
            }

            return $this->complete($pending);
        });
        if (! $result) {
            throw ValidationException::withMessages(['otp' => 'The code is incorrect.']);
        }

        return $result;
    }

    public function complete(PendingRegistration $pending, bool $emailVerified = false, ?int $adminId = null): User
    {
        return DB::transaction(function () use ($pending, $emailVerified, $adminId) {
            $pending = PendingRegistration::lockForUpdate()->findOrFail($pending->id);
            abort_unless($pending->status === 'pending', 422, 'This registration has already been completed. Please sign in.');
            $data = $pending->payload;
            if (User::where('email', $pending->email)->when($pending->email === null, fn ($q) => $q->whereRaw('1=0'))->exists() || ($pending->phone && User::where('phone', $pending->phone)->exists())) {
                throw ValidationException::withMessages(['email' => 'An account already exists. Please sign in.']);
            }
            $data['email_verified_at'] = $emailVerified ? now() : null;
            $user = app(RegistrationAccounts::class)->createRegisteredUser($data, app(EnsureProfileSlug::class), app(VoucherService::class));
            $pending->update(['status' => $adminId ? 'approved' : 'completed', 'user_id' => $user->id, 'approved_by' => $adminId, 'approved_at' => $adminId ? now() : null, 'otp_hash' => null, 'payload' => []]);

            return $user;
        });
    }
}
