<?php

namespace App\Filament\Resources\Campaigns;

use App\Domain\Advertising\Models\AdCampaign;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Resources\Campaigns\Pages\ViewCampaign;
use App\Filament\Resources\Campaigns\Schemas\CampaignInfolist;
use App\Filament\Resources\Campaigns\Tables\CampaignsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CampaignResource extends Resource
{
    protected static ?string $model = AdCampaign::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $navigationLabel = 'Campaign review';
    protected static ?string $modelLabel = 'campaign';
    protected static ?string $pluralModelLabel = 'campaigns';

    public static function getNavigationBadge(): ?string
    {
        $count = AdCampaign::where('status', 'pending_review')->count();
        return $count ? (string) $count : null;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function infolist(Schema $schema): Schema { return CampaignInfolist::configure($schema); }
    public static function table(Table $table): Table { return CampaignsTable::configure($table); }
    public static function getPages(): array
    {
        return ['index' => ListCampaigns::route('/'), 'view' => ViewCampaign::route('/{record}')];
    }
}
