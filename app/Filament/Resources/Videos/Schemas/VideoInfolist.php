<?php

namespace App\Filament\Resources\Videos\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VideoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('public_id'),
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('media.id')
                    ->label('Media'),
                TextEntry::make('sport.name')
                    ->label('Sport')
                    ->placeholder('-'),
                TextEntry::make('caption')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('hashtags')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('visibility'),
                TextEntry::make('status'),
                TextEntry::make('views_count')
                    ->numeric(),
                TextEntry::make('likes_count')
                    ->numeric(),
                TextEntry::make('comments_count')
                    ->numeric(),
                TextEntry::make('shares_count')
                    ->numeric(),
                TextEntry::make('saves_count')
                    ->numeric(),
                TextEntry::make('published_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
