<?php

namespace App\Filament\Resources\Media\Tables;

use App\Domain\Moderation\Services\ModerationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('kind')
                    ->searchable(),
                TextColumn::make('collection')
                    ->searchable(),
                TextColumn::make('disk')
                    ->searchable(),
                TextColumn::make('path')
                    ->searchable(),
                TextColumn::make('original_name')
                    ->searchable(),
                TextColumn::make('mime_type')
                    ->searchable(),
                TextColumn::make('size_bytes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('checksum_sha256')
                    ->searchable(),
                TextColumn::make('processing_status')
                    ->searchable(),
                TextColumn::make('moderation_status')
                    ->searchable(),
                TextColumn::make('thumbnail_path')
                    ->searchable(),
                TextColumn::make('duration_ms')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('width')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('height')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('processed_at')
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
                Action::make('approve')->color('success')->requiresConfirmation()->action(fn ($record) => app(ModerationService::class)->media(auth()->user(), $record, 'approved')),
                Action::make('reject')->color('danger')->requiresConfirmation()->action(fn ($record) => app(ModerationService::class)->media(auth()->user(), $record, 'rejected')),
            ])
            ->toolbarActions([]);
    }
}
