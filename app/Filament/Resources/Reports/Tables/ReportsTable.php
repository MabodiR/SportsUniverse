<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Domain\Moderation\Services\ModerationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->searchable(),
                TextColumn::make('reporter.name')
                    ->searchable(),
                TextColumn::make('reportable_type')
                    ->searchable(),
                TextColumn::make('reportable_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reason')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('assigned_to_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('resolved_at')
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
                Action::make('review')->color('warning')->action(fn ($record) => app(ModerationService::class)->resolve(auth()->user(), $record, 'reviewing', 'review_report')),
                Action::make('resolve')->color('success')->requiresConfirmation()->action(fn ($record) => app(ModerationService::class)->resolve(auth()->user(), $record, 'resolved', 'resolve_report')),
                Action::make('dismiss')->color('gray')->requiresConfirmation()->action(fn ($record) => app(ModerationService::class)->resolve(auth()->user(), $record, 'dismissed', 'dismiss_report')),
            ])
            ->toolbarActions([]);
    }
}
