<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\Services\UserAnalyticsService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class UserAnalytics extends Page
{
    protected static ?string $title = 'User analytics';

    protected static ?string $navigationLabel = 'User analytics';

    protected static ?string $slug = 'user-analytics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.user-analytics';

    public string $period = '365';

    public string $source = '';

    public string $role = '';

    public string $province = '';

    public string $city = '';

    public string $sport = '';

    public string $gender = '';

    public string $status = '';

    public string $ageMin = '';

    public string $ageMax = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['admin', 'system_admin', 'super_admin']);
    }

    public function getAnalyticsProperty(): array
    {
        return app(UserAnalyticsService::class)->dashboard([
            'period' => $this->period,
            'source' => $this->source,
            'role' => $this->role,
            'province' => $this->province,
            'city' => $this->city,
            'sport' => $this->sport,
            'gender' => $this->gender,
            'status' => $this->status,
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
        ]);
    }

    public function getFilterOptionsProperty(): array
    {
        return app(UserAnalyticsService::class)->filterOptions();
    }

    public function resetFilters(): void
    {
        $this->reset(['source', 'role', 'province', 'city', 'sport', 'gender', 'status', 'ageMin', 'ageMax']);
        $this->period = '365';
    }
}
