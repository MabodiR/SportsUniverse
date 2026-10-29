<?php
namespace App\Filament\Widgets;
use App\Domain\Promotions\Models\Voucher;
use App\Domain\Promotions\Models\VoucherRedemption;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
class PromotionStats extends StatsOverviewWidget
{
    protected function getStats():array
    {
        $voucher=Voucher::query()->where('auto_apply',true)->latest()->first();
        $redemptions=$voucher?->redemptions_count??0;
        $registrations=max(1,User::count());
        return[
            Stat::make('Total members',User::count())->description('All registered accounts'),
            Stat::make('Registered today',User::whereDate('created_at',today())->count()),
            Stat::make('This week',User::where('created_at','>=',now()->startOfWeek())->count()),
            Stat::make('This month',User::where('created_at','>=',now()->startOfMonth())->count()),
            Stat::make('Promotion claims',$redemptions)->description($voucher?->campaign_name??'No active campaign'),
            Stat::make('Places remaining',$voucher?->remaining??'Unlimited'),
            Stat::make('Conversion',number_format(($redemptions/$registrations)*100,1).'%')->description('Registrations with promotion'),
            Stat::make('Active promotional plans',VoucherRedemption::whereHas('subscription',fn($q)=>$q->where('status','active')->where(fn($e)=>$e->whereNull('ends_at')->orWhere('ends_at','>',now())))->count()),
        ];
    }
}
