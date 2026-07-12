<?php

namespace App\Filament\Resources\Videos\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VideoForm
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
                Select::make('media_id')
                    ->relationship('media', 'id')
                    ->required(),
                Select::make('sport_id')
                    ->relationship('sport', 'name'),
                Textarea::make('caption')
                    ->columnSpanFull(),
                Textarea::make('hashtags')
                    ->columnSpanFull(),
                TextInput::make('visibility')
                    ->required()
                    ->default('public'),
                TextInput::make('status')
                    ->required()
                    ->default('draft'),
                TextInput::make('views_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('likes_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('comments_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('shares_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('saves_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('published_at'),
            ]);
    }
}
