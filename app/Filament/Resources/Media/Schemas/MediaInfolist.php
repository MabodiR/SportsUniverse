<?php

namespace App\Filament\Resources\Media\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MediaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('public_id'),
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('kind'),
                TextEntry::make('collection'),
                TextEntry::make('disk'),
                TextEntry::make('path'),
                TextEntry::make('original_name'),
                TextEntry::make('mime_type'),
                TextEntry::make('size_bytes')
                    ->numeric(),
                TextEntry::make('checksum_sha256')
                    ->placeholder('-'),
                TextEntry::make('processing_status'),
                TextEntry::make('moderation_status'),
                TextEntry::make('thumbnail_path')
                    ->placeholder('-'),
                TextEntry::make('duration_ms')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('width')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('height')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('metadata')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('processing_error')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('processed_at')
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
