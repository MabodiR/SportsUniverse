<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Moderation\Services\ModerationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('onboarding_completed_at')
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
                Action::make('verify')->color('success')->requiresConfirmation()->action(fn ($record) => app(ModerationService::class)->verify(auth()->user(), $record, true)),
                Action::make('suspend')->color('danger')->requiresConfirmation()->action(fn ($record) => app(ModerationService::class)->userStatus(auth()->user(), $record, 'suspended')),
                Action::make('activate')->color('success')->action(fn ($record) => app(ModerationService::class)->userStatus(auth()->user(), $record, 'active')),
            ])
            ->toolbarActions([]);
    }
}
