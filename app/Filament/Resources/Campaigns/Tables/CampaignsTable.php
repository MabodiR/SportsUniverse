<?php

namespace App\Filament\Resources\Campaigns\Tables;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('submitted_at', 'desc')->columns([
            TextColumn::make('title')->searchable()->limit(40),
            TextColumn::make('user.name')->label('Owner')->searchable(),
            TextColumn::make('campaign_type')->label('Type')->badge(),
            TextColumn::make('goal')->badge(),
            TextColumn::make('total_budget_cents')->label('Budget')->money('ZAR', divideBy: 100)->sortable(),
            TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) { 'active', 'approved', 'completed' => 'success', 'rejected', 'cancelled' => 'danger', 'pending_review' => 'warning', default => 'gray' }),
            TextColumn::make('submitted_at')->dateTime()->sortable()->placeholder('Draft'),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Draft', 'pending_review' => 'Pending review', 'approved' => 'Approved', 'active' => 'Active', 'rejected' => 'Rejected', 'completed' => 'Completed', 'cancelled' => 'Cancelled']),
            SelectFilter::make('campaign_type')->options(['post_promotion' => 'Post promotion', 'sponsorship' => 'Sponsorship']),
        ])->recordActions([
            ViewAction::make(),
            Action::make('approve')->color('success')->icon('heroicon-o-check-circle')->requiresConfirmation()->visible(fn ($record) => $record->status === 'pending_review')->action(function ($record) {
                $record->update(['status' => 'approved', 'reviewed_at' => now(), 'review_notes' => null]);
                Notification::make()->title('Campaign approved')->success()->send();
            }),
            Action::make('reject')->color('danger')->icon('heroicon-o-x-circle')->visible(fn ($record) => $record->status === 'pending_review')->schema([Textarea::make('review_notes')->label('Reason for rejection')->required()->maxLength(2000)])->action(function ($record, array $data) {
                $record->update(['status' => 'rejected', 'reviewed_at' => now(), 'review_notes' => $data['review_notes']]);
                Notification::make()->title('Campaign rejected')->danger()->send();
            }),
        ])->toolbarActions([]);
    }
}
