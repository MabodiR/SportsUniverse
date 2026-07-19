<?php

namespace App\Providers;

use App\Contracts\Feed\SponsoredPostProvider;
use App\Contracts\Media\MediaLibrary;
use App\Contracts\Media\MediaUrlGenerator;
use App\Contracts\Moderation\ReportableContentResolver;
use App\Domain\Advertising\Services\SponsoredFeedDelivery;
use App\Domain\Media\Services\EloquentMediaLibrary;
use App\Domain\Media\Services\MediaUrlAdapter;
use App\Domain\Notifications\Listeners\SendRequestedNotification;
use App\Domain\Moderation\Adapters\EloquentReportableContentResolver;
use App\Events\NotificationRequested;
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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

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
        $this->app->bind(SponsoredPostProvider::class, SponsoredFeedDelivery::class);
        $this->app->bind(MediaLibrary::class, EloquentMediaLibrary::class);
        $this->app->bind(MediaUrlGenerator::class, MediaUrlAdapter::class);
        $this->app->bind(ReportableContentResolver::class, EloquentReportableContentResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(NotificationRequested::class, SendRequestedNotification::class);

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('apple', \SocialiteProviders\Apple\Provider::class);
            $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
        });

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        $shouldIndexProfiles = ! app()->environment('testing')
            && config('discovery.driver') === 'opensearch'
            && ! empty(config('discovery.hosts'));

        foreach ([UserProfile::class, AthleteProfile::class, ProfessionalProfile::class, OrganisationProfile::class] as $model) {
            $model::saved(function ($profile) use ($shouldIndexProfiles): void {
                if ($shouldIndexProfiles) {
                    IndexUserProfile::dispatch($profile->user_id)->afterCommit();
                }
            });
        }
    }
}
