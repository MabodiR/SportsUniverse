<?php
namespace App\Filament\Resources\BoostSettings\Tables;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
class BoostSettingsTable
{
    public static function configure(Table $table):Table{return $table->heading('Post boosting delivery rules')->description('Paid reach remains separate from organic ranking and every charged impression is recorded.')->columns([
        IconColumn::make('enabled')->boolean(),TextColumn::make('cpm_cents')->label('CPM')->money('ZAR',divideBy:100),
        TextColumn::make('organic_posts_between')->label('Organic interval')->suffix(' posts'),TextColumn::make('frequency_cap_per_day')->label('Daily frequency cap'),
        IconColumn::make('require_review')->label('Review required')->boolean(),TextColumn::make('updatedBy.name')->label('Last changed by')->placeholder('System default'),TextColumn::make('updated_at')->since(),
    ])->recordActions([EditAction::make()->label('Configure')])->toolbarActions([]);}
}
