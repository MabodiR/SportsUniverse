<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Domain\Feed\Models\Video;
use App\Domain\Moderation\Services\ModerationService;
use App\Filament\Resources\Videos\VideoResource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->label('Content type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->searchable(),
                TextColumn::make('reportable_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reason')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'reviewing' => 'warning',
                        'resolved' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('assignee.name')
                    ->label('Reviewer')
                    ->placeholder('Unassigned')
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
                SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'reviewing' => 'Under review',
                    'resolved' => 'Resolved',
                    'dismissed' => 'Dismissed',
                ]),
                SelectFilter::make('reason')->options([
                    'spam' => 'Spam',
                    'harassment' => 'Harassment or bullying',
                    'hate' => 'Hate speech',
                    'nudity' => 'Nudity or sexual content',
                    'violence' => 'Violence or dangerous activity',
                    'fraud' => 'Fraud or scam',
                    'impersonation' => 'Impersonation',
                    'copyright' => 'Copyright violation',
                    'other' => 'Other',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('viewPost')
                    ->label('View post')
                    ->icon('heroicon-o-play')
                    ->url(fn ($record): ?string => $record->reportable instanceof Video
                        ? VideoResource::getUrl('view', ['record' => $record->reportable])
                        : null)
                    ->visible(fn ($record): bool => $record->reportable instanceof Video),
                Action::make('review')->color('warning')->action(fn ($record) => app(ModerationService::class)->resolve(auth()->user(), $record, 'reviewing', 'review_report')),
                Action::make('resolve')->color('success')->requiresConfirmation()->action(fn ($record) => app(ModerationService::class)->resolve(auth()->user(), $record, 'resolved', 'resolve_report')),
                Action::make('dismiss')->color('gray')->requiresConfirmation()->action(fn ($record) => app(ModerationService::class)->resolve(auth()->user(), $record, 'dismissed', 'dismiss_report')),
            ])
            ->toolbarActions([]);
    }
}
