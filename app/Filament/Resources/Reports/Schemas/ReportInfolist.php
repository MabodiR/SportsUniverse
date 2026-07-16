<?php

namespace App\Filament\Resources\Reports\Schemas;

use App\Domain\Feed\Models\Video;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('public_id'),
                TextEntry::make('reporter.name')
                    ->label('Reporter'),
                TextEntry::make('reportable_type'),
                TextEntry::make('reportable_id')
                    ->numeric(),
                TextEntry::make('reported_content_owner')
                    ->label('Post owner')
                    ->state(fn ($record): string => $record->reportable instanceof Video
                        ? ($record->reportable->user?->name ?? 'Unknown user')
                        : 'Not a post'),
                TextEntry::make('reported_content_caption')
                    ->label('Reported post caption')
                    ->state(fn ($record): string => $record->reportable instanceof Video
                        ? ($record->reportable->caption ?: 'No caption')
                        : 'Not available')
                    ->columnSpanFull(),
                TextEntry::make('reason'),
                TextEntry::make('details')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status'),
                TextEntry::make('assignee.name')
                    ->label('Assigned reviewer')
                    ->placeholder('-'),
                TextEntry::make('resolved_at')
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
