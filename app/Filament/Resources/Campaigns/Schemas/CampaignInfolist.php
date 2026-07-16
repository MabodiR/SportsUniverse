<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title'),
            TextEntry::make('user.name')->label('Account owner'),
            TextEntry::make('user.email')->label('Owner email'),
            TextEntry::make('campaign_type')->badge(),
            TextEntry::make('goal')->badge(),
            TextEntry::make('status')->badge(),
            TextEntry::make('description')->columnSpanFull()->placeholder('-'),
            TextEntry::make('destination_url')->url(fn ($record) => $record->destination_url)->openUrlInNewTab()->placeholder('-'),
            TextEntry::make('daily_budget_cents')->label('Daily budget')->money('ZAR', divideBy: 100),
            TextEntry::make('total_budget_cents')->label('Total budget')->money('ZAR', divideBy: 100),
            TextEntry::make('starts_on')->date(),
            TextEntry::make('ends_on')->date(),
            TextEntry::make('audience')->formatStateUsing(fn ($state) => collect($state ?: [])->map(fn ($value, $key) => "$key: $value")->join(' · '))->columnSpanFull(),
            TextEntry::make('review_notes')->columnSpanFull()->placeholder('-'),
            TextEntry::make('submitted_at')->dateTime()->placeholder('-'),
            TextEntry::make('reviewed_at')->dateTime()->placeholder('-'),
        ]);
    }
}
