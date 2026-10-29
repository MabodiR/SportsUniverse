<?php
namespace App\Filament\Resources\Vouchers;
use App\Domain\Promotions\Models\Voucher;
use App\Filament\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Resources\Vouchers\Pages\EditVoucher;
use App\Filament\Resources\Vouchers\Pages\ListVouchers;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

class VoucherResource extends Resource
{
    protected static ?string $model=Voucher::class;
    protected static string|BackedEnum|null $navigationIcon=Heroicon::OutlinedTicket;
    protected static string|UnitEnum|null $navigationGroup='Promotions';
    protected static ?int $navigationSort=1;
    public static function form(Schema $schema):Schema{return $schema->components([
        Section::make('Voucher')->schema([TextInput::make('code')->required()->maxLength(80)->dehydrateStateUsing(fn($state)=>Str::upper(trim($state)))->unique(ignoreRecord:true),TextInput::make('campaign_name')->maxLength(160),Textarea::make('description')->columnSpanFull(),Select::make('discount_type')->options(['percentage'=>'Percentage','fixed'=>'Fixed amount','free_package'=>'Free package'])->required(),TextInput::make('discount_value')->numeric()->minValue(0)->required(),Select::make('plans')->relationship('plans','name')->multiple()->preload()->required()])->columns(2),
        Section::make('Eligibility and limits')->schema([TextInput::make('max_redemptions')->numeric()->integer()->minValue(1),TextInput::make('max_redemptions_per_user')->numeric()->integer()->minValue(1)->default(1),DateTimePicker::make('starts_at'),DateTimePicker::make('expires_at')->after('starts_at'),Toggle::make('is_active')->default(true),Toggle::make('new_users_only'),Toggle::make('auto_apply'),Toggle::make('is_archived')])->columns(2),
    ]);}
    public static function table(Table $table):Table{return $table->defaultSort('created_at','desc')->columns([TextColumn::make('code')->searchable()->weight('bold'),TextColumn::make('campaign_name')->searchable(),TextColumn::make('discount_type')->badge(),TextColumn::make('redemptions_count')->label('Used')->numeric(),TextColumn::make('remaining')->state(fn(Voucher $r)=>$r->remaining??'Unlimited'),IconColumn::make('is_active')->boolean(),IconColumn::make('auto_apply')->boolean(),TextColumn::make('expires_at')->dateTime()->sortable()])->filters([SelectFilter::make('discount_type')->options(['percentage'=>'Percentage','fixed'=>'Fixed','free_package'=>'Free package']),SelectFilter::make('is_active')->options([1=>'Active',0=>'Inactive'])])->recordActions([EditAction::make(),Action::make('duplicate')->icon(Heroicon::OutlinedDocumentDuplicate)->action(function(Voucher $record){$copy=$record->replicate(['redemptions_count']);$copy->code=$record->code.'-COPY-'.Str::upper(Str::random(4));$copy->is_active=false;$copy->redemptions_count=0;$copy->save();$copy->plans()->sync($record->plans()->pluck('subscription_plans.id'));}),Action::make('end')->color('danger')->requiresConfirmation()->action(fn(Voucher $record)=>$record->update(['is_active'=>false,'expires_at'=>now()])),Action::make('archive')->requiresConfirmation()->action(fn(Voucher $record)=>$record->update(['is_active'=>false,'is_archived'=>true]))])->toolbarActions([BulkActionGroup::make([])]);}
    public static function canDelete(Model $record):bool{return $record->redemptions_count===0;}
    public static function getPages():array{return['index'=>ListVouchers::route('/'),'create'=>CreateVoucher::route('/create'),'edit'=>EditVoucher::route('/{record}/edit')];}
}
