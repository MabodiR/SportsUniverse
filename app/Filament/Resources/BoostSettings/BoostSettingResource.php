<?php
namespace App\Filament\Resources\BoostSettings;
use App\Domain\Advertising\Models\BoostSetting;
use App\Filament\Resources\BoostSettings\Pages\EditBoostSetting;
use App\Filament\Resources\BoostSettings\Pages\ListBoostSettings;
use App\Filament\Resources\BoostSettings\Schemas\BoostSettingForm;
use App\Filament\Resources\BoostSettings\Tables\BoostSettingsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
class BoostSettingResource extends Resource
{
    protected static ?string $model=BoostSetting::class;
    protected static ?string $navigationLabel='Post boosting';
    protected static ?string $modelLabel='post boosting rules';
    protected static string|BackedEnum|null $navigationIcon=Heroicon::OutlinedMegaphone;
    protected static ?int $navigationSort=91;
    private static function isSystemOwner():bool{return(bool)auth()->user()?->hasAnyRole(['system_admin','super_admin']);}
    public static function canViewAny():bool{return static::isSystemOwner();}
    public static function canCreate():bool{return false;}
    public static function canEdit($record):bool{return static::isSystemOwner();}
    public static function canDelete($record):bool{return false;}
    public static function form(Schema $schema):Schema{return BoostSettingForm::configure($schema);}
    public static function table(Table $table):Table{return BoostSettingsTable::configure($table);}
    public static function getPages():array{return['index'=>ListBoostSettings::route('/'),'edit'=>EditBoostSetting::route('/{record}/edit')];}
}
