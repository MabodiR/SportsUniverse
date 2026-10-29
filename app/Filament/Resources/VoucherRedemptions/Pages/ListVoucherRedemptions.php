<?php
namespace App\Filament\Resources\VoucherRedemptions\Pages;
use App\Filament\Resources\VoucherRedemptions\VoucherRedemptionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
class ListVoucherRedemptions extends ListRecords
{
    protected static string $resource=VoucherRedemptionResource::class;
    protected function getHeaderActions():array{return[Action::make('export')->label('Export CSV')->action(function(){return response()->streamDownload(function(){ $out=fopen('php://output','w');fputcsv($out,['Name','Email','Voucher','Campaign','Package','Status','Registered','Redeemed']);foreach($this->getFilteredTableQuery()->with('user','voucher','plan','subscription')->cursor() as $r)fputcsv($out,[$r->user->name,$r->user->email,$r->voucher->code,$r->voucher->campaign_name,$r->plan->name,$r->subscription?->status,$r->user->created_at,$r->redeemed_at]);fclose($out);},'voucher-redemptions-'.now()->format('Y-m-d').'.csv');})];}
}
