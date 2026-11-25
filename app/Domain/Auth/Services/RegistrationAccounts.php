<?php

namespace App\Domain\Auth\Services;

use App\Domain\Profiles\Actions\EnsureProfileSlug;
use App\Domain\Promotions\Services\VoucherService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RegistrationAccounts
{
    public function createRegisteredUser(array $data, EnsureProfileSlug $slugs, VoucherService $vouchers): User
    {
        $user = DB::transaction(function () use ($data, $vouchers) {
            $user = User::create(collect($data)->only(['name', 'email', 'phone', 'password'])->all());
            $user->forceFill(['email_verified_at' => ($data['email_verified_at'] ?? null)])->save();
            $user->profile()->create(['completeness' => $data['profile_completeness'] ?? 35, 'bio' => $data['bio'] ?? null, 'date_of_birth' => $data['date_of_birth'] ?? null, 'country' => 'ZA', 'province' => $data['province'] ?? null, 'city' => $data['city'] ?? null, 'locality' => $data['locality'] ?? null]);
            Role::findOrCreate($data['role'], 'web');
            $user->assignRole($data['role']);
            if ($data['role'] === 'fan') {
                $user->fanProfile()->create(['interested_sports' => $data['interested_sports'] ?? []]);
            }
            if ($data['role'] === 'athlete') {
                $user->athleteProfile()->create(['sport_id' => $data['sport_id'] ?? null, 'position_id' => $data['position_id'] ?? null, 'club_name' => $data['club_name'] ?? null, 'playing_level' => $data['playing_level'] ?? null]);
            }
            if (in_array($data['role'], ['coach', 'referee', 'linesman', 'scout', 'agent'], true)) {
                $user->professionalProfile()->create(['professional_type' => $data['role'], 'specialisation' => $data['specialisation'] ?? null, 'years_experience' => $data['years_experience'] ?? null]);
            }
            if (in_array($data['role'], ['club', 'academy', 'business', 'sponsor'], true)) {
                $user->organisationProfile()->create(['organisation_name' => ($data['organisation_name'] ?? null) ?: $data['name'], 'organisation_type' => $data['role'], 'contact_email' => $data['email'] ?? null, 'contact_phone' => $data['phone'] ?? null, 'services' => array_values(array_filter(array_map('trim', explode(',', $data['services'] ?? ''))))]);
            }
            $vouchers->redeemForNewUser($user, $data['voucher_code'] ?? null);

            return $user;
        });
        $slugs->execute($user->load('profile'));

        return $user;
    }
}
