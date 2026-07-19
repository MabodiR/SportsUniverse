<?php
namespace App\Filament\Resources\BoostSettings\Pages;
use App\Filament\Resources\BoostSettings\BoostSettingResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
class EditBoostSetting extends EditRecord
{
    protected static string $resource=BoostSettingResource::class;
    protected function mutateFormDataBeforeSave(array $data):array{$data['updated_by']=auth()->id();return$data;}
    protected function afterSave():void{Notification::make()->success()->title('Post boosting rules updated')->body('The new rules now apply to web and mobile delivery.')->send();}
}
