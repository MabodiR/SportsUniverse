<?php

namespace App\Filament\Resources\Sponsors;

use App\Domain\Profiles\Models\OrganisationProfile;
use App\Filament\Resources\Sponsors\Pages\ListSponsors;
use App\Filament\Resources\Sponsors\Pages\ViewSponsor;
use App\Filament\Resources\Sponsors\Schemas\SponsorInfolist;
use App\Filament\Resources\Sponsors\Tables\SponsorsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SponsorResource extends Resource
{
    protected static ?string $model = OrganisationProfile::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $navigationLabel = 'Sponsor accounts';
    protected static ?string $modelLabel = 'sponsor account';
    protected static ?string $pluralModelLabel = 'sponsor accounts';

    public static function getNavigationBadge(): ?string
    {
        return (string) OrganisationProfile::where('organisation_type', 'sponsor')->count();
    }

    public static function getEloquentQuery(): Builder { return parent::getEloquentQuery()->where('organisation_type', 'sponsor')->with(['user.roles', 'user.profile']); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function infolist(Schema $schema): Schema { return SponsorInfolist::configure($schema); }
    public static function table(Table $table): Table { return SponsorsTable::configure($table); }
    public static function getPages(): array { return ['index' => ListSponsors::route('/'), 'view' => ViewSponsor::route('/{record}')]; }
}
