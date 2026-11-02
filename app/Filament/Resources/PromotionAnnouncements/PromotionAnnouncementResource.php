<?php

namespace App\Filament\Resources\PromotionAnnouncements;

use App\Domain\Promotions\Models\PromotionAnnouncement;
use App\Filament\Resources\PromotionAnnouncements\Pages\CreatePromotionAnnouncement;
use App\Filament\Resources\PromotionAnnouncements\Pages\EditPromotionAnnouncement;
use App\Filament\Resources\PromotionAnnouncements\Pages\ListPromotionAnnouncements;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PromotionAnnouncementResource extends Resource
{
    protected static ?string $model = PromotionAnnouncement::class;

    protected static ?string $navigationLabel = 'Banners & popups';

    protected static string|UnitEnum|null $navigationGroup = 'Promotions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make('Promotion display')->schema([Select::make('voucher_id')->relationship('voucher', 'code')->required()->searchable()->preload(), TextInput::make('title')->required()->maxLength(160), Textarea::make('message')->helperText('Dynamic placeholders: :limit, :remaining, :duration and :plan. These update automatically when the linked voucher changes.')->required()->columnSpanFull(), TextInput::make('cta_label')->required(), TextInput::make('cta_url')->required()->default('/register'), Toggle::make('show_banner')->default(true), Toggle::make('show_popup')->default(true), Toggle::make('is_active')->default(true), DateTimePicker::make('starts_at'), DateTimePicker::make('ends_at')->after('starts_at')])->columns(2)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->searchable(), TextColumn::make('voucher.code')->badge(), IconColumn::make('show_banner')->boolean(), IconColumn::make('show_popup')->boolean(), IconColumn::make('is_active')->boolean(), TextColumn::make('ends_at')->dateTime()])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPromotionAnnouncements::route('/'), 'create' => CreatePromotionAnnouncement::route('/create'), 'edit' => EditPromotionAnnouncement::route('/{record}/edit')];
    }
}
