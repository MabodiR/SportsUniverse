<?php

namespace App\Filament\Resources\Reports\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('public_id')
                    ->required(),
                Select::make('reporter_id')
                    ->relationship('reporter', 'name')
                    ->required(),
                TextInput::make('reportable_type')
                    ->required(),
                TextInput::make('reportable_id')
                    ->required()
                    ->numeric(),
                TextInput::make('reason')
                    ->required(),
                Textarea::make('details')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('open'),
                TextInput::make('assigned_to_id')
                    ->numeric(),
                DateTimePicker::make('resolved_at'),
            ]);
    }
}
