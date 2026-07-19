<?php
namespace App\Filament\Resources\SubscriptionPlans\Tables;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
class SubscriptionPlansTable
{
    public static function configure(Table $table):Table{return $table->defaultSort('sort_order')->columns([TextColumn::make('name')->searchable()->weight('bold'),TextColumn::make('monthly_price_cents')->label('Monthly')->money('ZAR',divideBy:100),TextColumn::make('annual_price_cents')->label('Yearly')->money('ZAR',divideBy:100),TextColumn::make('limits.live_viewers')->label('Live viewers')->numeric(),TextColumn::make('limits.storage_gb')->label('Storage')->suffix(' GB'),IconColumn::make('is_featured')->boolean(),IconColumn::make('is_active')->boolean(),TextColumn::make('subscriptions_count')->counts('subscriptions')->label('Subscriptions')])->recordActions([EditAction::make()])->toolbarActions([]);}
}
