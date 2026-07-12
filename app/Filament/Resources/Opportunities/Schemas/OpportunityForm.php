<?php

namespace App\Filament\Resources\Opportunities\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OpportunityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('public_id')
                    ->required(),
                TextInput::make('posted_by_id')
                    ->required()
                    ->numeric(),
                Select::make('sport_id')
                    ->relationship('sport', 'name'),
                Select::make('position_id')
                    ->relationship('position', 'name'),
                TextInput::make('title')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('country'),
                TextInput::make('province'),
                TextInput::make('city'),
                Toggle::make('is_remote')
                    ->required(),
                TextInput::make('minimum_age')
                    ->numeric(),
                TextInput::make('maximum_age')
                    ->numeric(),
                Textarea::make('requirements')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('draft'),
                DateTimePicker::make('published_at'),
                DateTimePicker::make('deadline'),
                TextInput::make('applications_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
