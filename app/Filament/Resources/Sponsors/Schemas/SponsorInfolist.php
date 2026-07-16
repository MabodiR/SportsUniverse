<?php

namespace App\Filament\Resources\Sponsors\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SponsorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('organisation_name')->label('Organisation'),
            TextEntry::make('user.name')->label('Account owner'),
            TextEntry::make('user.email')->label('Login email'),
            TextEntry::make('user.status')->badge(),
            TextEntry::make('registration_number')->placeholder('-'),
            TextEntry::make('website')->url(fn ($record) => $record->website)->openUrlInNewTab()->placeholder('-'),
            TextEntry::make('contact_email')->placeholder('-'),
            TextEntry::make('contact_phone')->placeholder('-'),
            TextEntry::make('services')->formatStateUsing(fn ($state) => collect($state ?: [])->join(', '))->columnSpanFull()->placeholder('-'),
            TextEntry::make('user.email_verified_at')->label('Email verified')->dateTime()->placeholder('Not verified'),
            TextEntry::make('created_at')->dateTime(),
        ]);
    }
}
