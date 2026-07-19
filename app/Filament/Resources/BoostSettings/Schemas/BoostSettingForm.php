<?php
namespace App\Filament\Resources\BoostSettings\Schemas;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
class BoostSettingForm
{
    public static function configure(Schema $schema):Schema{return $schema->components([
        Section::make('Availability and safety')->description('Global controls for paid post distribution on web and mobile.')->schema([
            Toggle::make('enabled')->label('Allow users to boost posts')->helperText('Turning this off stops new campaigns and removes sponsored placements from feeds.'),
            Toggle::make('require_review')->label('Require administrator approval after payment')->helperText('Recommended. Campaigns cannot run until payment is confirmed and content is approved.'),
        ]),
        Section::make('Pricing and limits')->description('Campaigns are prepaid in South African rand through PayFast.')->schema([
            TextInput::make('cpm_cents')->label('Price per 1,000 impressions (cents)')->numeric()->integer()->minValue(100)->maxValue(1000000)->required()->helperText('Example: 5000 cents is R50 CPM.'),
            TextInput::make('minimum_daily_budget_cents')->label('Minimum daily budget (cents)')->numeric()->integer()->minValue(100)->required(),
            TextInput::make('maximum_daily_budget_cents')->label('Maximum daily budget (cents)')->numeric()->integer()->minValue(100)->required()->gt('minimum_daily_budget_cents'),
            TextInput::make('maximum_duration_days')->label('Maximum campaign duration')->numeric()->integer()->minValue(1)->maxValue(180)->required()->suffix('days'),
        ])->columns(2),
        Section::make('Feed experience')->description('These limits prevent paid posts from overwhelming the organic feed.')->schema([
            TextInput::make('organic_posts_between')->label('Organic posts between sponsored posts')->numeric()->integer()->minValue(3)->maxValue(50)->required(),
            TextInput::make('frequency_cap_per_day')->label('Maximum impressions per user, per campaign, per day')->numeric()->integer()->minValue(1)->maxValue(20)->required(),
        ])->columns(2),
    ]);}
}
