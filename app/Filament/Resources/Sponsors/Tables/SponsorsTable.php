<?php

namespace App\Filament\Resources\Sponsors\Tables;

use App\Domain\Moderation\Services\ModerationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SponsorsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('organisation_name')->label('Sponsor')->searchable()->sortable(),
            TextColumn::make('user.name')->label('Owner')->searchable(),
            TextColumn::make('contact_email')->searchable()->copyable(),
            TextColumn::make('registration_number')->label('Registration')->searchable()->placeholder('-'),
            TextColumn::make('user.status')->label('Account status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
            TextColumn::make('user.email_verified_at')->label('Verified')->dateTime()->placeholder('No')->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('account_status')->label('Account status')->options(['active' => 'Active', 'suspended' => 'Suspended'])->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $status) => $query->whereHas('user', fn ($user) => $user->where('status', $status)))),
        ])->recordActions([
            ViewAction::make(),
            Action::make('verify')->color('success')->requiresConfirmation()->visible(fn ($record) => ! $record->user->hasVerifiedEmail())->action(fn ($record) => app(ModerationService::class)->verify(auth()->user(), $record->user, true)),
            Action::make('suspend')->color('danger')->requiresConfirmation()->visible(fn ($record) => $record->user->status === 'active')->action(fn ($record) => app(ModerationService::class)->userStatus(auth()->user(), $record->user, 'suspended')),
            Action::make('activate')->color('success')->visible(fn ($record) => $record->user->status !== 'active')->action(fn ($record) => app(ModerationService::class)->userStatus(auth()->user(), $record->user, 'active')),
        ])->toolbarActions([]);
    }
}
