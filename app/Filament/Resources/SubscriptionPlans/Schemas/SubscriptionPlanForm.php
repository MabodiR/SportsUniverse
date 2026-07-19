<?php
namespace App\Filament\Resources\SubscriptionPlans\Schemas;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
class SubscriptionPlanForm
{
    public static function configure(Schema $schema):Schema{return $schema->components([
        Section::make('Plan identity')->schema([TextInput::make('name')->required()->maxLength(80),TextInput::make('tagline')->required()->maxLength(180),Textarea::make('description')->rows(3),Toggle::make('is_active'),Toggle::make('is_featured')])->columns(2),
        Section::make('Pricing')->description('Prices are stored in South African cents. R149 is 14900 cents.')->schema([TextInput::make('monthly_price_cents')->numeric()->integer()->minValue(0)->prefix('c'),TextInput::make('annual_price_cents')->numeric()->integer()->minValue(0)->prefix('c')])->columns(2),
        Section::make('Benefits and entitlements')->description('Limits are consumed by the entitlement service across web and mobile. Keep existing keys when editing.')->schema([TagsInput::make('features')->reorderable()->columnSpanFull(),KeyValue::make('limits')->keyLabel('Entitlement key')->valueLabel('Limit or enabled value')->reorderable()->columnSpanFull()]),
    ]);}
}
