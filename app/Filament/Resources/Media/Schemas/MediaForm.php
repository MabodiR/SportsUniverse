<?php

namespace App\Filament\Resources\Media\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('public_id')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('kind')
                    ->required(),
                TextInput::make('collection')
                    ->required()
                    ->default('uploads'),
                TextInput::make('disk')
                    ->required(),
                TextInput::make('path')
                    ->required(),
                TextInput::make('original_name')
                    ->required(),
                TextInput::make('mime_type')
                    ->required(),
                TextInput::make('size_bytes')
                    ->required()
                    ->numeric(),
                TextInput::make('checksum_sha256'),
                TextInput::make('processing_status')
                    ->required()
                    ->default('pending'),
                TextInput::make('moderation_status')
                    ->required()
                    ->default('pending'),
                TextInput::make('thumbnail_path'),
                TextInput::make('duration_ms')
                    ->numeric(),
                TextInput::make('width')
                    ->numeric(),
                TextInput::make('height')
                    ->numeric(),
                Textarea::make('metadata')
                    ->columnSpanFull(),
                Textarea::make('processing_error')
                    ->columnSpanFull(),
                DateTimePicker::make('processed_at'),
            ]);
    }
}
