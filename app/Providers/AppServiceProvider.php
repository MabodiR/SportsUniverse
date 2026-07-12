<?php

namespace App\Providers;

use App\Domain\Discovery\Contracts\ProfileIndexer;
use App\Domain\Discovery\Contracts\ProfileSearchEngine;
use App\Domain\Discovery\Jobs\IndexUserProfile;
use App\Domain\Discovery\Services\DatabaseProfileSearch;
use App\Domain\Discovery\Services\OpenSearchProfileSearch;
use App\Domain\Profiles\Models\AthleteProfile;
use App\Domain\Profiles\Models\OrganisationProfile;
use App\Domain\Profiles\Models\ProfessionalProfile;
use App\Domain\Profiles\Models\UserProfile;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $implementation = config('discovery.driver') === 'opensearch' ? OpenSearchProfileSearch::class : DatabaseProfileSearch::class;
        $this->app->bind(ProfileSearchEngine::class, $implementation);
        $this->app->bind(ProfileIndexer::class, $implementation);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        foreach ([UserProfile::class, AthleteProfile::class, ProfessionalProfile::class, OrganisationProfile::class] as $model) {
            $model::saved(fn ($profile) => IndexUserProfile::dispatch($profile->user_id)->afterCommit());
        }
    }
}
