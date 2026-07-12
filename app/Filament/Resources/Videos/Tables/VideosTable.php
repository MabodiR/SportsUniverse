<?php

namespace App\Filament\Resources\Videos\Tables;

use App\Domain\Moderation\Services\ModerationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VideosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('media.id')
                    ->searchable(),
                TextColumn::make('sport.name')
                    ->searchable(),
                TextColumn::make('visibility')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('views_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('likes_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('comments_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('shares_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('saves_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('publish')->color('success')->requiresConfirmation()->action(fn ($record) => app(ModerationService::class)->video(auth()->user(), $record, 'published')),
                Action::make('hide')->color('danger')->requiresConfirmation()->action(fn ($record) => app(ModerationService::class)->video(auth()->user(), $record, 'hidden')),
            ])
            ->toolbarActions([]);
    }
}
