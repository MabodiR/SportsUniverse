<?php
namespace App\Filament\Resources\SubscriptionPlans;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use App\Filament\Resources\SubscriptionPlans\Schemas\SubscriptionPlanForm;
use App\Filament\Resources\SubscriptionPlans\Tables\SubscriptionPlansTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
class SubscriptionPlanResource extends Resource
{
    protected static ?string $model=SubscriptionPlan::class;
    protected static ?string $navigationLabel='Membership plans';
    protected static string|BackedEnum|null $navigationIcon=Heroicon::OutlinedCreditCard;
    protected static ?int $navigationSort=92;
    public static function form(Schema $schema):Schema{return SubscriptionPlanForm::configure($schema);}
    public static function table(Table $table):Table{return SubscriptionPlansTable::configure($table);}
    public static function canCreate():bool{return false;}
    public static function canDelete($record):bool{return false;}
    public static function getPages():array{return['index'=>ListSubscriptionPlans::route('/'),'edit'=>EditSubscriptionPlan::route('/{record}/edit')];}
}
