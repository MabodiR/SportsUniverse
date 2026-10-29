<?php
namespace App\Filament\Pages;
use App\Filament\Widgets\PromotionStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;
class PromotionAnalytics extends Page
{
    protected static ?string $title='Registration & promotion analytics';
    protected static ?string $navigationLabel='Promotion overview';
    protected static ?string $slug='promotion-analytics';
    protected static string|BackedEnum|null $navigationIcon=Heroicon::OutlinedChartBar;
    protected static string|UnitEnum|null $navigationGroup='Promotions';
    protected static ?int $navigationSort=0;
    protected function getHeaderWidgets():array{return[PromotionStats::class];}
    public function getHeaderWidgetsColumns():int|array{return['md'=>2,'xl'=>4];}
}
