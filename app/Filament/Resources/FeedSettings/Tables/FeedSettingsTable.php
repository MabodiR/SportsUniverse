<?php

namespace App\Filament\Resources\FeedSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeedSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('How the For You feed works')
            ->description('One shared algorithm controls recommendations on the website and mobile app. Not-interested exclusions and moderation checks are always enforced.')
            ->columns([
                TextColumn::make('ranking_mode')->label('Strategy')->badge()->formatStateUsing(fn (string $state) => ucfirst($state)),
                TextColumn::make('like_weight')->label('Like'),
                TextColumn::make('comment_weight')->label('Comment'),
                TextColumn::make('share_weight')->label('Share'),
                TextColumn::make('follow_boost')->label('Follow boost'),
                TextColumn::make('page_size')->label('Page size'),
                IconColumn::make('use_fan_sports')->label('Fan sports')->boolean(),
                TextColumn::make('updatedBy.name')->label('Last changed by')->placeholder('System default'),
                TextColumn::make('updated_at')->label('Last changed')->since(),
            ])
            ->recordActions([EditAction::make()->label('Configure')])
            ->toolbarActions([]);
    }
}
