<?php

namespace App\Filament\Resources\Opportunities\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OpportunityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('public_id'),
                TextEntry::make('posted_by_id')
                    ->numeric(),
                TextEntry::make('sport.name')
                    ->label('Sport')
                    ->placeholder('-'),
                TextEntry::make('position.name')
                    ->label('Position')
                    ->placeholder('-'),
                TextEntry::make('title'),
                TextEntry::make('type'),
                TextEntry::make('description')
                    ->columnSpanFull(),
                TextEntry::make('country')
                    ->placeholder('-'),
                TextEntry::make('province')
                    ->placeholder('-'),
                TextEntry::make('city')
                    ->placeholder('-'),
                IconEntry::make('is_remote')
                    ->boolean(),
                TextEntry::make('minimum_age')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('maximum_age')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('requirements')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status'),
                TextEntry::make('published_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deadline')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('applications_count')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
