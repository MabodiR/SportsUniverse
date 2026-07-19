<?php

namespace App\Filament\Resources\SubscriptionPayments;

use App\Domain\Subscriptions\Models\SubscriptionPayment;
use App\Filament\Resources\SubscriptionPayments\Pages\ListSubscriptionPayments;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;
    protected static ?string $navigationLabel = 'Membership payments';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static ?int $navigationSort = 93;

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('merchant_reference')->label('Reference')->searchable()->copyable(),
            TextColumn::make('user.name')->label('Member')->searchable(),
            TextColumn::make('plan.name')->label('Plan'),
            TextColumn::make('billing_interval')->badge(),
            TextColumn::make('amount_cents')->label('Amount')->money('ZAR', divideBy: 100),
            TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {'paid' => 'success', 'failed', 'cancelled' => 'danger', default => 'warning'}),
            TextColumn::make('paid_at')->dateTime()->placeholder('Awaiting payment'),
            TextColumn::make('created_at')->since(),
        ])->recordActions([])->toolbarActions([]);
    }

    public static function canCreate(): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function getPages(): array { return ['index' => ListSubscriptionPayments::route('/')]; }
}
