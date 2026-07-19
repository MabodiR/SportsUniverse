<?php

namespace App\Filament\Resources\FeedSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FeedSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Feed strategy')
                ->description('This configuration is shared by the website and mobile app.')
                ->schema([
                    Select::make('ranking_mode')
                        ->label('Ranking method')
                        ->options([
                            'personalized' => 'Personalized — engagement plus a following boost',
                            'engagement' => 'Engagement — strongest performing posts first',
                            'recent' => 'Recent — newest posts first',
                        ])->required()->native(false)
                        ->helperText('Personalized is recommended because it balances content quality with accounts a user follows.'),
                    Toggle::make('use_fan_sports')
                        ->label('Personalize using a fan’s selected sports')
                        ->helperText('When enabled, fan accounts only receive posts matching sports selected during onboarding.'),
                ])->columns(1),
            Section::make('Ranking weights')
                ->description('Higher values give that action more influence. Changes affect both web and mobile.')
                ->schema([
                    TextInput::make('view_weight')->label('View weight')->numeric()->minValue(0)->maxValue(1000)->step(0.01)->required(),
                    TextInput::make('like_weight')->label('Like weight')->numeric()->minValue(0)->maxValue(1000)->step(0.01)->required(),
                    TextInput::make('comment_weight')->label('Comment weight')->numeric()->minValue(0)->maxValue(1000)->step(0.01)->required(),
                    TextInput::make('share_weight')->label('Share/repost weight')->numeric()->minValue(0)->maxValue(1000)->step(0.01)->required(),
                    TextInput::make('follow_boost')->label('Following boost')->numeric()->minValue(0)->maxValue(100000)->step(1)->required()
                        ->helperText('Only used by Personalized ranking.'),
                ])->columns(2),
            Section::make('Feed capacity')
                ->description('Use measured changes here: larger values increase database, cache, and device workload.')
                ->schema([
                    TextInput::make('page_size')->label('Posts per page')->numeric()->integer()->minValue(5)->maxValue(50)->required(),
                    TextInput::make('recommendation_size')->label('Precomputed posts per user')->numeric()->integer()->minValue(50)->maxValue(2000)->required(),
                ])->columns(2),
        ]);
    }
}
