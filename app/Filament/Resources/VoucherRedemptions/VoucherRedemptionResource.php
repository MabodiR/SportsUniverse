<?php
namespace App\Filament\Resources\VoucherRedemptions;
use App\Domain\Promotions\Models\VoucherRedemption;
use App\Filament\Resources\VoucherRedemptions\Pages\ListVoucherRedemptions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;
class VoucherRedemptionResource extends Resource
{
    protected static ?string $model=VoucherRedemption::class;
    protected static ?string $navigationLabel='Registrations & redemptions';
    protected static string|UnitEnum|null $navigationGroup='Promotions';
    protected static string|BackedEnum|null $navigationIcon=Heroicon::OutlinedUsers;
    protected static ?int $navigationSort=2;
    public static function table(Table $table):Table{return $table->defaultSort('redeemed_at','desc')->columns([TextColumn::make('user.name')->label('Member')->searchable(),TextColumn::make('user.email')->searchable()->copyable(),TextColumn::make('user.roles.name')->label('Profile type')->badge(),TextColumn::make('voucher.code')->searchable()->badge(),TextColumn::make('voucher.campaign_name')->label('Campaign'),TextColumn::make('plan.name')->label('Package'),TextColumn::make('subscription.status')->badge(),TextColumn::make('final_amount_cents')->money('ZAR',divideBy:100)->label('Paid'),TextColumn::make('redeemed_at')->dateTime()->sortable()])->filters([SelectFilter::make('voucher_id')->relationship('voucher','code')->searchable()->preload(),SelectFilter::make('subscription_plan_id')->relationship('plan','name')->preload()]);}
    public static function canCreate():bool{return false;} public static function canEdit($record):bool{return false;} public static function canDelete($record):bool{return false;}
    public static function getPages():array{return['index'=>ListVoucherRedemptions::route('/')];}
}
