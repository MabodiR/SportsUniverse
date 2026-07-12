<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Profiles\Models\AthleteProfile;
use App\Domain\Profiles\Models\FanProfile;
use App\Domain\Profiles\Models\OrganisationProfile;
use App\Domain\Profiles\Models\ProfessionalProfile;
use App\Domain\Profiles\Models\UserProfile;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'status', 'onboarding_completed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function athleteProfile(): HasOne
    {
        return $this->hasOne(AthleteProfile::class);
    }

    public function fanProfile(): HasOne
    {
        return $this->hasOne(FanProfile::class);
    }

    public function professionalProfile(): HasOne
    {
        return $this->hasOne(ProfessionalProfile::class);
    }

    public function organisationProfile(): HasOne
    {
        return $this->hasOne(OrganisationProfile::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'active' && $this->hasRole('admin');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function postedOpportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'posted_by_id');
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'followed_id')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'followed_id', 'follower_id')->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'onboarding_completed_at' => 'datetime',
        ];
    }
}
