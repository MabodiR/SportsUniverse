<?php

namespace App\Filament\Resources\FeedSettings;

use App\Domain\Feed\Models\FeedSetting;
use App\Filament\Resources\FeedSettings\Pages\EditFeedSetting;
use App\Filament\Resources\FeedSettings\Pages\ListFeedSettings;
use App\Filament\Resources\FeedSettings\Schemas\FeedSettingForm;
use App\Filament\Resources\FeedSettings\Tables\FeedSettingsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FeedSettingResource extends Resource
{
    protected static ?string $model = FeedSetting::class;
    protected static ?string $navigationLabel = 'Feed algorithm';
    protected static ?string $modelLabel = 'feed algorithm';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;
    protected static ?int $navigationSort = 90;

    private static function isSystemOwner(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['system_admin', 'super_admin']);
    }

    public static function canViewAny(): bool { return static::isSystemOwner(); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return static::isSystemOwner(); }
    public static function canDelete($record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return FeedSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeedSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedSettings::route('/'),
            'edit' => EditFeedSetting::route('/{record}/edit'),
        ];
    }
}
